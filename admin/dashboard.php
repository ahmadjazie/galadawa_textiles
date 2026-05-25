<?php
session_start();
include '../includes/db_connect.php';
include '../includes/payouts.php';
include '../includes/order_workflow.php';

// Access control: allow only authenticated administrators.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

ensure_payout_table($conn);
ensure_order_workflow_schema($conn);
release_expired_holds($conn);
$admin_payout_unread = get_admin_unread_count($conn);

// Dashboard metrics: totals and low-stock product names.
// Total products.
$prod_result = $conn->query("SELECT COUNT(*) as total FROM products");
$prod_count = $prod_result ? (int)$prod_result->fetch_assoc()['total'] : 0;

// Total system users.
$user_result = $conn->query("SELECT COUNT(*) as total FROM users");
$user_count = $user_result ? (int)$user_result->fetch_assoc()['total'] : 0;

$hold_result = $conn->query("SELECT COUNT(*) as total FROM held_orders WHERE status = 'active'");
$hold_count = $hold_result ? (int)$hold_result->fetch_assoc()['total'] : 0;

$exchange_result = $conn->query("SELECT COUNT(*) as total FROM exchange_transactions");
$exchange_count = $exchange_result ? (int)$exchange_result->fetch_assoc()['total'] : 0;

// Low-stock count and product names.
$low_stock_sql = "SELECT name FROM products WHERE quantity < min_stock";
$low_stock_result = $conn->query($low_stock_sql);

$low_stock_count = $low_stock_result ? $low_stock_result->num_rows : 0;
$low_stock_names = [];
if ($low_stock_result) {
    while ($row = $low_stock_result->fetch_assoc()) {
        $low_stock_names[] = $row['name']; // Build an ordered list for the low-stock ticker.
    }
}

// Restock assistant: combine stock thresholds with recent sales velocity.
$restock_sql = "SELECT p.id, p.name, p.quantity, p.min_stock,
                       COALESCE(SUM(CASE WHEN s.created_at >= DATE_SUB(NOW(), INTERVAL 10 DAY) THEN si.quantity ELSE 0 END), 0) AS sold_10
                FROM products p
                LEFT JOIN sale_items si ON si.product_id = p.id
                LEFT JOIN sales s ON s.id = si.sale_id
                WHERE p.quantity < p.min_stock
                GROUP BY p.id, p.name, p.quantity, p.min_stock
                ORDER BY (p.min_stock - p.quantity) DESC, p.name ASC";
$restock_result = $conn->query($restock_sql);
$restock_items = [];
if ($restock_result) {
    while ($row = $restock_result->fetch_assoc()) {
        $qty = (float)$row['quantity'];
        $min_stock = (float)$row['min_stock'];
        $sold_10 = (float)$row['sold_10'];
        $avg_daily = $sold_10 / 10;
        $avg_weekly = $avg_daily * 7;
        $min_needed = max(0, $min_stock - $qty);
        $trend_needed = max(0, ($avg_weekly * 2) - $qty);
        $suggested = (int)ceil(max($min_needed, $trend_needed));
        $restock_items[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'quantity' => $qty,
            'min_stock' => $min_stock,
            'avg_weekly' => $avg_weekly,
            'suggested' => $suggested,
        ];
    }
}

if (isset($_GET['export_restock'])) {
    $export_type = $_GET['export_restock'];
    if ($export_type === 'csv') {
        // Export restock recommendations as CSV.
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="restock-assistant.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Product ID', 'Product Name', 'Current Stock', 'Min Stock', 'Avg Weekly Sales', 'Suggested Reorder']);
        foreach ($restock_items as $item) {
            fputcsv($out, [
                $item['id'],
                $item['name'],
                $item['quantity'],
                $item['min_stock'],
                number_format($item['avg_weekly'], 2, '.', ''),
                $item['suggested'],
            ]);
        }
        fclose($out);
        exit();
    }
    if ($export_type === 'print') {
        // Render a print-friendly restock report.
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Restock Assistant | Galadawa Textiles</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; color: #222; }
                h1 { font-size: 20px; margin-bottom: 15px; }
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background: #f3f4f6; }
            </style>
        </head>
        <body>
            <h1>Restock Assistant</h1>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Current</th>
                        <th>Min</th>
                        <th>Avg Weekly</th>
                        <th>Suggested</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($restock_items) > 0): ?>
                        <?php foreach ($restock_items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td><?php echo $item['min_stock']; ?></td>
                                <td><?php echo number_format($item['avg_weekly'], 2); ?></td>
                                <td><?php echo $item['suggested']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5">No low stock items.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <script>
                window.addEventListener('load', () => window.print());
            </script>
        </body>
        </html>
        <?php
        exit();
    }
}

// Recent transactions for quick dashboard visibility.
$trans_sql = "SELECT sales.id, sales.total_amount, sales.created_at, users.username 
              FROM sales 
              JOIN users ON sales.user_id = users.id 
              ORDER BY sales.created_at DESC 
              LIMIT 5";
$trans_result = $conn->query($trans_sql);
$recent_transactions = [];
if ($trans_result) {
    while ($row = $trans_result->fetch_assoc()) {
        $recent_transactions[] = $row;
    }
}

$recent_exchange_sql = "SELECT e.id, e.original_sale_id, e.amount_difference, e.adjustment_type, e.created_at, u.username
                        FROM exchange_transactions e
                        LEFT JOIN users u ON e.user_id = u.id
                        ORDER BY e.created_at DESC
                        LIMIT 5";
$recent_exchange_result = $conn->query($recent_exchange_sql);
$recent_exchanges = [];
if ($recent_exchange_result) {
    while ($row = $recent_exchange_result->fetch_assoc()) {
        $recent_exchanges[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Galadawa Textiles</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .dashboard-page {
            --fab-size: 58px;
            --mobile-gap: 14px;
        }

        .dashboard-page .dashboard-container {
            position: relative;
        }

        .dashboard-page .sidebar-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.42);
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.22s ease, visibility 0.22s ease;
            z-index: 1190;
        }

        .dashboard-page.sidebar-open .sidebar-backdrop {
            opacity: 1;
            visibility: visible;
        }

        .dashboard-page .mobile-fab {
            position: fixed;
            right: 18px;
            bottom: 20px;
            width: var(--fab-size);
            height: var(--fab-size);
            border: none;
            border-radius: 50%;
            background: linear-gradient(135deg, #1e3c72 0%, #294f92 100%);
            color: #fff;
            box-shadow: 0 16px 30px rgba(30, 60, 114, 0.3);
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            cursor: pointer;
            z-index: 1300;
            transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
        }

        .dashboard-page .mobile-fab:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 36px rgba(30, 60, 114, 0.36);
        }

        .dashboard-page .sidebar-logout {
            margin-top: auto;
            background: rgba(255,255,255,0.14);
            border: 1px solid rgba(255,255,255,0.16);
        }

        .dashboard-page .sidebar-logout:hover {
            background: rgba(255,255,255,0.22);
        }

        .dashboard-page .table-head-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .dashboard-page .table-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .dashboard-page .table-scroll {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .dashboard-page .table-scroll .styled-table {
            min-width: 640px;
        }

        .dashboard-page .mobile-panel-list {
            display: none;
        }

        .dashboard-page .mobile-data-card {
            border: 1px solid #e7edf5;
            border-radius: 18px;
            padding: 14px;
            background: #fff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
        }

        .dashboard-page .mobile-data-card + .mobile-data-card {
            margin-top: 12px;
        }

        .dashboard-page .mobile-data-card.clickable {
            cursor: pointer;
        }

        .dashboard-page .mobile-data-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        .dashboard-page .mobile-data-title {
            margin: 0;
            font-size: 16px;
            color: #24303d;
            font-weight: 700;
        }

        .dashboard-page .mobile-data-subtitle {
            margin: 4px 0 0;
            font-size: 13px;
            color: #667085;
        }

        .dashboard-page .mobile-data-callout {
            min-width: 86px;
            padding: 10px 12px;
            border-radius: 14px;
            background: #eef4ff;
            text-align: right;
        }

        .dashboard-page .mobile-data-callout span {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #667085;
        }

        .dashboard-page .mobile-data-callout strong {
            display: block;
            margin-top: 4px;
            font-size: 19px;
            color: #1e3c72;
            line-height: 1.1;
        }

        .dashboard-page .mobile-data-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 12px;
        }

        .dashboard-page .mobile-data-item {
            background: #f8fafc;
            border: 1px solid #edf2f7;
            border-radius: 12px;
            padding: 10px 12px;
        }

        .dashboard-page .mobile-data-item span {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #667085;
        }

        .dashboard-page .mobile-data-item strong {
            display: block;
            margin-top: 4px;
            font-size: 14px;
            color: #24303d;
        }

        .dashboard-page .mobile-empty-card {
            text-align: center;
            color: #98a2b3;
        }

        @media (max-width: 900px) {
            .dashboard-page .top-header .btn-logout {
                display: none;
            }

            .dashboard-page .dashboard-container {
                min-height: auto;
                height: auto;
                overflow: visible;
            }

            .dashboard-page .sidebar {
                position: fixed;
                top: 70px;
                left: 12px;
                bottom: 12px;
                width: min(292px, calc(100vw - 24px));
                height: auto;
                border-radius: 22px;
                transform: translateX(-115%);
                opacity: 0;
                pointer-events: none;
                z-index: 1200;
                transition: transform 0.24s ease, opacity 0.24s ease;
                box-shadow: 0 20px 44px rgba(15, 23, 42, 0.24);
                padding: 18px 14px;
            }

            .dashboard-page .sidebar.mobile-open {
                transform: translateX(0);
                opacity: 1;
                pointer-events: auto;
            }

            .dashboard-page .sidebar.mobile-open .sidebar-header {
                margin-bottom: 14px;
                padding-bottom: 12px;
            }

            .dashboard-page .sidebar.mobile-open a {
                display: flex;
                margin-bottom: 8px;
                padding: 12px 14px;
                border-radius: 14px;
            }

            .dashboard-page .sidebar-logout {
                margin-top: 14px;
            }

            .dashboard-page .main-content {
                padding: 14px 12px 88px;
                height: auto;
                overflow: visible;
            }

            .dashboard-page .header-title {
                font-size: 20px;
                line-height: 1.2;
                margin-bottom: 16px;
            }

            .dashboard-page .stats-grid {
                grid-template-columns: 1fr;
                gap: var(--mobile-gap);
                margin-bottom: 18px;
            }

            .dashboard-page .stat-card {
                padding: 18px;
                align-items: flex-start;
                gap: 12px;
            }

            .dashboard-page .stat-info p {
                font-size: 24px;
            }

            .dashboard-page .table-container {
                padding: 18px;
                margin-bottom: 16px;
            }

            .dashboard-page .table-head-row {
                align-items: stretch;
                flex-direction: column;
            }

            .dashboard-page .table-actions a {
                flex: 1 1 auto;
                text-align: center;
            }

            .dashboard-page .table-scroll {
                display: none;
            }

            .dashboard-page .mobile-panel-list {
                display: block;
                margin-top: 14px;
            }

            .dashboard-page .mobile-fab {
                display: inline-flex;
                right: 16px;
                bottom: 16px;
                width: 54px;
                height: 54px;
            }
        }

        @media (max-width: 560px) {
            .dashboard-page .header-title {
                font-size: 20px;
            }

            .dashboard-page .stat-card {
                padding: 16px;
            }

            .dashboard-page .stat-info h4 {
                font-size: 13px;
            }

            .dashboard-page .stat-info p {
                font-size: 22px;
            }

            .dashboard-page .mobile-data-head {
                flex-direction: column;
            }

            .dashboard-page .mobile-data-grid {
                grid-template-columns: 1fr;
            }

            .dashboard-page .mobile-data-callout {
                width: 100%;
                text-align: left;
            }
        }
    </style>
</head>
<body class="dashboard-page">
    <!-- Optional page loader container (currently disabled).
    <div id="loader-wrapper">
        <img src="../img/logo.png" alt="Loading..." class="loader-logo">
    </div> -->
    <?php $is_dashboard = true; include '../includes/topbar.php'; ?>



    <div class="dashboard-container">
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h3>Menu</h3>
                <button class="toggle-btn" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            <a href="dashboard.php" class="active"><i class="fas fa-home"></i> <span>Dashboard</span></a>
            <a href="add_product.php"><i class="fas fa-plus-circle"></i> <span>Add Product</span></a>
            <a href="view_inventory.php"><i class="fas fa-box"></i> <span>View Inventory</span></a>
            <a href="manage_users.php"><i class="fas fa-users"></i> <span>Manage Users</span></a>
            <a href="holding_orders.php"><i class="fas fa-boxes-stacked"></i> <span>Holding Orders</span></a>
            <a href="exchange_history.php"><i class="fas fa-right-left"></i> <span>Exchanges</span></a>
            <a href="payout_requests.php"><i class="fas fa-wallet"></i> <span>Payout Requests</span><?php if ($admin_payout_unread > 0): ?><span class="notif-dot"></span><?php endif; ?></a>
            <a href="transaction_history.php"><i class="fas fa-history"></i> <span>Transactions</span></a>
            <a href="../logout.php" class="sidebar-logout"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </div>
        <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="closeMobileSidebar()"></div>

        <div class="main-content">
            <div class="header-title">Admin Dashboard Overview</div>

            <div class="stats-grid">
                
                <a href="view_inventory.php" class="card-link">
                    <div class="stat-card">
                        <div class="stat-info">
                            <h4>Total Products</h4>
                            <p><?php echo $prod_count; ?></p>
                        </div>
                        <i class="fas fa-boxes fa-2x" style="color: #ddd;"></i>
                    </div>
                </a>

                <a href="view_inventory.php?filter=low" class="card-link">
                    <div class="stat-card" style="border-left-color: #e74c3c;">
                        <div class="stat-info">
                            <h4>Low Stock Alerts</h4>
                            <p style="color: #e74c3c;"><?php echo $low_stock_count; ?></p>
                            
                            <div id="lowStockSlideshow" class="low-stock-name">
                                <?php echo ($low_stock_count > 0) ? "Check Inventory" : "All Good"; ?>
                            </div>
                        </div>
                        <i class="fas fa-exclamation-triangle fa-2x" style="color: #fadbd8;"></i>
                    </div>
                </a>

                <a href="manage_users.php" class="card-link">
                    <div class="stat-card" style="border-left-color: #28a745;">
                        <div class="stat-info">
                            <h4>System Users</h4>
                            <p><?php echo $user_count; ?></p>
                        </div>
                        <i class="fas fa-user-friends fa-2x" style="color: #d4edda;"></i>
                    </div>
                </a>

                <a href="holding_orders.php" class="card-link">
                    <div class="stat-card" style="border-left-color: #f39c12;">
                        <div class="stat-info">
                            <h4>Active Holds</h4>
                            <p style="color: #f39c12;"><?php echo $hold_count; ?></p>
                        </div>
                        <i class="fas fa-boxes-stacked fa-2x" style="color: #fdebd0;"></i>
                    </div>
                </a>

                <a href="exchange_history.php" class="card-link">
                    <div class="stat-card" style="border-left-color: #6c5ce7;">
                        <div class="stat-info">
                            <h4>Total Exchanges</h4>
                            <p style="color: #6c5ce7;"><?php echo $exchange_count; ?></p>
                        </div>
                        <i class="fas fa-right-left fa-2x" style="color: #ece8ff;"></i>
                    </div>
                </a>

            </div>

            <div class="table-container">
                <h3>Recent Transactions</h3>
                <div class="table-scroll">
                    <table class="styled-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Attendant</th>
                                <th>Date & Time</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody id="recentTransactionsTableBody">
                            <?php if (count($recent_transactions) > 0): ?>
                                <?php foreach ($recent_transactions as $row): ?>
                                    <tr onclick="window.location.href='transaction_history.php?id=<?php echo $row['id']; ?>'">
                                        <td>#<?php echo $row['id']; ?></td>
                                        <td><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars(ucfirst($row['username']), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo date("M d, Y h:i A", strtotime($row['created_at'])); ?></td>
                                        <td class="amount-badge">₦<?php echo number_format($row['total_amount'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4">No transactions found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mobile-panel-list" id="recentTransactionsMobileList">
                    <?php if (count($recent_transactions) > 0): ?>
                        <?php foreach ($recent_transactions as $row): ?>
                            <article class="mobile-data-card clickable" onclick="window.location.href='transaction_history.php?id=<?php echo $row['id']; ?>'">
                                <div class="mobile-data-head">
                                    <div>
                                        <h4 class="mobile-data-title">Sale #<?php echo $row['id']; ?></h4>
                                        <p class="mobile-data-subtitle"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars(ucfirst($row['username']), ENT_QUOTES, 'UTF-8'); ?></p>
                                    </div>
                                    <div class="mobile-data-callout">
                                        <span>Amount</span>
                                        <strong>₦<?php echo number_format($row['total_amount'], 0); ?></strong>
                                    </div>
                                </div>
                                <div class="mobile-data-grid">
                                    <div class="mobile-data-item">
                                        <span>Date</span>
                                        <strong><?php echo date("M d, Y", strtotime($row['created_at'])); ?></strong>
                                    </div>
                                    <div class="mobile-data-item">
                                        <span>Time</span>
                                        <strong><?php echo date("h:i A", strtotime($row['created_at'])); ?></strong>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <article class="mobile-data-card mobile-empty-card">No transactions found.</article>
                    <?php endif; ?>
                </div>
            </div>

            <div class="table-container">
                <h3>Recent Exchanges</h3>
                <div class="table-scroll">
                    <table class="styled-table">
                        <thead>
                            <tr>
                                <th>Exchange #</th>
                                <th>Receipt #</th>
                                <th>Attendant</th>
                                <th>Type</th>
                                <th>Difference</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($recent_exchanges) > 0): ?>
                                <?php foreach ($recent_exchanges as $row): ?>
                                    <tr onclick="window.open('exchange_history.php', '_self')">
                                        <td>#<?php echo str_pad((int)$row['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                        <td>#<?php echo str_pad((int)$row['original_sale_id'], 6, '0', STR_PAD_LEFT); ?></td>
                                        <td><?php echo htmlspecialchars(ucfirst((string)($row['username'] ?? 'staff')), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars(get_exchange_adjustment_label((string)$row['adjustment_type']), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="amount-badge">₦<?php echo number_format(abs((float)$row['amount_difference']), 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5">No exchanges recorded yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mobile-panel-list">
                    <?php if (count($recent_exchanges) > 0): ?>
                        <?php foreach ($recent_exchanges as $row): ?>
                            <article class="mobile-data-card clickable" onclick="window.location.href='exchange_history.php'">
                                <div class="mobile-data-head">
                                    <div>
                                        <h4 class="mobile-data-title">Exchange #<?php echo str_pad((int)$row['id'], 6, '0', STR_PAD_LEFT); ?></h4>
                                        <p class="mobile-data-subtitle">Receipt #<?php echo str_pad((int)$row['original_sale_id'], 6, '0', STR_PAD_LEFT); ?> · <?php echo htmlspecialchars(ucfirst((string)($row['username'] ?? 'staff')), ENT_QUOTES, 'UTF-8'); ?></p>
                                    </div>
                                    <div class="mobile-data-callout">
                                        <span>Difference</span>
                                        <strong>₦<?php echo number_format(abs((float)$row['amount_difference']), 0); ?></strong>
                                    </div>
                                </div>
                                <div class="mobile-data-grid">
                                    <div class="mobile-data-item">
                                        <span>Type</span>
                                        <strong><?php echo htmlspecialchars(get_exchange_adjustment_label((string)$row['adjustment_type']), ENT_QUOTES, 'UTF-8'); ?></strong>
                                    </div>
                                    <div class="mobile-data-item">
                                        <span>Date</span>
                                        <strong><?php echo date("M d, Y", strtotime((string)$row['created_at'])); ?></strong>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <article class="mobile-data-card mobile-empty-card">No exchanges recorded yet.</article>
                    <?php endif; ?>
                </div>
            </div>

            <div class="table-container">
                <div class="table-head-row">
                    <h3 style="margin: 0;">Restock Assistant</h3>
                    <div class="table-actions">
                        <a href="dashboard.php?export_restock=csv" class="btn" style="text-decoration: none; padding: 8px 12px; border-radius: 6px; background: #1e3c72; color: #fff; font-size: 12px;">
                            <i class="fas fa-file-csv"></i> Export CSV
                        </a>
                        <a href="dashboard.php?export_restock=print" class="btn" style="text-decoration: none; padding: 8px 12px; border-radius: 6px; background: #6c757d; color: #fff; font-size: 12px;">
                            <i class="fas fa-print"></i> Print/PDF
                        </a>
                    </div>
                </div>
                <div class="table-scroll">
                    <table class="styled-table" style="margin-top: 10px;">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Current</th>
                                <th>Min</th>
                                <th>Avg Weekly</th>
                                <th>Suggested Reorder</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($restock_items) > 0): ?>
                                <?php foreach ($restock_items as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td><?php echo $item['min_stock']; ?></td>
                                        <td><?php echo number_format($item['avg_weekly'], 2); ?></td>
                                        <td class="amount-badge"><?php echo number_format($item['suggested'], 0); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5">No low stock items.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mobile-panel-list">
                    <?php if (count($restock_items) > 0): ?>
                        <?php foreach ($restock_items as $item): ?>
                            <article class="mobile-data-card">
                                <div class="mobile-data-head">
                                    <div>
                                        <h4 class="mobile-data-title"><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                        <p class="mobile-data-subtitle">Low stock recommendation</p>
                                    </div>
                                    <div class="mobile-data-callout">
                                        <span>Reorder</span>
                                        <strong><?php echo number_format($item['suggested'], 0); ?></strong>
                                    </div>
                                </div>
                                <div class="mobile-data-grid">
                                    <div class="mobile-data-item">
                                        <span>Current</span>
                                        <strong><?php echo number_format($item['quantity'], 0); ?></strong>
                                    </div>
                                    <div class="mobile-data-item">
                                        <span>Min Stock</span>
                                        <strong><?php echo number_format($item['min_stock'], 0); ?></strong>
                                    </div>
                                    <div class="mobile-data-item">
                                        <span>Avg Weekly</span>
                                        <strong><?php echo number_format($item['avg_weekly'], 2); ?></strong>
                                    </div>
                                    <div class="mobile-data-item">
                                        <span>Suggested</span>
                                        <strong><?php echo number_format($item['suggested'], 0); ?></strong>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <article class="mobile-data-card mobile-empty-card">No low stock items.</article>
                    <?php endif; ?>
                </div>
                <div style="margin-top: 8px; color: #777; font-size: 12px;">
                    Suggested reorder is based on the last 10 days of sales and minimum stock.
                </div>
            </div>

        </div>
    </div>
    <button type="button" class="mobile-fab" id="mobileFab" onclick="toggleSidebar()" aria-label="Open menu">
        <i class="fas fa-bars"></i>
    </button>

    <script>
        function isSmallScreenDashboard() {
            return window.matchMedia('(max-width: 900px)').matches;
        }

        function setFabIcon(isOpen) {
            const fabIcon = document.querySelector('#mobileFab i');
            if (!fabIcon) return;
            fabIcon.className = isOpen ? 'fas fa-times' : 'fas fa-bars';
        }

        function closeMobileSidebar() {
            const sidebar = document.getElementById('sidebar');
            document.body.classList.remove('sidebar-open');
            sidebar.classList.remove('mobile-open');
            setFabIcon(false);
        }

        // UI helper: toggle sidebar collapsed state.
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            if (isSmallScreenDashboard()) {
                const willOpen = !sidebar.classList.contains('mobile-open');
                sidebar.classList.toggle('mobile-open', willOpen);
                document.body.classList.toggle('sidebar-open', willOpen);
                setFabIcon(willOpen);
                return;
            }
            sidebar.classList.toggle('collapsed');
        }

        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            if (isSmallScreenDashboard()) {
                sidebar.classList.remove('collapsed');
                closeMobileSidebar();
            }

            sidebar.querySelectorAll('a').forEach((link) => {
                link.addEventListener('click', function() {
                    if (isSmallScreenDashboard()) {
                        closeMobileSidebar();
                    }
                });
            });
        });

        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            if (isSmallScreenDashboard()) {
                sidebar.classList.remove('collapsed');
                closeMobileSidebar();
            } else {
                document.body.classList.remove('sidebar-open');
                sidebar.classList.remove('mobile-open');
                setFabIcon(false);
            }
        });

        // Loader state reset when returning from browser history navigation.
        // 'pageshow' runs each time the page becomes visible, including back/forward.
        window.addEventListener('pageshow', function(event) {
            const loader = document.getElementById('loader-wrapper');
            if (loader) {
                // Ensure loader is hidden after navigation restore.
                loader.classList.add('loader-hidden');
            }
        });

        // Show loader before standard page-to-page navigation.
        document.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function(e) {
                const target = this.getAttribute('href');
                
                // Ignore placeholders and script-based links.
                if(target && target !== '#' && !target.startsWith('javascript')) {
                    const loader = document.getElementById('loader-wrapper');
                    if (loader) {
                        loader.classList.remove('loader-hidden');
                    }
                    
                    // Fallback: auto-hide loader if navigation does not complete promptly.
                    setTimeout(() => {
                        if (loader) {
                            loader.classList.add('loader-hidden');
                        }
                    }, 9000);
                }
            });
        });

        // Low-stock ticker: rotate product names in the dashboard card.
        // Guard against missing DOM nodes to avoid runtime errors.
        const slideshowElement = document.getElementById('lowStockSlideshow');
        if (slideshowElement) {
            // Inject server-side values safely into JavaScript.
            const lowStockNames = <?php echo isset($low_stock_names) ? json_encode($low_stock_names, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) : '[]'; ?>;
            let currentIndex = 0;

            if (lowStockNames.length > 0) {
                // Function: render current item and advance index circularly.
                function showNextName() {
                    slideshowElement.innerText = "⚠ " + lowStockNames[currentIndex];
                    currentIndex = (currentIndex + 1) % lowStockNames.length;
                }
                // Initial render.
                showNextName();
                // Scheduled rotation interval.
                setInterval(showNextName, 4000);
            }
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function renderRecentTransactions(items) {
            const tableBody = document.getElementById('recentTransactionsTableBody');
            const mobileList = document.getElementById('recentTransactionsMobileList');
            if (!tableBody || !mobileList || !Array.isArray(items)) return;

            if (items.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="4">No transactions found.</td></tr>';
                mobileList.innerHTML = '<article class="mobile-data-card mobile-empty-card">No transactions found.</article>';
                return;
            }

            tableBody.innerHTML = items.map((item) => `
                <tr onclick="window.location.href='transaction_history.php?id=${item.id}'">
                    <td>#${item.id}</td>
                    <td><i class="fas fa-user-circle"></i> ${escapeHtml(item.username)}</td>
                    <td>${escapeHtml(item.datetime_display)}</td>
                    <td class="amount-badge">₦${escapeHtml(item.amount_display)}</td>
                </tr>
            `).join('');

            mobileList.innerHTML = items.map((item) => `
                <article class="mobile-data-card clickable" onclick="window.location.href='transaction_history.php?id=${item.id}'">
                    <div class="mobile-data-head">
                        <div>
                            <h4 class="mobile-data-title">Sale #${item.id}</h4>
                            <p class="mobile-data-subtitle"><i class="fas fa-user-circle"></i> ${escapeHtml(item.username)}</p>
                        </div>
                        <div class="mobile-data-callout">
                            <span>Amount</span>
                            <strong>₦${escapeHtml(item.amount_whole_display)}</strong>
                        </div>
                    </div>
                    <div class="mobile-data-grid">
                        <div class="mobile-data-item">
                            <span>Date</span>
                            <strong>${escapeHtml(item.date_display)}</strong>
                        </div>
                        <div class="mobile-data-item">
                            <span>Time</span>
                            <strong>${escapeHtml(item.time_display)}</strong>
                        </div>
                    </div>
                </article>
            `).join('');
        }

        document.addEventListener('galadawa:live-update', function(event) {
            const payload = event.detail || {};
            if (!payload.admin || !Array.isArray(payload.admin.latest_sales)) return;
            renderRecentTransactions(payload.admin.latest_sales);
        });
    </script>
</body>
</html>
