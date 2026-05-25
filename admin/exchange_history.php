<?php
session_start();
include '../includes/db_connect.php';
include '../includes/payouts.php';
include '../includes/order_workflow.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

ensure_payout_table($conn);
ensure_order_workflow_schema($conn);
$admin_payout_unread = get_admin_unread_count($conn);

$summary_counts = ['customer_adds' => 0, 'balanced' => 0, 'customer_credit' => 0];
$summary_res = $conn->query("SELECT adjustment_type, COUNT(*) AS c FROM exchange_transactions GROUP BY adjustment_type");
if ($summary_res) {
    while ($row = $summary_res->fetch_assoc()) {
        $type = (string)($row['adjustment_type'] ?? '');
        if (isset($summary_counts[$type])) {
            $summary_counts[$type] = (int)$row['c'];
        }
    }
}

$search = trim((string)($_GET['search'] ?? ''));
$type_filter = trim((string)($_GET['type'] ?? 'all'));
$allowed_types = ['all', 'customer_adds', 'balanced', 'customer_credit'];
if (!in_array($type_filter, $allowed_types, true)) {
    $type_filter = 'all';
}

$where = ["1=1"];
if ($search !== '') {
    $search_sql = $conn->real_escape_string($search);
    $search_int = (int)$search;
    $where[] = "(e.id = '$search_int' OR e.original_sale_id = '$search_int' OR e.note LIKE '%$search_sql%' OR u.username LIKE '%$search_sql%')";
}
if ($type_filter !== 'all') {
    $type_sql = $conn->real_escape_string($type_filter);
    $where[] = "e.adjustment_type = '$type_sql'";
}

$exchanges = [];
$exchange_res = $conn->query("
    SELECT e.*, u.username
    FROM exchange_transactions e
    LEFT JOIN users u ON u.id = e.user_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY e.created_at DESC
");
if ($exchange_res) {
    while ($row = $exchange_res->fetch_assoc()) {
        $row['items'] = [];
        $exchange_id = (int)$row['id'];
        $items_res = $conn->query("
            SELECT ei.*, op.name AS old_product_name, np.name AS new_product_name,
                   old_img.color_name AS old_color_name,
                   new_img.color_name AS new_color_name
            FROM exchange_items ei
            LEFT JOIN products op ON op.id = ei.old_product_id
            LEFT JOIN products np ON np.id = ei.new_product_id
            LEFT JOIN product_images old_img ON old_img.id = ei.old_product_image_id
            LEFT JOIN product_images new_img ON new_img.id = ei.new_product_image_id
            WHERE ei.exchange_id = '$exchange_id'
            ORDER BY ei.id ASC
        ");
        if ($items_res) {
            while ($item = $items_res->fetch_assoc()) {
                $row['items'][] = $item;
            }
        }
        $exchanges[] = $row;
    }
}

function admin_exchange_item_label($productName, $colorName) {
    $label = (string)$productName;
    $color = trim((string)$colorName);
    if ($color !== '') {
        $label .= ' - Colour: ' . $color;
    }
    return $label;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exchange History | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .page-shell { max-width: 1180px; margin: 0 auto; }
        .summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-bottom: 18px; }
        .summary-card { background: white; border-radius: 18px; padding: 18px; box-shadow: 0 10px 24px rgba(15, 23, 42, 0.07); }
        .summary-card span { display: block; font-size: 12px; text-transform: uppercase; letter-spacing: 0.06em; color: #667085; }
        .summary-card strong { display: block; margin-top: 8px; font-size: 28px; color: #1e3c72; }
        .filter-bar { background: white; border-radius: 18px; padding: 14px; box-shadow: 0 10px 24px rgba(15, 23, 42, 0.07); display: flex; gap: 10px; align-items: center; margin-bottom: 18px; }
        .filter-bar input, .filter-bar select { flex: 1; min-width: 0; border: 1px solid #d0d5dd; border-radius: 12px; padding: 12px 14px; font-size: 14px; }
        .filter-btn { border: none; border-radius: 12px; background: #1e3c72; color: white; padding: 12px 16px; font-weight: 600; cursor: pointer; }
        .exchange-grid { display: grid; gap: 16px; }
        .exchange-card { background: white; border-radius: 20px; padding: 20px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08); }
        .exchange-head { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; margin-bottom: 14px; }
        .exchange-title { margin: 0; font-size: 20px; color: #1e3c72; }
        .exchange-subtitle { margin: 6px 0 0; color: #667085; font-size: 14px; }
        .exchange-total { text-align: right; }
        .exchange-total span { display: block; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: #667085; }
        .exchange-total strong { display: block; margin-top: 6px; font-size: 30px; color: #1e3c72; }
        .exchange-meta { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 16px; }
        .meta-box { background: #f8fafc; border: 1px solid #edf2f7; border-radius: 14px; padding: 12px; }
        .meta-box span { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #667085; }
        .meta-box strong { display: block; margin-top: 4px; font-size: 14px; color: #24303d; word-break: break-word; }
        .exchange-items { display: grid; gap: 10px; }
        .exchange-item { background: #f8fafc; border: 1px solid #edf2f7; border-radius: 14px; padding: 12px; }
        .exchange-item strong { color: #24303d; }
        .receipt-link { display: inline-flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; color: #1e3c72; font-weight: 700; margin-top: 14px; }
        .empty-state { background: white; border-radius: 20px; padding: 40px 20px; text-align: center; color: #98a2b3; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08); }
        @media (max-width: 980px) {
            .summary-grid, .exchange-meta { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .filter-bar { flex-direction: column; align-items: stretch; }
        }
        @media (max-width: 640px) {
            .summary-grid, .exchange-meta { grid-template-columns: 1fr; }
            .exchange-head { flex-direction: column; }
            .exchange-total { text-align: left; }
        }
    </style>
</head>
<body class="admin-mobile-ui">
<?php $is_dashboard = true; include '../includes/topbar.php'; ?>

<div class="dashboard-container">
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>Menu</h3>
            <button class="toggle-btn" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
        </div>
        <a href="dashboard.php"><i class="fas fa-home"></i> <span>Dashboard</span></a>
        <a href="add_product.php"><i class="fas fa-plus-circle"></i> <span>Add Product</span></a>
        <a href="view_inventory.php"><i class="fas fa-box"></i> <span>View Inventory</span></a>
        <a href="manage_users.php"><i class="fas fa-users"></i> <span>Manage Users</span></a>
        <a href="holding_orders.php"><i class="fas fa-boxes-stacked"></i> <span>Holding Orders</span></a>
        <a href="exchange_history.php" class="active"><i class="fas fa-right-left"></i> <span>Exchanges</span></a>
        <a href="payout_requests.php"><i class="fas fa-wallet"></i> <span>Payout Requests</span><?php if ($admin_payout_unread > 0): ?><span class="notif-dot"></span><?php endif; ?></a>
        <a href="transaction_history.php"><i class="fas fa-history"></i> <span>Transactions</span></a>
    </div>

    <div class="main-content">
        <div class="header-title">Exchange History</div>
        <div class="page-shell">
            <div class="summary-grid">
                <div class="summary-card"><span>Total Exchanges</span><strong><?php echo number_format(count($exchanges)); ?></strong></div>
                <div class="summary-card"><span>Customer Adds</span><strong><?php echo number_format($summary_counts['customer_adds']); ?></strong></div>
                <div class="summary-card"><span>Balanced</span><strong><?php echo number_format($summary_counts['balanced']); ?></strong></div>
                <div class="summary-card"><span>Customer Credit</span><strong><?php echo number_format($summary_counts['customer_credit']); ?></strong></div>
            </div>

            <form method="GET" class="filter-bar">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search by exchange ID, receipt ID, note, or user">
                <select name="type">
                    <?php foreach ($allowed_types as $filter): ?>
                        <option value="<?php echo htmlspecialchars($filter, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $type_filter === $filter ? 'selected' : ''; ?>><?php echo ucwords(str_replace('_', ' ', $filter)); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="filter-btn">Filter</button>
            </form>

            <div class="exchange-grid">
                <?php if (count($exchanges) > 0): ?>
                    <?php foreach ($exchanges as $exchange): ?>
                        <article class="exchange-card">
                            <div class="exchange-head">
                                <div>
                                    <h3 class="exchange-title">Exchange #<?php echo str_pad((int)$exchange['id'], 6, '0', STR_PAD_LEFT); ?></h3>
                                    <p class="exchange-subtitle">Receipt #<?php echo str_pad((int)$exchange['original_sale_id'], 6, '0', STR_PAD_LEFT); ?> · handled by @<?php echo htmlspecialchars((string)($exchange['username'] ?? 'staff'), ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                                <div class="exchange-total">
                                    <span>Difference</span>
                                    <strong>₦<?php echo number_format(abs((float)$exchange['amount_difference']), 2); ?></strong>
                                </div>
                            </div>

                            <div class="exchange-meta">
                                <div class="meta-box"><span>Type</span><strong><?php echo htmlspecialchars(get_exchange_adjustment_label((string)$exchange['adjustment_type']), ENT_QUOTES, 'UTF-8'); ?></strong></div>
                                <div class="meta-box"><span>Returned Value</span><strong>₦<?php echo number_format((float)$exchange['total_old_amount'], 2); ?></strong></div>
                                <div class="meta-box"><span>Replacement Value</span><strong>₦<?php echo number_format((float)$exchange['total_new_amount'], 2); ?></strong></div>
                                <div class="meta-box"><span>Date</span><strong><?php echo date('M d, Y h:i A', strtotime((string)$exchange['created_at'])); ?></strong></div>
                            </div>

                            <div class="exchange-items">
                                <?php foreach ($exchange['items'] as $item): ?>
                                    <?php $has_returned_item = (float)($item['old_quantity'] ?? 0) > 0; ?>
                                    <div class="exchange-item">
                                        <?php if ($has_returned_item): ?>
                                            <div><strong>Returned:</strong> <?php echo htmlspecialchars(admin_exchange_item_label($item['old_product_name'] ?? 'Original Item', $item['old_color_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> (<?php echo (float)$item['old_quantity']; ?> x ₦<?php echo number_format((float)$item['old_price'], 2); ?>)</div>
                                            <div style="margin-top:6px;"><strong>Replacement:</strong> <?php echo htmlspecialchars(admin_exchange_item_label($item['new_product_name'] ?? 'Replacement Item', $item['new_color_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> (<?php echo (float)$item['new_quantity']; ?> x ₦<?php echo number_format((float)$item['new_price'], 2); ?>)</div>
                                        <?php else: ?>
                                            <div><strong>Additional Replacement:</strong> <?php echo htmlspecialchars(admin_exchange_item_label($item['new_product_name'] ?? 'Replacement Item', $item['new_color_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> (<?php echo (float)$item['new_quantity']; ?> x ₦<?php echo number_format((float)$item['new_price'], 2); ?>)</div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <a href="../sales/exchange_receipt.php?id=<?php echo (int)$exchange['id']; ?>" class="receipt-link" target="_blank" rel="noopener">
                                <i class="fas fa-print"></i> Open Exchange Receipt
                            </a>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">No exchange records found for this filter.</div>
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
</script>
</body>
</html>
