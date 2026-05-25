<?php
session_start();
include '../includes/db_connect.php';
include '../includes/payouts.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }

$user_id = $_SESSION['user_id'];
ensure_payout_table($conn);
$payout_unread = get_user_unread_count($conn, $user_id);

$today = date('Y-m-d');
$res_sales = $conn->query("SELECT SUM(total_amount) as total FROM sales WHERE DATE(created_at) = '$today' AND user_id = '$user_id'");
$sales_today = $res_sales->fetch_assoc()['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Dashboard | Galadawa</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; margin: 0; background-color: #f4f7f6; color: #333; }
        
        /* SIDEBAR STYLES */
        .dashboard-container { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: #1e3c72; color: white; display: flex; flex-direction: column; padding: 20px; transition: width 0.3s; position: sticky; top: 0; height: 100vh; overflow: hidden; }
        
        /* COLLAPSED STATE */
        .sidebar.collapsed { width: 80px; }
        .sidebar.collapsed h3, .sidebar.collapsed span { display: none; } /* Hide text */
        .sidebar.collapsed .sidebar-header { justify-content: center; }
        .sidebar.collapsed a { justify-content: center; }
        .sidebar.collapsed i { margin: 0; font-size: 20px; }

        .sidebar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; height: 40px; }
        .toggle-btn { background: none; border: none; color: white; font-size: 20px; cursor: pointer; }

        .sidebar a { color: rgba(255,255,255,0.8); padding: 15px; margin-bottom: 5px; border-radius: 8px; display: flex; align-items: center; gap: 15px; text-decoration: none; transition: 0.3s; white-space: nowrap; position: relative; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.1); color: white; }
        .notif-dot { width: 8px; height: 8px; background: #e74c3c; border-radius: 50%; position: absolute; right: 12px; top: 50%; transform: translateY(-50%); }
        
        /* CONTENT */
        .main-content { flex: 1; padding: 30px; }
        .sales-welcome { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: white; padding: 30px; border-radius: 12px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .big-btn-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px; }
        .big-action-card { background: white; padding: 40px; border-radius: 15px; text-align: center; box-shadow: 0 10px 25px rgba(0,0,0,0.05); transition: 0.3s; cursor: pointer; text-decoration: none; color: #333; border: 2px solid transparent; display: block; }
        .big-action-card:hover { transform: translateY(-10px); border-color: #1e3c72; }
        .big-action-card i { font-size: 50px; color: #1e3c72; margin-bottom: 20px; }
        
        @media (max-width: 900px) {
            .main-content { width: 100%; padding: 20px; }
            .sales-welcome { flex-direction: column; align-items: flex-start; gap: 16px; padding: 22px; }
            .big-btn-grid { grid-template-columns: 1fr; gap: 16px; }
            .big-action-card { padding: 24px; text-align: left; }
            .big-action-card i { font-size: 38px; margin-bottom: 14px; }
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
            <a href="dashboard.php" class="active"><i class="fas fa-home"></i> <span>Home</span></a>
            <a href="pos.php"><i class="fas fa-cash-register"></i> <span>New Sale</span></a>
            <a href="holding_orders.php"><i class="fas fa-boxes-stacked"></i> <span>Holding</span></a>
            <a href="exchange.php"><i class="fas fa-right-left"></i> <span>Exchange</span></a>
            <a href="inventory_view.php"><i class="fas fa-box"></i> <span>View Inventory</span></a>
            <a href="my_history.php"><i class="fas fa-history"></i> <span>My History</span></a>
            <a href="profile.php"><i class="fas fa-user-cog"></i> <span>My Profile</span></a>
            <a href="payouts.php"><i class="fas fa-wallet"></i> <span>Payouts</span><?php if ($payout_unread > 0): ?><span class="notif-dot"></span><?php endif; ?></a>
        </div>
        <div class="main-content">
            <div class="sales-welcome">
                <div>
                    <h2>Welcome back, <?php echo $_SESSION['username']; ?>!</h2>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 30px; font-weight: bold;">₦ <?php echo number_format($sales_today); ?></div>
                    <small>Sold Today</small>
                </div>
            </div>

            <div class="big-btn-grid">
                <a href="pos.php" class="big-action-card">
                    <i class="fas fa-cash-register"></i>
                    <h3>New Transaction</h3>
                </a>
                
                <a href="check_stock.php" class="big-action-card">
                    <i class="fas fa-box-open"></i>
                    <h3>Check Stock</h3>
                </a>

                <a href="my_history.php" class="big-action-card">
                    <i class="fas fa-history"></i>
                    <h3>My Sales History</h3>
                </a>

                <a href="holding_orders.php" class="big-action-card">
                    <i class="fas fa-boxes-stacked"></i>
                    <h3>Holding Orders</h3>
                </a>

                <a href="exchange.php" class="big-action-card">
                    <i class="fas fa-right-left"></i>
                    <h3>Exchange Items</h3>
                </a>
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
