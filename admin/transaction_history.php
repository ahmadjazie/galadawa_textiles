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

function format_signed_naira($amount, $decimals = 2) {
    $amount = (float)$amount;
    $sign = $amount > 0 ? '+' : ($amount < 0 ? '-' : '');
    return $sign . '₦' . number_format(abs($amount), $decimals);
}

function ensure_sale_items_buy_price_column($conn) {
    $check = $conn->query("SHOW COLUMNS FROM sale_items LIKE 'buy_price_at_sale'");
    if ($check && $check->num_rows === 0) {
        $conn->query("ALTER TABLE sale_items ADD COLUMN buy_price_at_sale DECIMAL(10,2) NULL AFTER price");
    }
}

ensure_sale_items_buy_price_column($conn);

// AJAX: receipt items
if (isset($_GET['get_receipt_items'])) {
    $sale_id = (int)$_GET['get_receipt_items'];
    $sql_items = "SELECT si.*, p.name
                  FROM sale_items si
                  JOIN products p ON si.product_id = p.id
                  WHERE si.sale_id = '$sale_id'";
    $res_items = $conn->query($sql_items);
    $items = [];
    while ($row = $res_items->fetch_assoc()) { $items[] = $row; }

    $exchange_rows = [];
    $exchange_sql = "SELECT e.id,
                            e.adjustment_type,
                            e.amount_difference,
                            e.note,
                            e.created_at,
                            COALESCE(
                                SUM(
                                    ((ei.new_price - COALESCE(ei.new_buy_price_snapshot, new_product.buy_price, 0)) * ei.new_quantity)
                                    -
                                    ((ei.old_price - COALESCE(ei.old_buy_price_snapshot, si.buy_price_at_sale, old_product.buy_price, 0)) * ei.old_quantity)
                                ),
                                0
                            ) AS profit_delta
                     FROM exchange_transactions e
                     LEFT JOIN exchange_items ei ON ei.exchange_id = e.id
                     LEFT JOIN sale_items si ON si.id = ei.original_sale_item_id
                     LEFT JOIN products old_product ON old_product.id = ei.old_product_id
                     LEFT JOIN products new_product ON new_product.id = ei.new_product_id
                     WHERE e.original_sale_id = '$sale_id'
                     GROUP BY e.id, e.adjustment_type, e.amount_difference, e.note, e.created_at
                     ORDER BY e.created_at ASC";
    $exchange_res = $conn->query($exchange_sql);
    if ($exchange_res) {
        while ($row = $exchange_res->fetch_assoc()) {
            $exchange_rows[] = $row;
        }
    }

    echo json_encode([
        'items' => $items,
        'exchanges' => $exchange_rows,
    ]);
    exit();
}

// Filters
$search_raw = trim($_GET['search'] ?? '');
$start_date_raw = trim($_GET['start_date'] ?? '');
$end_date_raw = trim($_GET['end_date'] ?? '');

$search = $search_raw !== '' ? $conn->real_escape_string($search_raw) : '';
$search_int = (int)$search_raw;
$start_date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date_raw) ? $start_date_raw : '';
$end_date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date_raw) ? $end_date_raw : '';

$where = ["1=1"];
if ($search !== '') {
    $where[] = "(
        s.id LIKE '%$search_int%'
        OR p.name LIKE '%$search%'
        OR u.username LIKE '%$search%'
        OR EXISTS (
            SELECT 1
            FROM exchange_transactions e
            LEFT JOIN exchange_items ei ON ei.exchange_id = e.id
            LEFT JOIN products op ON op.id = ei.old_product_id
            LEFT JOIN products np ON np.id = ei.new_product_id
            WHERE e.original_sale_id = s.id
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

$sql = "SELECT s.id,
               s.total_amount,
               s.created_at,
               u.username,
               COALESCE(SUM((si.price - COALESCE(si.buy_price_at_sale, p.buy_price, 0)) * si.quantity), 0) AS profit
        FROM sales s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN sale_items si ON s.id = si.sale_id
        LEFT JOIN products p ON si.product_id = p.id
        WHERE $where_sql
        GROUP BY s.id, s.total_amount, s.created_at, u.username
        ORDER BY s.created_at DESC";
$result = $conn->query($sql);

$sales_data = [];
$gross_total_sales = 0;
$gross_total_profit = 0;
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $gross_total_sales += (float)$row['total_amount'];
        $gross_total_profit += (float)$row['profit'];
        $sales_data[] = $row;
    }
}

// Daily profit summary
$today_gross_profit = 0;
$today_profit_sql = "SELECT COALESCE(SUM((si.price - COALESCE(si.buy_price_at_sale, p.buy_price, 0)) * si.quantity), 0) AS profit_today
                     FROM sales s
                     JOIN sale_items si ON s.id = si.sale_id
                     LEFT JOIN products p ON si.product_id = p.id
                     WHERE DATE(s.created_at) = CURDATE()";
$today_profit_res = $conn->query($today_profit_sql);
if ($today_profit_res) {
    $today_gross_profit = (float)($today_profit_res->fetch_assoc()['profit_today'] ?? 0);
}

$exchange_where = ["1=1"];
if ($search !== '') {
    $exchange_where[] = "(e.id LIKE '%$search_int%' OR e.original_sale_id LIKE '%$search_int%' OR e.adjustment_type LIKE '%$search%' OR u.username LIKE '%$search%')";
}
if ($start_date !== '') {
    $exchange_where[] = "DATE(e.created_at) >= '$start_date'";
}
if ($end_date !== '') {
    $exchange_where[] = "DATE(e.created_at) <= '$end_date'";
}
$exchange_where_sql = implode(' AND ', $exchange_where);

$exchange_sql = "SELECT e.id,
                        e.original_sale_id,
                        e.adjustment_type,
                        e.amount_difference,
                        e.total_old_amount,
                        e.total_new_amount,
                        e.created_at,
                        u.username,
                        COALESCE(
                            SUM(
                                ((ei.new_price - COALESCE(ei.new_buy_price_snapshot, new_product.buy_price, 0)) * ei.new_quantity)
                                -
                                ((ei.old_price - COALESCE(ei.old_buy_price_snapshot, si.buy_price_at_sale, old_product.buy_price, 0)) * ei.old_quantity)
                            ),
                            0
                        ) AS profit_delta
                 FROM exchange_transactions e
                 LEFT JOIN users u ON u.id = e.user_id
                 LEFT JOIN exchange_items ei ON ei.exchange_id = e.id
                 LEFT JOIN sale_items si ON si.id = ei.original_sale_item_id
                 LEFT JOIN products old_product ON old_product.id = ei.old_product_id
                 LEFT JOIN products new_product ON new_product.id = ei.new_product_id
                 WHERE $exchange_where_sql
                 GROUP BY e.id, e.original_sale_id, e.adjustment_type, e.amount_difference, e.total_old_amount, e.total_new_amount, e.created_at, u.username
                 ORDER BY e.created_at DESC";
$exchange_result = $conn->query($exchange_sql);

$exchange_data = [];
$exchange_map = [];
$exchange_total = 0;
$exchange_add_total = 0;
$exchange_credit_total = 0;
$exchange_sales_delta = 0;
$exchange_profit_delta_total = 0;
$today_exchange_profit_delta = 0;
if ($exchange_result) {
    while ($row = $exchange_result->fetch_assoc()) {
        $difference = (float)$row['amount_difference'];
        $profit_delta = (float)($row['profit_delta'] ?? 0);
        $exchange_total++;
        $exchange_sales_delta += $difference;
        $exchange_profit_delta_total += $profit_delta;
        if ($difference > 0) {
            $exchange_add_total += $difference;
        } elseif ($difference < 0) {
            $exchange_credit_total += abs($difference);
        }
        if (date('Y-m-d', strtotime((string)$row['created_at'])) === date('Y-m-d')) {
            $today_exchange_profit_delta += $profit_delta;
        }
        $exchange_data[] = $row;
        $sale_key = (int)($row['original_sale_id'] ?? 0);
        if (!isset($exchange_map[$sale_key])) {
            $exchange_map[$sale_key] = [
                'exchange_count' => 0,
                'exchange_amount_delta' => 0.0,
                'exchange_profit_delta' => 0.0,
                'items' => [],
            ];
        }
        $exchange_map[$sale_key]['exchange_count']++;
        $exchange_map[$sale_key]['exchange_amount_delta'] += $difference;
        $exchange_map[$sale_key]['exchange_profit_delta'] += $profit_delta;
        $exchange_map[$sale_key]['items'][] = $row;
    }
}

$total_sales = $gross_total_sales + $exchange_sales_delta;
$total_profit = $gross_total_profit + $exchange_profit_delta_total;
$today_profit = $today_gross_profit + $today_exchange_profit_delta;

foreach ($sales_data as &$sale) {
    $sale_id = (int)$sale['id'];
    $sale_exchange = $exchange_map[$sale_id] ?? [
        'exchange_count' => 0,
        'exchange_amount_delta' => 0.0,
        'exchange_profit_delta' => 0.0,
        'items' => [],
    ];
    $sale['exchange_count'] = (int)$sale_exchange['exchange_count'];
    $sale['exchange_amount_delta'] = (float)$sale_exchange['exchange_amount_delta'];
    $sale['exchange_profit_delta'] = (float)$sale_exchange['exchange_profit_delta'];
    $sale['net_amount'] = (float)$sale['total_amount'] + $sale['exchange_amount_delta'];
    $sale['net_profit'] = (float)$sale['profit'] + $sale['exchange_profit_delta'];
}
unset($sale);

if (isset($_GET['live_data'])) {
    $rows = [];
    foreach ($sales_data as $sale) {
        $created_ts = strtotime($sale['created_at']);
        $rows[] = [
            'id' => (int)$sale['id'],
            'receipt' => str_pad((int)$sale['id'], 6, '0', STR_PAD_LEFT),
            'username' => ucfirst((string)$sale['username']),
            'datetime_display' => date('M d, Y h:i A', $created_ts),
            'total_amount' => (float)$sale['total_amount'],
            'total_amount_display' => number_format((float)$sale['total_amount'], 2),
            'total_amount_whole_display' => number_format((float)$sale['total_amount'], 0),
            'exchange_amount_delta' => (float)$sale['exchange_amount_delta'],
            'exchange_amount_delta_display' => format_signed_naira((float)$sale['exchange_amount_delta'], 2),
            'exchange_amount_delta_whole_display' => format_signed_naira((float)$sale['exchange_amount_delta'], 0),
            'exchange_count' => (int)$sale['exchange_count'],
            'net_amount' => (float)$sale['net_amount'],
            'net_amount_display' => number_format((float)$sale['net_amount'], 2),
            'net_amount_whole_display' => number_format((float)$sale['net_amount'], 0),
            'profit' => (float)$sale['profit'],
            'profit_display' => number_format((float)$sale['profit'], 2),
            'exchange_profit_delta' => (float)$sale['exchange_profit_delta'],
            'exchange_profit_delta_display' => format_signed_naira((float)$sale['exchange_profit_delta'], 2),
            'net_profit' => (float)$sale['net_profit'],
            'net_profit_display' => number_format((float)$sale['net_profit'], 2),
        ];
    }

    header('Content-Type: application/json');
    echo json_encode([
        'ok' => true,
        'summary' => [
            'transactions_count' => count($sales_data),
            'transactions_count_display' => number_format(count($sales_data)),
            'total_sales_display' => number_format($total_sales, 2),
            'total_profit_display' => number_format($total_profit, 2),
            'today_profit_display' => number_format($today_profit, 2),
            'exchange_count_display' => number_format($exchange_total),
            'exchange_add_total_display' => number_format($exchange_add_total, 2),
            'exchange_credit_total_display' => number_format($exchange_credit_total, 2),
        ],
        'sales' => $rows,
    ]);
    exit();
}

$report_params = $_GET;
unset($report_params['export_report'], $report_params['get_receipt_items'], $report_params['id']);
$back_query = http_build_query($report_params);
$back_link = 'transaction_history.php' . ($back_query !== '' ? '?' . $back_query : '');

// Report export
if (isset($_GET['export_report']) && in_array($_GET['export_report'], ['pdf', 'print'], true)) {
    $export_type = $_GET['export_report'];
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Transactions And Exchanges Report | Galadawa Textiles</title>
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
                <a href="<?php echo htmlspecialchars($back_link, ENT_QUOTES, 'UTF-8'); ?>" class="btn-back">← Back to Transactions</a>
                <div class="brand">Galadawa Textiles</div>
                <div class="spacer"></div>
            </div>
        <?php endif; ?>
        <div class="page-wrap">
            <div class="report-wrap">
                <h1 class="shop-title">Galadawa Textiles</h1>
                <h2 class="report-title">Transactions Report</h2>
                <p class="report-meta">
                    Date range:
                    <?php echo $start_date !== '' ? htmlspecialchars($start_date, ENT_QUOTES, 'UTF-8') : 'Any'; ?>
                    -
                    <?php echo $end_date !== '' ? htmlspecialchars($end_date, ENT_QUOTES, 'UTF-8') : 'Any'; ?>
                </p>
                <table>
                    <thead>
                        <tr>
                            <th>Receipt #</th>
                            <th>Attendant</th>
                            <th>Date / Time</th>
                            <th>Sale Amount</th>
                            <th>Exchange Adjustment</th>
                            <th>Net Value</th>
                            <th>Net Profit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($sales_data) > 0): ?>
                            <?php foreach ($sales_data as $sale): ?>
                                <tr>
                                    <td>#<?php echo str_pad((int)$sale['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($sale['username']), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($sale['created_at'])); ?></td>
                                    <td>₦<?php echo number_format((float)$sale['total_amount'], 2); ?></td>
                                    <td><?php echo format_signed_naira((float)$sale['exchange_amount_delta'], 2); ?></td>
                                    <td>₦<?php echo number_format((float)$sale['net_amount'], 2); ?></td>
                                    <td>₦<?php echo number_format((float)$sale['net_profit'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7">No transactions found for the selected criteria.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div class="summary">
                    <strong>Total Transactions:</strong> <?php echo number_format(count($sales_data)); ?><br>
                    <strong>Total Sales:</strong> ₦<?php echo number_format($total_sales, 2); ?><br>
                    <strong>Total Profit:</strong> ₦<?php echo number_format($total_profit, 2); ?>
                </div>
                <div class="summary" style="margin-top:12px;">
                    <strong>Total Exchanges:</strong> <?php echo number_format($exchange_total); ?><br>
                    <strong>Customer Adds:</strong> ₦<?php echo number_format($exchange_add_total, 2); ?><br>
                    <strong>Customer Credits:</strong> ₦<?php echo number_format($exchange_credit_total, 2); ?><br>
                    <strong>Exchange Sales Adjustment:</strong> ₦<?php echo number_format($exchange_sales_delta, 2); ?><br>
                    <strong>Exchange Profit Adjustment:</strong> ₦<?php echo number_format($exchange_profit_delta_total, 2); ?>
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

// Optional deep-link from dashboard
$focus_sale_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$focus_total = 0;
if ($focus_sale_id > 0) {
    $focus_res = $conn->query("SELECT total_amount FROM sales WHERE id = '$focus_sale_id'");
    $focus_total = $focus_res ? (float)$focus_res->fetch_assoc()['total_amount'] : 0;
}

$pdf_report_link = 'transaction_history.php?' . http_build_query(array_merge($report_params, ['export_report' => 'pdf']));
$print_report_link = 'transaction_history.php?' . http_build_query(array_merge($report_params, ['export_report' => 'print']));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions And Exchanges | Galadawa Textiles</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <script src="../js/sweetalert2.all.min.js"></script>
    <style>
        .filter-bar {
            background: white;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .filter-input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            outline: none;
            width: 100%;
        }
        .btn-filter {
            padding: 10px 20px;
            background: #1e3c72;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }
        .btn-reset {
            padding: 10px 15px;
            background: #eee;
            color: #333;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-view {
            padding: 6px 12px;
            background: #eef2f5;
            color: #1e3c72;
            border: 1px solid #1e3c72;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
        }
        .btn-view:hover { background: #1e3c72; color: white; }
        .date-range { display: flex; gap: 10px; width: 100%; max-width: 360px; }
        .date-range .filter-input { flex: 1; min-width: 0; }
        .card-link { cursor: pointer; }
        .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .transaction-mobile-list { display: none; }
        .transaction-mobile-card {
            border: 1px solid #e7edf5;
            border-radius: 16px;
            padding: 14px;
            background: #fff;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
        }
        .transaction-mobile-card + .transaction-mobile-card { margin-top: 12px; }
        .transaction-mobile-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
        .transaction-mobile-title { margin: 0; font-size: 16px; color: #24303d; font-weight: 700; }
        .transaction-mobile-subtitle { margin: 4px 0 0; color: #667085; font-size: 13px; }
        .transaction-mobile-callout {
            min-width: 92px;
            padding: 10px 12px;
            border-radius: 14px;
            background: #eef4ff;
            text-align: right;
        }
        .transaction-mobile-callout span {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #667085;
        }
        .transaction-mobile-callout strong {
            display: block;
            margin-top: 4px;
            font-size: 18px;
            line-height: 1.1;
            color: #1e3c72;
        }
        .transaction-mobile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 12px; }
        .transaction-mobile-item {
            background: #f8fafc;
            border: 1px solid #edf2f7;
            border-radius: 12px;
            padding: 10px 12px;
        }
        .transaction-mobile-item span {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #667085;
        }
        .transaction-mobile-item strong {
            display: block;
            margin-top: 4px;
            font-size: 14px;
            color: #24303d;
        }
        .transaction-mobile-actions { margin-top: 12px; }
        .exchange-mobile-list { display: none; }
        .exchange-mobile-card {
            border: 1px solid #e7edf5;
            border-radius: 16px;
            padding: 14px;
            background: #fff;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
        }
        .exchange-mobile-card + .exchange-mobile-card { margin-top: 12px; }
        @media(max-width: 700px) {
            .filter-bar { flex-direction: column; align-items: stretch; }
            .table-wrap { display: none; }
            .transaction-mobile-list { display: block; }
            .exchange-mobile-list { display: block; }
            .transaction-mobile-head { flex-direction: column; }
            .transaction-mobile-callout { width: 100%; text-align: left; }
            .transaction-mobile-grid { grid-template-columns: 1fr; }
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
            <a href="exchange_history.php"><i class="fas fa-right-left"></i> <span>Exchanges</span></a>
            <a href="payout_requests.php"><i class="fas fa-wallet"></i> <span>Payout Requests</span><?php if ($admin_payout_unread > 0): ?><span class="notif-dot"></span><?php endif; ?></a>
            <a href="transaction_history.php" class="active"><i class="fas fa-history"></i> <span>Transactions</span></a>
        </div>

        <div class="main-content">
            <div class="header-title">Sales And Exchange Activity</div>

            <div class="stats-grid">
                <div class="card-link">
                    <div class="stat-card">
                        <div class="stat-info">
                            <h4>Total Transactions (Filtered)</h4>
                            <p id="transactionsCountValue"><?php echo number_format(count($sales_data)); ?></p>
                        </div>
                        <i class="fas fa-receipt fa-2x" style="color: #ddd;"></i>
                    </div>
                </div>
                <div class="card-link">
                    <div class="stat-card" style="border-left-color: #28a745;">
                        <div class="stat-info">
                            <h4>Net Sales (Filtered)</h4>
                            <p id="transactionsTotalSalesValue" style="color: #28a745;">₦<?php echo number_format($total_sales, 2); ?></p>
                        </div>
                        <i class="fas fa-coins fa-2x" style="color: #d4edda;"></i>
                    </div>
                </div>
                <div class="card-link">
                    <div class="stat-card" style="border-left-color: #2b7a0b;">
                        <div class="stat-info">
                            <h4>Net Profit (Filtered)</h4>
                            <p id="transactionsTotalProfitValue" style="color: #2b7a0b;">₦<?php echo number_format($total_profit, 2); ?></p>
                        </div>
                        <i class="fas fa-chart-line fa-2x" style="color: #d7f5df;"></i>
                    </div>
                </div>
                <div class="card-link">
                    <div class="stat-card" style="border-left-color: #0f766e;">
                        <div class="stat-info">
                            <h4>Today's Net Profit</h4>
                            <p id="transactionsTodayProfitValue" style="color: #0f766e;">₦<?php echo number_format($today_profit, 2); ?></p>
                        </div>
                        <i class="fas fa-calendar-day fa-2x" style="color: #d1fae5;"></i>
                    </div>
                </div>
            </div>

            <form method="GET" class="filter-bar">
                <div style="flex:1;">
                    <input type="text" name="search" class="filter-input" placeholder="Receipt #, Product, Attendant, or Exchange" value="<?php echo htmlspecialchars($search_raw, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="date-range">
                    <input type="date" name="start_date" class="filter-input" value="<?php echo htmlspecialchars($start_date, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="date" name="end_date" class="filter-input" value="<?php echo htmlspecialchars($end_date, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
                <?php if ($search_raw !== '' || $start_date !== '' || $end_date !== ''): ?>
                    <a href="transaction_history.php" class="btn-reset">Reset</a>
                <?php endif; ?>
                <a href="<?php echo htmlspecialchars($pdf_report_link, ENT_QUOTES, 'UTF-8'); ?>" class="btn-reset" style="background:#1e3c72;color:#fff;">
                    <i class="fas fa-eye"></i> View Report
                </a>
                <a href="<?php echo htmlspecialchars($print_report_link, ENT_QUOTES, 'UTF-8'); ?>" class="btn-reset" style="background:#6c757d;color:#fff;">
                    <i class="fas fa-print"></i> Print Report
                </a>
            </form>

            <div class="table-container" id="transactionHistoryContent">
                <?php if (count($sales_data) > 0): ?>
                    <div class="table-wrap">
                        <table class="styled-table">
                            <thead>
                                <tr>
                                    <th>Receipt #</th>
                                    <th>Attendant</th>
                                    <th>Date / Time</th>
                                    <th>Sale Amount</th>
                                    <th>Exchange Adjustment</th>
                                    <th>Net Value</th>
                                    <th>Net Profit</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="transactionHistoryTableBody">
                                <?php foreach ($sales_data as $sale): ?>
                                    <?php $amount_class = (float)$sale['exchange_amount_delta'] < 0 ? 'color:#c0392b;' : 'color:#15803d;'; ?>
                                    <?php $profit_class = (float)$sale['net_profit'] < 0 ? 'color:#c0392b;' : 'color:#2b7a0b;'; ?>
                                    <tr>
                                        <td>#<?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                        <td><?php echo htmlspecialchars(ucfirst($sale['username']), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($sale['created_at'])); ?></td>
                                        <td class="amount-badge">₦<?php echo number_format($sale['total_amount'], 2); ?></td>
                                        <td style="<?php echo $amount_class; ?> font-weight:600;">
                                            <?php echo format_signed_naira((float)$sale['exchange_amount_delta'], 2); ?>
                                            <?php if ((int)$sale['exchange_count'] > 0): ?>
                                                <div style="font-size:11px; color:#667085; margin-top:4px;"><?php echo (int)$sale['exchange_count']; ?> exchange<?php echo (int)$sale['exchange_count'] === 1 ? '' : 's'; ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="amount-badge">₦<?php echo number_format((float)$sale['net_amount'], 2); ?></td>
                                        <td style="<?php echo $profit_class; ?> font-weight:600;">
                                            ₦<?php echo number_format((float)$sale['net_profit'], 2); ?>
                                            <?php if ((float)$sale['exchange_profit_delta'] != 0.0): ?>
                                                <div style="font-size:11px; color:#667085; margin-top:4px;">Exchange adjustment <?php echo format_signed_naira((float)$sale['exchange_profit_delta'], 2); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn-view" onclick="viewReceipt(<?php echo (int)$sale['id']; ?>, '<?php echo $sale['total_amount']; ?>', '<?php echo $sale['net_amount']; ?>')">View</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="transaction-mobile-list" id="transactionHistoryMobileList">
                        <?php foreach ($sales_data as $sale): ?>
                            <?php $amount_class = (float)$sale['exchange_amount_delta'] < 0 ? 'color:#c0392b;' : 'color:#15803d;'; ?>
                            <?php $profit_class = (float)$sale['net_profit'] < 0 ? 'color:#c0392b;' : 'color:#2b7a0b;'; ?>
                            <article class="transaction-mobile-card">
                                <div class="transaction-mobile-head">
                                    <div>
                                        <h4 class="transaction-mobile-title">Receipt #<?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?></h4>
                                        <p class="transaction-mobile-subtitle"><?php echo htmlspecialchars(ucfirst($sale['username']), ENT_QUOTES, 'UTF-8'); ?> · <?php echo date('M d, Y h:i A', strtotime($sale['created_at'])); ?></p>
                                    </div>
                                    <div class="transaction-mobile-callout">
                                        <span>Net Value</span>
                                        <strong>₦<?php echo number_format((float)$sale['net_amount'], 0); ?></strong>
                                    </div>
                                </div>
                                <div class="transaction-mobile-grid">
                                    <div class="transaction-mobile-item">
                                        <span>Sale Amount</span>
                                        <strong>₦<?php echo number_format((float)$sale['total_amount'], 2); ?></strong>
                                    </div>
                                    <div class="transaction-mobile-item">
                                        <span>Attendant</span>
                                        <strong><?php echo htmlspecialchars(ucfirst($sale['username']), ENT_QUOTES, 'UTF-8'); ?></strong>
                                    </div>
                                    <div class="transaction-mobile-item">
                                        <span>Exchange Adjustment</span>
                                        <strong style="<?php echo $amount_class; ?>"><?php echo format_signed_naira((float)$sale['exchange_amount_delta'], 2); ?></strong>
                                    </div>
                                    <div class="transaction-mobile-item">
                                        <span>Net Profit</span>
                                        <strong style="<?php echo $profit_class; ?>">₦<?php echo number_format((float)$sale['net_profit'], 2); ?></strong>
                                    </div>
                                </div>
                                <div class="transaction-mobile-actions">
                                    <button class="btn-view" onclick="viewReceipt(<?php echo (int)$sale['id']; ?>, '<?php echo $sale['total_amount']; ?>', '<?php echo $sale['net_amount']; ?>')">View Receipt</button>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div id="transactionHistoryEmptyState" style="text-align: center; padding: 40px; color: #999;">
                        <i class="fas fa-search" style="font-size: 40px; margin-bottom: 10px;"></i>
                        <p>No records found matching your search.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('collapsed'); }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function renderTransactionsContent(payload) {
            if (!payload || payload.ok !== true) return;

            const summary = payload.summary || {};
            const sales = Array.isArray(payload.sales) ? payload.sales : [];
            const countEl = document.getElementById('transactionsCountValue');
            const totalSalesEl = document.getElementById('transactionsTotalSalesValue');
            const totalProfitEl = document.getElementById('transactionsTotalProfitValue');
            const todayProfitEl = document.getElementById('transactionsTodayProfitValue');
            const contentEl = document.getElementById('transactionHistoryContent');

            if (countEl) countEl.textContent = summary.transactions_count_display || '0';
            if (totalSalesEl) totalSalesEl.textContent = `₦${summary.total_sales_display || '0.00'}`;
            if (totalProfitEl) totalProfitEl.textContent = `₦${summary.total_profit_display || '0.00'}`;
            if (todayProfitEl) todayProfitEl.textContent = `₦${summary.today_profit_display || '0.00'}`;
            if (!contentEl) return;

            if (sales.length === 0) {
                contentEl.innerHTML = `
                    <div id="transactionHistoryEmptyState" style="text-align: center; padding: 40px; color: #999;">
                        <i class="fas fa-search" style="font-size: 40px; margin-bottom: 10px;"></i>
                        <p>No records found matching your search.</p>
                    </div>
                `;
                return;
            }

            const desktopRows = sales.map((sale) => `
                <tr>
                    <td>#${sale.receipt}</td>
                    <td>${escapeHtml(sale.username)}</td>
                    <td>${escapeHtml(sale.datetime_display)}</td>
                    <td class="amount-badge">₦${escapeHtml(sale.total_amount_display)}</td>
                    <td style="color:${Number(sale.exchange_amount_delta || 0) < 0 ? '#c0392b' : '#15803d'}; font-weight:600;">
                        ${escapeHtml(sale.exchange_amount_delta_display)}
                        ${Number(sale.exchange_count || 0) > 0 ? `<div style="font-size:11px; color:#667085; margin-top:4px;">${escapeHtml(String(sale.exchange_count))} ${Number(sale.exchange_count) === 1 ? 'exchange' : 'exchanges'}</div>` : ''}
                    </td>
                    <td class="amount-badge">₦${escapeHtml(sale.net_amount_display)}</td>
                    <td style="color:${Number(sale.net_profit || 0) < 0 ? '#c0392b' : '#2b7a0b'}; font-weight:600;">
                        ₦${escapeHtml(sale.net_profit_display)}
                        ${Number(sale.exchange_profit_delta || 0) !== 0 ? `<div style="font-size:11px; color:#667085; margin-top:4px;">Exchange adjustment ${escapeHtml(sale.exchange_profit_delta_display)}</div>` : ''}
                    </td>
                    <td>
                        <button class="btn-view" onclick="viewReceipt(${sale.id}, '${sale.total_amount}', '${sale.net_amount}')">View</button>
                    </td>
                </tr>
            `).join('');

            const mobileCards = sales.map((sale) => `
                <article class="transaction-mobile-card">
                    <div class="transaction-mobile-head">
                        <div>
                            <h4 class="transaction-mobile-title">Receipt #${sale.receipt}</h4>
                            <p class="transaction-mobile-subtitle">${escapeHtml(sale.username)} · ${escapeHtml(sale.datetime_display)}</p>
                        </div>
                        <div class="transaction-mobile-callout">
                            <span>Net Value</span>
                            <strong>₦${escapeHtml(sale.net_amount_whole_display)}</strong>
                        </div>
                    </div>
                    <div class="transaction-mobile-grid">
                        <div class="transaction-mobile-item">
                            <span>Sale Amount</span>
                            <strong>₦${escapeHtml(sale.total_amount_display)}</strong>
                        </div>
                        <div class="transaction-mobile-item">
                            <span>Attendant</span>
                            <strong>${escapeHtml(sale.username)}</strong>
                        </div>
                        <div class="transaction-mobile-item">
                            <span>Exchange Adjustment</span>
                            <strong style="color:${Number(sale.exchange_amount_delta || 0) < 0 ? '#c0392b' : '#15803d'};">${escapeHtml(sale.exchange_amount_delta_display)}</strong>
                        </div>
                        <div class="transaction-mobile-item">
                            <span>Net Profit</span>
                            <strong style="color:${Number(sale.net_profit || 0) < 0 ? '#c0392b' : '#2b7a0b'};">₦${escapeHtml(sale.net_profit_display)}</strong>
                        </div>
                    </div>
                    <div class="transaction-mobile-actions">
                        <button class="btn-view" onclick="viewReceipt(${sale.id}, '${sale.total_amount}', '${sale.net_amount}')">View Receipt</button>
                    </div>
                </article>
            `).join('');

            contentEl.innerHTML = `
                <div class="table-wrap">
                    <table class="styled-table">
                        <thead>
                            <tr>
                                <th>Receipt #</th>
                                <th>Attendant</th>
                                <th>Date / Time</th>
                                <th>Sale Amount</th>
                                <th>Exchange Adjustment</th>
                                <th>Net Value</th>
                                <th>Net Profit</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="transactionHistoryTableBody">${desktopRows}</tbody>
                    </table>
                </div>
                <div class="transaction-mobile-list" id="transactionHistoryMobileList">${mobileCards}</div>
            `;
        }

        let transactionHistoryRefreshBusy = false;
        async function refreshTransactionHistoryLive() {
            if (transactionHistoryRefreshBusy) return;
            transactionHistoryRefreshBusy = true;

            try {
                const url = new URL(window.location.href);
                url.searchParams.set('live_data', '1');
                const response = await fetch(url.toString(), { credentials: 'same-origin', cache: 'no-store' });
                if (!response.ok) return;
                const payload = await response.json();
                renderTransactionsContent(payload);
            } catch (error) {
                // Ignore transient refresh failures and retry on the next poll cycle.
            } finally {
                transactionHistoryRefreshBusy = false;
            }
        }

        function viewReceipt(saleId, total, netTotal) {
            Swal.fire({ title: 'Loading...', didOpen: () => { Swal.showLoading() } });
            fetch('transaction_history.php?get_receipt_items=' + saleId)
                .then(response => response.json())
                .then(data => {
                    const items = Array.isArray(data.items) ? data.items : [];
                    const exchanges = Array.isArray(data.exchanges) ? data.exchanges : [];
                    const computedNetTotal = Number(total) + exchanges.reduce((sum, exchange) => sum + Number(exchange.amount_difference || 0), 0);
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
                            const profit = Number(exchange.profit_delta || 0);
                            const amountSign = amount > 0 ? '+' : (amount < 0 ? '-' : '');
                            const profitSign = profit > 0 ? '+' : (profit < 0 ? '-' : '');
                            const amountColor = amount < 0 ? '#c0392b' : '#15803d';
                            const profitColor = profit < 0 ? '#c0392b' : '#2b7a0b';
                            const adjustmentLabel = escapeHtml(String(exchange.adjustment_type || '').replace(/_/g, ' '));
                            const exchangeNote = exchange.note ? escapeHtml(exchange.note) : '';
                            html += `
                                <div style="border:1px solid #e5e7eb; border-radius:10px; padding:10px; margin-top:8px;">
                                    <div style="font-weight:600;">Exchange #${String(exchange.id).padStart(6, '0')}</div>
                                    <div style="font-size:12px; color:#667085; margin-top:4px;">${adjustmentLabel} · ${new Date(exchange.created_at).toLocaleString()}</div>
                                    <div style="margin-top:6px; color:${amountColor}; font-weight:700;">Amount Adjustment: ${amountSign}₦${Math.abs(amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                                    <div style="margin-top:4px; color:${profitColor}; font-weight:700;">Profit Adjustment: ${profitSign}₦${Math.abs(profit).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                                    ${exchangeNote ? `<div style="margin-top:6px; font-size:12px; color:#667085;">Note: ${exchangeNote}</div>` : ''}
                                </div>
                            `;
                        });
                        html += `</div>`;
                    }

                    html += `<div class="receipt-total"><span>NET TOTAL</span><span>₦${Number(Number.isFinite(Number(netTotal)) ? netTotal : computedNetTotal).toLocaleString()}</span></div></div>`;

                    Swal.fire({
                        title: 'Receipt #' + String(saleId).padStart(6, '0'),
                        html: html,
                        width: 400,
                        confirmButtonText: '<i class="fas fa-print"></i> Print / Download',
                        confirmButtonColor: '#1e3c72',
                        showCancelButton: true,
                        cancelButtonText: 'Close',
                        cancelButtonColor: '#6c757d'
                    }).then((result) => {
                        if (result.isConfirmed) { window.open('../sales/print_receipt.php?id=' + saleId, 'Receipt', 'width=400,height=600'); }
                    });
                });
        }

        <?php if ($focus_sale_id > 0): ?>
            window.addEventListener('load', function() {
                viewReceipt(<?php echo $focus_sale_id; ?>, '<?php echo $focus_total; ?>');
            });
        <?php endif; ?>

        document.addEventListener('galadawa:live-update', function(event) {
            const payload = event.detail || {};
            if (!payload.admin) return;
            refreshTransactionHistoryLive();
        });
    </script>
</body>
</html>
