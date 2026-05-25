<?php
session_start();
include '../includes/db_connect.php';

if (!isset($_GET['id'])) { die("Receipt ID missing."); }

$sale_id = $_GET['id'];

// 1. FETCH DATA
$sale = $conn->query("SELECT * FROM sales WHERE id = '$sale_id'")->fetch_assoc();
$user = $conn->query("SELECT username FROM users WHERE id = '".$sale['user_id']."'")->fetch_assoc();
$payment_method_labels = [
    'cash' => 'Cash',
    'transfer' => 'Transfer',
    'pos' => 'Bank Card',
];
$payment_method_label = $payment_method_labels[(string)($sale['payment_method'] ?? 'cash')] ?? 'Cash';

$sql_items = "SELECT si.*, p.name 
              FROM sale_items si 
              JOIN products p ON si.product_id = p.id 
              WHERE si.sale_id = '$sale_id'";
$items = $conn->query(query: $sql_items);
$display_items = [];
while($row = $items->fetch_assoc()) { $display_items[] = $row; }

// 2. HOST CHECK
$whitelist = array('127.0.0.1', '::1', 'localhost');
$is_localhost = in_array($_SERVER['REMOTE_ADDR'], $whitelist);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?php echo $sale_id; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <style>
        body { font-family: 'Courier New', Courier, monospace; font-size: 12px; margin: 0; padding: 20px; background: #eee; display: flex; flex-direction: column; align-items: center; }
        
        /* THE ACTUAL RECEIPT PAPER */
        #receipt-paper {
            background: white; padding: 45px; width: 200px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1); margin-bottom: 20px; color: #000;
        }

        .header { text-align: center; margin-bottom: 10px; border-bottom: 1px dashed #000; padding-bottom: 10px; }
        .logo { font-size: 18px; font-weight: bold; text-transform: uppercase; }
        .info { font-size: 10px; margin-bottom: 5px; }
        
        .item-list { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .item-list th { text-align: left; border-bottom: 1px solid #000; font-size: 10px; }
        .item-list td { padding: 4px 0; vertical-align: top; }
        
        .total-section { border-top: 1px dashed #000; padding-top: 5px; text-align: right; font-size: 14px; font-weight: bold; }
        .footer { text-align: center; margin-top: 20px; font-size: 10px; }

        /* BUTTONS */
        .no-print { width: 300px; display: flex; flex-direction: column; gap: 10px; }
        .btn { padding: 12px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; font-family: sans-serif; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; font-size: 13px; }
        .btn-print { background: #333; color: white; }
        .btn-img { background: #6f42c1; color: white; }
        .btn-share { background: #007bff; color: white; }
        .btn-close { background: #ddd; color: #333; }
        .btn:hover { opacity: 0.9; }

        @media print { 
            body { background: white; padding: 0; }
            .no-print { display: none; } 
            #receipt-paper { box-shadow: none; margin: 0; }
        }
    </style>
</head>
<body>

    <div id="receipt-paper">
        <div class="header">
            <div class="logo">Galadawa Textiles</div>
            <div class="info">Gusau, Zamfara State</div>
            <div class="info">Tel: +234 803 526 5065</div>
            <br>
            <div class="info">Receipt #: <?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?></div>
            <div class="info">Date: <?php echo date('d-M-Y h:i A', strtotime($sale['created_at'])); ?></div>
            <div class="info">Attendant: <?php echo $user['username']; ?></div>
            <div class="info">Payment: <?php echo htmlspecialchars($payment_method_label, ENT_QUOTES, 'UTF-8'); ?></div>
        </div>

        <table class="item-list">
            <thead>
                <tr>
                    <th width="50%">Item</th>
                    <th width="15%">Qty</th>
                    <th width="35%" style="text-align:right">Total Cost</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($display_items as $row): ?>
                <tr>
                    <td><?php echo $row['name']; ?></td>
                    <td><?php echo $row['quantity'] + 0; ?></td>
                    <td style="text-align:right"><?php echo '₦ ', number_format(num: $row['subtotal']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="total-section">
            TOTAL: ₦ <?php echo number_format($sale['total_amount']); ?>
        </div>

        <div class="footer">
            <p>Exchange only with store approval.</p>
            <p>Thanks for your patronage!</p>
        </div>
    </div>

    <div class="no-print">

        <button onclick="saveAsImage()" class="btn btn-img">
            <i class="fas fa-download"></i> Download Image
        </button>

        <button onclick="window.print()" class="btn btn-print">
            <i class="fas fa-print"></i> Print Receipt
        </button>

        <button onclick="shareReceipt()" class="btn btn-share">
            <i class="fas fa-share-alt"></i> Share Image (Bluetooth/Apps)
        </button>
        
        <button onclick="window.close()" class="btn btn-close">Close</button>
    </div>

    <script>
        // FUNCTION 1: SHARE VIA BLUETOOTH / APPS
        async function shareReceipt() {
            const receipt = document.getElementById("receipt-paper");
            
            try {
                // 1. Generate Image Blob
                const canvas = await html2canvas(receipt);
                const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
                
                // 2. Create File Object
                const file = new File([blob], "receipt_<?php echo $sale['id']; ?>.png", { type: "image/png" });

                // 3. Check if Browser Supports Sharing Files
                if (navigator.canShare && navigator.canShare({ files: [file] })) {
                    await navigator.share({
                        files: [file],
                        title: 'Receipt #<?php echo $sale['id']; ?>',
                        text: 'Here is your receipt from Galadawa Textiles.'
                    });
                } else {
                    alert("Sharing not supported on this device/browser. Try Downloading instead.");
                }
            } catch (err) {
                console.error("Share failed:", err);
                // Fallback: If share fails (e.g. on Desktop), just download
                saveAsImage();
            }
        }

        // FUNCTION 2: DOWNLOAD IMAGE ONLY
        function saveAsImage() {
            const receipt = document.getElementById("receipt-paper");
            html2canvas(receipt).then(canvas => {
                const link = document.createElement("a");
                link.download = "receipt_<?php echo $sale['id']; ?>.png";
                link.href = canvas.toDataURL("image/png");
                link.click();
            });
        }
    </script>

</body>
</html>
