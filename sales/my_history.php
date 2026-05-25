<?php
session_start();
include '../includes/db_connect.php';
include '../includes/payouts.php';
include '../includes/order_workflow.php';

// 1. SECURITY CHECK
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
ensure_payout_table($conn);
ensure_order_workflow_schema($conn);
$payout_unread = get_user_unread_count($conn, $user_id);
$user_info_res = $conn->query("SELECT fullname, username FROM users WHERE id = '$user_id' LIMIT 1");
$user_info = $user_info_res ? $user_info_res->fetch_assoc() : null;
$report_attendant = trim((string)($user_info['fullname'] ?? '')) !== ''
    ? $user_info['fullname']
    : ($_SESSION['username'] ?? 'Sales Attendant');
$report_attendant_username = (string)($user_info['username'] ?? ($_SESSION['username'] ?? ''));

function format_signed_naira($amount, $decimals = 2) {
    $amount = (float)$amount;
    $sign = $amount > 0 ? '+' : ($amount < 0 ? '-' : '');
    return $sign . '₦' . number_format(abs($amount), $decimals);
}

// 2. AJAX FOR RECEIPT POPUP
if (isset($_GET['get_receipt_items'])) {
    $sale_id = (int)$_GET['get_receipt_items'];
    $sql_items = "SELECT si.*, p.name FROM sale_items si JOIN products p ON si.product_id = p.id WHERE si.sale_id = '$sale_id'";
    $res_items = $conn->query($sql_items);
    $items = [];
    while ($row = $res_items->fetch_assoc()) { $items[] = $row; }

    $exchange_rows = [];
    $exchange_sql = "SELECT e.id, e.adjustment_type, e.amount_difference, e.note, e.created_at
                     FROM exchange_transactions e
                     LEFT JOIN sales s ON s.id = e.original_sale_id
                     WHERE e.original_sale_id = '$sale_id'
                       AND COALESCE(e.commission_user_id, s.user_id, e.user_id) = '$user_id'
                     ORDER BY e.created_at ASC";
    $exchange_res = $conn->query($exchange_sql);
    if ($exchange_res) {
        while ($row = $exchange_res->fetch_assoc()) {
            $row['commission_delta'] = (float)($row['amount_difference'] ?? 0) * 0.05;
            $exchange_rows[] = $row;
        }
    }

    echo json_encode([
        'items' => $items,
        'exchanges' => $exchange_rows,
    ]);
    exit();
}

// 3. FILTER LOGIC
$search_raw = trim($_GET['search'] ?? '');
$start_date_raw = trim($_GET['start_date'] ?? '');
$end_date_raw = trim($_GET['end_date'] ?? '');

$search = $search_raw !== '' ? $conn->real_escape_string($search_raw) : '';
$search_int = (int)$search_raw;
$start_date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date_raw) ? $start_date_raw : '';
$end_date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date_raw) ? $end_date_raw : '';

$where = ["s.user_id = '$user_id'"];
if ($search !== '') {
    $where[] = "(
        s.id LIKE '%$search_int%'
        OR p.name LIKE '%$search%'
        OR EXISTS (
            SELECT 1
            FROM exchange_transactions e
            LEFT JOIN exchange_items ei ON ei.exchange_id = e.id
            LEFT JOIN products op ON op.id = ei.old_product_id
            LEFT JOIN products np ON np.id = ei.new_product_id
            WHERE e.original_sale_id = s.id
              AND COALESCE(e.commission_user_id, s.user_id, e.user_id) = '$user_id'
              AND (
                  e.id LIKE '%$search_int%'
                  OR e.adjustment_type LIKE '%$search%'
                  OR e.note LIKE '%$search%'
                  OR op.name LIKE '%$search%'
                  OR np.name LIKE '%$search%'
              )
        )
    )";
}
if ($start_date !== '') {
    $where[] = "DATE(s.created_at) >= '$start_date'";
}
if ($end_date !== '') {
    $where[] = "DATE(s.created_at) <= '$end_date'";
}
$where_sql = implode(' AND ', $where);

$sql = "SELECT s.id, s.total_amount, s.created_at
        FROM sales s
        JOIN sale_items si ON s.id = si.sale_id
        JOIN products p ON si.product_id = p.id
        WHERE $where_sql
        GROUP BY s.id, s.total_amount, s.created_at
        ORDER BY s.created_at DESC";
$result = $conn->query($sql);

// 4. CALCULATE STATS (5% Commission)
$total_sales = 0;
$sales_data = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['commission_delta'] = (float)$row['total_amount'] * 0.05;
        $total_sales += (float)$row['total_amount'];
        $sales_data[] = $row;
    }
}
$sales_commission_total = $total_sales * 0.05;
$exchange_data = [];
$exchange_map = [];
$exchange_summary_sql = "SELECT e.id,
                                e.original_sale_id,
                                e.adjustment_type,
                                e.amount_difference,
                                e.total_old_amount,
                                e.total_new_amount,
                                e.note,
                                e.created_at,
                                s.customer_name
                         FROM exchange_transactions e
                         LEFT JOIN sales s ON s.id = e.original_sale_id
                         WHERE COALESCE(e.commission_user_id, s.user_id, e.user_id) = '$user_id'
                         ORDER BY e.created_at DESC";
$exchange_result = $conn->query($exchange_summary_sql);
if ($exchange_result) {
    while ($row = $exchange_result->fetch_assoc()) {
        $difference = (float)($row['amount_difference'] ?? 0);
        $row['commission_delta'] = $difference * 0.05;
        $exchange_data[] = $row;
        $sale_key = (int)($row['original_sale_id'] ?? 0);
        if (!isset($exchange_map[$sale_key])) {
            $exchange_map[$sale_key] = [
                'exchange_count' => 0,
                'exchange_amount_delta' => 0.0,
                'exchange_commission_delta' => 0.0,
                'items' => [],
            ];
        }
        $exchange_map[$sale_key]['exchange_count']++;
        $exchange_map[$sale_key]['exchange_amount_delta'] += $difference;
        $exchange_map[$sale_key]['exchange_commission_delta'] += $row['commission_delta'];
        $exchange_map[$sale_key]['items'][] = $row;
    }
}

foreach ($sales_data as &$sale) {
    $sale_id = (int)$sale['id'];
    $sale_exchange = $exchange_map[$sale_id] ?? [
        'exchange_count' => 0,
        'exchange_amount_delta' => 0.0,
        'exchange_commission_delta' => 0.0,
        'items' => [],
    ];
    $sale['exchange_count'] = (int)$sale_exchange['exchange_count'];
    $sale['exchange_amount_delta'] = (float)$sale_exchange['exchange_amount_delta'];
    $sale['exchange_commission_delta'] = (float)$sale_exchange['exchange_commission_delta'];
    $sale['net_amount'] = (float)$sale['total_amount'] + $sale['exchange_amount_delta'];
    $sale['net_commission'] = (float)$sale['commission_delta'] + $sale['exchange_commission_delta'];
}
unset($sale);

$filtered_exchange_count = 0;
$exchange_amount_delta = 0;
$exchange_commission_delta = 0;
foreach ($sales_data as $sale) {
    $filtered_exchange_count += (int)$sale['exchange_count'];
    $exchange_amount_delta += (float)$sale['exchange_amount_delta'];
    $exchange_commission_delta += (float)$sale['exchange_commission_delta'];
}

$net_activity_total = $total_sales + $exchange_amount_delta;
$net_activity_commission = $sales_commission_total + $exchange_commission_delta;

$commission = get_commission_totals($conn, $user_id, 0.05);

$report_params = $_GET;
unset($report_params['export_report'], $report_params['get_receipt_items']);
$back_query = http_build_query($report_params);
$back_link = 'my_history.php' . ($back_query !== '' ? '?' . $back_query : '');

// 5. REPORT EXPORT (FILTERED)
if (isset($_GET['export_report']) && in_array($_GET['export_report'], ['pdf', 'print'], true)) {
    $export_type = $_GET['export_report'];

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>My Sales And Exchange Report | Galadawa</title>
        <style>
            :root {
                --bg: #eef2f5;
                --card: #ffffff;
                --border: #d7dde5;
                --head: #e8edf3;
                --text: #24303d;
                --muted: #5d6b7c;
                --accent: #3b5a7a;
            }
            * { box-sizing: border-box; }
            body { margin: 0; font-family: Arial, sans-serif; background: var(--bg); color: var(--text); }
            .top-header {
                background: #2c4f84;
                color: #fff;
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 12px 18px;
            }
            .top-header .btn-back {
                text-decoration: none;
                color: #fff;
                background: rgba(255,255,255,0.2);
                padding: 8px 12px;
                border-radius: 6px;
                font-size: 13px;
                font-weight: 600;
            }
            .top-header .brand {
                font-size: 24px;
                font-weight: 700;
                letter-spacing: 0.4px;
                text-align: center;
                flex: 1;
            }
            .top-header .spacer { width: 120px; }
            .page-wrap { padding: 24px; }
            .report-wrap { max-width: 1000px; margin: 0 auto; background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 20px; }
            .shop-title { text-align: center; color: var(--accent); margin: 0 0 4px; font-size: 24px; letter-spacing: 0.5px; }
            .report-title { text-align: center; margin: 0 0 8px; font-size: 18px; }
            .report-meta { text-align: center; margin: 0 0 16px; color: var(--muted); font-size: 13px; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid var(--border); padding: 9px; font-size: 13px; text-align: left; }
            th { background: var(--head); color: #2f4256; }
            tbody tr:nth-child(even) { background: #f8fafc; }
            .summary { margin-top: 14px; border: 1px solid var(--border); background: #f6f9fc; border-radius: 8px; padding: 10px; color: #33485f; line-height: 1.8; }
            .toolbar { margin-top: 12px; text-align: right; }
            .btn { display: inline-block; text-decoration: none; background: #5f7288; color: #fff; border-radius: 6px; padding: 8px 12px; font-size: 13px; }
            @media print {
                body { background: #fff; }
                .top-header { display: none; }
                .page-wrap { padding: 0; }
                .report-wrap { border: none; border-radius: 0; padding: 0; }
                .toolbar { display: none; }
            }
        </style>
    </head>
    <body>
        <?php if ($export_type !== 'print'): ?>
            <div class="top-header">
                <a href="<?php echo htmlspecialchars($back_link, ENT_QUOTES, 'UTF-8'); ?>" class="btn-back">← Back to History</a>
                <div class="brand">Galadawa Textiles</div>
                <div class="spacer"></div>
            </div>
        <?php endif; ?>
        <div class="page-wrap">
            <div class="report-wrap">
                <h1 class="shop-title">Galadawa Textiles</h1>
                <h2 class="report-title">My Sales And Exchange Report</h2>
                <p class="report-meta">
                    Date range:
                    <?php echo $start_date !== '' ? htmlspecialchars($start_date, ENT_QUOTES, 'UTF-8') : 'Any'; ?>
                    -
                    <?php echo $end_date !== '' ? htmlspecialchars($end_date, ENT_QUOTES, 'UTF-8') : 'Any'; ?>
                    <br>
                    Prepared by:
                    <?php echo htmlspecialchars($report_attendant, ENT_QUOTES, 'UTF-8'); ?>
                    (<?php echo htmlspecialchars($report_attendant_username, ENT_QUOTES, 'UTF-8'); ?>)
                </p>
                <table>
                    <thead>
                        <tr>
                            <th>Receipt #</th>
                            <th>Date / Time</th>
                            <th>Sale Amount</th>
                            <th>Exchange Adjustment</th>
                            <th>Net Value</th>
                            <th>Net Commission</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($sales_data) > 0): ?>
                            <?php foreach ($sales_data as $sale): ?>
                                <tr>
                                    <td>#<?php echo str_pad((int)$sale['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($sale['created_at'])); ?></td>
                                    <td>₦<?php echo number_format((float)$sale['total_amount'], 2); ?></td>
                                    <td><?php echo format_signed_naira((float)$sale['exchange_amount_delta'], 2); ?></td>
                                    <td>₦<?php echo number_format((float)$sale['net_amount'], 2); ?></td>
                                    <td><?php echo format_signed_naira((float)$sale['net_commission'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6">No transactions found for the selected criteria.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div class="summary">
                    <strong>Total Transactions:</strong> <?php echo number_format(count($sales_data)); ?><br>
                    <strong>Total Sales:</strong> ₦<?php echo number_format($total_sales, 2); ?><br>
                    <strong>Sales Commission (5%):</strong> ₦<?php echo number_format($sales_commission_total, 2); ?>
                </div>
                <div class="summary" style="margin-top:12px;">
                    <strong>Total Exchanges:</strong> <?php echo number_format($filtered_exchange_count); ?><br>
                    <strong>Exchange Amount Adjustment:</strong> <?php echo format_signed_naira($exchange_amount_delta, 2); ?><br>
                    <strong>Exchange Commission Adjustment:</strong> <?php echo format_signed_naira($exchange_commission_delta, 2); ?><br>
                    <strong>Net Activity Value:</strong> <?php echo format_signed_naira($net_activity_total, 2); ?><br>
                    <strong>Net Commission From Activity:</strong> <?php echo format_signed_naira($net_activity_commission, 2); ?><br>
                    <strong>Current Commission Balance:</strong> <?php echo format_signed_naira($commission['balance'], 2); ?>
                </div>
                <?php if ($export_type !== 'print'): ?>
                    <div class="toolbar"><a class="btn" href="#" onclick="window.print(); return false;">Print This Report</a></div>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($export_type === 'print'): ?>
            <script>
                window.addEventListener('load', () => window.print());
            </script>
        <?php endif; ?>
    </body>
    </html>
    <?php
    exit();
}

$pdf_report_link = 'my_history.php?' . http_build_query(array_merge($report_params, ['export_report' => 'pdf']));
$print_report_link = 'my_history.php?' . http_build_query(array_merge($report_params, ['export_report' => 'print']));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My History | Galadawa</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="../js/sweetalert2.all.min.js"></script>
    <style>
        body { font-family: 'Poppins', sans-serif; margin: 0; background-color: #f4f7f6; color: #333; }
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
        .main-content { flex: 1; min-width: 0; }

        /* PAGE LAYOUT */
        .container { max-width: 1000px; margin: 20px auto; padding: 0 20px; }

        /* STATS CARDS */
        .stats-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; margin-bottom: 20px; }
        .stat-card { 
            background: white; padding: 20px; border-radius: 12px; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 20px;
            transition: transform 0.3s ease, box-shadow 0.3s ease; cursor: default;
        }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .stat-icon { width: 50px; height: 50px; background: #eef2f5; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 24px; color: #1e3c72; }
        .stat-info h3 { margin: 0; font-size: 24px; color: #333; }
        .stat-info p { margin: 0; font-size: 13px; color: #777; }
        .text-green { color: #28a745 !important; }
        .text-red { color: #c0392b !important; }
        .amount-positive { color: #15803d; font-weight: 700; }
        .amount-negative { color: #c0392b; font-weight: 700; }
        .section-title { margin: 0 0 14px; font-size: 18px; color: #24303d; }

        /* FILTER BAR */
        .filter-bar { background: white; padding: 15px; border-radius: 12px; margin-bottom: 20px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .search-wrap { flex: 1 1 300px; min-width: 260px; }
        .filter-input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; outline: none; }
        .btn-filter { padding: 10px 20px; background: #1e3c72; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; white-space: nowrap; }
        .btn-reset { padding: 10px 15px; background: #eee; color: #333; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; white-space: nowrap; }

        /* TABLE */
        .history-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .styled-table { width: 100%; border-collapse: collapse; }
        .styled-table thead tr { background-color: #f8f9fa; color: #333; text-align: left; }
        .styled-table th, .styled-table td { padding: 15px; border-bottom: 1px solid #eee; }
        .btn-view { padding: 6px 12px; background: #eef2f5; color: #1e3c72; border: 1px solid #1e3c72; border-radius: 5px; cursor: pointer; font-size: 12px; font-weight: 600; }
        .btn-view:hover { background: #1e3c72; color: white; }
        .date-range { display: flex; gap: 10px; width: 100%; max-width: 360px; }
        .date-range .filter-input { flex: 1; min-width: 0; }
        .history-mobile-list { display: none; }
        .history-mobile-card {
            border: 1px solid #e7edf5;
            border-radius: 16px;
            padding: 14px;
            background: #fff;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
        }
        .history-mobile-card + .history-mobile-card { margin-top: 12px; }
        .history-mobile-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
        .history-mobile-title { margin: 0; font-size: 16px; color: #24303d; font-weight: 700; }
        .history-mobile-subtitle { margin: 4px 0 0; color: #667085; font-size: 13px; }
        .history-mobile-callout {
            min-width: 92px;
            padding: 10px 12px;
            border-radius: 14px;
            background: #eef4ff;
            text-align: right;
        }
        .history-mobile-callout span {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #667085;
        }
        .history-mobile-callout strong {
            display: block;
            margin-top: 4px;
            font-size: 18px;
            line-height: 1.1;
            color: #1e3c72;
        }
        .history-mobile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 12px; }
        .history-mobile-item {
            background: #f8fafc;
            border: 1px solid #edf2f7;
            border-radius: 12px;
            padding: 10px 12px;
        }
        .history-mobile-item span {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #667085;
        }
        .history-mobile-item strong {
            display: block;
            margin-top: 4px;
            font-size: 14px;
            color: #24303d;
        }
        .history-mobile-actions { margin-top: 12px; }
        .history-empty { text-align: center; padding: 32px 16px; color: #98a2b3; }
        .stack-gap { margin-top: 20px; }

        /* RECEIPT MODAL CSS */
        .receipt-list { text-align: left; margin-top: 10px; }
        .receipt-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; }
        .receipt-total { display: flex; justify-content: space-between; padding: 15px 0; font-weight: bold; border-top: 2px solid #333; margin-top: 10px; font-size: 18px; }

        .styled-table { min-width: 720px; }
        @media(max-width: 900px) {
            .main-content { width: 100%; }
            .table-wrap { display: none; }
            .history-mobile-list { display: block; }
        }
        @media(max-width: 600px) {
            .stats-grid { grid-template-columns: 1fr; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .history-card { padding: 12px; }
            .history-mobile-head { flex-direction: column; }
            .history-mobile-callout { width: 100%; text-align: left; }
            .history-mobile-grid { grid-template-columns: 1fr; }
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
            <a href="holding_orders.php"><i class="fas fa-boxes-stacked"></i> <span>Holding</span></a>
            <a href="exchange.php"><i class="fas fa-right-left"></i> <span>Exchange</span></a>
            <a href="inventory_view.php"><i class="fas fa-box"></i> <span>View Inventory</span></a>
            <a href="my_history.php" class="active"><i class="fas fa-history"></i> <span>My History</span></a>
            <a href="profile.php"><i class="fas fa-user-cog"></i> <span>My Profile</span></a>
            <a href="payouts.php"><i class="fas fa-wallet"></i> <span>Payouts</span><?php if ($payout_unread > 0): ?><span class="notif-dot"></span><?php endif; ?></a>
        </div>

        <div class="main-content">
            <div class="container">
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-coins"></i></div>
                <div class="stat-info">
                    <h3>₦ <?php echo number_format($total_sales, 2); ?></h3>
                    <p>Total Sales (Filtered)</p>
                </div>
            </div>
            <div class="stat-card" style="border-left: 5px solid <?php echo $commission['balance'] < 0 ? '#c0392b' : '#28a745'; ?>;">
                <div class="stat-icon <?php echo $commission['balance'] < 0 ? '' : 'text-green'; ?>" style="background: <?php echo $commission['balance'] < 0 ? '#fdecec' : '#e0f9e6'; ?>; color: <?php echo $commission['balance'] < 0 ? '#c0392b' : '#28a745'; ?>;"><i class="fas fa-wallet"></i></div>
                <div class="stat-info">
                    <h3 class="<?php echo $commission['balance'] < 0 ? '' : 'text-green'; ?>" style="color: <?php echo $commission['balance'] < 0 ? '#c0392b' : '#28a745'; ?>;">₦ <?php echo number_format($commission['balance'], 2); ?></h3>
                    <p><?php echo $commission['balance'] < 0 ? 'Commission Balance (Outstanding)' : 'Commission Balance'; ?></p>
                </div>
            </div>
        </div>

        <form method="GET" class="filter-bar">
            <div class="search-wrap">
                <input type="text" name="search" class="filter-input" placeholder="Receipt #, Exchange #, or Product Name" value="<?php echo htmlspecialchars($search_raw, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="date-range">
                <input type="date" name="start_date" class="filter-input" value="<?php echo htmlspecialchars($start_date, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="date" name="end_date" class="filter-input" value="<?php echo htmlspecialchars($end_date, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
            <?php if ($search_raw !== '' || $start_date !== '' || $end_date !== ''): ?>
                <a href="my_history.php" class="btn-reset">Reset</a>
            <?php endif; ?>
            <a href="<?php echo htmlspecialchars($pdf_report_link, ENT_QUOTES, 'UTF-8'); ?>" class="btn-reset" style="background:#1e3c72; color:#fff;"><i class="fas fa-eye"></i> View Report</a>
            <a href="<?php echo htmlspecialchars($print_report_link, ENT_QUOTES, 'UTF-8'); ?>" class="btn-reset" style="background:#6c757d; color:#fff;"><i class="fas fa-print"></i> Print Report</a>
            <a href="payouts.php" class="btn-reset" style="background:#1e3c72; color:#fff;"><i class="fas fa-wallet"></i> Payouts</a>
        </form>

        <div class="history-card">
            <h3 class="section-title">Sales Activity</h3>
            <?php if (count($sales_data) > 0): ?>
                <div class="table-wrap">
                    <table class="styled-table">
                        <thead>
                            <tr>
                                <th>Receipt #</th>
                                <th>Date / Time</th>
                                <th>Sale Amount</th>
                                <th>Exchange Adjustment</th>
                                <th>Net Value</th>
                                <th>Net Commission</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($sales_data as $sale): ?>
                                <?php $amount_class = (float)$sale['exchange_amount_delta'] < 0 ? 'amount-negative' : 'amount-positive'; ?>
                                <?php $commission_class = (float)$sale['net_commission'] < 0 ? 'amount-negative' : 'amount-positive'; ?>
                                <tr>
                                    <td>#<?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo date('M d, h:i A', strtotime($sale['created_at'])); ?></td>
                                    <td style="font-weight: bold;">₦ <?php echo number_format((float)$sale['total_amount'], 2); ?></td>
                                    <td class="<?php echo $amount_class; ?>">
                                        <?php echo format_signed_naira((float)$sale['exchange_amount_delta'], 2); ?>
                                        <?php if ((int)$sale['exchange_count'] > 0): ?>
                                            <div style="font-size:11px; color:#667085; margin-top:4px;"><?php echo (int)$sale['exchange_count']; ?> exchange<?php echo (int)$sale['exchange_count'] === 1 ? '' : 's'; ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-weight: 700;">₦ <?php echo number_format((float)$sale['net_amount'], 2); ?></td>
                                    <td class="<?php echo $commission_class; ?>">
                                        <?php echo format_signed_naira((float)$sale['net_commission'], 2); ?>
                                        <?php if ((float)$sale['exchange_commission_delta'] != 0.0): ?>
                                            <div style="font-size:11px; color:#667085; margin-top:4px;">Exchange adjustment <?php echo format_signed_naira((float)$sale['exchange_commission_delta'], 2); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn-view" onclick="viewReceipt(<?php echo $sale['id']; ?>, '<?php echo $sale['total_amount']; ?>', '<?php echo $sale['net_amount']; ?>')">
                                            View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="history-mobile-list">
                    <?php foreach($sales_data as $sale): ?>
                        <?php $amount_class = (float)$sale['exchange_amount_delta'] < 0 ? 'amount-negative' : 'amount-positive'; ?>
                        <?php $commission_class = (float)$sale['net_commission'] < 0 ? 'amount-negative' : 'amount-positive'; ?>
                        <article class="history-mobile-card">
                            <div class="history-mobile-head">
                                <div>
                                    <h4 class="history-mobile-title">Receipt #<?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?></h4>
                                    <p class="history-mobile-subtitle"><?php echo date('M d, Y h:i A', strtotime($sale['created_at'])); ?></p>
                                </div>
                                <div class="history-mobile-callout">
                                    <span>Net Value</span>
                                    <strong>₦<?php echo number_format((float)$sale['net_amount'], 0); ?></strong>
                                </div>
                            </div>
                            <div class="history-mobile-grid">
                                <div class="history-mobile-item">
                                    <span>Sale Amount</span>
                                    <strong>₦<?php echo number_format((float)$sale['total_amount'], 2); ?></strong>
                                </div>
                                <div class="history-mobile-item">
                                    <span>Exchange Adjustment</span>
                                    <strong class="<?php echo $amount_class; ?>"><?php echo format_signed_naira((float)$sale['exchange_amount_delta'], 2); ?></strong>
                                </div>
                                <div class="history-mobile-item">
                                    <span>Net Commission</span>
                                    <strong class="<?php echo $commission_class; ?>"><?php echo format_signed_naira((float)$sale['net_commission'], 2); ?></strong>
                                </div>
                                <div class="history-mobile-item">
                                    <span>Exchanges</span>
                                    <strong><?php echo (int)$sale['exchange_count']; ?></strong>
                                </div>
                            </div>
                            <div class="history-mobile-actions">
                                <button class="btn-view" onclick="viewReceipt(<?php echo $sale['id']; ?>, '<?php echo $sale['total_amount']; ?>', '<?php echo $sale['net_amount']; ?>')">View Receipt</button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="history-empty">
                    <i class="fas fa-search" style="font-size: 40px; margin-bottom: 10px;"></i>
                    <p>No sales records found matching your search.</p>
                </div>
            <?php endif; ?>
        </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('collapsed');
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function viewReceipt(saleId, total, netTotal) {
            Swal.fire({ title: 'Loading...', didOpen: () => { Swal.showLoading() } });
            fetch('my_history.php?get_receipt_items=' + saleId)
                .then(response => response.json())
                .then(data => {
                    const items = Array.isArray(data.items) ? data.items : [];
                    const exchanges = Array.isArray(data.exchanges) ? data.exchanges : [];
                    let html = '<div class="receipt-list">';
                    items.forEach(item => {
                        html += `<div class="receipt-item"><span>${item.name} <br> <small style="color:#888;">${item.quantity} x ₦${Number(item.price).toLocaleString()}</small></span><span>₦${Number(item.subtotal).toLocaleString()}</span></div>`;
                    });
                    html += `<div class="receipt-total"><span>SALE TOTAL</span><span>₦${Number(total).toLocaleString()}</span></div>`;

                    if (exchanges.length > 0) {
                        html += `<div style="margin-top:14px; text-align:left;">
                            <div style="font-weight:700; margin-bottom:8px;">Exchange Activity</div>`;
                        exchanges.forEach(exchange => {
                            const amount = Number(exchange.amount_difference || 0);
                            const commission = Number(exchange.commission_delta || 0);
                            const amountSign = amount > 0 ? '+' : (amount < 0 ? '-' : '');
                            const commissionSign = commission > 0 ? '+' : (commission < 0 ? '-' : '');
                            const amountColor = amount < 0 ? '#c0392b' : '#15803d';
                            const commissionColor = commission < 0 ? '#c0392b' : '#15803d';
                            const adjustmentLabel = escapeHtml(String(exchange.adjustment_type || '').replace(/_/g, ' '));
                            const exchangeNote = exchange.note ? escapeHtml(exchange.note) : '';
                            html += `
                                <div style="border:1px solid #e5e7eb; border-radius:10px; padding:10px; margin-top:8px;">
                                    <div style="font-weight:600;">Exchange #${String(exchange.id).padStart(6, '0')}</div>
                                    <div style="font-size:12px; color:#667085; margin-top:4px;">${adjustmentLabel} · ${new Date(exchange.created_at).toLocaleString()}</div>
                                    <div style="margin-top:6px; color:${amountColor}; font-weight:700;">Amount Adjustment: ${amountSign}₦${Math.abs(amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                                    <div style="margin-top:4px; color:${commissionColor}; font-weight:700;">Commission Adjustment: ${commissionSign}₦${Math.abs(commission).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                                    ${exchangeNote ? `<div style="margin-top:6px; font-size:12px; color:#667085;">Note: ${exchangeNote}</div>` : ''}
                                </div>
                            `;
                        });
                        html += `</div>`;
                    }

                    html += `<div class="receipt-total"><span>NET TOTAL</span><span>₦${Number(netTotal || total).toLocaleString()}</span></div></div>`;
                    
                    Swal.fire({
                        title: 'Receipt #' + String(saleId).padStart(6, '0'),
                        html: html, width: 400,
                        confirmButtonText: '<i class="fas fa-print"></i> Print / Download',
                        confirmButtonColor: '#1e3c72',
                        showCancelButton: true, cancelButtonText: 'Close',
                        cancelButtonColor: '#6c757d'
                    }).then((result) => {
                        if (result.isConfirmed) { window.open('print_receipt.php?id=' + saleId, 'Receipt', 'width=400,height=600'); }
                    });
                });
        }
    </script>
</body>
</html>
