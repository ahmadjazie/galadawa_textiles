<?php
session_start();
include '../includes/db_connect.php';
include '../includes/payouts.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }

$user_id = $_SESSION['user_id'];
ensure_payout_table($conn);
$payout_unread = get_user_unread_count($conn, $user_id);
$msg = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    if (!empty($password)) {
        $sql = "UPDATE users SET username='$username', password='$password' WHERE id='$user_id'";
    } else {
        $sql = "UPDATE users SET username='$username' WHERE id='$user_id'";
    }

    if ($conn->query($sql)) {
        $_SESSION['username'] = $username;
        $msg = "Profile updated successfully!";
    } else {
        $error = "Error updating profile.";
    }
}

$user = $conn->query("SELECT * FROM users WHERE id='$user_id'")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Galadawa</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; margin: 0; background-color: #f4f7f6; color: #333; }
        
        /* SIDEBAR STYLES */
        .dashboard-container { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: #1e3c72; color: white; display: flex; flex-direction: column; padding: 20px; transition: width 0.3s; position: sticky; top: 0; height: 100vh; overflow: hidden; }
        
        /* COLLAPSED STATE */
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
        
        /* MAIN CONTENT */
        .main-content { flex: 1; padding: 30px; display: flex; justify-content: center; align-items: center; }
        .profile-card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); width: 100%; max-width: 500px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #555; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; box-sizing: border-box; }
        .readonly { background-color: #e9ecef; cursor: not-allowed; color: #6c757d; }
        .btn-save { width: 100%; padding: 12px; background: #1e3c72; color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; transition: 0.3s; }
        .btn-save:hover { background: #162f5f; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 8px; text-align: center; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-danger { background: #f8d7da; color: #721c24; }

        @media (max-width: 900px) {
            .main-content { padding: 20px; }
            .profile-card { padding: 24px; }
        }
    </style>
</head>
<body class="sales-mobile-ui">
<?php $is_dashboard = true; include '../includes/topbar.php'; ?>
    <div class="dashboard-container">
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h3>GaladawaTextiles</h3>
                <button class="toggle-btn" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            </div>
            <a href="dashboard.php"><i class="fas fa-home"></i> <span>Home</span></a>
            <a href="pos.php"><i class="fas fa-cash-register"></i> <span>New Sale</span></a>
            <a href="holding_orders.php"><i class="fas fa-boxes-stacked"></i> <span>Holding</span></a>
            <a href="exchange.php"><i class="fas fa-right-left"></i> <span>Exchange</span></a>
            <a href="inventory_view.php"><i class="fas fa-box"></i> <span>View Inventory</span></a>
            <a href="my_history.php"><i class="fas fa-history"></i> <span>My History</span></a>
            <a href="profile.php" class="active"><i class="fas fa-user-cog"></i> <span>My Profile</span></a>
            <a href="payouts.php"><i class="fas fa-wallet"></i> <span>Payouts</span><?php if ($payout_unread > 0): ?><span class="notif-dot"></span><?php endif; ?></a>
        </div>
        <div class="main-content">
            <div class="profile-card">
                <h2 style="margin-top:0; color:#1e3c72; text-align:center;">Edit Profile</h2>
                <p style="text-align:center; color:#777; margin-bottom:30px;">Update your login credentials</p>

                <?php if($msg): ?><div class="alert alert-success"><?php echo $msg; ?></div><?php endif; ?>
                <?php if($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" class="form-control readonly" value="<?php echo htmlspecialchars($user['fullname']); ?>" disabled>
                        <small style="color:#999; font-size:12px;">Full name cannot be changed.</small>
                    </div>

                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current password">
                    </div>

                    <button type="submit" class="btn-save">Save Changes</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('collapsed');
        }
    </script>
</body>
</html>
