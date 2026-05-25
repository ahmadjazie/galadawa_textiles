<?php
session_start();
include '../includes/db_connect.php';
include '../includes/payouts.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'sale_attendant') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
ensure_payout_table($conn);
$payout_unread = get_user_unread_count($conn, $user_id);

if (!isset($_GET['id'])) {
    header("Location: inventory_view.php");
    exit();
}

$product_id = $_GET['id'];

// FETCH PRODUCT
$sql_prod = "SELECT * FROM products WHERE id = '$product_id'";
$res_prod = $conn->query($sql_prod);
if ($res_prod->num_rows == 0) { echo "Product not found"; exit(); }
$product = $res_prod->fetch_assoc();

// FETCH IMAGES
$sql_img = "SELECT * FROM product_images WHERE product_id = '$product_id'";
$res_img = $conn->query($sql_img);
$images = [];
while($row = $res_img->fetch_assoc()) {
    $images[] = $row;
}

// DEFAULTS
$main_image = (count($images) > 0) ? "../uploads/" . $images[0]['image_name'] : "../img/logo.png";
$main_status = (count($images) > 0) ? $images[0]['status'] : 'available';
$is_low = ($product['quantity'] < $product['min_stock']);
$unit = (strpos($product['category'], 'Cap') !== false) ? 'Pieces' : 'Yards';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $product['name']; ?> | Details</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        * { box-sizing: border-box; }

        /* MAIN LAYOUT: 2 Columns */
        .details-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            padding: 26px;
            display: grid;
            grid-template-columns: minmax(320px, 380px) minmax(0, 1fr);
            gap: 26px;
            align-items: start;
            width: 100%;
            max-width: 1020px;
            margin: 0 auto;
        }

        /* --- LEFT SIDE: IMAGES --- */
        .left-column {
            width: 100%;
            min-width: 0;
            position: sticky;
            top: 90px;
            align-self: start;
        }

        .main-image-box {
            width: 100%;
            height: 420px;
            border: 1px solid #eee;
            border-radius: 8px;
            overflow: hidden;
            position: relative;
            margin-bottom: 15px;
            background: #fff;
        }

        .main-image-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: top center; /* Focus on the cap */
            transition: transform 0.3s;
        }

        /* Main Sold Overlay (Big Stamp) */
        .sold-overlay-main {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex; justify-content: center; align-items: center;
            color: #ff4757; font-size: 30px; font-weight: 900;
            border: 4px solid #ff4757;
            transform: rotate(-15deg) scale(0.8);
            opacity: 0; pointer-events: none; transition: 0.3s;
        }

        /* Thumbnail Grid */
        .thumb-grid {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            overflow-y: hidden;
            padding: 4px 2px 6px;
        }

        .thumb-item {
            flex: 0 0 88px;
            width: 88px;
            height: 88px;
            border: 1px solid #ddd;
            border-radius: 6px;
            cursor: pointer;
            overflow: hidden;
            position: relative;
            opacity: 0.7;
            transition: 0.2s;
        }
        .thumb-item.active { border: 2px solid #1e3c72; opacity: 1; }
        .thumb-item img { width: 100%; height: 100%; object-fit: cover; }

        /* Thumbnail Sold Label (Red Bar at Bottom) */
        .thumb-sold-label {
            position: absolute; bottom: 0; width: 100%;
            background: #ff4757; color: white;
            font-size: 8px; text-align: center;
            font-weight: bold; padding: 2px 0;
        }

        /* --- RIGHT SIDE: DETAILS --- */
        .right-column {
            min-width: 0;
            padding-top: 0;
            align-self: start;
        }

        .cat-label { color: #888; font-size: 13px; text-transform: uppercase; letter-spacing: 1px; }
        .prod-name { font-size: 32px; font-weight: 700; color: #333; margin: 5px 0 10px 0; line-height: 1.2; }
        .prod-price { font-size: 26px; color: #1e3c72; font-weight: 700; margin-bottom: 25px; }

        /* The Grey Info Box */
        .info-box {
            background: #f8f9fa; /* Light Grey */
            border-radius: 10px;
            padding: 25px;
            border-left: 5px solid #1e3c72; /* Blue Accent Line */
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 12px;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 12px;
        }
        .info-row:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }

        .label { font-weight: 600; color: #555; }
        .value { font-weight: 700; color: #333; }
        .value.green { color: #28a745; } /* Green Text for Stock */
        .value.red { color: #e74c3c; }

        /* Buttons */
        .btn-group { margin-top: 30px; display: flex; gap: 15px; flex-wrap: wrap; }
        .btn-back { padding: 12px 25px; background: #eee; color: #333; border-radius: 6px; font-weight: 600; text-decoration: none; }

        /* Mobile Responsive */
        @media(max-width: 900px) {
            .details-card { grid-template-columns: 1fr; padding: 18px; gap: 18px; max-width: 100%; }
            .left-column { position: static; top: auto; }
            .main-image-box { height: min(78vw, 380px); }
            .thumb-grid { overflow-x: auto; overflow-y: hidden; }
            .info-row { flex-direction: column; align-items: flex-start; gap: 6px; }
            .btn-group > * { width: 100%; text-align: center; }
        }
    </style>
</head>
<body class="sales-mobile-ui">
<?php $is_dashboard = true; include '../includes/topbar.php'; ?>

    <!-- <div id="loader-wrapper"><img src="../img/logo.png" class="loader-logo"></div> -->

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
            <a href="inventory_view.php" class="active"><i class="fas fa-box"></i> <span>View Inventory</span></a>
            <a href="my_history.php"><i class="fas fa-history"></i> <span>My History</span></a>
            <a href="profile.php"><i class="fas fa-user-cog"></i> <span>My Profile</span></a>
            <a href="payouts.php"><i class="fas fa-wallet"></i> <span>Payouts</span><?php if ($payout_unread > 0): ?><span class="notif-dot"></span><?php endif; ?></a>
        </div>

        <div class="main-content">
            <div class="header-title">Product Details</div>

            <div class="details-card">
                
                <div class="left-column">
                    <div class="main-image-box">
                        <img id="mainImg" src="<?php echo $main_image; ?>">
                        <div id="mainSoldOverlay" class="sold-overlay-main" style="opacity: <?php echo ($main_status=='sold_out')?'1':'0'; ?>;">SOLD OUT</div>
                    </div>

                    <div class="thumb-grid">
                        <?php foreach($images as $img): ?>
                            <?php $src = "../uploads/" . $img['image_name']; ?>
                            <div class="thumb-item" onclick="changeImage('<?php echo $src; ?>', '<?php echo $img['status']; ?>', this)">
                                <img src="<?php echo $src; ?>">
                                <?php if($img['status'] == 'sold_out'): ?>
                                    <div class="thumb-sold-label">SOLD</div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if(empty($images)): ?>
                             <div class="thumb-item active"><img src="../img/logo.png"></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="right-column">
                    <div class="cat-label"><?php echo strtoupper($product['category']); ?></div>
                    <div class="prod-name"><?php echo $product['name']; ?></div>
                    <div class="prod-price">₦ <?php echo number_format($product['sell_price'], 2); ?></div>

                    <div class="info-box">
                        <div class="info-row">
                            <span class="label">Current Stock:</span>
                            <span class="value green"><?php echo $product['quantity']; ?> <?php echo $unit; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Cost Price:</span>
                            <span class="value">₦ <?php echo number_format($product['buy_price'], 2); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Alert Level:</span>
                            <span class="value"><?php echo $product['min_stock']; ?> <?php echo $unit; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">Status:</span>
                            <span class="value <?php echo $is_low ? 'red' : ''; ?>">
                                <?php echo $is_low ? 'Restock Needed' : 'Good Standing'; ?>
                            </span>
                        </div>
                    </div>

                    <div class="btn-group">
                        <a href="inventory_view.php" class="btn-back">Back</a>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('collapsed'); }
        window.addEventListener('pageshow', function() { document.getElementById('loader-wrapper').classList.add('loader-hidden'); });
        
        // --- JS TO HANDLE IMAGE SWITCHING ---
        function changeImage(src, status, element) {
            // 1. Change Main Image
            document.getElementById('mainImg').src = src;
            
            // 2. Toggle Big Sold Overlay
            const overlay = document.getElementById('mainSoldOverlay');
            if (status === 'sold_out') {
                overlay.style.opacity = '1';
            } else {
                overlay.style.opacity = '0';
            }

            // 3. Highlight Active Thumbnail
            document.querySelectorAll('.thumb-item').forEach(el => el.classList.remove('active'));
            element.classList.add('active');
        }

        // Auto-select first thumbnail
        document.addEventListener('DOMContentLoaded', () => {
            const first = document.querySelector('.thumb-item');
            if(first) first.classList.add('active');
        });
    </script>
</body>
</html>
