<?php
// 1. ENABLE ERROR REPORTING (To identify the 500 Error)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include '../includes/db_connect.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }

// 2. GET IDs SAFELY
$sale_item_id = isset($_GET['sale_id']) ? $conn->real_escape_string($_GET['sale_id']) : 0;

// 3. ROBUST QUERY (Uses LEFT JOIN to prevent crashes if product is deleted)
$sql = "SELECT si.*, 
               p.name as product_name, p.image, p.category, 
               s.created_at, u.username 
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        LEFT JOIN products p ON si.product_id = p.id 
        JOIN users u ON s.user_id = u.id
        WHERE si.id = '$sale_item_id'";

$res = $conn->query($sql);

// 4. ERROR CHECKING
if (!$res) {
    die("Database Query Failed: " . $conn->error); // Shows the actual error
}

if ($res->num_rows == 0) {
    die("Error: Sale Item #$sale_item_id not found in database.");
}

$item = $res->fetch_assoc();

// 5. HANDLE MISSING DATA (If product was deleted)
$prod_name = $item['product_name'] ?? "Unknown Item (Deleted)";
$category = $item['category'] ?? "N/A";
$image = $item['image'] ?? "default.png";
if (empty($image)) $image = "default.png";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Details | Galadawa</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f4f7f6; margin: 0; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; box-sizing: border-box; }
        
        .container { background: white; width: 100%; max-width: 900px; display: grid; grid-template-columns: 1fr 1fr; gap: 40px; padding: 40px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        
        /* IMAGE SECTION */
        .image-box { position: relative; display: flex; align-items: center; justify-content: center; background: #fafafa; border-radius: 12px; border: 1px solid #eee; height: 400px; overflow: hidden; }
        .main-img { width: 100%; height: 100%; object-fit: cover; }
        
        .sold-stamp {
            position: absolute; 
            top: 50%; left: 50%; 
            transform: translate(-50%, -50%) rotate(-15deg);
            border: 5px solid #e74c3c; 
            color: #e74c3c; 
            font-size: 50px; 
            font-weight: 900;
            padding: 10px 30px; 
            text-transform: uppercase; 
            opacity: 0.7;
            pointer-events: none;
            letter-spacing: 5px;
            z-index: 10;
        }

        /* DETAILS SECTION */
        .details { display: flex; flex-direction: column; justify-content: center; }
        .details h5 { color: #888; text-transform: uppercase; letter-spacing: 2px; margin: 0 0 10px 0; font-size: 14px; }
        .details h1 { margin: 0 0 15px 0; color: #333; font-size: 32px; line-height: 1.2; }
        .price { font-size: 36px; color: #1e3c72; font-weight: bold; margin-bottom: 30px; }
        
        .info-card { background: #f8f9fa; padding: 25px; border-radius: 12px; border-left: 6px solid #1e3c72; }
        .row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #e9ecef; font-size: 15px; }
        .row:last-child { border-bottom: none; }
        
        .label { color: #6c757d; font-weight: 500; }
        .value { color: #212529; font-weight: 700; }

        .btn-back { display: inline-flex; align-items: center; justify-content: center; margin-top: 30px; padding: 15px 30px; background: #e9ecef; color: #333; text-decoration: none; border-radius: 8px; font-weight: bold; transition: 0.3s; width: fit-content; }
        .btn-back:hover { background: #dde2e6; transform: translateY(-2px); }
        .btn-back i { margin-right: 10px; }

        @media(max-width: 768px) { .container { grid-template-columns: 1fr; } .image-box { height: 300px; } }
    </style>
</head>
<body>

    <div class="container">
        <div class="image-box">
            <img src="../uploads/<?php echo $image; ?>" class="main-img" onerror="this.src='../img/logo.png'">
            <div class="sold-stamp">SOLD</div>
        </div>

        <div class="details">
            <h5><?php echo $category; ?></h5>
            <h1><?php echo $prod_name; ?></h1>
            <div class="price">₦ <?php echo number_format($item['price']); ?></div>

            <div class="info-card">
                <div class="row">
                    <span class="label">Date Sold:</span>
                    <span class="value"><?php echo date('M d, Y', strtotime($item['created_at'])); ?></span>
                </div>
                <div class="row">
                    <span class="label">Time:</span>
                    <span class="value"><?php echo date('h:i A', strtotime($item['created_at'])); ?></span>
                </div>
                <div class="row">
                    <span class="label">Quantity:</span>
                    <span class="value"><?php echo $item['quantity'] + 0; ?> Unit(s)</span>
                </div>
                <div class="row">
                    <span class="label">Attendant:</span>
                    <span class="value" style="color:#1e3c72;"><?php echo ucfirst($item['username']); ?></span>
                </div>
                <div class="row" style="border-top: 2px solid #ddd; margin-top: 5px; padding-top: 15px;">
                    <span class="label">Total Paid:</span>
                    <span class="value" style="color:#28a745; font-size:18px;">₦ <?php echo number_format($item['subtotal']); ?></span>
                </div>
            </div>

            <a href="sold_items.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Log</a>
        </div>
    </div>

    <script>
        let saleDetailSignature = null;

        async function pollSaleDetailChanges() {
            try {
                const response = await fetch(`../includes/live_updates.php?t=${Date.now()}`, {
                    credentials: 'same-origin',
                    cache: 'no-store'
                });
                if (!response.ok) return;

                const payload = await response.json();
                if (!payload || payload.ok !== true || !payload.signatures) return;

                const nextSignature = [
                    String(payload.signatures.sales || '0'),
                    String(payload.signatures.exchanges || '0'),
                    String(payload.signatures.products || '0')
                ].join('||');

                if (saleDetailSignature === null) {
                    saleDetailSignature = nextSignature;
                    return;
                }

                if (nextSignature !== saleDetailSignature) {
                    window.location.reload();
                }
            } catch (error) {
                // Ignore transient polling failures.
            }
        }

        pollSaleDetailChanges();
        setInterval(pollSaleDetailChanges, 5000);
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                pollSaleDetailChanges();
            }
        });
    </script>

</body>
</html>
