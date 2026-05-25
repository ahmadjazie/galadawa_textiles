<?php
session_start();
include '../includes/db_connect.php';
include '../includes/payouts.php';

// Access control for authenticated sales users.
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
ensure_payout_table($conn);
$payout_unread = get_user_unread_count($conn, $user_id);

$search = "";

// Build inventory query with grouped gallery status data.
// Format example: "image1.jpg::available,image2.jpg::sold_out"
$sql = "SELECT p.*, 
        GROUP_CONCAT(CONCAT(pi.image_name, '::', pi.status) SEPARATOR ',') as gallery_data 
        FROM products p 
        LEFT JOIN product_images pi ON p.id = pi.product_id 
        WHERE 1=1 ";

if (isset($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $sql .= " AND (p.name LIKE '%$search%' OR p.category LIKE '%$search%')";
}

// Show newest products first.
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
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f4f7f6; margin: 0; }
        * { box-sizing: border-box; }

        /* SIDEBAR (Sales Attendant Version) */
        .page-wrap { display: flex; min-height: calc(100vh - 80px); }
        .sidebar { width: 250px; background: #1e3c72; color: white; padding: 20px; display: flex; flex-direction: column; position: sticky; top: 80px; height: calc(100vh - 80px); align-self: flex-start; transition: width 0.3s; overflow: hidden; }
        .sidebar.collapsed { width: 80px; }
        .sidebar.collapsed h3, .sidebar.collapsed span { display: none; }
        .sidebar.collapsed .sidebar-header { justify-content: center; }
        .sidebar.collapsed a { justify-content: center; }
        .sidebar.collapsed i { margin: 0; font-size: 20px; }
        .sidebar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; height: 40px; }
        .toggle-btn { background: none; border: none; color: white; font-size: 20px; cursor: pointer; }
        .sidebar a { color: rgba(255,255,255,0.8); padding: 15px; display: flex; gap: 15px; text-decoration: none; border-radius: 8px; margin-bottom: 5px; align-items: center; white-space: nowrap; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.2); color: white; }
        @media(max-width:900px){ .page-wrap{min-height: auto;} }

        /* MAIN CONTENT */
        .main-content { flex: 1; padding: 30px; overflow-x: hidden; }
        .header-title { font-size: 24px; font-weight: bold; color: #333; margin-bottom: 20px; }

        /* SEARCH BAR */
        .search-container { margin-bottom: 20px; background: white; padding: 15px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .search-form { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .search-box { display: flex; align-items: center; background: #f4f7f6; padding: 5px 15px; border-radius: 20px; width: min(420px, 100%); border: 1px solid #ddd; }
        .search-box input { border: none; background: transparent; outline: none; margin-left: 10px; width: 100%; font-size: 14px; }
        .btn-submit { background: #1e3c72; color: white; border: none; border-radius: 20px; cursor: pointer; transition: 0.3s; padding: 10px 20px; line-height: 1; white-space: nowrap; }
        .btn-submit:hover { background: #162f5f; }
        
        /* TABLE STYLES */
        .table-container { background: white; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .styled-table { width: 100%; border-collapse: collapse; min-width: 800px; }
        .styled-table thead tr { background-color: #1e3c72; color: white; text-align: left; }
        .styled-table th, .styled-table td { padding: 15px; border-bottom: 1px solid #eee; }
        .styled-table tbody tr:hover { background-color: #f9f9f9; }

        /* BADGES */
        .stock-badge { padding: 5px 10px; border-radius: 15px; font-size: 11px; font-weight: bold; }
        .stock-ok { background-color: #d4edda; color: #155724; }
        .stock-low { background-color: #f8d7da; color: #721c24; }
        
        /* THUMBNAIL & GALLERY */
        .gallery-wrap { position: relative; width: 50px; height: 50px; cursor: pointer; }
        .prod-thumb { width: 100%; height: 100%; border-radius: 5px; object-fit: cover; border: 1px solid #ddd; }

        /* POPUP GALLERY STYLES */
        .swal-gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
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
        .gallery-item img { width: 100%; height: 100px; object-fit: cover; display: block; }
        
        .sold-overlay {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.6);
            display: flex; justify-content: center; align-items: center;
            color: #ff4757; font-weight: 900; font-size: 14px; text-transform: uppercase;
            border: 2px solid #ff4757; transform: rotate(-15deg);
        }

        .zoom-stage {
            width: 100%;
            max-height: 75vh;
            overflow: auto;
            text-align: center;
            cursor: zoom-in;
            background: #fafbfc;
            border-radius: 8px;
            padding: 8px;
        }
        .zoom-stage img {
            max-width: 100%;
            max-height: 70vh;
            transform-origin: center center;
            transition: transform 0.2s ease;
        }
        .zoom-stage.zoomed {
            cursor: zoom-out;
        }
        .zoom-stage.zoomed img {
            transform: scale(2);
        }
        
        .btn-view { color: #1e3c72; font-size: 18px; cursor: pointer; transition: 0.2s; }
        .btn-view:hover { transform: scale(1.1); }
        .notif-dot { width: 8px; height: 8px; background: #e74c3c; border-radius: 50%; position: absolute; right: 12px; top: 50%; transform: translateY(-50%); }
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
    <script src="../js/sweetalert2.all.min.js"></script>
</head>
<body class="sales-mobile-ui">
<?php $is_dashboard = true; include '../includes/topbar.php'; ?>

    <div class="page-wrap">
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h3>Menu</h3>
                <button class="toggle-btn" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            </div>
            <a href="dashboard.php" style="position:relative;"><i class="fas fa-home"></i> <span>Home</span></a>
            <a href="pos.php" style="position:relative;"><i class="fas fa-cash-register"></i> <span>New Sale</span></a>
            <a href="holding_orders.php" style="position:relative;"><i class="fas fa-boxes-stacked"></i> <span>Holding</span></a>
            <a href="exchange.php" style="position:relative;"><i class="fas fa-right-left"></i> <span>Exchange</span></a>
            <a href="inventory_view.php" class="active" style="position:relative;"><i class="fas fa-box"></i> <span>View Inventory</span></a>
            <a href="my_history.php" style="position:relative;"><i class="fas fa-history"></i> <span>My History</span></a>
            <a href="profile.php" style="position:relative;"><i class="fas fa-user-cog"></i> <span>My Profile</span></a>
            <a href="payouts.php" style="position:relative;"><i class="fas fa-wallet"></i> <span>Payouts</span><?php if ($payout_unread > 0): ?><span class="notif-dot"></span><?php endif; ?></a>
        </div>

        <div class="main-content">
        <div class="header-title">Current Inventory</div>

        <div class="search-container">
            <form action="" method="GET" class="search-form">
                <div class="search-box">
                    <i class="fas fa-search" style="color: #888;"></i>
                    <input type="text" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <button type="submit" class="btn-submit">Search</button>
            </form>
            </div>

        <div class="table-container">
            <table class="styled-table">
                <thead>
                    <tr>
                        <th>Gallery</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>In Stock</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($products) > 0): ?>
                        <?php foreach($products as $row): ?>
                            <?php 
                                $is_low = ($row['quantity'] < $row['min_stock']);
                                $unit = (strpos($row['category'], 'Cap') !== false) ? 'Pcs' : 'Yards';
                                $raw_data = $row['gallery_data'] ? explode(',', $row['gallery_data']) : [];
                                $thumb_src = "../img/logo.png"; 
                                if(count($raw_data) > 0) {
                                    $first_parts = explode('::', $raw_data[0]);
                                    $thumb_src = "../uploads/" . $first_parts[0];
                                }
                            ?>
                            
                            <tr>
                                <td onclick="openGridGallery(<?php 
                                    $js_items = [];
                                    foreach($raw_data as $rd) {
                                        $parts = explode('::', $rd);
                                        $js_items[] = ['src' => "../uploads/" . $parts[0], 'status' => $parts[1]];
                                    }
                                    echo htmlspecialchars(json_encode($js_items));
                                ?>, '<?php echo addslashes($row['name']); ?>')">
                                    <div class="gallery-wrap">
                                        <img src="<?php echo $thumb_src; ?>" class="prod-thumb" onerror="this.src='../img/logo.png'">
                                    </div>
                                </td>
                                <td><b><?php echo $row['name']; ?></b></td>
                                <td><?php echo $row['category']; ?></td>
                                <td>₦<?php echo number_format($row['sell_price']); ?></td>
                                <td>
                                    <?php echo $row['quantity'] . " " . $unit; ?> 
                                    <?php if($is_low): ?> <span class="stock-badge stock-low">Low</span> <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center; padding: 30px; color: #999;">No products found.</td></tr>
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
                                <div class="inventory-mobile-thumb" onclick="openGridGallery(<?php
                                    $js_items = [];
                                    foreach($raw_data as $rd) {
                                        $parts = explode('::', $rd);
                                        $js_items[] = ['src' => "../uploads/" . $parts[0], 'status' => $parts[1]];
                                    }
                                    echo htmlspecialchars(json_encode($js_items));
                                ?>, '<?php echo addslashes($row['name']); ?>')">
                                    <img src="<?php echo $thumb_src; ?>" alt="<?php echo htmlspecialchars($row['name']); ?>" onerror="this.src='../img/logo.png'">
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
                                <a class="inventory-view-link" href="view_product.php?id=<?php echo (int)$row['id']; ?>">View Details</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center; padding: 20px 10px; color:#98a2b3;">No products found.</div>
                <?php endif; ?>
            </div>
        </div>
        </div>
    </div>

    <script>
        let currentGalleryItems = [];
        let currentProductName = '';

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('collapsed');
        }

        // Gallery popup handlers.
        function openGridGallery(items, productName) {
            currentGalleryItems = Array.isArray(items) ? items : [];
            currentProductName = productName || '';

            let gridHtml = '<div class="swal-gallery-grid">';
            items.forEach(item => {
                let overlay = (item.status === 'sold_out') ? '<div class="sold-overlay">SOLD</div>' : '';
                gridHtml += `
                    <div class="gallery-item" onclick="enlargeImage('${item.src}')">
                        <img src="${item.src}">
                        ${overlay}
                    </div>
                `;
            });
            gridHtml += '</div>';

            Swal.fire({
                title: productName,
                html: gridHtml,
                width: 600,
                showConfirmButton: false,
                showCloseButton: true,
                footer: '<small>Click an image to zoom</small>'
            });
        }

        function enlargeImage(src) {
            Swal.fire({
                html: `<div id="zoomStage" class="zoom-stage"><img src="${src}" alt="Product Image"></div>`,
                showConfirmButton: false,
                showCloseButton: true,
                animation: false,
                allowOutsideClick: false,
                didOpen: () => {
                    const stage = document.getElementById('zoomStage');
                    if (!stage) return;
                    stage.addEventListener('click', () => {
                        if (!stage.classList.contains('zoomed')) {
                            stage.classList.add('zoomed');
                        } else {
                            Swal.close();
                            openGridGallery(currentGalleryItems, currentProductName);
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>
