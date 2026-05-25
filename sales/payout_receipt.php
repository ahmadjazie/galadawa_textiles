<?php
session_start();
include '../includes/db_connect.php';
include '../includes/payouts.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }
ensure_payout_table($conn);

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$sql = "SELECT pr.*, u.username, a.username as approved_by_name
        FROM payout_requests pr
        JOIN users u ON pr.user_id = u.id
        LEFT JOIN users a ON pr.reviewed_by = a.id
        WHERE pr.id = '$id'";
$res = $conn->query($sql);
$payout = $res ? $res->fetch_assoc() : null;

if (!$payout) { echo "Payout not found."; exit(); }
if ($role !== 'admin' && $payout['user_id'] != $user_id) { echo "Access denied."; exit(); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payout Receipt #<?php echo $payout['id']; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <style>
        body { font-family: 'Courier New', Courier, monospace; font-size: 12px; margin: 0; padding: 20px; background: #eee; display: flex; flex-direction: column; align-items: center; }
        #receipt-paper { background: white; padding: 15px; width: 300px; box-shadow: 0 0 10px rgba(0,0,0,0.1); margin-bottom: 20px; color: #000; }
        .header { text-align: center; margin-bottom: 10px; border-bottom: 1px dashed #000; padding-bottom: 10px; }
        .logo { font-size: 18px; font-weight: bold; text-transform: uppercase; }
        .info { font-size: 10px; margin-bottom: 5px; }
        .item-list { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .item-list th { text-align: left; border-bottom: 1px solid #000; font-size: 10px; }
        .item-list td { padding: 4px 0; vertical-align: top; }
        .total-section { border-top: 1px dashed #000; padding-top: 5px; text-align: right; font-size: 14px; font-weight: bold; }
        .footer { text-align: center; margin-top: 20px; font-size: 10px; }
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
            <div class="info">Tel: +234 800 000 0000</div>
            <br>
            <div class="info">Payout #: <?php echo str_pad($payout['id'], 6, '0', STR_PAD_LEFT); ?></div>
            <div class="info">Date: <?php echo date('d-M-Y h:i A', strtotime($payout['requested_at'])); ?></div>
            <div class="info">Attendant: <?php echo htmlspecialchars($payout['username']); ?></div>
            <div class="info">Status: <?php echo ucfirst($payout['status']); ?></div>
        </div>

        <table class="item-list">
            <thead>
                <tr>
                    <th width="50%">Description</th>
                    <th width="50%" style="text-align:right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Payout Request</td>
                    <td style="text-align:right">₦ <?php echo number_format($payout['amount']); ?></td>
                </tr>
                <?php if (!empty($payout['note'])): ?>
                <tr>
                    <td colspan="2">Bank Details: <?php echo htmlspecialchars($payout['note']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($payout['review_note'])): ?>
                <tr>
                    <td colspan="2">Admin Note: <?php echo htmlspecialchars($payout['review_note']); ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="total-section">
            TOTAL: ₦ <?php echo number_format($payout['amount']); ?>
        </div>

        <div class="footer">
            <p>For any issues, contact Admin.</p>
        </div>
    </div>

    <div class="no-print">
        <button onclick="window.print()" class="btn btn-print">
            <i class="fas fa-print"></i> Print Receipt
        </button>

        <button onclick="shareReceipt()" class="btn btn-share">
            <i class="fas fa-share-alt"></i> Share Image (Bluetooth/Apps)
        </button>

        <button onclick="saveAsImage()" class="btn btn-img">
            <i class="fas fa-download"></i> Download Image
        </button>
        
        <button onclick="window.close()" class="btn btn-close">Close</button>
    </div>

    <script>
        async function shareReceipt() {
            const receipt = document.getElementById("receipt-paper");
            try {
                const canvas = await html2canvas(receipt);
                const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
                const file = new File([blob], "payout_<?php echo $payout['id']; ?>.png", { type: "image/png" });

                if (navigator.canShare && navigator.canShare({ files: [file] })) {
                    await navigator.share({
                        files: [file],
                        title: 'Payout #<?php echo $payout['id']; ?>',
                        text: 'Payout receipt from Galadawa Textiles.'
                    });
                } else {
                    saveAsImage();
                }
            } catch (err) {
                saveAsImage();
            }
        }

        function saveAsImage() {
            const receipt = document.getElementById("receipt-paper");
            html2canvas(receipt).then(canvas => {
                const link = document.createElement("a");
                link.download = "payout_<?php echo $payout['id']; ?>.png";
                link.href = canvas.toDataURL("image/png");
                link.click();
            });
        }
    </script>

</body>
</html>
