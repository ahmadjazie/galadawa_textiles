<?php
session_start();
include '../includes/db_connect.php';
include '../includes/payouts.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }

$user_id = $_SESSION['user_id'];
ensure_payout_table($conn);

// Mark user notifications as read when opening page
$conn->query("UPDATE payout_requests SET user_unread = 0 WHERE user_id = '$user_id' AND user_unread = 1");

$commission = get_commission_totals($conn, $user_id, 0.05);

$payout_message = "";
$payout_message_type = "success";
// Search filter
$search = "";
if (isset($_GET['search']) && $_GET['search'] !== '') {
    $search = $conn->real_escape_string($_GET['search']);
}
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['request_payout'])) {
    $amount = (float)$_POST['payout_amount'];
    $account_details = $conn->real_escape_string(trim($_POST['account_details'] ?? ''));
    $commission = get_commission_totals($conn, $user_id, 0.05);

    if ($amount <= 0) {
        $payout_message = "Enter a valid amount.";
        $payout_message_type = "error";
    } elseif ($account_details === "") {
        $payout_message = "Account details are required.";
        $payout_message_type = "error";
    } elseif ($commission['available'] <= 0) {
        $payout_message = "You have no withdrawable commission yet. New commission will first clear your outstanding balance.";
        $payout_message_type = "warning";
    } elseif ($amount > $commission['available']) {
        $payout_message = "Requested amount exceeds your available commission.";
        $payout_message_type = "warning";
    } else {
        $conn->query("INSERT INTO payout_requests (user_id, amount, status, note, admin_unread, user_unread) VALUES ('$user_id', '$amount', 'pending', '$account_details', 1, 0)");
        $last_id = $conn->insert_id;
        if ($last_id) {
            $conn->query("UPDATE payout_requests SET admin_unread = 1 WHERE id = '$last_id'");
        }
        $payout_message = "Payout request submitted.";
        $payout_message_type = "success";
        $commission = get_commission_totals($conn, $user_id, 0.05);
    }
}

$payouts = [];
$where = "user_id = '$user_id'";
if ($search !== "") {
    $search_int = intval($search);
    $where .= " AND (id = '$search_int' OR status LIKE '%$search%')";
}
$res_payouts = $conn->query("SELECT * FROM payout_requests WHERE $where ORDER BY id DESC");
if ($res_payouts) { while ($row = $res_payouts->fetch_assoc()) { $payouts[] = $row; } }

$unread_count = get_user_unread_count($conn, $user_id);

if (isset($_GET['live_data'])) {
    $payout_rows = [];
    foreach ($payouts as $p) {
        $payout_rows[] = [
            'id' => (int)$p['id'],
            'receipt' => str_pad((int)$p['id'], 6, '0', STR_PAD_LEFT),
            'requested_display' => date('M d, h:i A', strtotime($p['requested_at'])),
            'requested_full_display' => date('M d, Y h:i A', strtotime($p['requested_at'])),
            'amount' => (float)$p['amount'],
            'amount_display' => number_format((float)$p['amount']),
            'amount_full_display' => number_format((float)$p['amount'], 2),
            'status' => (string)$p['status'],
            'note' => (string)($p['note'] ?? ''),
            'review_note' => (string)($p['review_note'] ?? ''),
        ];
    }

    header('Content-Type: application/json');
    echo json_encode([
        'ok' => true,
        'commission' => [
            'balance' => (float)$commission['balance'],
            'balance_display' => number_format((float)$commission['balance'], 2),
            'available' => (float)$commission['available'],
            'available_display' => number_format((float)$commission['available']),
            'total_commission_display' => number_format((float)$commission['total_commission']),
            'pending_out_display' => number_format((float)$commission['pending_out']),
            'outstanding' => (float)$commission['outstanding'],
            'outstanding_display' => number_format((float)$commission['outstanding'], 2),
        ],
        'payouts' => $payout_rows,
    ]);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payouts | Galadawa</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/toastify.min.css">
    <script src="../js/toastify.min.js"></script>
    <script src="../js/toast.js"></script>
    <style>
        body { font-family: 'Poppins', sans-serif; margin: 0; background-color: #f4f7f6; color: #333; }
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
        .main-content { flex: 1; padding: 30px; }
        .payout-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .payout-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px; }
        .payout-stat { background: #f8f9fa; border-radius: 10px; padding: 15px; }
        .payout-stat h4 { margin: 0; font-size: 14px; color: #777; }
        .payout-stat p { margin: 6px 0 0; font-size: 18px; font-weight: bold; }
        .amount-positive { color: #15803d; }
        .amount-negative { color: #c0392b; }
        .payout-form { display: grid; grid-template-columns: 1fr 1fr auto; gap: 10px; align-items: end; }
        .payout-form input { padding: 10px; border: 1px solid #ddd; border-radius: 6px; }
        .btn-request { padding: 10px 16px; background: #1e3c72; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .btn-request:disabled { opacity: 0.6; cursor: not-allowed; }
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge.pending { background: #fff3cd; color: #856404; }
        .badge.approved { background: #d4edda; color: #155724; }
        .badge.rejected { background: #f8d7da; color: #721c24; }
        .styled-table { width: 100%; border-collapse: collapse; }
        .styled-table thead tr { background-color: #f8f9fa; color: #333; text-align: left; }
        .styled-table th, .styled-table td { padding: 15px; border-bottom: 1px solid #eee; }
        .btn-view { padding: 6px 12px; background: #eef2f5; color: #1e3c72; border: 1px solid #1e3c72; border-radius: 5px; cursor: pointer; font-size: 12px; font-weight: 600; }
        .hint { font-size: 12px; color: #e74c3c; margin-top: 6px; display: none; }
        .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .styled-table { min-width: 860px; }
        .payout-search-form { margin-bottom:10px; display:flex; gap:10px; align-items:center; }
        .payout-mobile-list { display: none; }
        .payout-mobile-card {
            border: 1px solid #e7edf5;
            border-radius: 16px;
            padding: 14px;
            background: #fff;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
        }
        .payout-mobile-card + .payout-mobile-card { margin-top: 12px; }
        .payout-mobile-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
        .payout-mobile-title { margin: 0; font-size: 16px; color: #24303d; font-weight: 700; }
        .payout-mobile-subtitle { margin: 4px 0 0; color: #667085; font-size: 13px; }
        .payout-mobile-callout {
            min-width: 92px;
            padding: 10px 12px;
            border-radius: 14px;
            background: #eef4ff;
            text-align: right;
        }
        .payout-mobile-callout span {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #667085;
        }
        .payout-mobile-callout strong {
            display: block;
            margin-top: 4px;
            font-size: 18px;
            line-height: 1.1;
            color: #1e3c72;
        }
        .payout-mobile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 12px; }
        .payout-mobile-item {
            background: #f8fafc;
            border: 1px solid #edf2f7;
            border-radius: 12px;
            padding: 10px 12px;
        }
        .payout-mobile-item.full { grid-column: span 2; }
        .payout-mobile-item span {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #667085;
        }
        .payout-mobile-item strong {
            display: block;
            margin-top: 4px;
            font-size: 14px;
            color: #24303d;
            word-break: break-word;
        }
        .payout-mobile-actions { margin-top: 12px; }
        @media (max-width: 900px) {
            .payout-form { grid-template-columns: 1fr; }
            .payout-grid { grid-template-columns: 1fr; }
            .main-content { padding: 20px; }
            .payout-search-form { flex-direction: column; align-items: stretch; }
            .table-wrap { display: none; }
            .payout-mobile-list { display: block; margin-top: 12px; }
        }
        @media (max-width: 600px) {
            .payout-mobile-head { flex-direction: column; }
            .payout-mobile-callout { width: 100%; text-align: left; }
            .payout-mobile-grid { grid-template-columns: 1fr; }
            .payout-mobile-item.full { grid-column: span 1; }
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
            <a href="my_history.php"><i class="fas fa-history"></i> <span>My History</span></a>
            <a href="profile.php"><i class="fas fa-user-cog"></i> <span>My Profile</span></a>
            <a href="payouts.php" class="active"><i class="fas fa-wallet"></i> <span>Payouts</span><?php if ($unread_count > 0): ?><span class="notif-dot"></span><?php endif; ?></a>
        </div>
        <div class="main-content">
            <div class="payout-card">
                <h3 style="margin-top:0;">Payout Requests</h3>
                <div class="payout-grid">
                    <div class="payout-stat">
                        <h4>Commission Balance</h4>
                        <p id="balanceAmount" class="<?php echo $commission['balance'] < 0 ? 'amount-negative' : 'amount-positive'; ?>">₦ <?php echo number_format($commission['balance'], 2); ?></p>
                    </div>
                    <div class="payout-stat">
                        <h4>Total Commission</h4>
                        <p id="totalCommissionAmount">₦ <?php echo number_format($commission['total_commission']); ?></p>
                    </div>
                    <div class="payout-stat">
                        <h4>Pending Requests</h4>
                        <p id="pendingCommissionAmount">₦ <?php echo number_format($commission['pending_out']); ?></p>
                    </div>
                </div>

                <form method="POST" class="payout-form" id="payoutForm">
                    <div>
                        <label style="font-size:12px; color:#666;">Amount</label>
                        <input type="number" name="payout_amount" id="payoutAmount" step="0.01" min="0.01" placeholder="e.g. 5000" data-available="<?php echo $commission['available']; ?>">
                        <div class="hint" id="payoutHint"><?php echo $commission['available'] <= 0 ? 'You have no withdrawable commission yet. New commission will first clear your outstanding balance.' : 'Amount exceeds available commission.'; ?></div>
                    </div>
                    <div>
                        <label style="font-size:12px; color:#666;">Account Details</label>
                        <input type="text" name="account_details" placeholder="Account number, bank, name" required>
                    </div>
                    <button type="submit" name="request_payout" class="btn-request" id="payoutBtn">Request Payout</button>
                </form>

                <div style="margin-top:15px;">
                    <form method="GET" class="payout-search-form">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by Request ID or Status" style="padding:8px; border:1px solid #ddd; border-radius:6px; flex:1;">
                        <button type="submit" class="btn-request" style="padding:8px 12px;">Search</button>
                    </form>
                    <div class="table-wrap">
                    <table class="styled-table">
                        <thead>
                            <tr>
                                <th>Request #</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Note</th>
                                <th>Admin Note</th>
                                <th>Receipt</th>
                            </tr>
                        </thead>
                        <tbody id="salesPayoutsTableBody">
                            <?php if (count($payouts) > 0): ?>
                                <?php foreach ($payouts as $p): ?>
                                    <tr>
                                        <td>#<?php echo str_pad($p['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                        <td><?php echo date('M d, h:i A', strtotime($p['requested_at'])); ?></td>
                                        <td>₦ <?php echo number_format($p['amount']); ?></td>
                                        <td><span class="badge <?php echo $p['status']; ?>"><?php echo ucfirst($p['status']); ?></span></td>
                                        <td><?php echo !empty($p['note']) ? htmlspecialchars($p['note']) : '—'; ?></td>
                                            <td><?php echo !empty($p['review_note']) ? htmlspecialchars($p['review_note']) : '—'; ?></td>
                                            <td>
                                                <button class="btn-view" onclick="window.open('payout_receipt.php?id=<?php echo $p['id']; ?>','PayoutReceipt','width=420,height=600')">View</button>
                                            </td>
                                        </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" style="text-align:center; color:#999;">No payout requests yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                    <div class="payout-mobile-list" id="salesPayoutsMobileList">
                        <?php if (count($payouts) > 0): ?>
                            <?php foreach ($payouts as $p): ?>
                                <article class="payout-mobile-card">
                                    <div class="payout-mobile-head">
                                        <div>
                                            <h4 class="payout-mobile-title">Request #<?php echo str_pad($p['id'], 6, '0', STR_PAD_LEFT); ?></h4>
                                            <p class="payout-mobile-subtitle"><?php echo date('M d, Y h:i A', strtotime($p['requested_at'])); ?></p>
                                        </div>
                                        <div class="payout-mobile-callout">
                                            <span>Amount</span>
                                            <strong>₦<?php echo number_format($p['amount'], 0); ?></strong>
                                        </div>
                                    </div>
                                    <div class="payout-mobile-grid">
                                        <div class="payout-mobile-item">
                                            <span>Status</span>
                                            <strong><span class="badge <?php echo $p['status']; ?>"><?php echo ucfirst($p['status']); ?></span></strong>
                                        </div>
                                        <div class="payout-mobile-item">
                                            <span>Receipt</span>
                                            <strong><?php echo ($p['status'] === 'approved' || $p['status'] === 'rejected') ? 'Available' : 'Waiting'; ?></strong>
                                        </div>
                                        <div class="payout-mobile-item full">
                                            <span>Account Details</span>
                                            <strong><?php echo !empty($p['note']) ? htmlspecialchars($p['note']) : '—'; ?></strong>
                                        </div>
                                        <div class="payout-mobile-item full">
                                            <span>Admin Note</span>
                                            <strong><?php echo !empty($p['review_note']) ? htmlspecialchars($p['review_note']) : '—'; ?></strong>
                                        </div>
                                    </div>
                                    <div class="payout-mobile-actions">
                                        <button type="button" class="btn-view" onclick="window.open('payout_receipt.php?id=<?php echo $p['id']; ?>','PayoutReceipt','width=420,height=600')">View Receipt</button>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align:center; color:#98a2b3; padding: 20px 10px;">No payout requests yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('collapsed');
        }

        const payoutAmount = document.getElementById('payoutAmount');
        const payoutHint = document.getElementById('payoutHint');
        const payoutBtn = document.getElementById('payoutBtn');
        const balanceAmount = document.getElementById('balanceAmount');
        let available = parseFloat(payoutAmount.dataset.available || '0');

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function validatePayoutAmount() {
            const val = parseFloat(payoutAmount.value || '0');
            if (available <= 0) {
                payoutHint.textContent = 'You have no withdrawable commission yet. New commission will first clear your outstanding balance.';
                payoutHint.style.display = 'block';
                payoutAmount.style.borderColor = '#e74c3c';
                payoutBtn.disabled = true;
            } else if (val > available) {
                payoutHint.textContent = 'Amount exceeds available commission.';
                payoutHint.style.display = 'block';
                payoutAmount.style.borderColor = '#e74c3c';
                payoutBtn.disabled = true;
            } else {
                payoutHint.style.display = 'none';
                payoutAmount.style.borderColor = '#ddd';
                payoutBtn.disabled = false;
            }
        }

        payoutAmount.addEventListener('input', function() {
            validatePayoutAmount();
        });

        function renderSalesPayouts(payload) {
            if (!payload || payload.ok !== true) return;

            const commission = payload.commission || {};
            const payouts = Array.isArray(payload.payouts) ? payload.payouts : [];
            const totalCommissionEl = document.getElementById('totalCommissionAmount');
            const pendingCommissionEl = document.getElementById('pendingCommissionAmount');
            const tableBody = document.getElementById('salesPayoutsTableBody');
            const mobileList = document.getElementById('salesPayoutsMobileList');

            if (balanceAmount) {
                balanceAmount.textContent = `₦ ${commission.balance_display || '0.00'}`;
                balanceAmount.classList.toggle('amount-negative', Number(commission.balance || 0) < 0);
                balanceAmount.classList.toggle('amount-positive', Number(commission.balance || 0) >= 0);
            }
            if (totalCommissionEl) totalCommissionEl.textContent = `₦ ${commission.total_commission_display || '0'}`;
            if (pendingCommissionEl) pendingCommissionEl.textContent = `₦ ${commission.pending_out_display || '0'}`;
            available = parseFloat(commission.available || 0);
            payoutAmount.dataset.available = available;
            validatePayoutAmount();

            if (!tableBody || !mobileList) return;

            if (payouts.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="7" style="text-align:center; color:#999;">No payout requests yet.</td></tr>';
                mobileList.innerHTML = '<div style="text-align:center; color:#98a2b3; padding: 20px 10px;">No payout requests yet.</div>';
                return;
            }

            tableBody.innerHTML = payouts.map((payout) => `
                <tr>
                    <td>#${payout.receipt}</td>
                    <td>${escapeHtml(payout.requested_display)}</td>
                    <td>₦ ${escapeHtml(payout.amount_display)}</td>
                    <td><span class="badge ${escapeHtml(payout.status)}">${escapeHtml(payout.status.charAt(0).toUpperCase() + payout.status.slice(1))}</span></td>
                    <td>${payout.note ? escapeHtml(payout.note) : '—'}</td>
                    <td>${payout.review_note ? escapeHtml(payout.review_note) : '—'}</td>
                    <td><button type="button" class="btn-view" onclick="window.open('payout_receipt.php?id=${payout.id}','PayoutReceipt','width=420,height=600')">View</button></td>
                </tr>
            `).join('');

            mobileList.innerHTML = payouts.map((payout) => `
                <article class="payout-mobile-card">
                    <div class="payout-mobile-head">
                        <div>
                            <h4 class="payout-mobile-title">Request #${payout.receipt}</h4>
                            <p class="payout-mobile-subtitle">${escapeHtml(payout.requested_full_display)}</p>
                        </div>
                        <div class="payout-mobile-callout">
                            <span>Amount</span>
                            <strong>₦${escapeHtml(payout.amount_display)}</strong>
                        </div>
                    </div>
                    <div class="payout-mobile-grid">
                        <div class="payout-mobile-item">
                            <span>Status</span>
                            <strong><span class="badge ${escapeHtml(payout.status)}">${escapeHtml(payout.status.charAt(0).toUpperCase() + payout.status.slice(1))}</span></strong>
                        </div>
                        <div class="payout-mobile-item">
                            <span>Receipt</span>
                            <strong>${(payout.status === 'approved' || payout.status === 'rejected') ? 'Available' : 'Waiting'}</strong>
                        </div>
                        <div class="payout-mobile-item full">
                            <span>Account Details</span>
                            <strong>${payout.note ? escapeHtml(payout.note) : '—'}</strong>
                        </div>
                        <div class="payout-mobile-item full">
                            <span>Admin Note</span>
                            <strong>${payout.review_note ? escapeHtml(payout.review_note) : '—'}</strong>
                        </div>
                    </div>
                    <div class="payout-mobile-actions">
                        <button type="button" class="btn-view" onclick="window.open('payout_receipt.php?id=${payout.id}','PayoutReceipt','width=420,height=600')">View Receipt</button>
                    </div>
                </article>
            `).join('');
        }

        let salesPayoutsRefreshBusy = false;
        async function refreshSalesPayoutsLive() {
            if (salesPayoutsRefreshBusy) return;
            salesPayoutsRefreshBusy = true;

            try {
                const url = new URL(window.location.href);
                url.searchParams.set('live_data', '1');
                const response = await fetch(url.toString(), { credentials: 'same-origin', cache: 'no-store' });
                if (!response.ok) return;
                const payload = await response.json();
                renderSalesPayouts(payload);
            } catch (error) {
                // Ignore transient refresh failures.
            } finally {
                salesPayoutsRefreshBusy = false;
            }
        }

        document.addEventListener('galadawa:live-update', function(event) {
            const payload = event.detail || {};
            if (!payload.sales) return;
            refreshSalesPayoutsLive();
        });

        validatePayoutAmount();
    </script>
    <?php if (!empty($payout_message)): ?>
    <script>
        if (window.showToast) {
            showToast("<?php echo $payout_message; ?>", { type: "<?php echo $payout_message_type; ?>", duration: 2500 });
        }
    </script>
    <?php endif; ?>
</body>
</html>
