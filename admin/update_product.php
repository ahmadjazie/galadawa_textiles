<?php
session_start();
include '../includes/db_connect.php';
include '../includes/payouts.php';

// 1. SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

ensure_payout_table($conn);
$admin_payout_unread = get_admin_unread_count($conn);

if (!isset($_GET['id'])) {
    header("Location: view_inventory.php");
    exit();
}

$id = $_GET['id'];
$swal_json = "";

function ensure_product_image_textile_columns($conn) {
    $color_check = $conn->query("SHOW COLUMNS FROM product_images LIKE 'color_name'");
    if ($color_check && $color_check->num_rows === 0) {
        $conn->query("ALTER TABLE product_images ADD COLUMN color_name VARCHAR(100) NULL AFTER image_name");
    }

    $yards_check = $conn->query("SHOW COLUMNS FROM product_images LIKE 'yards'");
    if ($yards_check && $yards_check->num_rows === 0) {
        $conn->query("ALTER TABLE product_images ADD COLUMN yards DECIMAL(10,2) NULL AFTER color_name");
    }
}

ensure_product_image_textile_columns($conn);

// ---------------------------------------------------------
// HELPER FUNCTION: AUTO-SYNC QUANTITY FOR CAPS
// ---------------------------------------------------------
function syncCapQuantity($conn, $product_id) {
    // 1. Check if product is a "Cap"
    $chk = $conn->query("SELECT category FROM products WHERE id='$product_id'")->fetch_assoc();
    if (strpos($chk['category'], 'Cap') !== false) {
        // 2. Count only AVAILABLE images
        $count_res = $conn->query("SELECT COUNT(*) as total FROM product_images WHERE product_id='$product_id' AND status='available'");
        $count = $count_res->fetch_assoc()['total'];
        
        // 3. Update the main product Quantity
        $conn->query("UPDATE products SET quantity='$count' WHERE id='$product_id'");
    }
}
// ---------------------------------------------------------

function validate_unique_new_textile_colours($conn, $productId, $fileErrors, $colourNames) {
    $productId = (int)$productId;
    $seen = [];
    $existing = $conn->query("SELECT color_name FROM product_images WHERE product_id='$productId' AND color_name IS NOT NULL AND TRIM(color_name) <> ''");
    if ($existing) {
        while ($row = $existing->fetch_assoc()) {
            $colour = trim((string)($row['color_name'] ?? ''));
            if ($colour === '') {
                continue;
            }
            $key = function_exists('mb_strtolower') ? mb_strtolower($colour, 'UTF-8') : strtolower($colour);
            $seen[$key] = $colour;
        }
    }

    foreach ($fileErrors as $index => $error) {
        if ((int)$error !== 0) {
            continue;
        }

        $colour = trim((string)($colourNames[$index] ?? ''));
        if ($colour === '') {
            return 'Enter a colour name for every textile image.';
        }

        $key = function_exists('mb_strtolower') ? mb_strtolower($colour, 'UTF-8') : strtolower($colour);
        if (isset($seen[$key])) {
            return "Colour name \"$colour\" already exists in this product. Use a unique colour name.";
        }
        $seen[$key] = $colour;
    }

    return '';
}

// 2. HANDLE UPDATES
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // A. UPDATE TEXT DETAILS
    if (isset($_POST['update_details'])) {
        $name = $conn->real_escape_string($_POST['name']);
        $category = $conn->real_escape_string($_POST['category']);
        $buy_price = $_POST['buy_price'];
        $sell_price = $_POST['sell_price'];
        $quantity = $_POST['quantity'];
        $min_stock = $_POST['min_stock'];

        $sql = "UPDATE products SET name='$name', category='$category', buy_price='$buy_price', sell_price='$sell_price', quantity='$quantity', min_stock='$min_stock' WHERE id='$id'";
        
        if ($conn->query($sql)) {
            // Re-sync just in case user tried to manually change quantity for a Cap
            syncCapQuantity($conn, $id);
            $swal_json = json_encode(['icon'=>'success', 'title'=>'Updated!', 'text'=>'Product details updated.']);
        }
    }

    // B. UPLOAD NEW IMAGES
    if (isset($_FILES['new_images'])) {
        $target_dir = "../uploads/";
        $count = count($_FILES['new_images']['name']);
        $product_category_row = $conn->query("SELECT category FROM products WHERE id='$id' LIMIT 1")->fetch_assoc();
        $is_textile_upload = !((stripos((string)($product_category_row['category'] ?? ''), 'cap') !== false));
        $new_image_color_names = $_POST['new_image_color_name'] ?? [];
        $new_image_yards = $_POST['new_image_yards'] ?? [];
        if (!is_array($new_image_color_names)) $new_image_color_names = [];
        if (!is_array($new_image_yards)) $new_image_yards = [];
        $colour_error = '';
        if ($is_textile_upload && !empty($_FILES['new_images']['name'][0])) {
            $colour_error = validate_unique_new_textile_colours($conn, $id, $_FILES['new_images']['error'], $new_image_color_names);
        }
        
        if ($colour_error !== '') {
            $swal_json = json_encode(['icon'=>'error', 'title'=>'Duplicate Colour', 'text'=>$colour_error]);
        } else {
        for ($i = 0; $i < $count; $i++) {
            if ($_FILES['new_images']['error'][$i] == 0) {
                $ext = strtolower(pathinfo($_FILES['new_images']['name'][$i], PATHINFO_EXTENSION));
                $new_name = "prod_" . $id . "_" . uniqid() . "." . $ext;
                
                if (move_uploaded_file($_FILES['new_images']['tmp_name'][$i], $target_dir . $new_name)) {
                    $color_name = '';
                    $yards_sql = 'NULL';
                    if ($is_textile_upload) {
                        $color_name = $conn->real_escape_string(trim((string)($new_image_color_names[$i] ?? '')));
                        $yards_value = max(0, (float)($new_image_yards[$i] ?? 0));
                        $yards_sql = "'" . $yards_value . "'";
                    }
                    $color_sql = $color_name !== '' ? "'" . $color_name . "'" : "NULL";
                    $conn->query("INSERT INTO product_images (product_id, image_name, color_name, yards, status) VALUES ('$id', '$new_name', $color_sql, $yards_sql, 'available')");
                }
            }
        }
        
        // Auto-Update Quantity after upload
        syncCapQuantity($conn, $id);

        if($count > 0 && $_FILES['new_images']['name'][0] != "") {
             header("Location: update_product.php?id=$id");
             exit();
        }
        }
    }
}

// 3. HANDLE IMAGE ACTIONS (Mark Sold / Delete)
if (isset($_GET['action']) && isset($_GET['img_id'])) {
    $img_id = $_GET['img_id'];
    $is_ajax = (isset($_GET['ajax']) && $_GET['ajax'] == '1');
    
    if ($_GET['action'] == 'toggle_status') {
        // Toggle Status
        $conn->query("UPDATE product_images SET status = IF(status='available', 'sold_out', 'available') WHERE id='$img_id'");
        // Auto-Update Quantity after toggle
        syncCapQuantity($conn, $id);

        if ($is_ajax) {
            header('Content-Type: application/json');
            $status_row = $conn->query("SELECT status FROM product_images WHERE id='$img_id'")->fetch_assoc();
            $qty_row = $conn->query("SELECT quantity FROM products WHERE id='$id'")->fetch_assoc();
            echo json_encode([
                'success' => true,
                'status' => $status_row['status'] ?? 'available',
                'quantity' => $qty_row['quantity'] ?? null
            ]);
            exit();
        }
    } 
    elseif ($_GET['action'] == 'delete_img') {
        // Delete
        $res = $conn->query("SELECT image_name FROM product_images WHERE id='$img_id'");
        $row = $res->fetch_assoc();
        if ($row) {
            $file_path = "../uploads/" . $row['image_name'];
            if(file_exists($file_path)) unlink($file_path);
            $conn->query("DELETE FROM product_images WHERE id='$img_id'");
        }
        // Auto-Update Quantity after delete
        syncCapQuantity($conn, $id);
    }
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit();
    }

    header("Location: update_product.php?id=$id");
    exit();
}

// 4. FETCH DATA
$product = $conn->query("SELECT * FROM products WHERE id='$id'")->fetch_assoc();
$images = $conn->query("SELECT * FROM product_images WHERE product_id='$id'");
$is_cap = (strpos($product['category'], 'Cap') !== false);
$total_uploaded_images = $images->num_rows;
$existing_textile_colours = [];
$existing_colour_res = $conn->query("SELECT color_name FROM product_images WHERE product_id='$id' AND color_name IS NOT NULL AND TRIM(color_name) <> ''");
if ($existing_colour_res) {
    while ($colour_row = $existing_colour_res->fetch_assoc()) {
        $existing_textile_colours[] = (string)$colour_row['color_name'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product | Galadawa</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/toastify.min.css">
    <script src="../js/toastify.min.js"></script>
    <script src="../js/toast.js"></script>
    <script src="../js/sweetalert2.all.min.js"></script>
    <style>
        .edit-container { display: grid; grid-template-columns: 1fr 350px; gap: 30px; }
        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        
        .img-item {
            display: flex; align-items: center; justify-content: space-between;
            background: #f8f9fa; padding: 10px; border-radius: 8px; margin-bottom: 10px;
            border: 1px solid #eee;
        }
        .img-preview { width: 50px; height: 50px; object-fit: cover; border-radius: 5px; }
        
        .badge-status { 
            padding: 6px 12px; border-radius: 4px; font-size: 11px; font-weight: bold;
            cursor: pointer; text-decoration: none; display: inline-block; min-width: 80px; text-align: center;
            transition: 0.2s;
        }
        .st-available { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .st-sold { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .btn-trash { color: #e74c3c; cursor: pointer; padding: 8px; margin-left: 5px; transition: 0.2s; background: none; border: none; font-size: 16px; }
        .btn-trash:hover { color: #c0392b; transform: scale(1.1); }
        .new-image-detail-list { display: none; gap: 10px; margin-top: 12px; }
        .new-image-detail-row { display: grid; grid-template-columns: 58px 1fr 130px; gap: 10px; align-items: center; border: 1px solid #e5e7eb; border-radius: 10px; padding: 10px; background: #f8fafc; }
        .new-image-detail-row img { width: 58px; height: 58px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd; }
        .new-image-detail-row input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; outline: none; }
        .new-image-detail-meta { font-size: 12px; color: #667085; margin-top: 4px; word-break: break-word; }

        .btn-submit { width: 100%; padding: 12px; background: #1e3c72; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; }
        .btn-submit:disabled { background: #7e8a9a; cursor: not-allowed; }
        .btn-back { display: inline-block; margin-bottom: 20px; color: #666; text-decoration: none; font-weight: 600; }
        
        /* Disabled Input Style */
        .input-disabled { background: #e9ecef; color: #6c757d; cursor: not-allowed; }
        
        @media (max-width: 900px) {
            .edit-container { grid-template-columns: 1fr; }
            .new-image-detail-row { grid-template-columns: 58px 1fr; }
            .new-image-detail-row .yards-field { grid-column: 1 / -1; }
        }
    </style>
</head>
<body class="admin-mobile-ui">
<?php $is_dashboard = true; include '../includes/topbar.php'; ?>

    <!-- <div id="loader-wrapper"><img src="../img/logo.png" class="loader-logo"></div> -->

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
            <div class="header-title">Update Product</div>
            <a href="product_details.php?id=<?php echo $id; ?>" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Details</a>

            <div class="edit-container">
                
                <div class="card">
                    <form action="" method="POST" enctype="multipart/form-data">
                        <h4 style="margin-bottom: 20px; color: #1e3c72; border-bottom: 1px solid #eee; padding-bottom: 10px;">Edit Details</h4>
                        
                        <div class="input-box" style="margin-bottom: 15px;">
                            <label style="display:block; font-weight:500; margin-bottom:5px;">Product Name</label>
                            <input type="text" name="name" value="<?php echo $product['name']; ?>" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;">
                        </div>

                        <div class="input-box" style="margin-bottom: 15px;">
                            <label style="display:block; font-weight:500; margin-bottom:5px;">Category</label>
                            <select name="category" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;">
                                <option value="<?php echo $product['category']; ?>"><?php echo $product['category']; ?></option>
                                <optgroup label="Change To:">
                                    <option value="Lace">Lace</option>
                                    <option value="Swiss Lace">Swiss Lace</option>
                                    <option value="Atampa">Atampa</option>
                                    <option value="Yard">Yard</option>
                                    <option value="Zanna Cap">Zanna Cap</option>
                                    <option value="Tangaran Cap">Tangaran Cap</option>
                                    <option value="Bama Cap">Bama Cap</option>
                                </optgroup>
                            </select>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div class="input-box">
                                <label style="display:block; font-weight:500; margin-bottom:5px;">Cost Price</label>
                                <input type="number" name="buy_price" value="<?php echo $product['buy_price']; ?>" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;">
                            </div>
                            <div class="input-box">
                                <label style="display:block; font-weight:500; margin-bottom:5px;">Selling Price</label>
                                <input type="number" name="sell_price" value="<?php echo $product['sell_price']; ?>" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px;">
                            <div class="input-box">
                                <label style="display:block; font-weight:500; margin-bottom:5px;">Quantity 
                                    <?php if($is_cap): ?> <small style="color:#28a745;">(Auto-Synced)</small> <?php endif; ?>
                                </label>
                                <input type="number" step="0.01" name="quantity" value="<?php echo $product['quantity']; ?>" 
                                       id="qtyInput"
                                       style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;"
                                       class="<?php echo $is_cap ? 'input-disabled' : ''; ?>"
                                       <?php echo $is_cap ? 'readonly' : ''; ?>>
                            </div>
                            <div class="input-box">
                                <label style="display:block; font-weight:500; margin-bottom:5px;">Min Alert</label>
                                <input type="number" name="min_stock" value="<?php echo $product['min_stock']; ?>" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;">
                            </div>
                        </div>

                        <div class="input-box" style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
                            <label style="display:block; font-weight:500; margin-bottom:5px;">Add More Images</label>
                            <input type="file" name="new_images[]" id="newImagesInput" multiple accept="image/*" style="width:100%; padding:10px; background:#f9f9f9; border-radius:5px;">
                            <small id="imageCountInfo" style="display:block; margin-top:8px; color:#555; font-weight:600;">
                                Total uploaded images: <?php echo $total_uploaded_images; ?>
                            </small>
                            <small id="imageCountPreview" style="display:none; margin-top:4px; color:#1e3c72; font-weight:700;"></small>
                            <div id="newImageDetailList" class="new-image-detail-list"></div>
                        </div>

                        <button type="submit" name="update_details" class="btn-submit" style="margin-top: 20px;">Save Changes</button>
                    </form>
                </div>

                <div class="card">
                    <h4 style="margin-bottom: 20px; color: #1e3c72; border-bottom: 1px solid #eee; padding-bottom: 10px;">Manage Colors</h4>
                    <p style="font-size: 13px; color: #666; margin-bottom: 15px;">Click status to mark as sold. Quantity updates automatically.</p>

                    <div style="max-height: 500px; overflow-y: auto;">
                        <?php if ($images->num_rows > 0): ?>
                            <?php while($img = $images->fetch_assoc()): ?>
	                                <div class="img-item">
	                                    <div style="display:flex; align-items:center;">
	                                        <img src="../uploads/<?php echo $img['image_name']; ?>" class="img-preview">
                                            <?php if (!$is_cap && (!empty($img['color_name']) || $img['yards'] !== null)): ?>
                                                <div style="margin-left:10px;">
                                                    <div style="font-weight:700; color:#24303d;"><?php echo !empty($img['color_name']) ? htmlspecialchars((string)$img['color_name'], ENT_QUOTES, 'UTF-8') : 'Unnamed colour'; ?></div>
                                                    <div style="font-size:12px; color:#667085;"><?php echo $img['yards'] !== null ? number_format((float)$img['yards'], 2) . ' yards' : 'No yards set'; ?></div>
                                                </div>
                                            <?php endif; ?>
	                                    </div>
                                    
                                    <div style="display:flex; align-items:center;">
                                        <a href="update_product.php?id=<?php echo $id; ?>&action=toggle_status&img_id=<?php echo $img['id']; ?>" 
                                           class="badge-status js-toggle-status <?php echo ($img['status']=='sold_out') ? 'st-sold' : 'st-available'; ?>"
                                           data-img-id="<?php echo $img['id']; ?>">
                                            <?php echo ($img['status']=='sold_out') ? 'SOLD OUT' : 'Available'; ?>
                                        </a>

                                        <button type="button" class="btn-trash" 
                                                onclick="confirmDelete('update_product.php?id=<?php echo $id; ?>&action=delete_img&img_id=<?php echo $img['id']; ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p style="color: #999; text-align: center; padding: 20px;">No images uploaded yet.</p>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('collapsed'); }
        window.addEventListener('pageshow', function() {
            const loader = document.getElementById('loader-wrapper');
            if (loader) loader.classList.add('loader-hidden');
        });

        const newImagesInput = document.getElementById('newImagesInput');
        const imageCountPreview = document.getElementById('imageCountPreview');
        const newImageDetailList = document.getElementById('newImageDetailList');
        const qtyInput = document.getElementById('qtyInput');
        const existingTotalImages = <?php echo (int)$total_uploaded_images; ?>;
        const isCapProduct = <?php echo $is_cap ? 'true' : 'false'; ?>;
        const baseCapQty = <?php echo (float)$product['quantity']; ?>;
        const existingTextileColours = new Set(<?php echo json_encode(array_map('strtolower', $existing_textile_colours)); ?>);
        let hasDuplicateNewColour = false;

        function findSaveButton() {
            return document.querySelector('button[name="update_details"]');
        }

        function syncUpdateSubmitState() {
            const button = findSaveButton();
            if (button) {
                button.disabled = hasDuplicateNewColour;
            }
        }

        function calculateNewTextileYards() {
            if (!newImageDetailList || !qtyInput) return;
            let addedYards = 0;
            newImageDetailList.querySelectorAll('.js-new-image-yards').forEach((input) => {
                addedYards += Math.max(0, Number(input.value || 0));
            });
            qtyInput.value = addedYards > 0 ? (baseCapQty + addedYards).toFixed(2) : baseCapQty;
        }

        function validateNewTextileColours() {
            hasDuplicateNewColour = false;
            if (!newImageDetailList || isCapProduct) {
                syncUpdateSubmitState();
                return;
            }

            const seen = new Set(existingTextileColours);
            newImageDetailList.querySelectorAll('.js-new-image-color').forEach((input) => {
                const key = (input.value || '').trim().toLowerCase();
                input.style.borderColor = '#ddd';
                if (!key) return;
                if (seen.has(key)) {
                    hasDuplicateNewColour = true;
                    input.style.borderColor = '#dc3545';
                }
                seen.add(key);
            });

            syncUpdateSubmitState();
        }

        function renderNewImageDetails() {
            if (!newImagesInput || !newImageDetailList) return;
            newImageDetailList.innerHTML = '';

            if (isCapProduct || !newImagesInput.files || newImagesInput.files.length === 0) {
                newImageDetailList.style.display = 'none';
                hasDuplicateNewColour = false;
                syncUpdateSubmitState();
                return;
            }

            newImageDetailList.style.display = 'grid';
            Array.from(newImagesInput.files).forEach((file) => {
                const row = document.createElement('div');
                const previewUrl = URL.createObjectURL(file);
                row.className = 'new-image-detail-row';
                row.innerHTML = `
                    <img src="${previewUrl}" alt="">
                    <div>
                        <input type="text" name="new_image_color_name[]" class="js-new-image-color" value="Colour " placeholder="Colour name" required>
                        <div class="new-image-detail-meta">${file.name}</div>
                    </div>
                    <div class="yards-field">
                        <input type="number" name="new_image_yards[]" class="js-new-image-yards" min="0.01" step="0.01" placeholder="Available yards" required>
                    </div>
                `;
                newImageDetailList.appendChild(row);
            });

            newImageDetailList.querySelectorAll('.js-new-image-yards').forEach((input) => {
                input.addEventListener('input', calculateNewTextileYards);
            });
            newImageDetailList.querySelectorAll('.js-new-image-color').forEach((input) => {
                input.addEventListener('input', validateNewTextileColours);
            });
            calculateNewTextileYards();
            validateNewTextileColours();
        }

        if (newImagesInput) {
            newImagesInput.addEventListener('change', function() {
                const selectedCount = this.files ? this.files.length : 0;

                if (selectedCount > 0) {
                    const liveTotal = existingTotalImages + selectedCount;
	                    imageCountPreview.style.display = 'block';
	                    imageCountPreview.textContent = isCapProduct
                            ? `Selected: ${selectedCount}. New total after save: ${liveTotal}.`
                            : `Selected: ${selectedCount}. Enter colour details below.`;

                    if (isCapProduct && qtyInput) {
                        qtyInput.value = baseCapQty + selectedCount;
                    } else {
                        renderNewImageDetails();
                    }
                } else {
                    imageCountPreview.style.display = 'none';
                    imageCountPreview.textContent = '';

                    if (isCapProduct && qtyInput) {
                        qtyInput.value = baseCapQty;
                    } else if (qtyInput) {
                        qtyInput.value = baseCapQty;
                    }
                    renderNewImageDetails();
                }
            });
        }

        const updateDetailsForm = document.querySelector('form[enctype="multipart/form-data"]');
        if (updateDetailsForm) {
            updateDetailsForm.addEventListener('submit', function(event) {
                validateNewTextileColours();
                if (hasDuplicateNewColour) {
                    event.preventDefault();
                    if (window.showToast) {
                        showToast('Duplicate Colour - Use a unique colour name for this product.', { type: 'error', duration: 3500 });
                    }
                }
            });
        }

        document.querySelectorAll('.js-toggle-status').forEach(function(el) {
            el.addEventListener('click', function(e) {
                e.preventDefault();
                const imgId = this.dataset.imgId;
                if (!imgId || this.dataset.loading === '1') return;

                this.dataset.loading = '1';
                fetch(`update_product.php?id=<?php echo $id; ?>&action=toggle_status&img_id=${encodeURIComponent(imgId)}&ajax=1`)
                    .then(response => response.json())
                    .then(data => {
                        if (!data || !data.success) return;

                        const isSold = data.status === 'sold_out';
                        this.textContent = isSold ? 'SOLD OUT' : 'Available';
                        this.classList.toggle('st-sold', isSold);
                        this.classList.toggle('st-available', !isSold);

                        if (qtyInput && isCapProduct && data.quantity !== null && data.quantity !== undefined) {
                            qtyInput.value = data.quantity;
                        }
                    })
                    .catch(() => {
                        if (window.showToast) {
                            showToast('Could not update image status right now.', { type: 'error', duration: 2500 });
                        }
                    })
                    .finally(() => {
                        this.dataset.loading = '0';
                    });
            });
        });
        
        // --- CUSTOM SWEETALERT FOR DELETE ---
        function confirmDelete(url) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e74c3c', // Red for delete
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
        }

        <?php if (!empty($swal_json)): ?>
            const swalData = <?php echo $swal_json; ?>;
            if (window.showToast) {
                showToast(swalData.title + " - " + swalData.text, { type: swalData.icon || "success", duration: 2000 });
            }
        <?php endif; ?>
    </script>
</body>
</html>
