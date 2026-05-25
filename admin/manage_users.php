<?php
session_start();
include '../includes/db_connect.php';
include '../includes/payouts.php';

// 1. SECURITY: Admin Only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

ensure_payout_table($conn);
$admin_payout_unread = get_admin_unread_count($conn);

// 2. Process user-management actions.
$swal_json = "";

// A. Add new user.
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_user'])) {
    $user = $conn->real_escape_string($_POST['username']);
    $pass = md5($_POST['password']);
    $role = $conn->real_escape_string($_POST['role']);
    $fullname = $conn->real_escape_string($_POST['fullname']);

    // Prevent duplicate usernames.
    $check = $conn->query("SELECT id FROM users WHERE username='$user'");
    if ($check->num_rows > 0) {
        $swal_json = json_encode(['icon'=>'error', 'title'=>'Error', 'text'=>'Username already exists!']);
    } else {
        $sql = "INSERT INTO users (username, password, role, fullname, status) VALUES ('$user', '$pass', '$role', '$fullname', 'active')";
        if ($conn->query($sql)) {
            $swal_json = json_encode(['icon'=>'success', 'title'=>'Success', 'text'=>'New staff added!']);
        }
    }
}

// B. Update account details.
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_user'])) {
    $edit_id = (int)($_POST['edit_user_id'] ?? 0);
    $edit_fullname = $conn->real_escape_string(trim($_POST['edit_fullname'] ?? ''));
    $edit_username = $conn->real_escape_string(trim($_POST['edit_username'] ?? ''));
    $edit_role = $conn->real_escape_string(trim($_POST['edit_role'] ?? 'sale_attendant'));
    $edit_password_raw = trim($_POST['edit_password'] ?? '');

    if ($edit_id <= 0 || $edit_fullname === '' || $edit_username === '') {
        $swal_json = json_encode(['icon'=>'error', 'title'=>'Error', 'text'=>'Name and username are required.']);
    } else {
        if ($edit_id === (int)$_SESSION['user_id']) {
            // Prevent current admin from removing own admin role.
            $edit_role = 'admin';
        }

        $check_edit = $conn->query("SELECT id FROM users WHERE username='$edit_username' AND id != '$edit_id'");
        if ($check_edit && $check_edit->num_rows > 0) {
            $swal_json = json_encode(['icon'=>'error', 'title'=>'Error', 'text'=>'Username already exists!']);
        } else {
            $sql_update = "UPDATE users SET fullname='$edit_fullname', username='$edit_username', role='$edit_role'";
            if ($edit_password_raw !== '') {
                $edit_password = md5($edit_password_raw);
                $sql_update .= ", password='$edit_password'";
            }
            $sql_update .= " WHERE id='$edit_id'";

            if ($conn->query($sql_update)) {
                if ($edit_id === (int)$_SESSION['user_id']) {
                    $_SESSION['username'] = $edit_username;
                    $_SESSION['role'] = 'admin';
                }
                $swal_json = json_encode(['icon'=>'success', 'title'=>'Updated!', 'text'=>'User account updated successfully.']);
            } else {
                $swal_json = json_encode(['icon'=>'error', 'title'=>'Error', 'text'=>'Failed to update user.']);
            }
        }
    }
}

// C. Toggle account status (active/suspended).
if (isset($_GET['toggle_id'])) {
    $id = (int)$_GET['toggle_id'];
    $current_status = $conn->real_escape_string($_GET['status']);
    $new_status = ($current_status == 'active') ? 'suspended' : 'active';
    
    // Prevent self-suspension.
    if ($id != $_SESSION['user_id']) {
        $conn->query("UPDATE users SET status='$new_status' WHERE id='$id'");
        header("Location: manage_users.php");
        exit();
    }
}

// D. Delete user.
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    if ($id != $_SESSION['user_id']) {
        $conn->query("DELETE FROM users WHERE id='$id'");
        header("Location: manage_users.php");
        exit();
    }
}

$edit_user_id = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;
$edit_user = null;
if ($edit_user_id > 0) {
    $edit_res = $conn->query("SELECT * FROM users WHERE id='$edit_user_id' LIMIT 1");
    if ($edit_res && $edit_res->num_rows > 0) {
        $edit_user = $edit_res->fetch_assoc();
    }
}

// 3. Load users list.
$users_result = $conn->query("SELECT * FROM users ORDER BY id DESC");
$users = [];
if ($users_result) {
    while ($row = $users_result->fetch_assoc()) {
        $users[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | Galadawa</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/toastify.min.css">
    <script src="../js/toastify.min.js"></script>
    <script src="../js/toast.js"></script>
    <style>
        /* Status Badges */
        .badge { padding: 5px 10px; border-radius: 15px; font-size: 12px; font-weight: bold; color: white; }
        .bg-active { background-color: #28a745; }
        .bg-suspended { background-color: #dc3545; }
        
        /* Action Buttons */
        .action-btn { padding: 5px 10px; border: none; border-radius: 5px; cursor: pointer; color: white; margin-right: 5px; }
        .btn-suspend { background-color: #f39c12; }
        .btn-activate { background-color: #28a745; }
        .btn-delete { background-color: #e74c3c; }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px; }
        .edit-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .edit-actions .btn-submit { width: 100%; text-align: center; }
        .table-container { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .styled-table { min-width: 780px; }
        .user-mobile-list { display: none; }
        .user-mobile-card {
            border: 1px solid #e7edf5;
            border-radius: 16px;
            padding: 14px;
            background: #fff;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
        }
        .user-mobile-card + .user-mobile-card { margin-top: 12px; }
        .user-mobile-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
        .user-mobile-title { margin: 0; font-size: 16px; color: #24303d; font-weight: 700; }
        .user-mobile-subtitle { margin: 4px 0 0; color: #667085; font-size: 13px; }
        .user-mobile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 12px; }
        .user-mobile-item {
            background: #f8fafc;
            border: 1px solid #edf2f7;
            border-radius: 12px;
            padding: 10px 12px;
        }
        .user-mobile-item span {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #667085;
        }
        .user-mobile-item strong {
            display: block;
            margin-top: 4px;
            font-size: 14px;
            color: #24303d;
        }
        .user-mobile-actions { margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap; }
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .edit-actions { grid-template-columns: 1fr; }
            .styled-table { display: none; }
            .user-mobile-list { display: block; }
            .user-mobile-head { flex-direction: column; }
            .user-mobile-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="admin-mobile-ui">
<?php $is_dashboard = true; include '../includes/topbar.php'; ?>

    <!-- <div id="loader-wrapper">
        <img src="../img/logo.png" alt="Loading..." class="loader-logo">
    </div> -->

    <div class="dashboard-container">
        
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h3>Menu</h3>
                <button class="toggle-btn" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            </div>
            <a href="dashboard.php"><i class="fas fa-home"></i> <span>Dashboard</span></a>
            <a href="add_product.php"><i class="fas fa-plus-circle"></i> <span>Add Product</span></a>
            <a href="view_inventory.php"><i class="fas fa-box"></i> <span>View Inventory</span></a>
            <a href="manage_users.php" class="active"><i class="fas fa-users"></i> <span>Manage Users</span></a>
            <a href="holding_orders.php"><i class="fas fa-boxes-stacked"></i> <span>Holding Orders</span></a>
            <a href="exchange_history.php"><i class="fas fa-right-left"></i> <span>Exchanges</span></a>
            <a href="payout_requests.php"><i class="fas fa-wallet"></i> <span>Payout Requests</span><?php if ($admin_payout_unread > 0): ?><span class="notif-dot"></span><?php endif; ?></a>
            <a href="transaction_history.php"><i class="fas fa-history"></i> <span>Transactions</span></a>
        </div>

        <div class="main-content">
            <div class="header-title">Staff Management</div>

            <div class="form-card" style="margin-bottom: 40px; padding: 30px;">
                <h3 style="margin-bottom: 20px; color: #333;">Add New Staff</h3>
                <form action="" method="POST">
                    <input type="hidden" name="add_user" value="1">
                    <div class="form-grid">
                        <input type="text" name="fullname" placeholder="Full Name (e.g. Musa Ibrahim)" required>
                        <input type="text" name="username" placeholder="Username" required>
                        <input type="password" name="password" placeholder="Password" required>
                        <select name="role">
                            <option value="sale_attendant">Sales Attendant</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-submit">Create Account</button>
                </form>
            </div>

            <?php if ($edit_user): ?>
            <div class="form-card" style="margin-bottom: 40px; padding: 30px; border-left: 4px solid #1e3c72;">
                <h3 style="margin-bottom: 20px; color: #333;">Edit User Account</h3>
                <form action="" method="POST">
                    <input type="hidden" name="update_user" value="1">
                    <input type="hidden" name="edit_user_id" value="<?php echo (int)$edit_user['id']; ?>">
                    <div class="form-grid">
                        <input type="text" name="edit_fullname" placeholder="Full Name" value="<?php echo htmlspecialchars($edit_user['fullname'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        <input type="text" name="edit_username" placeholder="Username" value="<?php echo htmlspecialchars($edit_user['username'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        <input type="password" name="edit_password" placeholder="New Password (leave empty to keep current)">
                        <select name="edit_role" <?php echo ((int)$edit_user['id'] === (int)$_SESSION['user_id']) ? 'disabled' : ''; ?>>
                            <option value="sale_attendant" <?php echo ($edit_user['role'] === 'sale_attendant') ? 'selected' : ''; ?>>Sales Attendant</option>
                            <option value="admin" <?php echo ($edit_user['role'] === 'admin') ? 'selected' : ''; ?>>Administrator</option>
                        </select>
                        <?php if ((int)$edit_user['id'] === (int)$_SESSION['user_id']): ?>
                            <input type="hidden" name="edit_role" value="admin">
                        <?php endif; ?>
                    </div>
                    <div class="edit-actions">
                        <button type="submit" class="btn-submit">Save Changes</button>
                        <a href="manage_users.php" class="btn-submit" style="text-decoration:none; background:#6c757d; display:inline-flex; align-items:center; justify-content:center;">Cancel</a>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <div class="table-container">
                <h3>Registered Staff</h3>
                <table class="styled-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $row): ?>
                        <tr>
                            <td>
                                <b><?php echo $row['fullname']; ?></b><br>
                                <small style="color: #666;">@<?php echo $row['username']; ?></small>
                            </td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $row['role'])); ?></td>
                            <td>
                                <span class="badge <?php echo ($row['status'] == 'active') ? 'bg-active' : 'bg-suspended'; ?>">
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="manage_users.php?edit_id=<?php echo (int)$row['id']; ?>" title="Edit User">
                                    <button class="action-btn" style="background:#3498db;"><i class="fas fa-pen"></i></button>
                                </a>
                                <?php if($row['id'] != $_SESSION['user_id']): ?>
                                    <a href="manage_users.php?toggle_id=<?php echo $row['id']; ?>&status=<?php echo $row['status']; ?>" 
                                       title="<?php echo ($row['status']=='active') ? 'Suspend User' : 'Activate User'; ?>">
                                        <button class="action-btn <?php echo ($row['status']=='active') ? 'btn-suspend' : 'btn-activate'; ?>">
                                            <i class="fas <?php echo ($row['status']=='active') ? 'fa-ban' : 'fa-check'; ?>"></i>
                                        </button>
                                    </a>

                                    <a href="manage_users.php?delete_id=<?php echo $row['id']; ?>" 
                                       onclick="return confirm('Are you sure you want to delete this user?');" title="Delete User">
                                        <button class="action-btn btn-delete"><i class="fas fa-trash"></i></button>
                                    </a>

                                <?php else: ?>
                                    <small style="color: #999;">(You)</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="user-mobile-list">
                    <?php foreach($users as $row): ?>
                        <article class="user-mobile-card">
                            <div class="user-mobile-head">
                                <div>
                                    <h4 class="user-mobile-title"><?php echo htmlspecialchars($row['fullname'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                    <p class="user-mobile-subtitle">@<?php echo htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                                <span class="badge <?php echo ($row['status'] == 'active') ? 'bg-active' : 'bg-suspended'; ?>">
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </div>
                            <div class="user-mobile-grid">
                                <div class="user-mobile-item">
                                    <span>Role</span>
                                    <strong><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $row['role'])), ENT_QUOTES, 'UTF-8'); ?></strong>
                                </div>
                                <div class="user-mobile-item">
                                    <span>Status</span>
                                    <strong><?php echo htmlspecialchars(ucfirst($row['status']), ENT_QUOTES, 'UTF-8'); ?></strong>
                                </div>
                            </div>
                            <div class="user-mobile-actions">
                                <a href="manage_users.php?edit_id=<?php echo (int)$row['id']; ?>" title="Edit User">
                                    <button class="action-btn" style="background:#3498db;"><i class="fas fa-pen"></i></button>
                                </a>
                                <?php if($row['id'] != $_SESSION['user_id']): ?>
                                    <a href="manage_users.php?toggle_id=<?php echo $row['id']; ?>&status=<?php echo $row['status']; ?>" title="<?php echo ($row['status']=='active') ? 'Suspend User' : 'Activate User'; ?>">
                                        <button class="action-btn <?php echo ($row['status']=='active') ? 'btn-suspend' : 'btn-activate'; ?>">
                                            <i class="fas <?php echo ($row['status']=='active') ? 'fa-ban' : 'fa-check'; ?>"></i>
                                        </button>
                                    </a>
                                    <a href="manage_users.php?delete_id=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure you want to delete this user?');" title="Delete User">
                                        <button class="action-btn btn-delete"><i class="fas fa-trash"></i></button>
                                    </a>
                                <?php else: ?>
                                    <small style="color: #999;">(You)</small>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </div>

    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('collapsed'); }
        window.addEventListener('pageshow', function(event) { document.getElementById('loader-wrapper').classList.add('loader-hidden'); });
        
        <?php if (!empty($swal_json)): ?>
            const swalData = <?php echo $swal_json; ?>;
            if (window.showToast) {
                showToast(swalData.title + " - " + swalData.text, { type: swalData.icon || "success", duration: 3000 });
            }
        <?php endif; ?>
    </script>
</body>
</html>
