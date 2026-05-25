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

function hold_status_class($status) {
    if ($status === 'completed') return 'status-completed';
    if ($status === 'released') return 'status-released';
    if ($status === 'expired') return 'status-expired';
    return 'status-active';
}

function hold_status_label($status) {
    if ($status === 'completed') return 'Completed';
    if ($status === 'released') return 'Released';
    if ($status === 'expired') return 'Expired';
    return 'Active';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hold_action'], $_POST['hold_id'])) {
    $hold_id = (int)$_POST['hold_id'];
    $hold_action = (string)$_POST['hold_action'];
    $hold = get_hold_order($conn, $hold_id);

    if (!$hold) {
        header("Location: holding_orders.php?message=" . urlencode('Hold order not found.') . "&type=error");
        exit();
    }

    if (!user_can_manage_hold($hold, $user_id)) {
        header("Location: holding_orders.php?message=" . urlencode('You can only change holds you created yourself.') . "&type=error");
        exit();
    }

    if ($hold_action === 'extend') {
        if (($hold['status'] ?? '') !== 'active') {
            header("Location: holding_orders.php?message=" . urlencode('Only active holds can be extended.') . "&type=warning");
            exit();
        }

        $minutes = max(1, (int)($hold['hold_minutes'] ?? get_hold_duration_minutes()));
        $conn->query("UPDATE held_orders SET release_at = DATE_ADD(release_at, INTERVAL $minutes MINUTE) WHERE id = '$hold_id' AND status = 'active'");
        header("Location: holding_orders.php?message=" . urlencode('Hold time extended.') . "&type=success");
        exit();
    }

    $items = get_hold_items($conn, $hold_id);
    if (empty($items)) {
        header("Location: holding_orders.php?message=" . urlencode('This hold has no items to process.') . "&type=error");
        exit();
    }

    if ($hold_action === 'release') {
        if (($hold['status'] ?? '') !== 'active') {
            header("Location: holding_orders.php?message=" . urlencode('Only active holds can be released.') . "&type=warning");
            exit();
        }

        $conn->begin_transaction();

        try {
            foreach ($items as $item) {
                $image_id = $item['product_image_id'] !== null ? (int)$item['product_image_id'] : null;
                if (!release_held_inventory($conn, (int)$item['product_id'], (float)$item['quantity'], $image_id)) {
                    throw new RuntimeException('Unable to restore held stock.');
                }
            }

            $conn->query("UPDATE held_orders SET status = 'released', released_at = NOW() WHERE id = '$hold_id' AND status = 'active'");
            if ($conn->affected_rows !== 1) {
                throw new RuntimeException('Unable to release this hold.');
            }

            $conn->commit();
            header("Location: holding_orders.php?message=" . urlencode('Hold released back to stock.') . "&type=success");
            exit();
        } catch (Throwable $e) {
            $conn->rollback();
            header("Location: holding_orders.php?message=" . urlencode($e->getMessage()) . "&type=error");
            exit();
        }
    }

    if ($hold_action === 'complete') {
        if (($hold['status'] ?? '') !== 'active') {
            header("Location: holding_orders.php?message=" . urlencode('Only active holds can be completed.') . "&type=warning");
            exit();
        }

        $customer_name_sql = $conn->real_escape_string((string)($hold['customer_name'] ?? 'Walk-in Customer'));
        $total_amount = (float)($hold['total_amount'] ?? 0);

        $conn->begin_transaction();

        try {
            $conn->query("INSERT INTO sales (user_id, customer_name, total_amount) VALUES ('$user_id', '$customer_name_sql', '$total_amount')");
            $sale_id = (int)$conn->insert_id;
            if ($sale_id <= 0) {
                throw new RuntimeException('Unable to convert hold to sale.');
            }

            foreach ($items as $item) {
                $product_id = (int)$item['product_id'];
                $image_id = $item['product_image_id'] !== null ? (int)$item['product_image_id'] : null;
                $quantity = (float)$item['quantity'];
                $price = (float)$item['price'];
                $subtotal = (float)$item['subtotal'];
                $buy_price = isset($item['buy_price_at_hold']) ? (float)$item['buy_price_at_hold'] : 0;
                $image_sql = $image_id ? "'$image_id'" : "NULL";

                if (!finalize_held_sale_inventory($conn, $product_id, $image_id)) {
                    throw new RuntimeException('Unable to finalize held stock for this sale.');
                }

                $conn->query("INSERT INTO sale_items (sale_id, product_id, product_image_id, quantity, price, buy_price_at_sale, subtotal) VALUES ('$sale_id', '$product_id', $image_sql, '$quantity', '$price', '$buy_price', '$subtotal')");
                if ($conn->affected_rows !== 1) {
                    throw new RuntimeException('Unable to save held sale item.');
                }
            }

            $conn->query("UPDATE held_orders SET status = 'completed', completed_at = NOW(), completed_sale_id = '$sale_id' WHERE id = '$hold_id' AND status = 'active'");
            if ($conn->affected_rows !== 1) {
                throw new RuntimeException('Unable to mark hold as completed.');
            }

            $conn->commit();
            header("Location: holding_orders.php?message=" . urlencode('Hold converted to sale successfully.') . "&type=success&receipt=$sale_id");
            exit();
        } catch (Throwable $e) {
            $conn->rollback();
            header("Location: holding_orders.php?message=" . urlencode($e->getMessage()) . "&type=error");
            exit();
        }
    }
}

$summary_counts = ['active' => 0, 'completed' => 0, 'released' => 0, 'expired' => 0];
$summary_res = $conn->query("SELECT status, COUNT(*) AS c FROM held_orders GROUP BY status");
if ($summary_res) {
    while ($row = $summary_res->fetch_assoc()) {
        $status = (string)($row['status'] ?? '');
        if (isset($summary_counts[$status])) {
            $summary_counts[$status] = (int)$row['c'];
        }
    }
}

$search = trim((string)($_GET['search'] ?? ''));
$status_filter = trim((string)($_GET['status'] ?? 'all'));
$allowed_filters = ['all', 'active', 'completed', 'released', 'expired'];
if (!in_array($status_filter, $allowed_filters, true)) {
    $status_filter = 'all';
}

$where = ["1=1"];
if ($search !== '') {
    $search_sql = $conn->real_escape_string($search);
    $search_int = (int)$search;
    $where[] = "(h.id = '$search_int' OR h.customer_name LIKE '%$search_sql%' OR h.note LIKE '%$search_sql%' OR u.username LIKE '%$search_sql%')";
}
if ($status_filter !== 'all') {
    $status_sql = $conn->real_escape_string($status_filter);
    $where[] = "h.status = '$status_sql'";
}

$holds = [];
$holds_res = $conn->query("
    SELECT h.*, u.username
    FROM held_orders h
    LEFT JOIN users u ON u.id = h.user_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY CASE WHEN h.status = 'active' THEN 0 ELSE 1 END, h.created_at DESC
");

if ($holds_res) {
    while ($row = $holds_res->fetch_assoc()) {
        $row['items'] = get_hold_items($conn, (int)$row['id']);
        $holds[] = $row;
    }
}

$flash_message = trim((string)($_GET['message'] ?? ''));
$flash_type = trim((string)($_GET['type'] ?? 'success'));
$flash_receipt = isset($_GET['receipt']) ? (int)$_GET['receipt'] : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Holding Orders | Galadawa</title>
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
        .page-shell { max-width: 1180px; margin: 0 auto; }
        .page-intro { background: white; border-radius: 20px; padding: 22px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08); margin-bottom: 18px; }
        .page-intro p { margin: 8px 0 0; color: #667085; line-height: 1.6; }
        .summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-bottom: 18px; }
        .summary-card { background: white; border-radius: 18px; padding: 18px; box-shadow: 0 10px 24px rgba(15, 23, 42, 0.07); }
        .summary-card span { display: block; font-size: 12px; text-transform: uppercase; letter-spacing: 0.06em; color: #667085; }
        .summary-card strong { display: block; margin-top: 8px; font-size: 28px; color: #1e3c72; }
        .filter-bar { background: white; border-radius: 18px; padding: 14px; box-shadow: 0 10px 24px rgba(15, 23, 42, 0.07); display: flex; gap: 10px; align-items: center; margin-bottom: 18px; }
        .filter-bar input, .filter-bar select { flex: 1; min-width: 0; border: 1px solid #d0d5dd; border-radius: 12px; padding: 12px 14px; font-size: 14px; }
        .filter-btn { border: none; border-radius: 12px; background: #1e3c72; color: white; padding: 12px 16px; font-weight: 600; cursor: pointer; }
        .holds-grid { display: grid; gap: 16px; }
        .hold-card { background: white; border-radius: 20px; padding: 20px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08); }
        .hold-head { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; margin-bottom: 14px; }
        .hold-title { margin: 0; font-size: 20px; color: #1e3c72; }
        .hold-subtitle { margin: 6px 0 0; color: #667085; font-size: 14px; }
        .hold-total { text-align: right; }
        .hold-total span { display: block; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: #667085; }
        .hold-total strong { display: block; margin-top: 6px; font-size: 30px; color: #1e3c72; }
        .status-badge { display: inline-flex; align-items: center; justify-content: center; padding: 6px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .status-active { background: #e6f4ea; color: #137333; }
        .status-completed { background: #e8f1ff; color: #1e4ea7; }
        .status-released { background: #fff3cd; color: #8a6116; }
        .status-expired { background: #fdecec; color: #c0392b; }
        .hold-meta { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 16px; }
        .meta-box { background: #f8fafc; border: 1px solid #edf2f7; border-radius: 14px; padding: 12px; }
        .meta-box span { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #667085; }
        .meta-box strong { display: block; margin-top: 4px; font-size: 14px; color: #24303d; word-break: break-word; }
        .hold-items { display: grid; gap: 10px; margin-bottom: 16px; }
        .hold-item { display: flex; gap: 12px; align-items: center; background: #f8fafc; border: 1px solid #edf2f7; border-radius: 14px; padding: 12px; }
        .hold-item-thumb { width: 58px; height: 58px; border-radius: 12px; overflow: hidden; flex-shrink: 0; border: 1px solid #e5e7eb; background: #fff; }
        .hold-item-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .hold-item-title { margin: 0; font-size: 15px; color: #24303d; font-weight: 700; }
        .hold-item-copy { margin: 4px 0 0; font-size: 13px; color: #667085; }
        .hold-item-price { margin-left: auto; text-align: right; font-size: 14px; font-weight: 700; color: #24303d; white-space: nowrap; }
        .hold-actions { display: flex; flex-wrap: wrap; gap: 10px; }
        .action-btn { border: none; border-radius: 12px; padding: 12px 14px; font-weight: 600; cursor: pointer; }
        .btn-complete { background: #28a745; color: white; }
        .btn-release { background: #e74c3c; color: white; }
        .btn-extend { background: #1e3c72; color: white; }
        .btn-receipt { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 14px; border-radius: 12px; text-decoration: none; background: #eef4ff; color: #1e3c72; font-weight: 700; }
        .hold-readonly-note { margin-top: 14px; padding: 12px 14px; border-radius: 12px; background: #fff8e6; color: #8a6116; border: 1px solid #f4e1a1; font-size: 13px; font-weight: 600; }
        .empty-state { background: white; border-radius: 20px; padding: 40px 20px; text-align: center; color: #98a2b3; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08); }

        @media (max-width: 980px) {
            .summary-grid,
            .hold-meta { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .filter-bar { flex-direction: column; align-items: stretch; }
        }

        @media (max-width: 640px) {
            .main-content { padding: 16px; }
            .summary-grid,
            .hold-meta { grid-template-columns: 1fr; }
            .hold-head { flex-direction: column; }
            .hold-total { text-align: left; }
            .hold-item { align-items: flex-start; }
            .hold-item-price { margin-left: 0; text-align: left; }
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
        <a href="holding_orders.php" class="active"><i class="fas fa-boxes-stacked"></i> <span>Holding</span><?php if ($active_hold_count > 0): ?><span class="notif-dot"></span><span class="live-notif-count" style="margin-left:6px; font-size:11px; color:#fff; opacity:0.8;">(<?php echo $active_hold_count; ?>)</span><?php endif; ?></a>
        <a href="exchange.php"><i class="fas fa-right-left"></i> <span>Exchange</span></a>
        <a href="inventory_view.php"><i class="fas fa-box"></i> <span>View Inventory</span></a>
        <a href="my_history.php"><i class="fas fa-history"></i> <span>My History</span></a>
        <a href="profile.php"><i class="fas fa-user-cog"></i> <span>My Profile</span></a>
        <a href="payouts.php"><i class="fas fa-wallet"></i> <span>Payouts</span><?php if ($payout_unread > 0): ?><span class="notif-dot"></span><?php endif; ?></a>
    </div>

    <div class="main-content">
        <div class="page-shell">
            <div class="page-intro">
                <h2 style="margin:0;">Holding Orders</h2>
            </div>

            <div class="summary-grid">
                <div class="summary-card"><span>Active</span><strong><?php echo number_format($summary_counts['active']); ?></strong></div>
                <div class="summary-card"><span>Completed</span><strong><?php echo number_format($summary_counts['completed']); ?></strong></div>
                <div class="summary-card"><span>Released</span><strong><?php echo number_format($summary_counts['released']); ?></strong></div>
                <div class="summary-card"><span>Expired</span><strong><?php echo number_format($summary_counts['expired']); ?></strong></div>
            </div>

            <form method="GET" class="filter-bar">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search by hold ID, customer, note, or attendant">
                <select name="status">
                    <?php foreach ($allowed_filters as $filter): ?>
                        <option value="<?php echo htmlspecialchars($filter, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $status_filter === $filter ? 'selected' : ''; ?>>
                            <?php echo ucfirst($filter); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="filter-btn">Filter</button>
            </form>

            <div class="holds-grid">
                <?php if (count($holds) > 0): ?>
                    <?php foreach ($holds as $hold): ?>
                        <?php $can_manage_hold = user_can_manage_hold($hold, $user_id); ?>
                        <article class="hold-card">
                            <div class="hold-head">
                                <div>
                                    <h3 class="hold-title">Hold #<?php echo str_pad((int)$hold['id'], 6, '0', STR_PAD_LEFT); ?></h3>
                                    <p class="hold-subtitle"><?php echo htmlspecialchars((string)($hold['customer_name'] ?? 'Walk-in Customer'), ENT_QUOTES, 'UTF-8'); ?><?php if (!empty($hold['username'])): ?> · Saved by @<?php echo htmlspecialchars((string)$hold['username'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?></p>
                                </div>
                                <div class="hold-total">
                                    <span>Total</span>
                                    <strong>₦<?php echo number_format((float)$hold['total_amount'], 2); ?></strong>
                                </div>
                            </div>

                            <div class="hold-meta">
                                <div class="meta-box">
                                    <span>Status</span>
                                    <strong><span class="status-badge <?php echo hold_status_class((string)$hold['status']); ?>"><?php echo hold_status_label((string)$hold['status']); ?></span></strong>
                                </div>
                                <div class="meta-box">
                                    <span>Release Time</span>
                                    <strong><?php echo date('M d, Y h:i A', strtotime((string)$hold['release_at'])); ?></strong>
                                </div>
                                <div class="meta-box">
                                    <span>Time Left</span>
                                    <strong class="js-hold-countdown" data-release-ts="<?php echo (int)strtotime((string)$hold['release_at']); ?>" data-status="<?php echo htmlspecialchars((string)$hold['status'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(format_hold_time_left_label((string)$hold['release_at'], (string)$hold['status']), ENT_QUOTES, 'UTF-8'); ?></strong>
                                </div>
                                <div class="meta-box">
                                    <span>Note</span>
                                    <strong><?php echo !empty($hold['note']) ? htmlspecialchars((string)$hold['note'], ENT_QUOTES, 'UTF-8') : 'No note'; ?></strong>
                                </div>
                            </div>

                            <div class="hold-items">
                                <?php foreach ($hold['items'] as $item): ?>
                                    <div class="hold-item">
                                        <div class="hold-item-thumb">
                                            <img src="<?php echo !empty($item['image_preview']) ? htmlspecialchars((string)$item['image_preview'], ENT_QUOTES, 'UTF-8') : '../img/logo.png'; ?>" alt="">
                                        </div>
                                        <div>
                                            <p class="hold-item-title"><?php echo htmlspecialchars((string)$item['product_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p class="hold-item-copy"><?php echo (float)$item['quantity']; ?> x ₦<?php echo number_format((float)$item['price'], 2); ?></p>
                                        </div>
                                        <div class="hold-item-price">₦<?php echo number_format((float)$item['subtotal'], 2); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="hold-actions">
                                <?php if (($hold['status'] ?? '') === 'active' && $can_manage_hold): ?>
                                    <form method="POST">
                                        <input type="hidden" name="hold_id" value="<?php echo (int)$hold['id']; ?>">
                                        <input type="hidden" name="hold_action" value="complete">
                                        <button type="submit" class="action-btn btn-complete">Complete Sale</button>
                                    </form>
                                    <form method="POST">
                                        <input type="hidden" name="hold_id" value="<?php echo (int)$hold['id']; ?>">
                                        <input type="hidden" name="hold_action" value="extend">
                                        <button type="submit" class="action-btn btn-extend">Extend <?php echo htmlspecialchars(get_hold_duration_label(), ENT_QUOTES, 'UTF-8'); ?></button>
                                    </form>
                                    <form method="POST" onsubmit="return confirm('Release this hold back to stock?');">
                                        <input type="hidden" name="hold_id" value="<?php echo (int)$hold['id']; ?>">
                                        <input type="hidden" name="hold_action" value="release">
                                        <button type="submit" class="action-btn btn-release">Release Hold</button>
                                    </form>
                                <?php endif; ?>

                                <?php if (!empty($hold['completed_sale_id'])): ?>
                                    <a class="btn-receipt" href="print_receipt.php?id=<?php echo (int)$hold['completed_sale_id']; ?>" target="_blank" rel="noopener">
                                        <i class="fas fa-print"></i> Receipt
                                    </a>
                                <?php endif; ?>
                            </div>

                            <?php if (($hold['status'] ?? '') === 'active' && !$can_manage_hold): ?>
                                <div class="hold-readonly-note">
                                    Only @<?php echo htmlspecialchars((string)($hold['username'] ?? 'owner'), ENT_QUOTES, 'UTF-8'); ?> can change this hold.
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-boxes-stacked" style="font-size:40px; margin-bottom:10px;"></i>
                        <p style="margin:0;">No holding orders found for this filter.</p>
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

        function formatHoldCountdown(secondsLeft) {
            if (secondsLeft <= 0) return 'Releasing now';
            const hours = Math.floor(secondsLeft / 3600);
            const minutes = Math.floor((secondsLeft % 3600) / 60);
            const seconds = Math.floor(secondsLeft % 60);
            if (hours > 0) {
                return `${hours}h ${String(minutes).padStart(2, '0')}m ${String(seconds).padStart(2, '0')}s left`;
            }
            if (minutes > 0) {
                return `${minutes}m ${String(seconds).padStart(2, '0')}s left`;
            }
            return `${Math.max(1, seconds)}s left`;
        }

        function updateHoldCountdowns() {
            const now = Math.floor(Date.now() / 1000);
            document.querySelectorAll('.js-hold-countdown').forEach((node) => {
                const status = String(node.dataset.status || '');
                if (status !== 'active') return;
                const releaseTs = Number(node.dataset.releaseTs || 0);
                if (!Number.isFinite(releaseTs) || releaseTs <= 0) return;
                const secondsLeft = releaseTs - now;
                node.textContent = formatHoldCountdown(secondsLeft);
                if (secondsLeft <= 0) {
                    node.dataset.expired = '1';
                }
            });
        }

        updateHoldCountdowns();
        setInterval(updateHoldCountdowns, 1000);

        let activeHoldCount = <?php echo (int)$summary_counts['active']; ?>;
        document.addEventListener('galadawa:live-update', function (event) {
            const payload = event.detail || {};
            const nextCount = payload.sales ? Number(payload.sales.active_hold_count || 0) : activeHoldCount;
            activeHoldCount = nextCount;
        });

    <?php if ($flash_message !== ''): ?>
    if (window.showToast) {
        showToast(<?php echo json_encode($flash_message); ?>, { type: <?php echo json_encode($flash_type); ?>, duration: 2600 });
    }
    <?php endif; ?>

    <?php if ($flash_receipt > 0): ?>
    setTimeout(function() {
        window.open('print_receipt.php?id=<?php echo $flash_receipt; ?>', 'Receipt', 'width=420,height=640');
    }, 300);
    <?php endif; ?>
</script>
</body>
</html>
