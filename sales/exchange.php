<?php
session_start();
include '../includes/db_connect.php';
include '../includes/payouts.php';
include '../includes/order_workflow.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
ensure_payout_table($conn);
ensure_order_workflow_schema($conn);
release_expired_holds($conn);

$payout_unread = get_user_unread_count($conn, $user_id);
$active_hold_count = get_active_holds_count($conn);

function exchange_available_quantity($row) {
    $quantity = (float)($row['quantity'] ?? 0);
    $exchanged = (float)($row['exchanged_quantity'] ?? 0);
    return max(0, $quantity - $exchanged);
}

function load_exchange_product_catalog($conn) {
    $catalog = [];
    $catalog_res = $conn->query("
        SELECT p.*,
               (SELECT image_name FROM product_images WHERE product_id = p.id ORDER BY id ASC LIMIT 1) AS gallery_image,
               (SELECT COUNT(*) FROM product_images WHERE product_id = p.id AND yards IS NOT NULL) AS variant_count
        FROM products p
        ORDER BY p.name ASC
    ");
    if ($catalog_res) {
        while ($row = $catalog_res->fetch_assoc()) {
            $catalog[] = $row;
        }
    }
    return $catalog;
}

function render_replacement_product_options($productCatalog) {
    ob_start();
    ?>
    <option value="">Choose replacement product</option>
    <?php foreach ($productCatalog as $product): ?>
        <option value="<?php echo (int)$product['id']; ?>"
            data-price="<?php echo (float)$product['sell_price']; ?>"
            data-stock="<?php echo (float)$product['quantity']; ?>"
            data-cap="<?php echo strpos((string)($product['category'] ?? ''), 'Cap') !== false ? '1' : '0'; ?>"
            data-variant-count="<?php echo (int)($product['variant_count'] ?? 0); ?>"
            data-name="<?php echo htmlspecialchars((string)$product['name'], ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars((string)$product['name'], ENT_QUOTES, 'UTF-8'); ?> · ₦<?php echo number_format((float)$product['sell_price'], 2); ?> · stock <?php echo (float)$product['quantity']; ?>
        </option>
    <?php endforeach; ?>
    <?php
    return (string)ob_get_clean();
}

function fetch_exchange_lookup_data($conn, $saleLookupId) {
    $saleLookupId = (int)$saleLookupId;
    $data = [
        'sale_lookup_id' => $saleLookupId,
        'sale' => null,
        'sale_items' => [],
        'sale_exchange_deadline' => 0,
        'sale_exchange_open' => false,
    ];

    if ($saleLookupId <= 0) {
        return $data;
    }

    $sale_res = $conn->query("
        SELECT s.*, u.username
        FROM sales s
        JOIN users u ON u.id = s.user_id
        WHERE s.id = '$saleLookupId'
        LIMIT 1
    ");
    $sale = $sale_res ? $sale_res->fetch_assoc() : null;
    $data['sale'] = $sale;

    if (!$sale) {
        return $data;
    }

    $data['sale_exchange_deadline'] = get_exchange_deadline_timestamp((string)($sale['created_at'] ?? ''));
    $data['sale_exchange_open'] = can_exchange_sale((string)($sale['created_at'] ?? ''));

    $items_res = $conn->query("
        SELECT si.*, p.name AS product_name, p.category, pi.color_name AS item_color_name
        FROM sale_items si
        JOIN products p ON p.id = si.product_id
        LEFT JOIN product_images pi ON pi.id = si.product_image_id
        WHERE si.sale_id = '$saleLookupId'
        ORDER BY si.id ASC
    ");
    if ($items_res) {
        while ($row = $items_res->fetch_assoc()) {
            $row['available_to_exchange'] = exchange_available_quantity($row);
            if ($row['available_to_exchange'] > 0) {
                $data['sale_items'][] = $row;
            }
        }
    }

    return $data;
}

function render_exchange_lookup_markup($lookupData, $productCatalog) {
    $sale_lookup_id = (int)($lookupData['sale_lookup_id'] ?? 0);
    $sale = $lookupData['sale'] ?? null;
    $sale_items = $lookupData['sale_items'] ?? [];
    $sale_exchange_deadline = (int)($lookupData['sale_exchange_deadline'] ?? 0);
    $sale_exchange_open = !empty($lookupData['sale_exchange_open']);
    $replacement_options_html = render_replacement_product_options($productCatalog);

    ob_start();
    ?>
    <?php if ($sale_lookup_id > 0 && !$sale): ?>
        <div class="empty-state">
            <i class="fas fa-receipt" style="font-size:40px; margin-bottom:10px;"></i>
            <p style="margin:0;">No receipt found for #<?php echo str_pad($sale_lookup_id, 6, '0', STR_PAD_LEFT); ?>.</p>
        </div>
    <?php endif; ?>

    <?php if ($sale): ?>
        <div class="panel">
            <h3 style="margin-top:0;">Receipt #<?php echo str_pad((int)$sale['id'], 6, '0', STR_PAD_LEFT); ?></h3>
            <div class="sale-meta">
                <div class="meta-box"><span>Customer</span><strong><?php echo htmlspecialchars((string)($sale['customer_name'] ?? 'Walk-in Customer'), ENT_QUOTES, 'UTF-8'); ?></strong></div>
                <div class="meta-box"><span>Attendant</span><strong>@<?php echo htmlspecialchars((string)$sale['username'], ENT_QUOTES, 'UTF-8'); ?></strong></div>
                <div class="meta-box"><span>Date</span><strong><?php echo date('M d, Y h:i A', strtotime((string)$sale['created_at'])); ?></strong></div>
                <div class="meta-box"><span>Total</span><strong>₦<?php echo number_format((float)$sale['total_amount'], 2); ?></strong></div>
                <div class="meta-box"><span>Exchange Deadline</span><strong><?php echo $sale_exchange_deadline > 0 ? date('M d, Y h:i A', $sale_exchange_deadline) : 'Unavailable'; ?></strong></div>
                <div class="meta-box"><span>Status</span><strong style="color: <?php echo $sale_exchange_open ? '#15803d' : '#b42318'; ?>;"><?php echo $sale_exchange_open ? 'Within 24-hour window' : 'Exchange window closed'; ?></strong></div>
            </div>

            <?php if (count($sale_items) > 0): ?>
                <div class="sale-items">
                    <?php foreach ($sale_items as $item): ?>
                        <div class="sale-item-card <?php echo $sale_exchange_open ? 'selectable-sale-item js-sale-item-card' : ''; ?>"
                            <?php if ($sale_exchange_open): ?>
                                data-sale-item-id="<?php echo (int)$item['id']; ?>"
                                role="button"
                                tabindex="0"
                                onclick="selectSaleItemFromCard(<?php echo (int)$item['id']; ?>)"
                                onkeydown="return handleSaleItemCardKey(event, <?php echo (int)$item['id']; ?>)"
                            <?php endif; ?>>
                            <h4><?php echo htmlspecialchars((string)$item['product_name'], ENT_QUOTES, 'UTF-8'); ?></h4>
                            <?php if (!empty($item['item_color_name'])): ?>
                                <p class="item-colour-line">Colour: <?php echo htmlspecialchars((string)$item['item_color_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php endif; ?>
                            <p style="margin:0; color:#667085;">Sold: <?php echo (float)$item['quantity']; ?> | Already exchanged: <?php echo (float)$item['exchanged_quantity']; ?> | Available now: <?php echo (float)$item['available_to_exchange']; ?></p>
                            <p style="margin:4px 0 0; color:#667085;">Unit price: ₦<?php echo number_format((float)$item['price'], 2); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($sale_exchange_open): ?>
                    <form method="POST" class="exchange-form" id="exchangeForm">
                        <input type="hidden" name="process_exchange" value="1">
                        <input type="hidden" name="sale_id" value="<?php echo (int)$sale['id']; ?>">
                        <div class="form-row">
                            <div class="field">
                                <label for="saleItemSelect">Sold Item To Exchange</label>
                                <select name="sale_item_id" id="saleItemSelect" required>
                                    <option value="">Select sold item</option>
                                    <?php foreach ($sale_items as $item): ?>
                                        <option value="<?php echo (int)$item['id']; ?>" data-price="<?php echo (float)$item['price']; ?>" data-maxqty="<?php echo (float)$item['available_to_exchange']; ?>">
                                            <?php echo htmlspecialchars((string)$item['product_name'], ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($item['item_color_name']) ? ' · Colour: ' . htmlspecialchars((string)$item['item_color_name'], ENT_QUOTES, 'UTF-8') : ''; ?> · available <?php echo (float)$item['available_to_exchange']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label for="oldQuantityInput">Returned Quantity</label>
                                <input type="number" name="old_quantity" id="oldQuantityInput" min="0.1" step="0.1" required>
                            </div>
                        </div>

                        <div class="field">
                            <label>Replacement Items</label>
                            <div class="replacement-rows" id="replacementRows">
                                <div class="replacement-row">
                                    <div class="replacement-row-grid">
                                        <div class="field">
                                            <label>Replacement Product</label>
                                            <select name="new_product_id[]" class="replacement-product-select" required>
                                                <?php echo $replacement_options_html; ?>
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label>Replacement Quantity</label>
                                            <input type="number" name="new_quantity[]" class="replacement-quantity-input" min="0.1" step="0.1" required>
                                        </div>
                                        <div class="replacement-row-actions">
                                            <button type="button" class="remove-replacement-btn" disabled>Remove</button>
                                        </div>
                                    </div>
                                    <input type="hidden" name="new_product_image_id[]" class="replacement-image-id" value="">
                                    <div class="field replacement-cap-field" style="display:none;">
                                        <label>Replacement Cap Image</label>
                                        <div class="cap-grid replacement-cap-grid"></div>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn-secondary" id="addReplacementRowBtn">Add Another Replacement Item</button>
                        </div>

                        <div class="field">
                            <label for="exchangeNote">Exchange Note</label>
                            <textarea name="exchange_note" id="exchangeNote" rows="3" placeholder="Why is this item being exchanged?"></textarea>
                        </div>

                        <div class="preview-box">
                            <span>Difference</span>
                            <strong id="differenceValue">₦0.00</strong>
                            <p id="differenceLabel" style="margin:8px 0 0; color:#667085;"></p>
                        </div>

                        <button type="submit" class="btn-primary" id="processExchangeBtn">Process Exchange</button>
                    </form>
                    <template id="replacementRowTemplate">
                        <div class="replacement-row">
                            <div class="replacement-row-grid">
                                <div class="field">
                                    <label>Replacement Product</label>
                                    <select name="new_product_id[]" class="replacement-product-select" required>
                                        <?php echo $replacement_options_html; ?>
                                    </select>
                                </div>
                                <div class="field">
                                    <label>Replacement Quantity</label>
                                    <input type="number" name="new_quantity[]" class="replacement-quantity-input" min="0.1" step="0.1" required>
                                </div>
                                <div class="replacement-row-actions">
                                    <button type="button" class="remove-replacement-btn">Remove</button>
                                </div>
                            </div>
                            <input type="hidden" name="new_product_image_id[]" class="replacement-image-id" value="">
                            <div class="field replacement-cap-field" style="display:none;">
                                <label>Replacement Cap Image</label>
                                <div class="cap-grid replacement-cap-grid"></div>
                            </div>
                        </div>
                    </template>
                <?php else: ?>
                    <div class="empty-state" style="box-shadow:none; padding:24px 18px; margin-top:16px; color:#b42318;">
                        <p style="margin:0;">This receipt can no longer be exchanged. The 24-hour window ended on <?php echo $sale_exchange_deadline > 0 ? date('M d, Y h:i A', $sale_exchange_deadline) : 'the configured deadline'; ?>.</p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state" style="box-shadow:none; padding:24px 18px; margin-top:16px;">
                    <p style="margin:0;">This receipt has no remaining items available for exchange.</p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php
    return (string)ob_get_clean();
}

if (isset($_GET['get_cap_images'])) {
    $product_id = (int)$_GET['get_cap_images'];
    $res = $conn->query("SELECT id, image_name FROM product_images WHERE product_id = '$product_id' AND status = 'available' ORDER BY id ASC");
    $images = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $images[] = $row;
        }
    }

    header('Content-Type: application/json');
    echo json_encode($images);
    exit();
}

if (isset($_GET['get_textile_variants'])) {
    $product_id = (int)$_GET['get_textile_variants'];
    $res = $conn->query("SELECT id, image_name, color_name, yards FROM product_images WHERE product_id = '$product_id' AND status = 'available' AND yards IS NOT NULL AND yards > 0 ORDER BY id ASC");
    $variants = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $row['yards'] = (float)$row['yards'];
            $variants[] = $row;
        }
    }

    header('Content-Type: application/json');
    echo json_encode($variants);
    exit();
}

if (isset($_GET['lookup_receipt'])) {
    $lookup_data = fetch_exchange_lookup_data($conn, (int)$_GET['lookup_receipt']);
    $product_catalog = load_exchange_product_catalog($conn);

    header('Content-Type: application/json');
    echo json_encode([
        'ok' => true,
        'sale_id' => (int)($lookup_data['sale_lookup_id'] ?? 0),
        'found' => !empty($lookup_data['sale']),
        'html' => render_exchange_lookup_markup($lookup_data, $product_catalog),
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_exchange'])) {
    $sale_id = (int)($_POST['sale_id'] ?? 0);
    $sale_item_id = (int)($_POST['sale_item_id'] ?? 0);
    $old_quantity = (float)($_POST['old_quantity'] ?? 0);
    $exchange_note = trim((string)($_POST['exchange_note'] ?? ''));
    $new_product_ids = $_POST['new_product_id'] ?? [];
    $new_quantities = $_POST['new_quantity'] ?? [];
    $new_product_image_ids = $_POST['new_product_image_id'] ?? [];

    if (!is_array($new_product_ids)) {
        $new_product_ids = [$new_product_ids];
    }
    if (!is_array($new_quantities)) {
        $new_quantities = [$new_quantities];
    }
    if (!is_array($new_product_image_ids)) {
        $new_product_image_ids = [$new_product_image_ids];
    }

    $sale_item_res = $conn->query("
        SELECT si.*,
               s.created_at AS sale_created_at,
               s.user_id AS sale_user_id,
               p.name AS product_name,
               p.category,
               COALESCE(si.buy_price_at_sale, p.buy_price, 0) AS old_buy_price_snapshot
        FROM sale_items si
        JOIN sales s ON s.id = si.sale_id
        JOIN products p ON p.id = si.product_id
        WHERE si.id = '$sale_item_id' AND si.sale_id = '$sale_id'
        LIMIT 1
    ");
    $sale_item = $sale_item_res ? $sale_item_res->fetch_assoc() : null;

    if (!$sale_item) {
        header("Location: exchange.php?sale_id=$sale_id&message=" . urlencode('Invalid exchange request.') . "&type=error");
        exit();
    }

    if (!can_exchange_sale((string)($sale_item['sale_created_at'] ?? ''))) {
        $deadline = get_exchange_deadline_timestamp((string)($sale_item['sale_created_at'] ?? ''));
        $deadline_label = $deadline > 0 ? date('M d, Y h:i A', $deadline) : 'the allowed time';
        header("Location: exchange.php?sale_id=$sale_id&message=" . urlencode("Exchange window has closed. Exchanges are only allowed within 24 hours of the original sale. Deadline was $deadline_label.") . "&type=warning");
        exit();
    }

    $old_available = exchange_available_quantity($sale_item);
    if ($old_quantity <= 0 || $old_quantity > $old_available) {
        header("Location: exchange.php?sale_id=$sale_id&message=" . urlencode('Selected quantity is not available for exchange.') . "&type=warning");
        exit();
    }

    $old_price = (float)$sale_item['price'];
    $old_subtotal = $old_price * $old_quantity;
    $replacement_items = [];
    $selected_cap_image_ids = [];
    $new_total_amount = 0.0;

    foreach ($new_product_ids as $index => $productIdRaw) {
        $new_product_id = (int)$productIdRaw;
        if ($new_product_id <= 0) {
            continue;
        }

        $new_product_res = $conn->query("SELECT * FROM products WHERE id = '$new_product_id' LIMIT 1");
        $new_product = $new_product_res ? $new_product_res->fetch_assoc() : null;
        if (!$new_product) {
            header("Location: exchange.php?sale_id=$sale_id&message=" . urlencode('One of the selected replacement products is invalid.') . "&type=error");
            exit();
        }

        $new_quantity = isset($new_quantities[$index]) ? (float)$new_quantities[$index] : 0.0;
        $new_product_image_id = isset($new_product_image_ids[$index]) && $new_product_image_ids[$index] !== '' ? (int)$new_product_image_ids[$index] : null;
        $new_is_cap = strpos((string)($new_product['category'] ?? ''), 'Cap') !== false;
        $variant_count_res = $conn->query("SELECT COUNT(*) AS c FROM product_images WHERE product_id = '$new_product_id' AND yards IS NOT NULL");
        $new_has_textile_variants = !$new_is_cap && $variant_count_res && (int)($variant_count_res->fetch_assoc()['c'] ?? 0) > 0;

        if ($new_is_cap) {
            $new_quantity = 1.0;
            if (!$new_product_image_id) {
                header("Location: exchange.php?sale_id=$sale_id&message=" . urlencode('Select the cap image for every cap replacement item.') . "&type=warning");
                exit();
            }
            if (in_array($new_product_image_id, $selected_cap_image_ids, true)) {
                header("Location: exchange.php?sale_id=$sale_id&message=" . urlencode('You selected the same cap image more than once. Choose a different cap image for each replacement row.') . "&type=warning");
                exit();
            }
            $selected_cap_image_ids[] = $new_product_image_id;
        } elseif ($new_has_textile_variants) {
            if (!$new_product_image_id) {
                header("Location: exchange.php?sale_id=$sale_id&message=" . urlencode('Select the textile colour for every textile replacement item.') . "&type=warning");
                exit();
            }
            if ($new_quantity <= 0) {
                header("Location: exchange.php?sale_id=$sale_id&message=" . urlencode('Enter a valid quantity for every replacement item.') . "&type=warning");
                exit();
            }
        } elseif ($new_quantity <= 0) {
            header("Location: exchange.php?sale_id=$sale_id&message=" . urlencode('Enter a valid quantity for every replacement item.') . "&type=warning");
            exit();
        }

        $new_price = (float)$new_product['sell_price'];
        $new_subtotal = $new_price * $new_quantity;
        $new_buy_price_snapshot = (float)($new_product['buy_price'] ?? 0);
        $new_total_amount += $new_subtotal;
        $replacement_items[] = [
            'product_id' => $new_product_id,
            'product_image_id' => $new_product_image_id,
            'quantity' => $new_quantity,
            'price' => $new_price,
            'subtotal' => $new_subtotal,
            'buy_price_snapshot' => $new_buy_price_snapshot,
        ];
    }

    if (empty($replacement_items)) {
        header("Location: exchange.php?sale_id=$sale_id&message=" . urlencode('Select at least one replacement item.') . "&type=warning");
        exit();
    }

    $difference = $new_total_amount - $old_subtotal;
    if ($difference < 0) {
        header("Location: exchange.php?sale_id=$sale_id&message=" . urlencode('Exchange blocked. Replacement value must be equal to or higher than the returned item value; customer refunds are not allowed.') . "&type=warning");
        exit();
    }

    $adjustment_type = get_exchange_adjustment_type($difference);
    $note_sql = $conn->real_escape_string($exchange_note);
    $old_buy_price_snapshot = (float)($sale_item['old_buy_price_snapshot'] ?? 0);
    $commission_user_id = (int)($sale_item['sale_user_id'] ?? 0);
    if ($commission_user_id <= 0) {
        $commission_user_id = $user_id;
    }

    $conn->begin_transaction();

    try {
        $old_product_id = (int)$sale_item['product_id'];
        $old_product_image_id = $sale_item['product_image_id'] !== null ? (int)$sale_item['product_image_id'] : null;

        if (!restore_exchanged_item_inventory($conn, $old_product_id, $old_quantity, $old_product_image_id)) {
            throw new RuntimeException('Unable to return the original item back to stock.');
        }

        foreach ($replacement_items as $replacement_item) {
            if (!take_exchange_replacement_inventory(
                $conn,
                (int)$replacement_item['product_id'],
                (float)$replacement_item['quantity'],
                $replacement_item['product_image_id'] !== null ? (int)$replacement_item['product_image_id'] : null
            )) {
                throw new RuntimeException('One of the replacement items is no longer available.');
            }
        }

        $conn->query("INSERT INTO exchange_transactions (user_id, commission_user_id, original_sale_id, adjustment_type, total_old_amount, total_new_amount, amount_difference, note) VALUES ('$user_id', '$commission_user_id', '$sale_id', '$adjustment_type', '$old_subtotal', '$new_total_amount', '$difference', '$note_sql')");
        $exchange_id = (int)$conn->insert_id;
        if ($exchange_id <= 0) {
            throw new RuntimeException('Unable to save the exchange transaction.');
        }

        $old_product_image_sql = $old_product_image_id ? "'$old_product_image_id'" : "NULL";

        foreach ($replacement_items as $index => $replacement_item) {
            $new_product_image_sql = $replacement_item['product_image_id'] ? "'" . (int)$replacement_item['product_image_id'] . "'" : "NULL";
            $row_old_quantity = $index === 0 ? $old_quantity : 0;
            $row_old_subtotal = $index === 0 ? $old_subtotal : 0;
            $row_old_image_sql = $index === 0 ? $old_product_image_sql : "NULL";
            $conn->query("INSERT INTO exchange_items (exchange_id, original_sale_item_id, old_product_id, old_product_image_id, old_quantity, old_price, old_buy_price_snapshot, old_subtotal, new_product_id, new_product_image_id, new_quantity, new_price, new_buy_price_snapshot, new_subtotal) VALUES ('$exchange_id', '$sale_item_id', '$old_product_id', $row_old_image_sql, '$row_old_quantity', '$old_price', '$old_buy_price_snapshot', '$row_old_subtotal', '" . (int)$replacement_item['product_id'] . "', $new_product_image_sql, '" . (float)$replacement_item['quantity'] . "', '" . (float)$replacement_item['price'] . "', '" . (float)$replacement_item['buy_price_snapshot'] . "', '" . (float)$replacement_item['subtotal'] . "')");
            if ($conn->affected_rows !== 1) {
                throw new RuntimeException('Unable to save exchange item details.');
            }
        }

        $conn->query("UPDATE sale_items SET exchanged_quantity = exchanged_quantity + $old_quantity WHERE id = '$sale_item_id'");
        if ($conn->affected_rows !== 1) {
            throw new RuntimeException('Unable to lock the exchanged quantity.');
        }

        $conn->commit();
        header("Location: exchange.php?sale_id=$sale_id&message=" . urlencode('Exchange completed successfully.') . "&type=success&exchange_id=$exchange_id");
        exit();
    } catch (Throwable $e) {
        $conn->rollback();
        header("Location: exchange.php?sale_id=$sale_id&message=" . urlencode($e->getMessage()) . "&type=error");
        exit();
    }
}

$sale_lookup_id = isset($_GET['sale_id']) ? (int)$_GET['sale_id'] : 0;
$lookup_data = fetch_exchange_lookup_data($conn, $sale_lookup_id);
$sale = $lookup_data['sale'];
$sale_items = $lookup_data['sale_items'];
$sale_exchange_deadline = (int)$lookup_data['sale_exchange_deadline'];
$sale_exchange_open = !empty($lookup_data['sale_exchange_open']);

$recent_exchanges = [];
$recent_res = $conn->query("
    SELECT e.*, u.username
    FROM exchange_transactions e
    JOIN users u ON u.id = e.user_id
    ORDER BY e.id DESC
    LIMIT 8
");
if ($recent_res) {
    while ($row = $recent_res->fetch_assoc()) {
        $recent_exchanges[] = $row;
    }
}

$product_catalog = load_exchange_product_catalog($conn);

$flash_message = trim((string)($_GET['message'] ?? ''));
$flash_type = trim((string)($_GET['type'] ?? 'success'));
$flash_exchange_id = isset($_GET['exchange_id']) ? (int)$_GET['exchange_id'] : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exchange | Galadawa</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/toastify.min.css">
    <script src="../js/toastify.min.js"></script>
    <script src="../js/toast.js"></script>
    <style>
        body { font-family: 'Poppins', sans-serif; margin: 0; background: #f4f7f6; color: #24303d; }
        * { box-sizing: border-box; }
        .dashboard-container { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: #1e3c72; color: white; display: flex; flex-direction: column; padding: 20px; transition: width 0.3s; position: sticky; top: 0; height: 100vh; overflow: hidden; }
        .sidebar.collapsed { width: 80px; }
        .sidebar.collapsed h3, .sidebar.collapsed span { display: none; }
        .sidebar.collapsed .sidebar-header { justify-content: center; }
        .sidebar.collapsed a { justify-content: center; }
        .sidebar.collapsed i { margin: 0; font-size: 20px; }
        .sidebar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; height: 40px; }
        .toggle-btn { background: none; border: none; color: white; font-size: 20px; cursor: pointer; }
        .sidebar a { color: rgba(255,255,255,0.8); padding: 15px; margin-bottom: 5px; border-radius: 8px; display: flex; align-items: center; gap: 15px; text-decoration: none; transition: 0.3s; white-space: nowrap; position: relative; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.1); color: white; }
        .notif-dot { width: 8px; height: 8px; background: #e74c3c; border-radius: 50%; position: absolute; right: 12px; top: 50%; transform: translateY(-50%); }
        .main-content { flex: 1; padding: 30px; min-width: 0; }
        .page-shell { max-width: 1220px; margin: 0 auto; }
        .panel { background: white; border-radius: 20px; padding: 22px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08); margin-bottom: 18px; }
        .search-form { display: flex; gap: 10px; align-items: center; }
        .search-form input { flex: 1; min-width: 0; border: 1px solid #d0d5dd; border-radius: 12px; padding: 12px 14px; font-size: 14px; }
        .btn-primary { border: none; border-radius: 12px; background: #1e3c72; color: white; padding: 12px 16px; font-weight: 700; cursor: pointer; }
        .btn-primary:disabled { background: #98a2b3; cursor: not-allowed; }
        .lookup-loading { background: white; border-radius: 20px; padding: 24px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08); color: #667085; }
        .sale-meta { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-top: 16px; }
        .meta-box { background: #f8fafc; border: 1px solid #edf2f7; border-radius: 14px; padding: 12px; }
        .meta-box span { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #667085; }
        .meta-box strong { display: block; margin-top: 4px; font-size: 14px; color: #24303d; word-break: break-word; }
        .sale-items { display: grid; gap: 10px; margin-top: 16px; }
        .sale-item-card { background: #f8fafc; border: 1px solid #edf2f7; border-radius: 14px; padding: 14px; }
        .sale-item-card h4 { margin: 0 0 6px; font-size: 15px; }
        .item-colour-line { margin: 0 0 6px; color: #1e3c72; font-size: 13px; font-weight: 700; }
        .selectable-sale-item { cursor: pointer; transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease; }
        .selectable-sale-item:hover,
        .selectable-sale-item:focus-visible { border-color: #1e3c72; box-shadow: 0 0 0 2px rgba(30, 60, 114, 0.12); outline: none; transform: translateY(-1px); }
        .selectable-sale-item.selected { border-color: #1e3c72; background: #eef4ff; box-shadow: 0 0 0 2px rgba(30, 60, 114, 0.12); }
        .exchange-form { display: grid; gap: 14px; margin-top: 18px; }
        .form-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .field label { display: block; font-size: 12px; color: #667085; font-weight: 600; margin-bottom: 6px; }
        .field input, .field select, .field textarea { width: 100%; border: 1px solid #d0d5dd; border-radius: 12px; padding: 12px 14px; font-size: 14px; }
        .replacement-rows { display: grid; gap: 12px; }
        .replacement-row { background: #f8fafc; border: 1px solid #edf2f7; border-radius: 14px; padding: 14px; }
        .replacement-row-grid { display: grid; grid-template-columns: minmax(0, 1.8fr) minmax(0, 1fr) auto; gap: 12px; align-items: end; }
        .replacement-row-actions { display: flex; align-items: end; }
        .remove-replacement-btn { border: 1px solid #d0d5dd; background: #fff; color: #344054; border-radius: 12px; padding: 12px 14px; font-weight: 700; cursor: pointer; }
        .remove-replacement-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-secondary { margin-top: 10px; border: 1px dashed #1e3c72; background: #eef4ff; color: #1e3c72; border-radius: 12px; padding: 11px 14px; font-weight: 700; cursor: pointer; }
        .preview-box { background: linear-gradient(135deg, #eef4ff 0%, #f8fbff 100%); border: 1px solid #dbe7ff; border-radius: 16px; padding: 16px; }
        .preview-box span { display: block; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: #667085; }
        .preview-box strong { display: block; margin-top: 6px; font-size: 28px; color: #1e3c72; }
        .cap-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)); gap: 10px; margin-top: 12px; }
        .cap-option { border: 2px solid #d0d5dd; border-radius: 12px; overflow: hidden; cursor: pointer; background: white; min-width: 0; }
        .cap-option img { display: block; width: 100%; height: 90px; object-fit: cover; }
        .cap-option strong { word-break: break-word; }
        .cap-option.selected { border-color: #1e3c72; box-shadow: 0 0 0 2px rgba(30, 60, 114, 0.1); }
        .history-grid { display: grid; gap: 12px; margin-top: 18px; }
        .history-card { background: white; border-radius: 18px; padding: 18px; box-shadow: 0 10px 24px rgba(15, 23, 42, 0.07); }
        .history-card h4 { margin: 0 0 6px; font-size: 17px; color: #1e3c72; }
        .history-card p { margin: 4px 0; font-size: 14px; color: #667085; }
        .history-card a { display: inline-flex; margin-top: 10px; text-decoration: none; color: #1e3c72; font-weight: 700; }
        .empty-state { background: white; border-radius: 20px; padding: 40px 20px; text-align: center; color: #98a2b3; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08); }

        @media (max-width: 980px) {
            .sale-meta,
            .form-row { grid-template-columns: 1fr; }
            .search-form { flex-direction: column; align-items: stretch; }
            .replacement-row-grid { grid-template-columns: 1fr; }
            .replacement-row-actions { align-items: stretch; }
        }

        @media (max-width: 640px) {
            .main-content { padding: 16px; }
            .btn-primary,
            .btn-secondary,
            .remove-replacement-btn { width: 100%; }
            .cap-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
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
        <a href="pos.php"><i class="fas fa-cash-register"></i> <span>New Sale</span></a>
        <a href="holding_orders.php"><i class="fas fa-boxes-stacked"></i> <span>Holding</span><?php if ($active_hold_count > 0): ?><span class="notif-dot"></span><span class="live-notif-count" style="margin-left:6px; font-size:11px; color:#fff; opacity:0.8;">(<?php echo $active_hold_count; ?>)</span><?php endif; ?></a>
        <a href="exchange.php" class="active"><i class="fas fa-right-left"></i> <span>Exchange</span></a>
        <a href="inventory_view.php"><i class="fas fa-box"></i> <span>View Inventory</span></a>
        <a href="my_history.php"><i class="fas fa-history"></i> <span>My History</span></a>
        <a href="profile.php"><i class="fas fa-user-cog"></i> <span>My Profile</span></a>
        <a href="payouts.php"><i class="fas fa-wallet"></i> <span>Payouts</span><?php if ($payout_unread > 0): ?><span class="notif-dot"></span><?php endif; ?></a>
    </div>

    <div class="main-content">
        <div class="page-shell">
            <div class="panel">
                <h2 style="margin:0;">Product Exchange</h2>
                <form method="GET" class="search-form" id="receiptLookupForm">
                    <input type="number" name="sale_id" id="receiptLookupInput" min="1" value="<?php echo $sale_lookup_id > 0 ? $sale_lookup_id : ''; ?>" placeholder="Enter receipt number" autocomplete="off">
                </form>
            </div>

            <div id="receiptLookupContainer">
                <?php echo render_exchange_lookup_markup($lookup_data, $product_catalog); ?>
            </div>

            <div class="history-grid">
                <?php if (count($recent_exchanges) > 0): ?>
                    <?php foreach ($recent_exchanges as $exchange): ?>
                        <article class="history-card">
                            <h4>Exchange #<?php echo str_pad((int)$exchange['id'], 6, '0', STR_PAD_LEFT); ?></h4>
                            <p>Receipt #<?php echo str_pad((int)$exchange['original_sale_id'], 6, '0', STR_PAD_LEFT); ?> · handled by @<?php echo htmlspecialchars((string)$exchange['username'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p><?php echo htmlspecialchars(get_exchange_adjustment_label((string)$exchange['adjustment_type']), ENT_QUOTES, 'UTF-8'); ?> · difference ₦<?php echo number_format(abs((float)$exchange['amount_difference']), 2); ?></p>
                            <p><?php echo date('M d, Y h:i A', strtotime((string)$exchange['created_at'])); ?></p>
                            <a href="exchange_receipt.php?id=<?php echo (int)$exchange['id']; ?>" target="_blank" rel="noopener">Open Exchange Receipt</a>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-right-left" style="font-size:40px; margin-bottom:10px;"></i>
                        <p style="margin:0;">No exchanges have been recorded yet.</p>
                    </div>
                <?php endif; ?>
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

    const receiptLookupForm = document.getElementById('receiptLookupForm');
    const receiptLookupInput = document.getElementById('receiptLookupInput');
    const receiptLookupContainer = document.getElementById('receiptLookupContainer');
    let receiptLookupTimer = null;
    let receiptLookupToken = 0;

    function getSelectedOption(select) {
        return select && select.selectedIndex > -1 ? select.options[select.selectedIndex] : null;
    }

    function formatCurrency(value) {
        return '₦' + Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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

    function getReplacementRows() {
        return Array.from(document.querySelectorAll('#replacementRows .replacement-row'));
    }

    function clearReplacementRow(row) {
        if (!row) return;
        const select = row.querySelector('.replacement-product-select');
        const qty = row.querySelector('.replacement-quantity-input');
        const capField = row.querySelector('.replacement-cap-field');
        const capGrid = row.querySelector('.replacement-cap-grid');
        const imageInput = row.querySelector('.replacement-image-id');

        if (select) {
            select.value = '';
        }
        if (qty) {
            qty.readOnly = false;
            qty.value = '';
            qty.max = '';
        }
        if (capField) {
            capField.style.display = 'none';
        }
        if (capGrid) {
            capGrid.innerHTML = '';
        }
        if (imageInput) {
            imageInput.value = '';
        }
    }

    function clampQuantityInput(input, maxValue) {
        if (!input) return;
        const rawValue = Number(input.value || 0);
        const max = Number(maxValue || 0);
        if (max > 0 && rawValue > max) {
            input.value = String(max);
            return;
        }
        if (rawValue < 0) {
            input.value = '';
        }
    }

    function syncReturnedQuantityWithSelectedItem(forceFill = false) {
        const saleItemSelect = document.getElementById('saleItemSelect');
        const oldQuantityInput = document.getElementById('oldQuantityInput');
        if (!saleItemSelect || !oldQuantityInput) return;

        const option = getSelectedOption(saleItemSelect);
        if (!option || !option.value) {
            oldQuantityInput.value = '';
            oldQuantityInput.max = '';
            oldQuantityInput.placeholder = '';
            return;
        }

        const maxQty = Number(option.dataset.maxqty || 0);
        oldQuantityInput.max = option.dataset.maxqty || '';
        oldQuantityInput.placeholder = `Max ${option.dataset.maxqty || ''}`;

        if (forceFill || !oldQuantityInput.value || Number(oldQuantityInput.value) <= 0) {
            oldQuantityInput.value = maxQty > 0 ? String(maxQty) : '';
            return;
        }

        clampQuantityInput(oldQuantityInput, maxQty);
    }

    async function loadCapOptions(row) {
        if (!row) return;
        const newProductSelect = row.querySelector('.replacement-product-select');
        const capSelectionField = row.querySelector('.replacement-cap-field');
        const capSelectionLabel = capSelectionField ? capSelectionField.querySelector('label') : null;
        const capGrid = row.querySelector('.replacement-cap-grid');
        const newProductImageId = row.querySelector('.replacement-image-id');
        const newQuantityInput = row.querySelector('.replacement-quantity-input');
        if (!newProductSelect || !capSelectionField || !capGrid || !newProductImageId || !newQuantityInput) return;

        const option = getSelectedOption(newProductSelect);
        if (!option || !option.value) {
            capSelectionField.style.display = 'none';
            capGrid.innerHTML = '';
            newProductImageId.value = '';
            newQuantityInput.readOnly = false;
            newQuantityInput.value = '';
            newQuantityInput.max = '';
            updateDifferencePreview();
            return;
        }

        const hasTextileVariants = option.dataset.cap !== '1' && Number(option.dataset.variantCount || 0) > 0;
        if (option.dataset.cap !== '1' && !hasTextileVariants) {
            capSelectionField.style.display = 'none';
            capGrid.innerHTML = '';
            newProductImageId.value = '';
            newQuantityInput.readOnly = false;
            if (!newQuantityInput.value || Number(newQuantityInput.value) <= 0) {
                newQuantityInput.value = '1';
            }
            newQuantityInput.max = option ? option.dataset.stock || '' : '';
            clampQuantityInput(newQuantityInput, option ? option.dataset.stock || 0 : 0);
            updateDifferencePreview();
            return;
        }

        capSelectionField.style.display = 'block';
        newProductImageId.value = '';
        const requestToken = String(Date.now()) + Math.random().toString(16).slice(2);
        row.dataset.capRequestToken = requestToken;

        if (option.dataset.cap === '1') {
            newQuantityInput.value = '1';
            newQuantityInput.readOnly = true;
            newQuantityInput.max = '1';
            if (capSelectionLabel) capSelectionLabel.textContent = 'Replacement Cap Image';
            capGrid.innerHTML = '<div style="color:#667085;">Loading cap images...</div>';
        } else {
            newQuantityInput.readOnly = false;
            newQuantityInput.value = '';
            newQuantityInput.max = '';
            if (capSelectionLabel) capSelectionLabel.textContent = 'Replacement Textile Colour';
            capGrid.innerHTML = '<div style="color:#667085;">Loading textile colours...</div>';
        }

        try {
            const endpoint = option.dataset.cap === '1' ? 'get_cap_images' : 'get_textile_variants';
            const response = await fetch(`exchange.php?${endpoint}=${option.value}`, {
                credentials: 'same-origin',
                cache: 'no-store'
            });
            const images = await response.json();
            if (row.dataset.capRequestToken !== requestToken) {
                return;
            }
            if (!Array.isArray(images) || images.length === 0) {
                capGrid.innerHTML = option.dataset.cap === '1'
                    ? '<div style="color:#c0392b;">No available cap images left for this product.</div>'
                    : '<div style="color:#c0392b;">No available textile colours left for this product.</div>';
                return;
            }

            if (option.dataset.cap === '1') {
                capGrid.innerHTML = images.map((image) => `
                    <div class="cap-option" data-image-id="${image.id}" onclick="selectExchangeCap(this, ${image.id})">
                        <img src="../uploads/${image.image_name}" alt="">
                    </div>
                `).join('');
            } else {
                capGrid.innerHTML = images.map((image) => {
                    const color = image.color_name ? image.color_name : 'Unnamed colour';
                    const yards = Number(image.yards || 0);
                    return `
                        <div class="cap-option" data-image-id="${image.id}" onclick="selectExchangeVariant(this, ${image.id}, ${yards})">
                            <img src="../uploads/${image.image_name}" alt="">
                            <div style="padding:8px; font-size:12px;">
                                <strong>${escapeHtml(color)}</strong><br>
                                <span style="color:#667085;">${yards} yards</span>
                            </div>
                        </div>
                    `;
                }).join('');
            }
        } catch (error) {
            if (row.dataset.capRequestToken !== requestToken) {
                return;
            }
            capGrid.innerHTML = '<div style="color:#c0392b;">Unable to load options right now.</div>';
        }
    }

    function selectExchangeCap(card, imageId) {
        const row = card ? card.closest('.replacement-row') : null;
        if (!row) return;
        const newProductImageId = row.querySelector('.replacement-image-id');
        row.querySelectorAll('.cap-option').forEach((option) => {
            option.classList.toggle('selected', option.dataset.imageId === String(imageId));
        });
        if (!newProductImageId) return;
        newProductImageId.value = imageId;
    }

    function selectExchangeVariant(card, imageId, yards) {
        const row = card ? card.closest('.replacement-row') : null;
        if (!row) return;
        const newProductImageId = row.querySelector('.replacement-image-id');
        const newQuantityInput = row.querySelector('.replacement-quantity-input');
        row.querySelectorAll('.cap-option').forEach((option) => {
            option.classList.toggle('selected', option.dataset.imageId === String(imageId));
        });
        if (newProductImageId) {
            newProductImageId.value = imageId;
        }
        if (newQuantityInput) {
            newQuantityInput.max = String(yards || '');
            if (!newQuantityInput.value || Number(newQuantityInput.value) <= 0) {
                newQuantityInput.value = '1';
            }
            clampQuantityInput(newQuantityInput, yards);
        }
        updateDifferencePreview();
    }

    function updateDifferencePreview() {
        const saleItemSelect = document.getElementById('saleItemSelect');
        const oldQuantityInput = document.getElementById('oldQuantityInput');
        const differenceValue = document.getElementById('differenceValue');
        const differenceLabel = document.getElementById('differenceLabel');
        const processExchangeBtn = document.getElementById('processExchangeBtn');
        if (!saleItemSelect || !differenceValue || !differenceLabel) return;

        const oldOption = getSelectedOption(saleItemSelect);
        const oldPrice = oldOption ? Number(oldOption.dataset.price || 0) : 0;
        const oldQty = Number(oldQuantityInput.value || 0);
        let newTotal = 0;
        let hasReplacement = false;

        document.querySelectorAll('.replacement-row').forEach((row) => {
            const newProductSelect = row.querySelector('.replacement-product-select');
            const newQuantityInput = row.querySelector('.replacement-quantity-input');
            const newOption = getSelectedOption(newProductSelect);
            const newPrice = newOption ? Number(newOption.dataset.price || 0) : 0;
            const newQty = Number(newQuantityInput ? newQuantityInput.value || 0 : 0);
            if (newOption && newOption.value && newQty > 0) {
                newTotal += (newPrice * newQty);
                hasReplacement = true;
            }
        });

        const diff = newTotal - (oldPrice * oldQty);

        differenceValue.textContent = formatCurrency(Math.abs(diff));
        if (!oldOption || oldQty <= 0 || !hasReplacement) {
            differenceValue.textContent = '₦0.00';
            differenceLabel.textContent = '';
            if (processExchangeBtn) {
                processExchangeBtn.disabled = false;
            }
            return;
        }

        if (diff > 0) {
            differenceLabel.textContent = 'Customer needs to add this amount.';
            differenceLabel.style.color = '#667085';
            if (processExchangeBtn) {
                processExchangeBtn.disabled = false;
            }
        } else if (diff < 0) {
            differenceLabel.textContent = 'Not allowed: replacement value must be equal or higher. Choose another item or increase replacement quantity.';
            differenceLabel.style.color = '#b42318';
            if (processExchangeBtn) {
                processExchangeBtn.disabled = true;
            }
        } else {
            differenceLabel.textContent = 'This exchange is balanced.';
            differenceLabel.style.color = '#667085';
            if (processExchangeBtn) {
                processExchangeBtn.disabled = false;
            }
        }
    }

    function syncSelectedSaleItemCard() {
        const saleItemSelect = document.getElementById('saleItemSelect');
        const selectedValue = saleItemSelect ? String(saleItemSelect.value || '') : '';
        document.querySelectorAll('.js-sale-item-card').forEach((card) => {
            card.classList.toggle('selected', String(card.dataset.saleItemId || '') === selectedValue && selectedValue !== '');
        });
    }

    function selectSaleItemFromCard(itemId) {
        const saleItemSelect = document.getElementById('saleItemSelect');
        const oldQuantityInput = document.getElementById('oldQuantityInput');
        if (!saleItemSelect) return false;

        saleItemSelect.value = String(itemId);
        saleItemSelect.dispatchEvent(new Event('change', { bubbles: true }));
        syncSelectedSaleItemCard();

        if (oldQuantityInput) {
            syncReturnedQuantityWithSelectedItem(true);
            oldQuantityInput.focus();
            oldQuantityInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        return false;
    }

    function handleSaleItemCardKey(event, itemId) {
        if (!event) return false;
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            return selectSaleItemFromCard(itemId);
        }
        return true;
    }

    function updateReplacementRowButtons() {
        const rows = getReplacementRows();
        rows.forEach((row, index) => {
            const removeBtn = row.querySelector('.remove-replacement-btn');
            if (!removeBtn) return;
            removeBtn.disabled = rows.length === 1;
            removeBtn.textContent = 'Remove';
        });
    }

    function createReplacementRow() {
        const template = document.getElementById('replacementRowTemplate');
        if (!template) return null;
        return template.content.firstElementChild.cloneNode(true);
    }

    function bindReplacementRow(row) {
        if (!row || row.dataset.bound === '1') return;

        const newProductSelect = row.querySelector('.replacement-product-select');
        const newQuantityInput = row.querySelector('.replacement-quantity-input');
        const removeBtn = row.querySelector('.remove-replacement-btn');

        if (newProductSelect) {
            newProductSelect.addEventListener('change', function() {
                loadCapOptions(row);
                updateDifferencePreview();
            });
        }

        if (newQuantityInput) {
            newQuantityInput.addEventListener('input', function() {
                const option = getSelectedOption(newProductSelect);
                const max = option && Number(option.dataset.variantCount || 0) > 0
                    ? Number(newQuantityInput.max || 0)
                    : Number(option ? option.dataset.stock || 0 : 0);
                if (option && option.dataset.cap !== '1') {
                    clampQuantityInput(newQuantityInput, max);
                }
                updateDifferencePreview();
            });
        }

        if (removeBtn) {
            removeBtn.addEventListener('click', function() {
                const rows = getReplacementRows();
                if (rows.length <= 1) {
                    clearReplacementRow(row);
                } else {
                    row.remove();
                }
                updateReplacementRowButtons();
                updateDifferencePreview();
            });
        }

        row.dataset.bound = '1';
    }

    function bindExchangeControls() {
        const saleItemSelect = document.getElementById('saleItemSelect');
        const oldQuantityInput = document.getElementById('oldQuantityInput');
        const saleItemCards = document.querySelectorAll('.js-sale-item-card');
        const addReplacementRowBtn = document.getElementById('addReplacementRowBtn');
        const replacementRowsWrap = document.getElementById('replacementRows');

        if (saleItemSelect && oldQuantityInput && saleItemSelect.dataset.bound !== '1') {
            saleItemSelect.addEventListener('change', function() {
                syncReturnedQuantityWithSelectedItem(false);
                syncSelectedSaleItemCard();
                updateDifferencePreview();
            });
            saleItemSelect.dataset.bound = '1';
        }

        if (oldQuantityInput && oldQuantityInput.dataset.bound !== '1') {
            oldQuantityInput.addEventListener('input', function() {
                const option = getSelectedOption(saleItemSelect);
                if (!option) return;
                const max = Number(option.dataset.maxqty || 0);
                if (Number(oldQuantityInput.value || 0) > max) {
                    oldQuantityInput.value = max > 0 ? max : '';
                }
                updateDifferencePreview();
            });
            oldQuantityInput.dataset.bound = '1';
        }

        saleItemCards.forEach((card) => {
            if (card.dataset.bound === '1') return;

            const selectFromCard = () => {
                selectSaleItemFromCard(card.dataset.saleItemId || '');
            };

            card.addEventListener('click', selectFromCard);
            card.addEventListener('keydown', function(event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    selectFromCard();
                }
            });
            card.dataset.bound = '1';
        });

        getReplacementRows().forEach(bindReplacementRow);

        if (addReplacementRowBtn && replacementRowsWrap && addReplacementRowBtn.dataset.bound !== '1') {
            addReplacementRowBtn.addEventListener('click', function() {
                const nextRow = createReplacementRow();
                if (!nextRow) return;
                replacementRowsWrap.appendChild(nextRow);
                updateReplacementRowButtons();
                bindReplacementRow(nextRow);
                const nextSelect = nextRow.querySelector('.replacement-product-select');
                if (nextSelect) {
                    nextSelect.focus();
                }
            });
            addReplacementRowBtn.dataset.bound = '1';
        }

        updateReplacementRowButtons();
        syncSelectedSaleItemCard();
        syncReturnedQuantityWithSelectedItem(false);
        updateDifferencePreview();
    }

    function syncReceiptLookupUrl(value) {
        const url = new URL(window.location.href);
        if (value) {
            url.searchParams.set('sale_id', String(value));
        } else {
            url.searchParams.delete('sale_id');
        }
        window.history.replaceState({}, '', url.toString());
    }

    async function lookupReceiptLive(receiptId) {
        const token = ++receiptLookupToken;
        if (!receiptLookupContainer) return;

        if (!receiptId || Number(receiptId) <= 0) {
            receiptLookupContainer.innerHTML = '';
            syncReceiptLookupUrl('');
            return;
        }

        receiptLookupContainer.innerHTML = '<div class="lookup-loading">Searching receipt...</div>';

        try {
            const response = await fetch(`exchange.php?lookup_receipt=${encodeURIComponent(receiptId)}&t=${Date.now()}`, {
                credentials: 'same-origin',
                cache: 'no-store'
            });
            if (!response.ok) return;
            const payload = await response.json();
            if (token !== receiptLookupToken || !payload || payload.ok !== true) return;

            receiptLookupContainer.innerHTML = payload.html || '';
            syncReceiptLookupUrl(receiptId);
            bindExchangeControls();
        } catch (error) {
            if (token !== receiptLookupToken) return;
            receiptLookupContainer.innerHTML = '<div class="empty-state"><p style="margin:0;">Unable to load this receipt right now.</p></div>';
        }
    }

    if (receiptLookupForm && receiptLookupInput) {
        receiptLookupForm.addEventListener('submit', function(event) {
            event.preventDefault();
            clearTimeout(receiptLookupTimer);
            lookupReceiptLive(receiptLookupInput.value.trim());
        });

        receiptLookupInput.addEventListener('input', function() {
            const value = receiptLookupInput.value.trim();
            clearTimeout(receiptLookupTimer);
            receiptLookupTimer = setTimeout(function() {
                lookupReceiptLive(value);
            }, 220);
        });
    }

    bindExchangeControls();

    <?php if ($flash_message !== ''): ?>
    if (window.showToast) {
        showToast(<?php echo json_encode($flash_message); ?>, { type: <?php echo json_encode($flash_type); ?>, duration: 2800 });
    }
    <?php endif; ?>

    <?php if ($flash_exchange_id > 0): ?>
    setTimeout(function() {
        window.open('exchange_receipt.php?id=<?php echo $flash_exchange_id; ?>', 'ExchangeReceipt', 'width=420,height=680');
    }, 300);
    <?php endif; ?>
</script>
</body>
</html>
