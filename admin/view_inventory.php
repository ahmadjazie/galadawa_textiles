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

$search = "";

// SQL MAGIC: 
// We use GROUP_CONCAT to pack the filename AND the status together into one string
// Format: "image1.jpg::available,image2.jpg::sold_out"
$sql = "SELECT p.*, 
        GROUP_CONCAT(CONCAT(pi.image_name, '::', pi.status) SEPARATOR ',') as gallery_data 
        FROM products p 
        LEFT JOIN product_images pi ON p.id = pi.product_id 
        WHERE 1=1 ";

if (isset($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $sql .= " AND (p.name LIKE '%$search%' OR p.category LIKE '%$search%')";
}
if (isset($_GET['filter']) && $_GET['filter'] == 'low') {
    $sql .= " AND p.quantity < p.min_stock";
}

$sql .= " GROUP BY p.id ORDER BY p.id DESC";
$result = $conn->query($sql);
$products = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Inventory | Galadawa</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <script src="../js/sweetalert2.all.min.js"></script>
    <style>
        .search-container { display: flex; justify-content: space-between; margin-bottom: 20px; background: white; padding: 15px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .search-box { display: flex; align-items: center; background: #f4f7f6; padding: 5px 15px; border-radius: 20px; width: 300px; border: 1px solid #ddd; }
        .search-box input { border: none; background: transparent; outline: none; margin-left: 10px; width: 100%; }
        
        /* Table Styles */
        .row-danger { background-color: #fff5f5; border-left: 5px solid #e74c3c; }
        .stock-badge { padding: 5px 10px; border-radius: 15px; font-size: 12px; font-weight: bold; }
        .stock-ok { background-color: #d4edda; color: #155724; }
        .stock-low { background-color: #f8d7da; color: #721c24; }
        
        /* Thumbnail Preview */
        .gallery-wrap { position: relative; width: 50px; height: 50px; cursor: pointer; }
        .prod-thumb { width: 100%; height: 100%; border-radius: 5px; object-fit: cover; border: 1px solid #ddd; }
        .more-badge { position: absolute; bottom: 0; right: 0; background: rgba(0,0,0,0.7); color: white; font-size: 10px; padding: 2px 4px; border-radius: 4px; }

        /* --- NEW POPUP GALLERY STYLES --- */
        .swal-gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); /* Auto-fit grid */
            gap: 10px;
            max-height: 400px;
            overflow-y: auto;
            padding: 10px;
        }
        .gallery-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #eee;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .gallery-item:hover { transform: scale(1.05); border-color: #1e3c72; }
        .gallery-item img {
            width: 100%;
            height: 100px; /* Fixed height for neatness */
            object-fit: cover;
            display: block;
        }
        
        /* THE "SOLD OUT" OVERLAY */
        .sold-overlay {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.6); /* Darkens the image */
            display: flex;
            justify-content: center;
            align-items: center;
            color: #ff4757;
            font-weight: 900;
            font-size: 14px;
            text-transform: uppercase;
            border: 2px solid #ff4757;
            transform: rotate(-15deg); /* Angled stamp effect */
        }
        .inventory-mobile-list { display: none; }
        .inventory-mobile-card {
            border: 1px solid #e7edf5;
            border-radius: 16px;
            padding: 14px;
            background: #fff;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
        }
        .inventory-mobile-card + .inventory-mobile-card { margin-top: 12px; }
        .inventory-mobile-head { display: flex; align-items: flex-start; gap: 12px; }
        .inventory-mobile-thumb {
            width: 74px;
            height: 74px;
            border-radius: 14px;
            overflow: hidden;
            flex-shrink: 0;
            border: 1px solid #e5e7eb;
            background: #fff;
        }
        .inventory-mobile-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .inventory-mobile-title { margin: 0; font-size: 16px; color: #24303d; font-weight: 700; }
        .inventory-mobile-subtitle { margin: 4px 0 0; color: #667085; font-size: 13px; }
        .inventory-mobile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 12px; }
        .inventory-mobile-item {
            background: #f8fafc;
            border: 1px solid #edf2f7;
            border-radius: 12px;
            padding: 10px 12px;
        }
        .inventory-mobile-item span {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #667085;
        }
        .inventory-mobile-item strong {
            display: block;
            margin-top: 4px;
            font-size: 14px;
            color: #24303d;
        }
        .inventory-mobile-actions { margin-top: 12px; display: flex; gap: 10px; flex-wrap: wrap; }
        .inventory-view-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 14px;
            border-radius: 10px;
            background: #1e3c72;
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
        }
        @media(max-width:900px) {
            .table-container { overflow: visible; }
            .styled-table { display: none; }
            .inventory-mobile-list { display: block; }
        }
        @media(max-width:600px) {
            .inventory-mobile-head { flex-direction: column; }
            .inventory-mobile-thumb { width: 100%; height: 220px; }
            .inventory-mobile-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="admin-mobile-ui">
<?php $is_dashboard = true; include '../includes/topbar.php'; ?>

    <!-- <div id="loader-wrapper"><img src="../img/logo.png" alt="Loading..." class="loader-logo"></div> -->

    <div class="dashboard-container">
        
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h3>Menu</h3>
                <button class="toggle-btn" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            </div>
            <a href="dashboard.php"><i class="fas fa-home"></i> <span>Dashboard</span></a>
            <a href="add_product.php"><i class="fas fa-plus-circle"></i> <span>Add Product</span></a>
            <a href="view_inventory.php" class="active"><i class="fas fa-box"></i> <span>View Inventory</span></a>
            <a href="manage_users.php"><i class="fas fa-users"></i> <span>Manage Users</span></a>
            <a href="holding_orders.php"><i class="fas fa-boxes-stacked"></i> <span>Holding Orders</span></a>
            <a href="exchange_history.php"><i class="fas fa-right-left"></i> <span>Exchanges</span></a>
            <a href="payout_requests.php"><i class="fas fa-wallet"></i> <span>Payout Requests</span><?php if ($admin_payout_unread > 0): ?><span class="notif-dot"></span><?php endif; ?></a>
            <a href="transaction_history.php"><i class="fas fa-history"></i> <span>Transactions</span></a>
        </div>

        <div class="main-content">
            <div class="header-title">Current Inventory</div>

            <div class="search-container">
                <form action="" method="GET" style="display: flex; align-items: center;">
                    <div class="search-box">
                        <i class="fas fa-search" style="color: #888;"></i>
                        <input type="text" name="search" placeholder="Search..." value="<?php echo $search; ?>">
                    </div>
                    <button type="submit" class="btn-submit" style="width: auto; padding: 10px 20px; margin-left: 10px;">Search</button>
                </form>
                <a href="add_product.php"><button class="btn-submit" style="width: auto; background: #28a745;">+ New Item</button></a>
            </div>

            <div class="table-container">
                <table class="styled-table">
                    <thead>
                        <tr>
                            <th>Gallery</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
    <?php if (count($products) > 0): ?>
        <?php foreach($products as $row): ?>
            <?php 
                $is_low = ($row['quantity'] < $row['min_stock']);
                $unit = (strpos($row['category'], 'Cap') !== false) ? 'Pcs' : 'Yards';
                
                // Get the first image for the thumbnail
                $raw_data = $row['gallery_data'] ? explode(',', $row['gallery_data']) : [];
                $thumb_src = "../img/logo.png"; // Default
                
                if(count($raw_data) > 0) {
                    // Extract just the filename from "image.jpg::available"
                    $first_parts = explode('::', $raw_data[0]);
                    $thumb_src = "../uploads/" . $first_parts[0];
                }
            ?>
            
            <tr onclick="window.location.href='product_details.php?id=<?php echo $row['id']; ?>'" style="cursor: pointer;">
                <td>
                    <img src="<?php echo $thumb_src; ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px; border: 1px solid #ddd;">
                </td>
                <td><b><?php echo $row['name']; ?></b></td>
                <td><?php echo $row['category']; ?></td>
                <td>₦<?php echo number_format($row['sell_price']); ?></td>
                <td>
                    <?php echo $row['quantity'] . " " . $unit; ?> 
                    <?php if($is_low): ?> <span class="stock-badge stock-low">Low</span> <?php endif; ?>
                </td>
                <td>
                    <a href="product_details.php?id=<?php echo $row['id']; ?>" class="btn-view" style="text-decoration: none;">
                        <i class="fas fa-eye" style="color: #1e3c72;"></i>
                    </a>
                </td>
            </tr>

        <?php endforeach; ?>
    <?php else: ?>
        <tr><td colspan="6" style="text-align:center;">No products found.</td></tr>
    <?php endif; ?>
</tbody>
                </table>
                <div class="inventory-mobile-list">
                    <?php if (count($products) > 0): ?>
                        <?php foreach($products as $row): ?>
                            <?php
                                $is_low = ($row['quantity'] < $row['min_stock']);
                                $unit = (strpos($row['category'], 'Cap') !== false) ? 'Pcs' : 'Yards';
                                $raw_data = $row['gallery_data'] ? explode(',', $row['gallery_data']) : [];
                                $thumb_src = "../img/logo.png";
                                if (count($raw_data) > 0) {
                                    $first_parts = explode('::', $raw_data[0]);
                                    $thumb_src = "../uploads/" . $first_parts[0];
                                }
                            ?>
                            <article class="inventory-mobile-card">
                                <div class="inventory-mobile-head">
                                    <div class="inventory-mobile-thumb">
                                        <img src="<?php echo $thumb_src; ?>" alt="<?php echo htmlspecialchars($row['name']); ?>">
                                    </div>
                                    <div>
                                        <h4 class="inventory-mobile-title"><?php echo htmlspecialchars($row['name']); ?></h4>
                                        <p class="inventory-mobile-subtitle"><?php echo htmlspecialchars($row['category']); ?></p>
                                    </div>
                                </div>
                                <div class="inventory-mobile-grid">
                                    <div class="inventory-mobile-item">
                                        <span>Price</span>
                                        <strong>₦<?php echo number_format($row['sell_price']); ?></strong>
                                    </div>
                                    <div class="inventory-mobile-item">
                                        <span>Stock</span>
                                        <strong><?php echo $row['quantity'] . " " . $unit; ?><?php if($is_low): ?> · Low<?php endif; ?></strong>
                                    </div>
                                </div>
                                <div class="inventory-mobile-actions">
                                    <a class="inventory-view-link" href="product_details.php?id=<?php echo (int)$row['id']; ?>">Open Details</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align:center; padding:20px 10px; color:#98a2b3;">No products found.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('collapsed'); }
        window.addEventListener('pageshow', function() { document.getElementById('loader-wrapper').classList.add('loader-hidden'); });
        
        // --- NEW GRID GALLERY VIEWER ---
        function openGridGallery(items, productName) {
            
            // Build the HTML for the Grid
            let gridHtml = '<div class="swal-gallery-grid">';
            
            items.forEach(item => {
                // If status is sold_out, add the overlay div
                let overlay = (item.status === 'sold_out') 
                    ? '<div class="sold-overlay">SOLD</div>' 
                    : '';
                
                // Add image to grid
                gridHtml += `
                    <div class="gallery-item" onclick="enlargeImage('${item.src}')">
                        <img src="${item.src}">
                        ${overlay}
                    </div>
                `;
            });
            gridHtml += '</div>';

            // Show Popup
            Swal.fire({
                title: productName,
                html: gridHtml,
                width: 600, // Make popup wider for grid
                showConfirmButton: false,
                showCloseButton: true,
                footer: '<small>Click an image to zoom</small>'
            });
        }

        // Helper to Zoom in on a specific image from the grid
        function enlargeImage(src) {
            Swal.fire({
                imageUrl: src,
                imageWidth: 500,
                showConfirmButton: false,
                showCloseButton: true,
                animation: false // Snappy transition
            });
        }
    </script>
</body>
</html>
