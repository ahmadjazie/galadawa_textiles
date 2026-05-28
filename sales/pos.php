<?php
// 1. Configuration
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include '../includes/db_connect.php';
include '../includes/payouts.php';
include '../includes/order_workflow.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }
if (!isset($_SESSION['cart'])) { $_SESSION['cart'] = []; }

$user_id = $_SESSION['user_id'];
ensure_payout_table($conn);
$payout_unread = get_user_unread_count($conn, $user_id);
ensure_order_workflow_schema($conn);
release_expired_holds($conn);
$active_holds = get_active_holds_count($conn);

function ensure_sale_items_buy_price_column($conn) {
    $check = $conn->query("SHOW COLUMNS FROM sale_items LIKE 'buy_price_at_sale'");
    if ($check && $check->num_rows === 0) {
        $conn->query("ALTER TABLE sale_items ADD COLUMN buy_price_at_sale DECIMAL(10,2) NULL AFTER price");
    }
}

ensure_sale_items_buy_price_column($conn);

function build_pos_cart_html() {
    ob_start();
    if (file_exists('cart_list_template.php')) {
        include 'cart_list_template.php';
    }
    return ob_get_clean();
}

function cart_quantity_for_product($productId, $productImageId = null) {
    $total = 0.0;
    foreach ($_SESSION['cart'] as $item) {
        if ((int)($item['id'] ?? 0) !== (int)$productId) {
            continue;
        }

        if ($productImageId !== null) {
            $itemImageId = !empty($item['product_image_id']) ? (int)$item['product_image_id'] : (!empty($item['cap_img_id']) ? (int)$item['cap_img_id'] : null);
            if ($itemImageId !== (int)$productImageId) {
                continue;
            }
        }

        $total += (float)($item['qty'] ?? 0);
    }

    return $total;
}

// 2. AJAX handlers
if (isset($_GET['get_cap_images'])) {
    $pid = (int)$_GET['get_cap_images'];
    $res = $conn->query("SELECT * FROM product_images WHERE product_id='$pid' AND status IN ('available','held') ORDER BY FIELD(status, 'available', 'held'), id ASC");
    $images = [];
    while($row = $res->fetch_assoc()) { $images[] = $row; }
    header('Content-Type: application/json');
    echo json_encode($images);
    exit();
}

if (isset($_GET['get_textile_variants'])) {
    $pid = (int)$_GET['get_textile_variants'];
    $res = $conn->query("SELECT id, image_name, color_name, yards, status FROM product_images WHERE product_id='$pid' AND status IN ('available','held') AND yards IS NOT NULL ORDER BY FIELD(status, 'available', 'held'), id ASC");
    $variants = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cartQty = cart_quantity_for_product($pid, (int)$row['id']);
            $row['yards'] = max(0, (float)$row['yards'] - $cartQty);
            if ($row['yards'] > 0 || (string)($row['status'] ?? '') === 'held') {
                $variants[] = $row;
            }
        }
    }
    header('Content-Type: application/json');
    echo json_encode($variants);
    exit();
}

if (isset($_GET['inventory_snapshot'])) {
    $products = [];
    $res = $conn->query("SELECT id, quantity FROM products ORDER BY id ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cartQty = cart_quantity_for_product((int)$row['id']);
            $visibleQty = max(0, (float)$row['quantity'] - $cartQty);
            $products[] = [
                'id' => (int)$row['id'],
                'quantity' => $visibleQty,
                'quantity_display' => rtrim(rtrim(number_format($visibleQty, 2, '.', ''), '0'), '.'),
            ];
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        'ok' => true,
        'products' => $products,
        'active_holds' => get_active_holds_count($conn),
    ]);
    exit();
}

if (isset($_POST['action']) && $_POST['action'] == 'add') {
    $id = (int)$_POST['product_id'];
    $qty = (float)$_POST['quantity'];
    $cap_img_id = isset($_POST['cap_img_id']) && $_POST['cap_img_id'] !== '' ? (int)$_POST['cap_img_id'] : null;
    $cap_img_src = $_POST['cap_img_src'] ?? null;
    $product_image_id = isset($_POST['product_image_id']) && $_POST['product_image_id'] !== '' ? (int)$_POST['product_image_id'] : ($cap_img_id ?: null);

    $prod = $conn->query("SELECT * FROM products WHERE id='$id'")->fetch_assoc();
    if (!$prod || $qty <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid product or quantity.']);
        exit();
    }

    $cat = $prod['category'] ?? '';
    $is_cap = (stripos($cat, 'Cap') !== false);
    $is_textile = !$is_cap;
    $variant = null;
    $variant_color = '';
    $has_textile_variants = false;

    if ($is_textile) {
        $variantCheck = $conn->query("SELECT COUNT(*) AS c FROM product_images WHERE product_id='$id' AND yards IS NOT NULL");
        $has_textile_variants = $variantCheck && (int)($variantCheck->fetch_assoc()['c'] ?? 0) > 0;
    }

    if ($is_textile && $has_textile_variants) {
        if (!$product_image_id) {
            echo json_encode(['status' => 'error', 'message' => 'Select a textile colour before adding to cart.']);
            exit();
        }

        $variantRes = $conn->query("SELECT id, image_name, color_name, yards FROM product_images WHERE id='$product_image_id' AND product_id='$id' AND status='available' AND yards IS NOT NULL LIMIT 1");
        $variant = $variantRes && $variantRes->num_rows > 0 ? $variantRes->fetch_assoc() : null;
        if (!$variant) {
            echo json_encode(['status' => 'error', 'message' => 'This textile colour is no longer available.']);
            exit();
        }

        $current_stock = (float)$variant['yards'];
        $variant_color = trim((string)($variant['color_name'] ?? ''));
        if ($cap_img_src === null && !empty($variant['image_name'])) {
            $cap_img_src = '../uploads/' . $variant['image_name'];
        }
    } else {
        $current_stock = (float)$prod['quantity'];
    }
    
    // Validate requested quantity against current available stock.
    $cart_id = $id . '_' . ($product_image_id ?? 'main');
    $qty_in_cart = isset($_SESSION['cart'][$cart_id]) ? $_SESSION['cart'][$cart_id]['qty'] : 0;
    
    if (($qty + $qty_in_cart) > $current_stock) {
        echo json_encode(['status' => 'error', 'message' => "Insufficient Stock! Only $current_stock left."]);
        exit();
    }

    $price = $prod['sell_price'];
    $subtotal = $price * $qty;

    // Prevent duplicate cap color variants from being added to the cart.
    if ($is_cap && $cap_img_id) {
        $cap_cart_id = $id . '_' . $cap_img_id;
        if (isset($_SESSION['cart'][$cap_cart_id])) {
            echo json_encode(['status' => 'error', 'message' => "This cap color is already in the cart."]);
            exit();
        }
    }

    if (isset($_SESSION['cart'][$cart_id])) {
        $_SESSION['cart'][$cart_id]['qty'] += $qty;
        $_SESSION['cart'][$cart_id]['subtotal'] += $subtotal;
    } else {
        $_SESSION['cart'][$cart_id] = [
            'id' => $id, 'name' => $prod['name'], 'price' => $price, 'qty' => $qty,
            'buy_price' => (float)$prod['buy_price'],
            'subtotal' => $subtotal, 'is_cap' => $is_cap,
            'is_textile_variant' => $is_textile && $has_textile_variants,
            'cap_img_id' => $is_cap ? $cap_img_id : null,
            'product_image_id' => $product_image_id,
            'color_name' => $variant_color,
            'img_preview' => $cap_img_src
        ];
    }

    $total = cart_total_amount($_SESSION['cart']);

    echo json_encode(['status' => 'success', 'cart_html' => build_pos_cart_html(), 'total' => number_format($total)]);
    exit();
}

if (isset($_POST['action']) && $_POST['action'] == 'remove_ajax') {
    $cart_id = (string)($_POST['cart_id'] ?? '');
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $product_image_id = isset($_POST['product_image_id']) && $_POST['product_image_id'] !== '' ? (int)$_POST['product_image_id'] : null;
    $removed_qty = 0.0;
    $removed_key = '';

    if ($cart_id !== '' && isset($_SESSION['cart'][$cart_id])) {
        $removed_key = $cart_id;
    } elseif ($product_id > 0 && $product_image_id !== null) {
        foreach ($_SESSION['cart'] as $key => $item) {
            $itemImageId = !empty($item['product_image_id']) ? (int)$item['product_image_id'] : (!empty($item['cap_img_id']) ? (int)$item['cap_img_id'] : null);
            if ((int)($item['id'] ?? 0) === $product_id && $itemImageId === $product_image_id) {
                $removed_key = (string)$key;
                break;
            }
        }
    }

    if ($removed_key !== '' && isset($_SESSION['cart'][$removed_key])) {
        $removed_item = $_SESSION['cart'][$removed_key];
        $removed_qty = (float)($_SESSION['cart'][$removed_key]['qty'] ?? 0);
        $product_id = (int)($removed_item['id'] ?? $product_id);
        $product_image_id = !empty($removed_item['product_image_id'])
            ? (int)$removed_item['product_image_id']
            : (!empty($removed_item['cap_img_id']) ? (int)$removed_item['cap_img_id'] : $product_image_id);
        unset($_SESSION['cart'][$removed_key]);
    }

    $total = cart_total_amount($_SESSION['cart']);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'cart_html' => build_pos_cart_html(),
        'total' => number_format($total),
        'removed_qty' => $removed_qty,
        'removed_product_id' => $product_id,
        'removed_product_image_id' => $product_image_id,
    ]);
    exit();
}

// 3. Standard form handlers
if (isset($_POST['action'])) {
    if ($_POST['action'] == 'remove') {
        unset($_SESSION['cart'][$_POST['cart_id']]);
    }

    if ($_POST['action'] == 'clear') {
        $_SESSION['cart'] = [];
    }

    if ($_POST['action'] == 'checkout') {
        $total = cart_total_amount($_SESSION['cart']);
        $payment_method = trim((string)($_POST['payment_method'] ?? 'cash'));
        $allowed_payment_methods = ['cash', 'transfer', 'pos'];
        if (!in_array($payment_method, $allowed_payment_methods, true)) {
            $payment_method = 'cash';
        }

        if ($total > 0) {
            $conn->begin_transaction();

            try {
                $payment_method_sql = $conn->real_escape_string($payment_method);
                $conn->query("INSERT INTO sales (user_id, total_amount, payment_method) VALUES ('$user_id', '$total', '$payment_method_sql')");
                $sale_id = (int)$conn->insert_id;
                if ($sale_id <= 0) {
                    throw new RuntimeException('Unable to save this sale right now.');
                }

                foreach ($_SESSION['cart'] as $item) {
                    $pid = (int)$item['id'];
                    $q = (float)$item['qty'];
                    $p = (float)$item['price'];
                    $sub = (float)$item['subtotal'];
                    $buy_price_at_sale = isset($item['buy_price']) ? (float)$item['buy_price'] : 0;
                    $product_image_id = !empty($item['product_image_id']) ? (int)$item['product_image_id'] : (!empty($item['cap_img_id']) ? (int)$item['cap_img_id'] : null);

                    if (!finalize_direct_sale_inventory($conn, $pid, $q, $product_image_id)) {
                        throw new RuntimeException('Stock changed before checkout for ' . $item['name'] . '. Refresh and try again.');
                    }

                    $imageSql = $product_image_id ? "'$product_image_id'" : "NULL";
                    $conn->query("INSERT INTO sale_items (sale_id, product_id, product_image_id, quantity, price, buy_price_at_sale, subtotal) VALUES ('$sale_id', '$pid', $imageSql, '$q', '$p', '$buy_price_at_sale', '$sub')");
                    if ($conn->affected_rows !== 1) {
                        throw new RuntimeException('Unable to save sale item details.');
                    }
                }

                $conn->commit();
                $_SESSION['cart'] = [];
                header("Location: pos.php?success=$sale_id");
                exit();
            } catch (Throwable $e) {
                $conn->rollback();
                header("Location: pos.php?error=" . urlencode($e->getMessage()));
                exit();
            }
        }
    }

    if ($_POST['action'] == 'create_hold') {
        $total = cart_total_amount($_SESSION['cart']);
        $customer_name = trim((string)($_POST['customer_name'] ?? ''));
        $hold_note = trim((string)($_POST['hold_note'] ?? ''));
        $hold_minutes = get_hold_duration_minutes();

        if ($total > 0) {
            if ($customer_name === '') {
                $customer_name = 'Walk-in Customer';
            }

            $customer_name_sql = $conn->real_escape_string($customer_name);
            $hold_note_sql = $conn->real_escape_string($hold_note);
            $conn->begin_transaction();

            try {
                $conn->query("INSERT INTO held_orders (user_id, customer_name, note, hold_minutes, total_amount, release_at) VALUES ('$user_id', '$customer_name_sql', '$hold_note_sql', '$hold_minutes', '$total', DATE_ADD(NOW(), INTERVAL $hold_minutes MINUTE))");
                $hold_id = (int)$conn->insert_id;
                if ($hold_id <= 0) {
                    throw new RuntimeException('Unable to place this order on hold right now.');
                }

                foreach ($_SESSION['cart'] as $item) {
                    $pid = (int)$item['id'];
                    $q = (float)$item['qty'];
                    $p = (float)$item['price'];
                    $sub = (float)$item['subtotal'];
                    $buy_price_at_hold = isset($item['buy_price']) ? (float)$item['buy_price'] : 0;
                    $product_image_id = !empty($item['product_image_id']) ? (int)$item['product_image_id'] : (!empty($item['cap_img_id']) ? (int)$item['cap_img_id'] : null);
                    $product_name_sql = $conn->real_escape_string((string)$item['name']);
                    $image_preview_sql = isset($item['img_preview']) ? "'" . $conn->real_escape_string((string)$item['img_preview']) . "'" : "NULL";
                    $image_id_sql = $product_image_id ? "'$product_image_id'" : "NULL";

                    if (!reserve_inventory_for_hold($conn, $pid, $q, $product_image_id)) {
                        throw new RuntimeException('Stock changed before hold for ' . $item['name'] . '. Refresh and try again.');
                    }

                    $conn->query("INSERT INTO held_order_items (hold_id, product_id, product_image_id, product_name, quantity, price, buy_price_at_hold, subtotal, image_preview) VALUES ('$hold_id', '$pid', $image_id_sql, '$product_name_sql', '$q', '$p', '$buy_price_at_hold', '$sub', $image_preview_sql)");
                    if ($conn->affected_rows !== 1) {
                        throw new RuntimeException('Unable to save held item details.');
                    }
                }

                $conn->commit();
                $_SESSION['cart'] = [];
                header("Location: holding_orders.php?created=$hold_id");
                exit();
            } catch (Throwable $e) {
                $conn->rollback();
                header("Location: pos.php?error=" . urlencode($e->getMessage()));
                exit();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS | Galadawa</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/toastify.min.css">
    <script src="../js/toastify.min.js"></script>
    <script src="../js/toast.js"></script>
    <script src="../js/sweetalert2.all.min.js"></script>
    <style>
        /* Global box sizing */
        * { box-sizing: border-box; }

        body {
            background: #eef2f5;
            margin: 0;
            overflow-x: hidden;
            min-height: 100vh;
        }

        .pos-shell {
            width: 100%;
            min-height: 100%;
        }

        .pos-container {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(340px, 400px);
            gap: 20px;
            width: 100%;
            max-width: 1440px;
            margin: 0 auto;
            min-height: 0;
            height: 100%;
            align-items: stretch;
        }

        .pos-main {
            background: #eef2f5 !important;
            padding: 20px !important;
        }
        
        .product-area {
            background: white;
            border-radius: 18px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            min-width: 0;
            min-height: 0;
            box-shadow: 0 8px 24px rgba(15,23,42,0.06);
        }
        .search-bar { width: 100%; padding: 15px; border: 2px solid #eee; border-radius: 8px; font-size: 16px; margin-bottom: 20px; outline: none; }
        .prod-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
            gap: 15px;
            overflow-y: auto;
            padding-right: 5px;
            min-height: 0;
            align-content: start;
        }
        .prod-card { border: 1px solid #eee; border-radius: 10px; padding: 10px; cursor: pointer; transition: 0.2s; text-align: center; background: white; position: relative; overflow: hidden; }
        .prod-card:hover { border-color: #1e3c72; transform: translateY(-3px); }
        .prod-card.out-of-stock { opacity: 0.52; }
        .prod-card.out-of-stock:hover { transform: none; border-color: #eee; }
        .prod-card img { width: 100%; height: 130px; object-fit: cover; border-radius: 8px; margin-bottom: 10px; }
        .prod-name { font-weight: 600; font-size: 14px; margin-bottom: 5px; color: #333; height: 40px; overflow: hidden; }
        .prod-price { color: #28a745; font-weight: bold; font-size: 16px; }
        .prod-stock { font-size: 11px; color: #777; background:#f0f0f0; padding:2px 8px; border-radius:10px; display:inline-block; margin-top:5px; transition: 0.3s; }
        .prod-stock.stock-zero { color: #c0392b; background: #fdecec; font-weight: 700; }
        .prod-hold-badge {
            position: absolute;
            top: 18px;
            left: 18px;
            z-index: 2;
            background: rgba(245, 158, 11, 0.72);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.7);
            border-radius: 999px;
            padding: 4px 8px;
            font-size: 10px;
            font-weight: 900;
            letter-spacing: 0.08em;
        }

        /* Cart scrolling behavior */
        .cart-area { 
            background: white; border-radius: 18px; padding: 20px; 
            display: flex; flex-direction: column; height: 100%; 
            min-height: 0;
            box-shadow: 0 8px 24px rgba(15,23,42,0.06); overflow: hidden;
            position: sticky;
            top: 20px;
            max-height: calc(100vh - 120px);
        }
        .cart-title {
            font-size: 18px;
            font-weight: bold;
            border-bottom: 2px solid #f4f4f4;
            padding-bottom: 12px;
            margin-bottom: 8px;
        }
        .cart-items { 
            flex: 1; overflow-y: auto; padding-right: 5px; 
            min-height: 0; /* Allows the list to shrink within the flex container. */
        }
        .cart-item { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
        .cart-item img { width: 42px; height: 42px; border-radius: 6px; margin-right: 12px; object-fit: cover; border: 1px solid #eee; }
        .cart-item-main { display:flex; align-items:center; min-width:0; flex:1; }
        .cart-item-info { min-width:0; }
        .cart-item-name { font-weight:600; font-size:14px; color:#24303d; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .cart-item-meta { color:#667085; font-size:12px; }
        .cart-item-side { text-align:right; flex-shrink:0; }
        .cart-item-total { font-weight:bold; color:#24303d; font-size:14px; }
        .cart-remove-btn { border:none; background:none; color:#e74c3c; cursor:pointer; font-size:17px; margin-top:4px; padding: 0; }
        .cart-empty {
            text-align: center;
            color: #98a2b3;
            min-height: 150px;
            display:flex;
            flex-direction:column;
            align-items:center;
            justify-content:center;
            gap: 8px;
        }
        
        /* Footer layout stability */
        .total-section { 
            background: #f8f9fa; border-radius: 10px; padding: 16px; margin-top: 14px; 
            text-align: center; flex-shrink: 0; /* Keeps the total section visible at all times. */
        }
        .grand-total { font-size: 24px; font-weight: bold; color: #333; margin-bottom: 12px; }
        .btn-pay, .btn-hold { width: 100%; padding: 13px; color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; }
        .btn-pay { background: #28a745; }
        .btn-hold { background: #1e3c72; margin-top: 10px; }
        .hold-panel {
            margin-top: 12px;
            text-align: left;
            border: 1px solid #dbe5f0;
            border-radius: 12px;
            background: #fff;
            overflow: hidden;
        }
        .hold-panel summary {
            list-style: none;
            cursor: pointer;
            padding: 12px 14px;
            font-size: 13px;
            font-weight: 700;
            color: #1e3c72;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .hold-panel summary::-webkit-details-marker { display: none; }
        .hold-panel summary::after {
            content: '+';
            font-size: 18px;
            line-height: 1;
        }
        .hold-panel[open] summary::after {
            content: '-';
        }
        .hold-form {
            margin-top: 0;
            text-align: left;
            border-top: 1px solid #e5e7eb;
            padding: 14px;
        }
        .hold-form label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #667085;
            margin-bottom: 6px;
        }
        .hold-form input,
        .hold-form textarea {
            width: 100%;
            border: 1px solid #d0d5dd;
            border-radius: 10px;
            padding: 11px 12px;
            font-size: 14px;
            margin-bottom: 10px;
            resize: vertical;
        }
        .cart-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-top: 12px;
        }
        .cart-secondary-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            color: #1e3c72;
            font-weight: 600;
            font-size: 13px;
        }
        .cart-cancel-form {
            margin: 0;
        }
        .cart-cancel-btn {
            border: none;
            background: none;
            color: #e74c3c;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
        }

        .cap-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; max-height: 400px; overflow-y: auto; padding: 10px; }
        .cap-option { border: 2px solid #eee; border-radius: 8px; cursor: pointer; overflow: hidden; transition: 0.2s; position: relative; opacity: 1; }
        .cap-option:hover { transform: scale(1.03); border-color: #ccc; }
        .cap-option img { width: 100%; height: 120px; object-fit: cover; display: block; }
        .cap-option.selected { border-color: #32cd32; opacity: 0.5; }
        .cap-option.selected::after { content: "\f00c"; font-family: "Font Awesome 5 Free"; font-weight: 900; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #32cd32; font-size: 40px; }
        .cap-option.held { opacity: 0.58; cursor: not-allowed; }
        .cap-option.held:hover { transform: none; border-color: #f0b429; }
        .cap-option.held::after {
            content: "HOLD";
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(245, 158, 11, 0.24);
            color: rgba(120, 53, 15, 0.86);
            font-weight: 900;
            font-size: 20px;
            letter-spacing: 0.12em;
            text-shadow: 0 1px 0 rgba(255,255,255,0.5);
        }
        .variant-option { width: 100%; text-align: left; background: #fff; appearance: none; font: inherit; }
        .variant-option-body { padding: 10px; }
        .variant-color { font-weight: 800; color: #24303d; font-size: 13px; margin-bottom: 3px; }
        .variant-yards { color: #1e3c72; font-weight: 700; font-size: 12px; }

        @media (max-width: 1180px) {
            .pos-container {
                grid-template-columns: minmax(0, 1fr) minmax(320px, 360px);
            }
        }

        @media (max-width: 980px) {
            .pos-main { padding: 12px !important; }
            .pos-container {
                grid-template-columns: 1fr;
                height: auto;
                min-height: auto;
                gap: 12px;
            }
            .product-area, .cart-area { height: auto; min-height: 0; }
            .cart-area { position: static; max-height: none; }
            .prod-grid { max-height: 54vh; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); }
            .total-section { margin-top: 14px; }
            .cap-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 600px) {
            .product-area, .cart-area { padding: 16px; }
            .search-bar { padding: 13px; margin-bottom: 16px; }
            .prod-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; max-height: none; }
            .prod-card img { height: 120px; }
            .prod-name { height: auto; min-height: 34px; }
            .cart-item { align-items: flex-start; }
            .cart-item-main { align-items: flex-start; }
            .cart-item img { margin-right: 12px; }
            .grand-total { font-size: 24px; }
            .cap-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .cart-actions { flex-direction: column; align-items: stretch; }
            .cart-secondary-link, .cart-cancel-btn { justify-content: center; text-align: center; }
        }
    </style>
</head>
<body class="sales-mobile-ui">
    <?php $is_dashboard = true; include '../includes/topbar.php'; ?>

    <div class="dashboard-container">
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h3>Menu</h3>
                <button class="toggle-btn" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            </div>
            <a href="dashboard.php"><i class="fas fa-home"></i> <span>Home</span></a>
            <a href="pos.php" class="active"><i class="fas fa-cash-register"></i> <span>New Sale</span></a>
            <a href="holding_orders.php"><i class="fas fa-boxes-stacked"></i> <span>Holding</span><?php if ($active_holds > 0): ?><span class="notif-dot"></span><span class="live-notif-count" style="margin-left:6px; font-size:11px; color:#fff; opacity:0.8;">(<?php echo $active_holds; ?>)</span><?php endif; ?></a>
            <a href="exchange.php"><i class="fas fa-right-left"></i> <span>Exchange</span></a>
            <a href="inventory_view.php"><i class="fas fa-box"></i> <span>View Inventory</span></a>
            <a href="my_history.php"><i class="fas fa-history"></i> <span>My History</span></a>
            <a href="profile.php"><i class="fas fa-user-cog"></i> <span>My Profile</span></a>
            <a href="payouts.php"><i class="fas fa-wallet"></i> <span>Payouts</span><?php if ($payout_unread > 0): ?><span class="notif-dot"></span><?php endif; ?></a>
        </div>

        <div class="main-content pos-main">
            <div class="header-title">Point Of Sale</div>
            <div class="pos-shell">
            <div class="pos-container">
        
        <div class="product-area">
            <input type="text" class="search-bar" id="searchInput" placeholder="Search Product..." onkeyup="searchProducts()" autofocus>
            <div class="prod-grid">
                <?php 
                $products = $conn->query("SELECT * FROM products ORDER BY id ASC");
                while($p = $products->fetch_assoc()): 
                    $cat = $p['category'] ?? '';
                    $is_cap = (strpos($cat, 'Cap') !== false);
                    $held_res = $conn->query("SELECT COUNT(*) AS c FROM product_images WHERE product_id='".$p['id']."' AND status='held'");
                    $held_count = $held_res ? (int)($held_res->fetch_assoc()['c'] ?? 0) : 0;
                    $img_src = "../img/logo.png";
                    if (!empty($p['image']) && $p['image'] != 'default.png') { $img_src = "../uploads/" . $p['image']; } 
                    else { $gal = $conn->query("SELECT image_name FROM product_images WHERE product_id='".$p['id']."' LIMIT 1")->fetch_assoc(); if ($gal) $img_src = "../uploads/" . $gal['image_name']; }
                    
                    // Calculate display stock after subtracting quantities already in the cart.
                    $cart_check_id = $p['id'];
                    $in_cart = 0;
                    foreach($_SESSION['cart'] as $c_item) {
                        if($c_item['id'] == $cart_check_id) { $in_cart += $c_item['qty']; }
                    }
                    $visible_stock = $p['quantity'] - $in_cart;
                    if($visible_stock < 0) $visible_stock = 0;
                ?>
                    <div class="prod-card <?php echo $visible_stock <= 0 ? 'out-of-stock' : ''; ?>" data-product-id="<?php echo $p['id']; ?>" data-held-count="<?php echo $held_count; ?>" onclick="handleClick(<?php echo $p['id']; ?>, '<?php echo addslashes($p['name']); ?>', <?php echo $p['sell_price']; ?>, <?php echo $is_cap ? 'true' : 'false'; ?>, <?php echo $held_count > 0 ? 'true' : 'false'; ?>)">
                        <img src="<?php echo $img_src; ?>" onerror="this.src='../img/logo.png'">
                        <?php if ($held_count > 0): ?><span class="prod-hold-badge">HOLD</span><?php endif; ?>
                        <div class="prod-name"><?php echo $p['name']; ?></div>
                        <div class="prod-price">₦<?php echo number_format($p['sell_price']); ?></div>
                        
                        <div class="prod-stock <?php echo $visible_stock <= 0 ? 'stock-zero' : ''; ?>" id="stock_display_<?php echo $p['id']; ?>" data-stock="<?php echo $visible_stock; ?>">
                            <?php echo $visible_stock > 0 ? $visible_stock . ' left' : 'Out of stock'; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

        <div class="cart-area">
            <div class="cart-title"><i class="fas fa-shopping-cart"></i> Current Sale</div>
            <div class="cart-items" id="cartContainer">
                <?php if(file_exists('cart_list_template.php')) include 'cart_list_template.php'; ?>
            </div>
            <div class="total-section">
                <?php $total = cart_total_amount($_SESSION['cart']); ?>
                <div class="grand-total" id="grandTotal">Total: ₦<?php echo number_format($total); ?></div>
                <form method="POST" id="checkoutForm">
                    <input type="hidden" name="action" value="checkout">
                    <input type="hidden" name="payment_method" id="paymentMethodInput" value="">
                    <button class="btn-pay" type="submit">CONFIRM PAYMENT</button>
                </form>
                <details class="hold-panel">
                    <summary>Place This Order On Hold</summary>
                    <form method="POST" class="hold-form">
                        <input type="hidden" name="action" value="create_hold">
                        <label for="holdCustomerName">Customer Name</label>
                        <input type="text" id="holdCustomerName" name="customer_name" placeholder="Who asked for the hold?">
                        <label for="holdNote">Hold Note</label>
                        <textarea id="holdNote" name="hold_note" rows="3" placeholder="Phone number, preferred pickup time, or special note"></textarea>
                        <button class="btn-hold" type="submit">PLACE ON HOLD</button>
                    </form>
                </details>
                <div class="cart-actions">
                    <a href="holding_orders.php" class="cart-secondary-link"><i class="fas fa-boxes-stacked"></i> Open Holding Page</a>
                    <form method="POST" class="cart-cancel-form">
                        <input type="hidden" name="action" value="clear">
                        <button class="cart-cancel-btn" type="submit">Cancel Sale</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            if (!sidebar) return;
            sidebar.classList.toggle('collapsed');
        }

        const selectedCapIds = new Set(<?php
            $cap_ids = [];
            foreach ($_SESSION['cart'] as $item) {
                if (!empty($item['cap_img_id'])) { $cap_ids[] = (int)$item['cap_img_id']; }
            }
            echo json_encode($cap_ids);
        ?>);

        function setProductStockState(productId, quantity) {
            const stockLabel = document.getElementById('stock_display_' + productId);
            const card = document.querySelector('.prod-card[data-product-id="' + productId + '"]');
            if (!stockLabel || !card) return;

            const value = Math.max(0, parseFloat(quantity || 0));
            stockLabel.setAttribute('data-stock', value);
            stockLabel.textContent = value > 0 ? `${value} left` : 'Out of stock';
            stockLabel.classList.toggle('stock-zero', value <= 0);
            card.classList.toggle('out-of-stock', value <= 0);
        }

        function syncHoldBadge(activeHolds) {
            const holdLink = document.querySelector('.sidebar a[href$="holding_orders.php"]');
            if (!holdLink) return;

            let dot = holdLink.querySelector('.notif-dot');
            let counter = holdLink.querySelector('.live-notif-count, .live-hold-count');

            if (activeHolds > 0) {
                if (!dot) {
                    dot = document.createElement('span');
                    dot.className = 'notif-dot';
                    holdLink.appendChild(dot);
                }
                if (!counter) {
                    counter = document.createElement('span');
                    counter.className = 'live-notif-count';
                    counter.style.marginLeft = '6px';
                    counter.style.fontSize = '11px';
                    counter.style.color = '#fff';
                    counter.style.opacity = '0.8';
                    holdLink.appendChild(counter);
                }
                counter.textContent = `(${activeHolds})`;
            } else {
                if (dot) dot.remove();
                if (counter) counter.remove();
            }
        }

        async function refreshPosInventory() {
            try {
                const response = await fetch(`pos.php?inventory_snapshot=1&t=${Date.now()}`, {
                    credentials: 'same-origin',
                    cache: 'no-store'
                });
                if (!response.ok) return;

                const payload = await response.json();
                if (!payload || payload.ok !== true || !Array.isArray(payload.products)) return;

                payload.products.forEach((product) => {
                    setProductStockState(product.id, product.quantity);
                });

                syncHoldBadge(Number(payload.active_holds || 0));
            } catch (error) {
                // Ignore transient inventory refresh failures.
            }
        }

        function searchProducts() {
            let input = document.getElementById('searchInput').value.toLowerCase();
            let cards = document.getElementsByClassName('prod-card');
            for (let i = 0; i < cards.length; i++) {
                let name = cards[i].querySelector('.prod-name').innerText.toLowerCase();
                cards[i].style.display = name.includes(input) ? "" : "none";
            }
        }

        function escapeHtml(value) {
            return String(value || '').replace(/[&<>"']/g, (char) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char]));
        }

        function promptYardsForProduct(id, maxStock, productImageId = null) {
            Swal.fire({
                title: 'Enter Yards',
                html: `<b>${maxStock}</b> Yards Remaining`,
                input: 'number',
                inputAttributes: { step: '0.1', min: '0.1', max: String(maxStock) },
                showCancelButton: true,
                confirmButtonText: 'Add to Cart',
                confirmButtonColor: '#1e3c72',
                cancelButtonColor: '#6c757d',
                didOpen: () => {
                    const input = Swal.getInput();
                    const confirmBtn = Swal.getConfirmButton();
                    const errorMsg = document.createElement('div');
                    errorMsg.id = 'stock-error';
                    errorMsg.style.color = 'red';
                    errorMsg.style.fontSize = '13px';
                    errorMsg.style.marginTop = '5px';
                    errorMsg.style.fontWeight = 'bold';
                    errorMsg.style.display = 'none';
                    errorMsg.innerText = `Cannot exceed ${maxStock} yards!`;
                    input.parentNode.insertBefore(errorMsg, input.nextSibling);

                    input.addEventListener('input', () => {
                        const val = parseFloat(input.value);
                        const invalid = val > Number(maxStock || 0) || val <= 0;
                        input.style.borderColor = invalid ? 'red' : '#d9d9d9';
                        input.style.color = invalid ? 'red' : '#333';
                        errorMsg.style.display = invalid && val > Number(maxStock || 0) ? 'block' : 'none';
                        confirmBtn.disabled = invalid;
                    });
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    submitViaAjax(id, result.value, null, null, productImageId);
                }
            });
        }

        function selectTextileVariant(event, prodId, variantId, yards) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            Swal.close();
            promptYardsForProduct(prodId, Number(yards || 0), variantId);
        }

        function showHeldNotice() {
            if (window.showToast) {
                showToast("This item is currently on hold.", { type: "warning", duration: 2200 });
            }
        }

        // Read the latest visible stock value directly from the product card.
        function handleClick(id, name, price, isCap, hasHeld = false) {
            
            // Read current available stock from the card's data attribute.
            let stockLabel = document.getElementById('stock_display_' + id);
            let maxStock = parseFloat(stockLabel.getAttribute('data-stock'));

            if (maxStock <= 0 && !hasHeld) {
                if (window.showToast) {
                    showToast("Out of Stock - You have added all available units to the cart.", { type: "warning", duration: 2500 });
                }
                return;
            }

            if (isCap) {
                fetch('pos.php?get_cap_images=' + id).then(r => r.json()).then(data => {
                    if (data.length === 0) {
                        if (window.showToast) {
                            showToast("Out of Stock - No available colors!", { type: "warning", duration: 2500 });
                        }
                        return;
                    }
                    let html = '<div class="cap-grid">';
                    data.forEach(img => {
                        let src = '../uploads/' + img.image_name;
                        let selectedClass = selectedCapIds.has(Number(img.id)) ? ' selected' : '';
                        let heldClass = img.status === 'held' ? ' held' : '';
                        let clickHandler = img.status === 'held'
                            ? 'showHeldNotice()'
                            : `selectCap(${id}, '${src}', ${img.id})`;
                        html += `<div class="cap-option${selectedClass}${heldClass}" id="cap_${img.id}" onclick="${clickHandler}"><img src="${src}"></div>`;
                    });
                    html += '</div>';
                    Swal.fire({ title: 'Select Caps', html: html, showConfirmButton: true, confirmButtonText: 'Done', confirmButtonColor: '#1e3c72', width: '800px' });
                });
            } else {
                fetch('pos.php?get_textile_variants=' + id)
                    .then(r => r.json())
                    .then(data => {
                        if (!Array.isArray(data) || data.length === 0) {
                            promptYardsForProduct(id, maxStock);
                            return;
                        }

                        let html = '<div class="cap-grid">';
                        data.forEach(variant => {
                            const src = '../uploads/' + variant.image_name;
                            const color = variant.color_name ? variant.color_name : 'Unnamed colour';
                            const yards = Number(variant.yards || 0);
                            const isHeld = variant.status === 'held' || yards <= 0;
                            const heldClass = isHeld ? ' held' : '';
                            const clickHandler = isHeld
                                ? 'showHeldNotice()'
                                : `selectTextileVariant(event, ${id}, ${Number(variant.id)}, ${yards})`;
                            html += `
                                <button type="button" class="cap-option variant-option${heldClass}" onclick="${clickHandler}">
                                    <img src="${src}" onerror="this.src='../img/logo.png'">
                                    <div class="variant-option-body">
                                        <div class="variant-color">${escapeHtml(color)}</div>
                                        <div class="variant-yards">${isHeld ? 'On hold' : `${yards} yards left`}</div>
                                    </div>
                                </button>
                            `;
                        });
                        html += '</div>';
                        Swal.fire({
                            title: 'Select Textile Colour',
                            html: html,
                            showConfirmButton: false,
                            showCancelButton: true,
                            cancelButtonText: 'Close',
                            width: '800px'
                        });
                    })
                    .catch(() => {
                        promptYardsForProduct(id, maxStock);
                    });
            }
        }

        function selectCap(prodId, imgSrc, imgId) {
            let el = document.getElementById('cap_' + imgId);
            if (selectedCapIds.has(Number(imgId)) || (el && el.classList.contains('selected'))) {
                removeViaAjax(prodId, imgId, function() {
                    selectedCapIds.delete(Number(imgId));
                    if (el) el.classList.remove('selected');
                });
                return;
            }
            el.classList.add('selected');
            submitViaAjax(prodId, 1, imgId, imgSrc);
        }

        function removeViaAjax(productId, productImageId, afterRemove = null, cartId = '') {
            const formData = new FormData();
            formData.append('action', 'remove_ajax');
            if (cartId) formData.append('cart_id', cartId);
            if (productId) formData.append('product_id', productId);
            if (productImageId) formData.append('product_image_id', productImageId);

            fetch('pos.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.status !== 'success') return;
                    document.getElementById('cartContainer').innerHTML = data.cart_html;
                    document.getElementById('grandTotal').innerText = 'Total: ₦' + data.total;
                    const removedQty = Number(data.removed_qty || 0);
                    const resolvedProductId = Number(data.removed_product_id || productId || 0);
                    const resolvedImageId = Number(data.removed_product_image_id || productImageId || 0);
                    const stockLabel = document.getElementById('stock_display_' + resolvedProductId);
                    if (stockLabel && removedQty > 0) {
                        const currentVal = parseFloat(stockLabel.getAttribute('data-stock'));
                        setProductStockState(resolvedProductId, currentVal + removedQty);
                    }
                    if (resolvedImageId) {
                        selectedCapIds.delete(resolvedImageId);
                    }
                    if (typeof afterRemove === 'function') afterRemove();
                    if (window.showToast) {
                        showToast("Removed from cart", { type: "info", duration: 1500 });
                    }
                });
        }

        const cartContainer = document.getElementById('cartContainer');
        if (cartContainer) {
            cartContainer.addEventListener('submit', function(event) {
                const form = event.target.closest('.cart-remove-form');
                if (!form) return;

                event.preventDefault();
                const formData = new FormData(form);
                removeViaAjax(
                    Number(formData.get('product_id') || 0),
                    Number(formData.get('product_image_id') || 0),
                    null,
                    String(formData.get('cart_id') || '')
                );
            });
        }

        function submitViaAjax(id, qty, capImgId = null, capImgSrc = null, productImageId = null) {
            let formData = new FormData();
            formData.append('action', 'add');
            formData.append('product_id', id);
            formData.append('quantity', qty);
            if (capImgId) formData.append('cap_img_id', capImgId);
            if (capImgSrc) formData.append('cap_img_src', capImgSrc);
            if (productImageId) formData.append('product_image_id', productImageId);

            fetch('pos.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if(data.status === 'success') {
                    document.getElementById('cartContainer').innerHTML = data.cart_html;
                    document.getElementById('grandTotal').innerText = 'Total: ₦' + data.total;
                    if (window.showToast) {
                        showToast("Added to cart", { type: "success", duration: 1500 });
                    }
                    if (capImgId) { selectedCapIds.add(Number(capImgId)); }
                    
                    // Update visible stock immediately after a successful add-to-cart action.
                    let stockLabel = document.getElementById('stock_display_' + id);
                    if(stockLabel) {
                        // Read the current value from the `data-stock` attribute.
                        let currentVal = parseFloat(stockLabel.getAttribute('data-stock'));
                        let newVal = currentVal - parseFloat(qty);
                        if(newVal < 0) newVal = 0;
                        setProductStockState(id, newVal);
                    }

                } else if (data.status === 'error') {
                    if (window.showToast) {
                        showToast("Stock Error - " + data.message, { type: "error", duration: 3000 });
                    }
                }
            });
        }

        const checkoutForm = document.getElementById('checkoutForm');
        if (checkoutForm) {
            checkoutForm.addEventListener('submit', function(event) {
                const paymentInput = document.getElementById('paymentMethodInput');
                if (paymentInput && paymentInput.value) {
                    return;
                }

                event.preventDefault();
                Swal.fire({
                    title: 'Select Payment Method',
                    input: 'radio',
                    inputOptions: {
                        cash: 'Cash',
                        transfer: 'Transfer',
                        pos: 'Bank Card'
                    },
                    inputValidator: (value) => {
                        if (!value) return 'Choose a payment method.';
                        return null;
                    },
                    showCancelButton: true,
                    confirmButtonText: 'Continue',
                    confirmButtonColor: '#1e3c72',
                    cancelButtonColor: '#6c757d'
                }).then((methodResult) => {
                    if (!methodResult.isConfirmed || !methodResult.value) return;

                    Swal.fire({
                        icon: 'question',
                        title: 'Payment Made?',
                        text: 'Confirm that the customer payment has been received before saving this sale.',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, save sale',
                        cancelButtonText: 'No, go back',
                        confirmButtonColor: '#28a745',
                        cancelButtonColor: '#6c757d'
                    }).then((confirmResult) => {
                        if (!confirmResult.isConfirmed) return;
                        if (paymentInput) {
                            paymentInput.value = methodResult.value;
                        }
                        checkoutForm.submit();
                    });
                });
            });
        }
        
        <?php if(isset($_GET['success'])): $sid = $_GET['success']; ?>
        Swal.fire({ 
            icon: 'success', 
            title: 'Payment Successful!', 
            html: 'Transaction #<?php echo str_pad($sid, 6, '0', STR_PAD_LEFT); ?> saved.',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-print"></i> Print Receipt',
            cancelButtonText: 'New Sale',
            confirmButtonColor: '#1e3c72',
            cancelButtonColor: '#6c757d'
        }).then((result) => {
            if (result.isConfirmed) {
                window.open('print_receipt.php?id=<?php echo $sid; ?>', 'Receipt', 'width=400,height=600');
            }
        });
        window.history.replaceState({}, document.title, "pos.php");
        <?php endif; ?>

        <?php if(isset($_GET['hold_created'])): $hold_created = (int)$_GET['hold_created']; ?>
        if (window.showToast) {
            showToast("Order placed on hold as #" + String(<?php echo $hold_created; ?>).padStart(6, '0') + ".", { type: "success", duration: 2500 });
        }
        window.history.replaceState({}, document.title, "pos.php");
        <?php endif; ?>

        <?php if(isset($_GET['error'])): ?>
        if (window.showToast) {
            showToast(<?php echo json_encode($_GET['error']); ?>, { type: "error", duration: 3200 });
        }
        window.history.replaceState({}, document.title, "pos.php");
        <?php endif; ?>

        document.addEventListener('galadawa:live-update', function() {
            refreshPosInventory();
        });
    </script>
</body>
</html>
