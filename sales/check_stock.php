<?php
session_start();
include '../includes/db_connect.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }

//  AJAX endpoint: Return available variant images for a product
if (isset($_GET['get_stock_images'])) {
    $pid = $_GET['get_stock_images'];
    // Retrieve only image variants currently marked as available.
    $res = $conn->query("SELECT * FROM product_images WHERE product_id='$pid' AND status='available'");
    $images = [];
    while($row = $res->fetch_assoc()) {
        $images[] = $row;
    }
    echo json_encode($images);
    exit();
}

// Search handling: Filter in-stock products by name/category

$search = $_GET['search'] ?? '';
$sql = "SELECT * FROM products WHERE quantity > 0";
if ($search) {
    $s = $conn->real_escape_string($search);
    $sql .= " AND (name LIKE '%$s%' OR category LIKE '%$s%')";
}
$sql .= " ORDER BY name ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Stock | Galadawa</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/toastify.min.css">
    <script src="../js/toastify.min.js"></script>
    <script src="../js/toast.js"></script>
    <script src="../js/sweetalert2.all.min.js"></script>
    <style>
        body { font-family: 'Poppins', sans-serif; margin: 0; background-color: #f4f7f6; color: #333; }
        
        /* Header and overall page layout */
        .top-header { background: #1e3c72; color: white; padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; height: 60px; position: sticky; top: 0; z-index: 1000; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .brand-area { display: flex; align-items: center; gap: 15px; }
        .brand-logo { height: 40px; background: white; border-radius: 5px; padding: 2px; }
        .brand-title { font-size: 20px; font-weight: bold; letter-spacing: 0.5px; color: white; }
        .btn-back-nav { background: rgba(255,255,255,0.2); color: white; padding: 8px 15px; border-radius: 6px; font-weight: 600; font-size: 14px; border: none; cursor: pointer; display: flex; align-items: center; gap: 5px; text-decoration: none; }

        .container { max-width: 1000px; margin: 30px auto; padding: 0 20px; }
        
        /* Search controls */
        .search-container { margin-bottom: 20px; display: flex; gap: 10px; }
        .search-input { flex: 1; padding: 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px; outline: none; }
        .btn-search { background: #1e3c72; color: white; border: none; padding: 0 25px; border-radius: 8px; cursor: pointer; font-size: 16px; }

        /* Product grid and product cards */
        .stock-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; }
        
        .stock-card { 
            background: white; padding: 15px; border-radius: 10px; text-align: center; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.05); transition: 0.3s; border: 1px solid #eee; cursor: pointer;
            position: relative;
        }
        .stock-card:hover { transform: translateY(-5px); border-color: #1e3c72; box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        
        .stock-img { width: 100%; height: 150px; object-fit: cover; border-radius: 8px; margin-bottom: 10px; background: #eee; }
        .stock-name { font-weight: 600; margin-bottom: 5px; height: 40px; overflow: hidden; }
        .stock-price { color: #28a745; font-weight: bold; font-size: 16px; margin-bottom: 5px; }
        
        .stock-qty { 
            font-size: 13px; color: #555; background: #f0f0f0; padding: 5px 10px; 
            border-radius: 15px; display: inline-block; margin-top: 5px;
        }
        
        /* Variant gallery displayed in the popup */
        .color-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; max-height: 400px; overflow-y: auto; padding: 5px; }
        .color-item img { width: 100%; height: 120px; object-fit: cover; border-radius: 6px; border: 1px solid #eee; }
        .color-item p { margin: 5px 0 0 0; font-size: 12px; color: #555; }
        @media (max-width: 700px) {
            .top-header { padding: 8px 12px; height: auto; gap: 10px; }
            .brand-area { gap: 10px; min-width: 0; }
            .brand-title { font-size: 16px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .brand-logo { height: 34px; }
            .container { margin: 18px auto; padding: 0 12px; }
            .search-container { flex-direction: column; }
            .btn-search { width: 100%; padding: 14px 18px; }
            .stock-grid { grid-template-columns: 1fr; gap: 14px; }
        }
    </style>
</head>
<body>

    <div class="top-header">
        <div><a href="dashboard.php" class="btn-back-nav"><i class="fas fa-arrow-left"></i> Dashboard</a></div>
        <div class="brand-area"><span class="brand-title">Galadawa Textiles</span><img src="../img/logo.png" alt="Logo" class="brand-logo"></div>
    </div>

    <div class="container">
        <h2 style="color: #1e3c72;">Inventory Lookup</h2>
        
        <form class="search-container">
            <input type="text" name="search" class="search-input" placeholder="Search Item Name (e.g. Zanna)..." value="<?php echo htmlspecialchars($search); ?>" autofocus>
            <button type="submit" class="btn-search"><i class="fas fa-search"></i></button>
        </form>

        <div class="stock-grid">
            <?php while($p = $result->fetch_assoc()): 
                 $is_cap = (strpos($p['category'], 'Cap') !== false);
                 $img_src = "../img/logo.png";
                 
                 // Use the product's main image when available; otherwise fall back to the first available gallery image.
                 if (!empty($p['image']) && $p['image'] != 'default.png') { 
                     $img_src = "../uploads/" . $p['image']; 
                 } else { 
                     $gal = $conn->query("SELECT image_name FROM product_images WHERE product_id='".$p['id']."' AND status='available' LIMIT 1")->fetch_assoc(); 
                     if ($gal) $img_src = "../uploads/" . $gal['image_name']; 
                 }
            ?>
                <div class="stock-card" onclick="viewVariants(<?php echo $p['id']; ?>, '<?php echo $p['name']; ?>', <?php echo $is_cap ? 'true' : 'false'; ?>)">
                    <img src="<?php echo $img_src; ?>" class="stock-img" onerror="this.src='../img/logo.png'">
                    <div class="stock-name"><?php echo $p['name']; ?></div>
                    <div class="stock-price">₦<?php echo number_format($p['sell_price']); ?></div>
                    
                    <div class="stock-qty">
                        <i class="fas fa-box"></i> <?php echo $p['quantity']; ?> Available
                    </div>

                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <script>
        let liveProductsSignature = null;
        let pendingInventoryRefresh = false;

        function canRefreshInventoryNow() {
            const activeEl = document.activeElement;
            return !(activeEl && ['INPUT', 'TEXTAREA', 'SELECT'].includes(activeEl.tagName));
        }

        function tryApplyInventoryRefresh() {
            if (!pendingInventoryRefresh || !canRefreshInventoryNow()) return;
            window.location.reload();
        }

        async function pollInventoryChanges() {
            try {
                const response = await fetch(`../includes/live_updates.php?t=${Date.now()}`, {
                    credentials: 'same-origin',
                    cache: 'no-store'
                });
                if (!response.ok) return;

                const payload = await response.json();
                if (!payload || payload.ok !== true || !payload.signatures) return;

                const nextSignature = String(payload.signatures.products || '0');
                if (liveProductsSignature === null) {
                    liveProductsSignature = nextSignature;
                    return;
                }

                if (nextSignature === liveProductsSignature) return;

                liveProductsSignature = nextSignature;
                pendingInventoryRefresh = true;
                if (canRefreshInventoryNow()) {
                    window.location.reload();
                } else if (window.showToast) {
                    showToast('Inventory changed. This page will update when you finish typing.', { type: 'info', duration: 2200 });
                }
            } catch (error) {
                // Ignore transient live polling failures.
            }
        }

        document.addEventListener('focusout', function() {
            setTimeout(tryApplyInventoryRefresh, 80);
        });
        document.addEventListener('change', function() {
            setTimeout(tryApplyInventoryRefresh, 0);
        });

        function viewVariants(id, name, isCap) {
            if (!isCap) {
                if (window.showToast) {
                    showToast(name + " - Sold by yards; stock is managed by total quantity.", { type: "info", duration: 3000 });
                }
                return;
            }

            // For cap items, load available variant images before opening the gallery.
            Swal.fire({ title: 'Loading...', didOpen: () => { Swal.showLoading() } });

            fetch('check_stock.php?get_stock_images=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.length === 0) {
                        if (window.showToast) {
                            showToast("No Specific Images - We have stock counts, but no specific photo variants uploaded.", { type: "info", duration: 3000 });
                        }
                        return;
                    }

                    // Build the popup gallery markup from the returned images.
                    let html = '<div class="color-grid">';
                    data.forEach(img => {
                        let src = '../uploads/' + img.image_name;
                        html += `
                            <div class="color-item">
                                <img src="${src}">
                                <p>Available</p>
                            </div>
                        `;
                    });
                    html += '</div>';

                    Swal.fire({
                        title: 'Available Colors: ' + name,
                        html: html,
                        width: '600px',
                        confirmButtonText: 'Close',
                        confirmButtonColor: '#1e3c72'
                    });
                });
        }

        pollInventoryChanges();
        setInterval(pollInventoryChanges, 5000);
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                pollInventoryChanges();
            }
        });
    </script>

</body>
</html>
