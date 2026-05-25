<?php
session_start();
include '../includes/db_connect.php';
include '../includes/payouts.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

ensure_payout_table($conn);
$admin_payout_unread = get_admin_unread_count($conn);
$res_pending = $conn->query("SELECT COUNT(*) as c FROM payout_requests WHERE status='pending'");
$admin_pending = $res_pending ? (int)($res_pending->fetch_assoc()['c'] ?? 0) : 0;

$message = "";
$message_type = "success";

// Search filter
$search = "";
if (isset($_GET['search']) && $_GET['search'] !== '') {
    $search = $conn->real_escape_string($_GET['search']);
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'], $_POST['request_id'])) {
    $request_id = intval($_POST['request_id']);
    $action = $_POST['action'];
    $note = $conn->real_escape_string(trim($_POST['review_note'] ?? ''));
    $admin_id = $_SESSION['user_id'];

    if ($action === 'approve') {
    $conn->query("UPDATE payout_requests SET status='approved', reviewed_at=NOW(), reviewed_by='$admin_id', review_note='$note', user_unread=1, admin_unread=0 WHERE id='$request_id' AND status='pending'");
        $message = "Payout approved.";
        $message_type = "success";
    } elseif ($action === 'reject') {
    $conn->query("UPDATE payout_requests SET status='rejected', reviewed_at=NOW(), reviewed_by='$admin_id', review_note='$note', user_unread=1, admin_unread=0 WHERE id='$request_id' AND status='pending'");
        $message = "Payout rejected.";
        $message_type = "warning";
    }
}

$requests = [];
$where = "1=1";
if ($search !== "") {
    $search_int = intval($search);
    $where .= " AND (pr.id = '$search_int' OR pr.status LIKE '%$search%' OR u.username LIKE '%$search%')";
}
$res = $conn->query("SELECT pr.*, u.username FROM payout_requests pr JOIN users u ON pr.user_id = u.id WHERE $where ORDER BY pr.id DESC");
if ($res) { while ($row = $res->fetch_assoc()) { $requests[] = $row; } }

$status_counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
foreach ($requests as $request_row) {
    $status = $request_row['status'] ?? '';
    if (isset($status_counts[$status])) {
        $status_counts[$status]++;
    }
}

if (isset($_GET['live_data'])) {
    $request_rows = [];
    foreach ($requests as $r) {
        $request_rows[] = [
            'id' => (int)$r['id'],
            'receipt' => str_pad((int)$r['id'], 6, '0', STR_PAD_LEFT),
            'username' => (string)$r['username'],
            'amount' => (float)$r['amount'],
            'amount_display' => number_format((float)$r['amount']),
            'requested_display' => date('M d, h:i A', strtotime($r['requested_at'])),
            'status' => (string)$r['status'],
            'note' => (string)($r['note'] ?? ''),
            'review_note' => (string)($r['review_note'] ?? ''),
        ];
    }

    header('Content-Type: application/json');
    echo json_encode([
        'ok' => true,
        'summary' => [
            'pending' => number_format($status_counts['pending']),
            'approved' => number_format($status_counts['approved']),
            'rejected' => number_format($status_counts['rejected']),
        ],
        'requests' => $request_rows,
    ]);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payout Requests | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/toastify.min.css">
    <script src="../js/toastify.min.js"></script>
    <script src="../js/toast.js"></script>
    <style>
        .page-shell { max-width: 1120px; margin: 0 auto; }
        .page-card { background: white; padding: 24px; border-radius: 20px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08); }
        .page-intro { color: #667085; font-size: 14px; line-height: 1.6; margin: 0 0 16px; }
        .filter-form { margin-bottom: 14px; display: flex; gap: 10px; align-items: center; padding: 12px; border: 1px solid #e7edf5; border-radius: 16px; background: #f8fbff; }
        .filter-input { padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; flex: 1; min-width: 0; }
        .request-summary { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-bottom: 18px; }
        .summary-chip { background: linear-gradient(180deg, #fbfdff 0%, #f4f7fb 100%); border: 1px solid #e7edf5; border-radius: 14px; padding: 14px 16px; }
        .summary-chip h4 { margin: 0; font-size: 12px; color: #667085; text-transform: uppercase; letter-spacing: 0.05em; }
        .summary-chip p { margin: 6px 0 0; font-size: 26px; font-weight: 700; color: #1e3c72; }
        .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; border: 1px solid #e7edf5; border-radius: 18px; }
        .styled-table { min-width: 920px; margin-top: 0; }
        .request-mobile-list { display: none; }
        .request-mobile-card { border: 1px solid #e7edf5; border-radius: 18px; padding: 16px; background: #fff; box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06); }
        .request-mobile-card.status-pending { border-color: #f2d68a; }
        .request-mobile-card.status-approved { border-color: #b9e6c2; }
        .request-mobile-card.status-rejected { border-color: #f1b8bf; }
        .request-card-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
        .request-id { margin: 0; font-size: 17px; color: #1e3c72; }
        .request-user { margin: 4px 0 0; color: #667085; font-size: 13px; }
        .request-amount { font-size: 25px; font-weight: 700; color: #1e3c72; line-height: 1; white-space: nowrap; }
        .request-meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .request-meta-item { background: #f8fafc; border: 1px solid #edf2f7; border-radius: 12px; padding: 11px 12px; }
        .request-meta-item.full { grid-column: span 2; }
        .meta-label { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #667085; margin-bottom: 5px; }
        .meta-value { display: block; font-size: 14px; font-weight: 600; color: #24303d; word-break: break-word; }
        .request-card-actions { margin-top: 14px; }
        .request-card-actions .request-action-form { grid-template-columns: 1fr 1fr; }
        .request-card-actions .action-btn,
        .request-card-actions .note-input { width: 100%; }
        .request-empty { text-align: center; color: #98a2b3; padding: 32px 12px 18px; }
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge.pending { background: #fff3cd; color: #856404; }
        .badge.approved { background: #d4edda; color: #155724; }
        .badge.rejected { background: #f8d7da; color: #721c24; }
        .action-btn { padding: 6px 10px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .btn-approve { background: #1e3c72; color: #fff; }
        .btn-reject { background: #e74c3c; color: #fff; }
        .note-input { width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 6px; }
        .request-action-form { display:grid; grid-template-columns: 1fr auto auto; gap:6px; }
        .account-cell, .note-cell { max-width: 240px; word-break: break-word; }

        @media (max-width: 900px) {
            .page-shell { max-width: 100%; }
            .page-card { padding: 0; background: transparent; box-shadow: none; }
            .page-intro { margin-bottom: 14px; }
            .filter-form { flex-direction: column; align-items: stretch; margin-bottom: 12px; }
            .filter-input, .filter-form .btn-approve { width: 100%; }
            .request-summary { display: flex; gap: 10px; overflow-x: auto; margin-bottom: 14px; padding-bottom: 2px; }
            .summary-chip { min-width: 145px; flex: 0 0 auto; }
            .table-wrap { display: none; }
            .request-mobile-list { display: grid; gap: 14px; }
            .request-card-top { flex-direction: column; }
            .request-amount { font-size: 22px; }
            .request-meta-grid { grid-template-columns: 1fr; }
            .request-meta-item.full { grid-column: span 1; }
            .request-card-actions .request-action-form { grid-template-columns: 1fr; }
            .request-card-actions .action-btn { width: 100%; }
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
            <a href="transaction_history.php"><i class="fas fa-history"></i> <span>Transactions</span></a>
            <a href="payout_requests.php" class="active"><i class="fas fa-wallet"></i> <span>Payout Requests</span><?php if ($admin_payout_unread > 0 || $admin_pending > 0): ?><span class="notif-dot"></span><span class="js-admin-pending-count" style="margin-left:6px; font-size:11px; color:#fff; opacity:0.8;">(<?php echo $admin_pending; ?>)</span><?php endif; ?></a>
        </div>

        <div class="main-content">
            <div class="header-title">Payout Requests</div>

            <div class="page-shell">
            <div class="page-card">
                <p class="page-intro">Review pending requests quickly, approve or reject them, and open payout receipts when needed.</p>
                <form method="GET" class="filter-form">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by ID, status, or user" class="filter-input">
                    <button type="submit" class="btn-approve">Search</button>
                </form>
                <div class="request-summary">
                    <div class="summary-chip">
                        <h4>Pending</h4>
                        <p id="requestsPendingValue"><?php echo number_format($status_counts['pending']); ?></p>
                    </div>
                    <div class="summary-chip">
                        <h4>Approved</h4>
                        <p id="requestsApprovedValue"><?php echo number_format($status_counts['approved']); ?></p>
                    </div>
                    <div class="summary-chip">
                        <h4>Rejected</h4>
                        <p id="requestsRejectedValue"><?php echo number_format($status_counts['rejected']); ?></p>
                    </div>
                </div>
                <div class="table-wrap">
                    <table class="styled-table">
                        <thead>
                            <tr>
                                <th>Request #</th>
                                <th>User</th>
                                <th>Amount</th>
                                <th>Requested</th>
                                <th>Status</th>
                                <th>Account Details</th>
                                <th>Review Note</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="payoutRequestsTableBody">
                            <?php if (count($requests) > 0): ?>
                                <?php foreach ($requests as $r): ?>
                                    <tr>
                                        <td>#<?php echo str_pad($r['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                        <td><?php echo htmlspecialchars($r['username']); ?></td>
                                        <td>₦ <?php echo number_format($r['amount']); ?></td>
                                        <td><?php echo date('M d, h:i A', strtotime($r['requested_at'])); ?></td>
                                        <td><span class="badge <?php echo $r['status']; ?>"><?php echo ucfirst($r['status']); ?></span></td>
                                        <td class="account-cell"><?php echo !empty($r['note']) ? htmlspecialchars($r['note']) : '—'; ?></td>
                                        <td class="note-cell"><?php echo !empty($r['review_note']) ? htmlspecialchars($r['review_note']) : '—'; ?></td>
                                        <td>
                                            <?php if ($r['status'] === 'pending'): ?>
                                                <form method="POST" class="request-action-form">
                                                    <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                                                    <input type="text" name="review_note" class="note-input" placeholder="Note (optional)">
                                                    <button class="action-btn btn-approve" name="action" value="approve">Approve</button>
                                                    <button class="action-btn btn-reject" name="action" value="reject">Reject</button>
                                                </form>
                                            <?php elseif ($r['status'] === 'approved' || $r['status'] === 'rejected'): ?>
                                                <button type="button" class="action-btn btn-approve" onclick="window.open('../sales/payout_receipt.php?id=<?php echo $r['id']; ?>','PayoutReceipt','width=420,height=600')">Receipt</button>
                                            <?php else: ?>
                                                <span style="color:#999;">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="8" style="text-align:center; color:#999;">No payout requests yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="request-mobile-list" id="payoutRequestsMobileList">
                    <?php if (count($requests) > 0): ?>
                        <?php foreach ($requests as $r): ?>
                            <article class="request-mobile-card status-<?php echo $r['status']; ?>">
                                <div class="request-card-top">
                                    <div>
                                        <h4 class="request-id">#<?php echo str_pad($r['id'], 6, '0', STR_PAD_LEFT); ?></h4>
                                        <p class="request-user">@<?php echo htmlspecialchars($r['username']); ?></p>
                                    </div>
                                    <div class="request-amount">₦ <?php echo number_format($r['amount']); ?></div>
                                </div>
                                <div class="request-meta-grid">
                                    <div class="request-meta-item">
                                        <span class="meta-label">Status</span>
                                        <span class="meta-value"><span class="badge <?php echo $r['status']; ?>"><?php echo ucfirst($r['status']); ?></span></span>
                                    </div>
                                    <div class="request-meta-item">
                                        <span class="meta-label">Requested</span>
                                        <span class="meta-value"><?php echo date('M d, h:i A', strtotime($r['requested_at'])); ?></span>
                                    </div>
                                    <div class="request-meta-item full">
                                        <span class="meta-label">Account Details</span>
                                        <span class="meta-value"><?php echo !empty($r['note']) ? htmlspecialchars($r['note']) : '—'; ?></span>
                                    </div>
                                    <div class="request-meta-item full">
                                        <span class="meta-label">Review Note</span>
                                        <span class="meta-value"><?php echo !empty($r['review_note']) ? htmlspecialchars($r['review_note']) : '—'; ?></span>
                                    </div>
                                </div>
                                <div class="request-card-actions">
                                    <?php if ($r['status'] === 'pending'): ?>
                                        <form method="POST" class="request-action-form">
                                            <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                                            <input type="text" name="review_note" class="note-input" placeholder="Note (optional)">
                                            <button class="action-btn btn-approve" name="action" value="approve">Approve</button>
                                            <button class="action-btn btn-reject" name="action" value="reject">Reject</button>
                                        </form>
                                    <?php elseif ($r['status'] === 'approved' || $r['status'] === 'rejected'): ?>
                                        <button type="button" class="action-btn btn-approve" onclick="window.open('../sales/payout_receipt.php?id=<?php echo $r['id']; ?>','PayoutReceipt','width=420,height=600')">Open Receipt</button>
                                    <?php else: ?>
                                        <div class="request-empty">No actions available.</div>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="request-empty">No payout requests yet.</div>
                    <?php endif; ?>
                </div>
            </div>
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

        function buildRequestActionMarkup(request) {
            if (request.status === 'pending') {
                return `
                    <form method="POST" class="request-action-form">
                        <input type="hidden" name="request_id" value="${request.id}">
                        <input type="text" name="review_note" class="note-input" placeholder="Note (optional)">
                        <button class="action-btn btn-approve" name="action" value="approve">Approve</button>
                        <button class="action-btn btn-reject" name="action" value="reject">Reject</button>
                    </form>
                `;
            }

            if (request.status === 'approved' || request.status === 'rejected') {
                return `<button type="button" class="action-btn btn-approve" onclick="window.open('../sales/payout_receipt.php?id=${request.id}','PayoutReceipt','width=420,height=600')">Receipt</button>`;
            }

            return '<span style="color:#999;">—</span>';
        }

        function buildRequestMobileActionMarkup(request) {
            if (request.status === 'pending') {
                return `
                    <form method="POST" class="request-action-form">
                        <input type="hidden" name="request_id" value="${request.id}">
                        <input type="text" name="review_note" class="note-input" placeholder="Note (optional)">
                        <button class="action-btn btn-approve" name="action" value="approve">Approve</button>
                        <button class="action-btn btn-reject" name="action" value="reject">Reject</button>
                    </form>
                `;
            }

            if (request.status === 'approved' || request.status === 'rejected') {
                return `<button type="button" class="action-btn btn-approve" onclick="window.open('../sales/payout_receipt.php?id=${request.id}','PayoutReceipt','width=420,height=600')">Open Receipt</button>`;
            }

            return '<div class="request-empty">No actions available.</div>';
        }

        function renderPayoutRequests(payload) {
            if (!payload || payload.ok !== true) return;

            const summary = payload.summary || {};
            const requests = Array.isArray(payload.requests) ? payload.requests : [];
            const pendingEl = document.getElementById('requestsPendingValue');
            const approvedEl = document.getElementById('requestsApprovedValue');
            const rejectedEl = document.getElementById('requestsRejectedValue');
            const tableBody = document.getElementById('payoutRequestsTableBody');
            const mobileList = document.getElementById('payoutRequestsMobileList');

            if (pendingEl) pendingEl.textContent = summary.pending || '0';
            if (approvedEl) approvedEl.textContent = summary.approved || '0';
            if (rejectedEl) rejectedEl.textContent = summary.rejected || '0';
            if (!tableBody || !mobileList) return;

            if (requests.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="8" style="text-align:center; color:#999;">No payout requests yet.</td></tr>';
                mobileList.innerHTML = '<div class="request-empty">No payout requests yet.</div>';
                return;
            }

            tableBody.innerHTML = requests.map((request) => `
                <tr>
                    <td>#${request.receipt}</td>
                    <td>${escapeHtml(request.username)}</td>
                    <td>₦ ${escapeHtml(request.amount_display)}</td>
                    <td>${escapeHtml(request.requested_display)}</td>
                    <td><span class="badge ${escapeHtml(request.status)}">${escapeHtml(request.status.charAt(0).toUpperCase() + request.status.slice(1))}</span></td>
                    <td class="account-cell">${request.note ? escapeHtml(request.note) : '—'}</td>
                    <td class="note-cell">${request.review_note ? escapeHtml(request.review_note) : '—'}</td>
                    <td>${buildRequestActionMarkup(request)}</td>
                </tr>
            `).join('');

            mobileList.innerHTML = requests.map((request) => `
                <article class="request-mobile-card status-${escapeHtml(request.status)}">
                    <div class="request-card-top">
                        <div>
                            <h4 class="request-id">#${request.receipt}</h4>
                            <p class="request-user">@${escapeHtml(request.username)}</p>
                        </div>
                        <div class="request-amount">₦ ${escapeHtml(request.amount_display)}</div>
                    </div>
                    <div class="request-meta-grid">
                        <div class="request-meta-item">
                            <span class="meta-label">Status</span>
                            <span class="meta-value"><span class="badge ${escapeHtml(request.status)}">${escapeHtml(request.status.charAt(0).toUpperCase() + request.status.slice(1))}</span></span>
                        </div>
                        <div class="request-meta-item">
                            <span class="meta-label">Requested</span>
                            <span class="meta-value">${escapeHtml(request.requested_display)}</span>
                        </div>
                        <div class="request-meta-item full">
                            <span class="meta-label">Account Details</span>
                            <span class="meta-value">${request.note ? escapeHtml(request.note) : '—'}</span>
                        </div>
                        <div class="request-meta-item full">
                            <span class="meta-label">Review Note</span>
                            <span class="meta-value">${request.review_note ? escapeHtml(request.review_note) : '—'}</span>
                        </div>
                    </div>
                    <div class="request-card-actions">${buildRequestMobileActionMarkup(request)}</div>
                </article>
            `).join('');
        }

        let payoutRequestsRefreshBusy = false;
        async function refreshPayoutRequestsLive() {
            if (payoutRequestsRefreshBusy) return;
            if (document.querySelector('input[name="review_note"]:focus')) return;
            payoutRequestsRefreshBusy = true;

            try {
                const url = new URL(window.location.href);
                url.searchParams.set('live_data', '1');
                const response = await fetch(url.toString(), { credentials: 'same-origin', cache: 'no-store' });
                if (!response.ok) return;
                const payload = await response.json();
                renderPayoutRequests(payload);
            } catch (error) {
                // Ignore transient refresh failures.
            } finally {
                payoutRequestsRefreshBusy = false;
            }
        }

        document.addEventListener('galadawa:live-update', function(event) {
            const payload = event.detail || {};
            if (!payload.admin) return;
            refreshPayoutRequestsLive();
        });
    </script>
    <?php if (!empty($message)): ?>
    <script>
        if (window.showToast) {
            showToast("<?php echo $message; ?>", { type: "<?php echo $message_type; ?>", duration: 2500 });
        }
    </script>
    <?php endif; ?>
</body>
</html>
