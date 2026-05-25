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

function validate_unique_uploaded_textile_colours($fileErrors, $colourNames) {
    $seen = [];
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
            return "Colour name \"$colour\" is already used in this product. Use a unique colour name for each image.";
        }
        $seen[$key] = true;
    }

    return '';
}

if (isset($_GET['check_duplicate'])) {
    header('Content-Type: application/json');

    $name_check = trim($_GET['name'] ?? '');
    $category_check = trim($_GET['category'] ?? '');
    $is_cap_check = (stripos($category_check, 'cap') !== false);

    if ($name_check === '' || $category_check === '' || !$is_cap_check) {
        echo json_encode(['duplicate' => false]);
        exit();
    }

    $dup_stmt = $conn->prepare("SELECT id FROM products WHERE LOWER(name) = LOWER(?) AND LOWER(category) = LOWER(?) LIMIT 1");
    if (!$dup_stmt) {
        echo json_encode(['duplicate' => false]);
        exit();
    }

    $dup_stmt->bind_param("ss", $name_check, $category_check);
    $dup_stmt->execute();
    $dup_stmt->store_result();
    $is_duplicate = ($dup_stmt->num_rows > 0);
    $dup_stmt->close();

    echo json_encode(['duplicate' => $is_duplicate]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $conn->real_escape_string($_POST['name']);
    $category = $conn->real_escape_string($_POST['category']);
    $buy_price = $_POST['buy_price'];
    $sell_price = $_POST['sell_price'];
    $min_stock = $_POST['min_stock'];
    $is_cap_category = (stripos($category, 'cap') !== false);
    $is_textile_category = !$is_cap_category;
    $image_color_names = $_POST['image_color_name'] ?? [];
    $image_yards = $_POST['image_yards'] ?? [];
    if (!is_array($image_color_names)) $image_color_names = [];
    if (!is_array($image_yards)) $image_yards = [];

    // --- SMART QUANTITY LOGIC ---
    // 1. If it's a Cap, we ignore the input and count the images
    if ($is_cap_category && isset($_FILES['product_images'])) {
        // Count uploaded files (minus any errors)
        $quantity = 0;
        foreach($_FILES['product_images']['error'] as $err) {
            if ($err == 0) $quantity++;
        }
    } elseif ($is_textile_category && isset($_FILES['product_images']) && !empty($_FILES['product_images']['name'][0])) {
        $quantity = 0;
        foreach($_FILES['product_images']['error'] as $i => $err) {
            if ($err == 0) {
                $quantity += max(0, (float)($image_yards[$i] ?? 0));
            }
        }
    } else {
        // 2. If it's Textile, use the manual input (Yards)
        $quantity = $_POST['quantity']; 
    }

    $has_duplicate_cap = false;

    // Prevent duplicate cap entries with same name + category.
    // Textile categories are allowed to repeat.
    if ($is_cap_category) {
        $dup_stmt = $conn->prepare("SELECT id FROM products WHERE LOWER(name) = LOWER(?) AND LOWER(category) = LOWER(?) LIMIT 1");
        if ($dup_stmt) {
            $dup_stmt->bind_param("ss", $name, $category);
            $dup_stmt->execute();
            $dup_stmt->store_result();
            $has_duplicate_cap = ($dup_stmt->num_rows > 0);
            $dup_stmt->close();
        }
    }

    $textile_colour_error = '';
    if ($is_textile_category && isset($_FILES['product_images']) && !empty($_FILES['product_images']['name'][0])) {
        $textile_colour_error = validate_unique_uploaded_textile_colours($_FILES['product_images']['error'], $image_color_names);
    }

    if ($has_duplicate_cap) {
        $swal_json = json_encode([
            'icon' => 'error',
            'title' => 'Duplicate Not Allowed',
            'text' => 'A cap with this same product name and category already exists.'
        ]);
    } elseif ($textile_colour_error !== '') {
        $swal_json = json_encode([
            'icon' => 'error',
            'title' => 'Duplicate Colour',
            'text' => $textile_colour_error
        ]);
    } else {
        // 1. INSERT PRODUCT
        $sql = "INSERT INTO products (name, category, buy_price, sell_price, quantity, min_stock) 
                VALUES ('$name', '$category', '$buy_price', '$sell_price', '$quantity', '$min_stock')";

        if ($conn->query($sql) === TRUE) {
        $product_id = $conn->insert_id;

        // 2. HANDLE IMAGES
        if (isset($_FILES['product_images'])) {
            $target_dir = "../uploads/";
            $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
            $file_count = count($_FILES['product_images']['name']);

            for ($i = 0; $i < $file_count; $i++) {
                if ($_FILES['product_images']['error'][$i] == 0) {
                    $file_ext = strtolower(pathinfo($_FILES['product_images']['name'][$i], PATHINFO_EXTENSION));

                    if (in_array($file_ext, $allowed_ext)) {
                        $new_name = "prod_" . $product_id . "_" . uniqid() . "." . $file_ext;
                        $target_file = $target_dir . $new_name;

                        if (move_uploaded_file($_FILES['product_images']['tmp_name'][$i], $target_file)) {
                            $color_name = '';
                            $yards_sql = 'NULL';
                            if ($is_textile_category) {
                                $color_name = $conn->real_escape_string(trim((string)($image_color_names[$i] ?? '')));
                                $yards_value = max(0, (float)($image_yards[$i] ?? 0));
                                $yards_sql = "'" . $yards_value . "'";
                            }
                            $color_sql = $color_name !== '' ? "'" . $color_name . "'" : "NULL";
                            $conn->query("INSERT INTO product_images (product_id, image_name, color_name, yards, status) VALUES ('$product_id', '$new_name', $color_sql, $yards_sql, 'available')");
                        }
                    }
                }
            }
        }

            $swal_json = json_encode(['icon' => 'success', 'title' => 'Saved!', 'text' => "Product added. Initial Stock: $quantity"]);
        } else {
            $swal_json = json_encode(['icon' => 'error', 'title' => 'Error', 'text' => $conn->error]);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product | Galadawa</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/toastify.min.css">
    <script src="../js/toastify.min.js"></script>
    <script src="../js/toast.js"></script>
    <style>
        .form-card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); max-width: 900px; margin: 0 auto; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        .full-width { grid-column: span 2; }
        .input-box label { display: block; color: #555; margin-bottom: 8px; font-weight: 500; }
        .input-box input, .input-box select { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; outline: none; }
        
        .file-upload-wrapper { border: 2px dashed #ddd; padding: 20px; text-align: center; border-radius: 8px; cursor: pointer; transition: 0.3s; background: #fafafa; }
        .file-upload-wrapper:hover { border-color: #1e3c72; background: #f0f4f8; }
        .image-detail-list { display: none; grid-column: span 2; gap: 12px; }
        .image-detail-row { display: grid; grid-template-columns: 72px 1fr 160px; gap: 12px; align-items: center; border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px; background: #f8fafc; }
        .image-detail-row img { width: 72px; height: 72px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd; }
        .image-detail-row input { width: 100%; padding: 11px; border: 1px solid #ddd; border-radius: 8px; outline: none; }
        .image-detail-meta { font-size: 12px; color: #667085; margin-top: 4px; word-break: break-word; }
        .btn-submit { width: 100%; padding: 15px; background: #1e3c72; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; }
        .btn-submit:disabled { background: #7e8a9a; cursor: not-allowed; }
        .field-error { color: #dc3545; margin-top: 6px; display: none; font-weight: 600; }
        
        /* Disabled Input Style (Grayed out) */
        .input-disabled { background-color: #e9ecef; color: #6c757d; pointer-events: none; }

        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .full-width,
            .image-detail-list { grid-column: span 1; }
            .image-detail-row { grid-template-columns: 64px 1fr; }
            .image-detail-row .yards-field { grid-column: 1 / -1; }
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
            <a href="add_product.php" class="active"><i class="fas fa-plus-circle"></i> <span>Add Product</span></a>
            <a href="view_inventory.php"><i class="fas fa-box"></i> <span>View Inventory</span></a>
            <a href="manage_users.php"><i class="fas fa-users"></i> <span>Manage Users</span></a>
            <a href="holding_orders.php"><i class="fas fa-boxes-stacked"></i> <span>Holding Orders</span></a>
            <a href="exchange_history.php"><i class="fas fa-right-left"></i> <span>Exchanges</span></a>
            <a href="payout_requests.php"><i class="fas fa-wallet"></i> <span>Payout Requests</span><?php if ($admin_payout_unread > 0): ?><span class="notif-dot"></span><?php endif; ?></a>
            <a href="transaction_history.php"><i class="fas fa-history"></i> <span>Transactions</span></a>
        </div>

        <div class="main-content">
            <div class="header-title">Add New Product</div>
            <div class="form-card">
                <form id="addProductForm" action="" method="POST" enctype="multipart/form-data">
                    <div class="form-grid">
                        
                        <div class="input-box full-width">
                            <label>Product Name</label>
                            <input type="text" name="name" id="nameInput" placeholder="e.g. Royal Blue Swiss Lace" required>
                            <small id="duplicateError" class="field-error">A cap with this same product name and category already exists.</small>
                        </div>

                        <div class="input-box">
                            <label>Category</label>
                            <select name="category" id="categorySelect" onchange="updateFormLogic()" required>
                                <optgroup label="Textiles">
                                    <option value="Lace">Lace</option>
                                    <option value="Ankara">Swiss Lace</option>
                                    <option value="Atamfa">Atamfa</option>
                                    <option value="Shadda">Shadda</option>
                                    <option value="Yard">Yard</option>
                                </optgroup>
                                <optgroup label="Caps (Hula)">
                                    <option value="Zanna Cap">Zanna Cap</option>
                                    <option value="Tangaran Cap">Tangaran Cap</option>
                                    <option value="Bama Cap">Bama Cap</option>
                                    <option value="Maitama Cap">Maitama Cap</option>
                                </optgroup>
                            </select>
                        </div>

                        <div class="input-box">
                            <label>Product Gallery</label>
                            <div class="file-upload-wrapper" onclick="document.getElementById('fileInput').click()">
                                <i class="fas fa-images fa-2x" style="color: #1e3c72;"></i>
                                <input type="file" name="product_images[]" id="fileInput" accept="image/*" multiple style="display: none;" onchange="handleFileSelect()">
                            </div>
                            <small id="fileLabel" style="color: #28a745; display:none; margin-top:5px; font-weight:bold;"></small>
                        </div>

                        <div id="imageDetailList" class="image-detail-list"></div>

                        <div class="input-box">
                            <label id="qtyLabel">Quantity (Yards)</label>
                            <input type="number" step="0.01" name="quantity" id="qtyInput" placeholder="50.0" required>
                            <small id="autoCalcMsg" style="color: #28a745; display:none;">Auto-calculated from images</small>
                        </div>

                        <div class="input-box"><label>Cost Price</label><input type="number" name="buy_price" required></div>
                        <div class="input-box"><label>Selling Price</label><input type="number" name="sell_price" required></div>

                        <div class="input-box full-width">
                            <label>Low Stock Alert</label>
                            <input type="number" name="min_stock" value="10" required>
                        </div>

                        <button type="submit" id="submitBtn" class="btn-submit full-width">Save Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('collapsed'); }
        window.addEventListener('pageshow', function() {
            const loader = document.getElementById('loader-wrapper');
            if (loader) loader.classList.add('loader-hidden');
        });

        const categorySelect = document.getElementById('categorySelect');
        const nameInput = document.getElementById('nameInput');
        const duplicateError = document.getElementById('duplicateError');
        const submitBtn = document.getElementById('submitBtn');
        const addProductForm = document.getElementById('addProductForm');
        const imageDetailList = document.getElementById('imageDetailList');
        let duplicateCheckTimer = null;
        let duplicateRequestToken = 0;
        let hasDuplicateCap = false;
        let hasDuplicateTextileColour = false;

        function syncSubmitState() {
            submitBtn.disabled = hasDuplicateCap || hasDuplicateTextileColour;
        }

        function setDuplicateState(isDuplicate) {
            hasDuplicateCap = !!isDuplicate;
            duplicateError.style.display = hasDuplicateCap ? 'block' : 'none';
            syncSubmitState();
        }

        function checkDuplicateRealtime() {
            const category = categorySelect.value || '';
            const name = (nameInput.value || '').trim();
            const isCap = category.includes('Cap');

            if (!isCap || name === '') {
                setDuplicateState(false);
                return;
            }

            const token = ++duplicateRequestToken;
            fetch(`add_product.php?check_duplicate=1&name=${encodeURIComponent(name)}&category=${encodeURIComponent(category)}`)
                .then(response => response.json())
                .then(data => {
                    if (token !== duplicateRequestToken) return;
                    setDuplicateState(!!data.duplicate);
                })
                .catch(() => {
                    if (token !== duplicateRequestToken) return;
                    setDuplicateState(false);
                });
        }

        function scheduleDuplicateCheck() {
            clearTimeout(duplicateCheckTimer);
            duplicateCheckTimer = setTimeout(checkDuplicateRealtime, 250);
        }
        
        // --- AUTO-CALCULATION LOGIC ---
        function updateFormLogic() {
            const cat = categorySelect.value;
            const qtyInput = document.getElementById('qtyInput');
            const qtyLabel = document.getElementById('qtyLabel');
            const fileInput = document.getElementById('fileInput');
            const autoCalcMsg = document.getElementById('autoCalcMsg');

            if (cat.includes('Cap')) {
                // CAP MODE: Quantity comes from images
                qtyLabel.innerText = "Quantity (Pieces - Auto Counted)";
                qtyInput.step = "1";
                
                // If files are already selected, update count immediately
                if(fileInput.files.length > 0) {
                    qtyInput.value = fileInput.files.length;
                    qtyInput.readOnly = true;
                    qtyInput.classList.add('input-disabled');
                    document.getElementById('autoCalcMsg').style.display = 'block';
                } else {
                    // Waiting for files
                    qtyInput.value = "";
                    qtyInput.placeholder = "Upload images to set quantity";
                    qtyInput.readOnly = true; // Still readonly, waiting for upload
                    qtyInput.classList.add('input-disabled');
                }
                autoCalcMsg.style.display = fileInput.files.length > 0 ? 'block' : 'none';

            } else {
                qtyLabel.innerText = "Quantity (Yards)";
                qtyInput.step = "0.01";
                qtyInput.readOnly = false;
                qtyInput.classList.remove('input-disabled');
                qtyInput.placeholder = "50.0";
                autoCalcMsg.style.display = 'none';
            }

            renderImageDetailFields();
            scheduleDuplicateCheck();
        }

        function handleFileSelect() {
            const input = document.getElementById('fileInput');
            const label = document.getElementById('fileLabel');
            const cat = categorySelect.value;
            const qtyInput = document.getElementById('qtyInput');

            // 1. Show file count text
            if(input.files.length > 0){
                label.style.display = 'block';
                label.innerText = input.files.length + " images selected.";
            }

            // 2. If it is a CAP, update Quantity box automatically
            if (cat.includes('Cap')) {
                qtyInput.value = input.files.length;
                document.getElementById('autoCalcMsg').style.display = 'block';
            }

            renderImageDetailFields();
        }

        function isTextileCategory() {
            return !((categorySelect.value || '').includes('Cap'));
        }

        function calculateTextileImageYards() {
            if (!imageDetailList) return;
            const qtyInput = document.getElementById('qtyInput');
            const yardsInputs = imageDetailList.querySelectorAll('.js-image-yards');
            let total = 0;
            yardsInputs.forEach((input) => {
                total += Math.max(0, Number(input.value || 0));
            });
            if (yardsInputs.length > 0 && qtyInput) {
                qtyInput.value = total ? total.toFixed(2) : '';
            }
        }

        function validateTextileColourNames() {
            hasDuplicateTextileColour = false;
            if (!isTextileCategory() || !imageDetailList) {
                syncSubmitState();
                return;
            }

            const seen = new Set();
            imageDetailList.querySelectorAll('.js-image-color').forEach((input) => {
                const key = (input.value || '').trim().toLowerCase();
                input.style.borderColor = '#ddd';
                if (!key) return;
                if (seen.has(key)) {
                    hasDuplicateTextileColour = true;
                    input.style.borderColor = '#dc3545';
                }
                seen.add(key);
            });

            syncSubmitState();
        }

        function renderImageDetailFields() {
            const fileInput = document.getElementById('fileInput');
            const qtyInput = document.getElementById('qtyInput');
            const autoCalcMsg = document.getElementById('autoCalcMsg');
            if (!imageDetailList || !fileInput) return;

            imageDetailList.innerHTML = '';
            if (!isTextileCategory() || !fileInput.files || fileInput.files.length === 0) {
                imageDetailList.style.display = 'none';
                hasDuplicateTextileColour = false;
                syncSubmitState();
                return;
            }

            imageDetailList.style.display = 'grid';
            if (qtyInput) {
                qtyInput.readOnly = true;
                qtyInput.classList.add('input-disabled');
                qtyInput.placeholder = 'Sum of colour yards';
            }
            if (autoCalcMsg) {
                autoCalcMsg.style.display = 'block';
            }

            Array.from(fileInput.files).forEach((file) => {
                const row = document.createElement('div');
                const previewUrl = URL.createObjectURL(file);
                row.className = 'image-detail-row';
                row.innerHTML = `
                    <img src="${previewUrl}" alt="">
                    <div>
                        <input type="text" name="image_color_name[]" class="js-image-color" value="Colour " placeholder="Colour name" required>
                        <div class="image-detail-meta">${file.name}</div>
                    </div>
                    <div class="yards-field">
                        <input type="number" name="image_yards[]" class="js-image-yards" min="0.01" step="0.01" placeholder="Available yards" required>
                    </div>
                `;
                imageDetailList.appendChild(row);
            });

            imageDetailList.querySelectorAll('.js-image-yards').forEach((input) => {
                input.addEventListener('input', calculateTextileImageYards);
            });
            imageDetailList.querySelectorAll('.js-image-color').forEach((input) => {
                input.addEventListener('input', validateTextileColourNames);
            });
            calculateTextileImageYards();
            validateTextileColourNames();
        }

        nameInput.addEventListener('input', scheduleDuplicateCheck);
        categorySelect.addEventListener('change', updateFormLogic);
        addProductForm.addEventListener('submit', function(e) {
            validateTextileColourNames();
            if (hasDuplicateCap || hasDuplicateTextileColour) {
                e.preventDefault();
                if (window.showToast) {
                    const message = hasDuplicateTextileColour
                        ? 'Duplicate Colour - Use a unique colour name for each textile image in this product.'
                        : 'Duplicate Not Allowed - A cap with this same product name and category already exists.';
                    showToast(message, { type: 'error', duration: 3500 });
                }
            }
        });
        
        // Run on load just in case
        updateFormLogic();

        <?php if (!empty($swal_json)): ?>
            const swalData = <?php echo $swal_json; ?>;
            if (window.showToast) {
                showToast(swalData.title + " - " + swalData.text, { type: swalData.icon || "success", duration: 3000 });
            }
        <?php endif; ?>
    </script>
</body>
</html>
