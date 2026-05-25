<?php
session_start();
include '../includes/db_connect.php';
include '../includes/order_workflow.php';

if (!isset($_GET['id'])) {
    die("Exchange ID missing.");
}

ensure_order_workflow_schema($conn);

$exchange_id = (int)$_GET['id'];
$exchange_res = $conn->query("
    SELECT e.*, u.username, s.customer_name, s.created_at AS sale_created_at
    FROM exchange_transactions e
    LEFT JOIN users u ON u.id = e.user_id
    LEFT JOIN sales s ON s.id = e.original_sale_id
    WHERE e.id = '$exchange_id'
    LIMIT 1
");
$exchange = $exchange_res ? $exchange_res->fetch_assoc() : null;

if (!$exchange) {
    die("Exchange not found.");
}

$item_res = $conn->query("
    SELECT ei.*, op.name AS old_product_name, np.name AS new_product_name,
           old_img.color_name AS old_color_name,
           new_img.color_name AS new_color_name
    FROM exchange_items ei
    LEFT JOIN products op ON op.id = ei.old_product_id
    LEFT JOIN products np ON np.id = ei.new_product_id
    LEFT JOIN product_images old_img ON old_img.id = ei.old_product_image_id
    LEFT JOIN product_images new_img ON new_img.id = ei.new_product_image_id
    WHERE ei.exchange_id = '$exchange_id'
    ORDER BY ei.id ASC
");
$items = [];
if ($item_res) {
    while ($row = $item_res->fetch_assoc()) {
        $items[] = $row;
    }
}

function exchange_receipt_item_label($productName, $colorName) {
    $label = (string)$productName;
    $color = trim((string)$colorName);
    if ($color !== '') {
        $label .= ' - Colour: ' . $color;
    }
    return $label;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exchange Receipt #<?php echo str_pad($exchange_id, 6, '0', STR_PAD_LEFT); ?></title>
    <style>
        body { font-family: 'Courier New', Courier, monospace; font-size: 12px; margin: 0; padding: 20px; background: #eee; display: flex; flex-direction: column; align-items: center; }
        #receipt-paper { background: white; padding: 24px; width: 320px; box-shadow: 0 0 10px rgba(0,0,0,0.1); color: #000; }
        .header { text-align: center; margin-bottom: 12px; border-bottom: 1px dashed #000; padding-bottom: 10px; }
        .logo { font-size: 18px; font-weight: bold; text-transform: uppercase; }
        .info { font-size: 11px; margin-bottom: 4px; }
        .section-title { margin: 14px 0 6px; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .item-block { border: 1px dashed #000; padding: 8px; margin-bottom: 10px; }
        .item-row { display: flex; justify-content: space-between; gap: 12px; margin-top: 4px; }
        .total-section { border-top: 1px dashed #000; padding-top: 8px; text-align: right; font-size: 13px; font-weight: bold; }
        .footer { text-align: center; margin-top: 16px; font-size: 10px; }
        .no-print { width: 320px; display: flex; flex-direction: column; gap: 10px; margin-top: 16px; }
        .btn { padding: 12px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; font-family: sans-serif; font-size: 13px; }
        .btn-print { background: #333; color: white; }
        .btn-close { background: #ddd; color: #333; }

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
        <div class="info">Exchange Receipt #: <?php echo str_pad($exchange_id, 6, '0', STR_PAD_LEFT); ?></div>
        <div class="info">Original Receipt #: <?php echo str_pad((int)$exchange['original_sale_id'], 6, '0', STR_PAD_LEFT); ?></div>
        <div class="info">Date: <?php echo date('d-M-Y h:i A', strtotime((string)$exchange['created_at'])); ?></div>
        <div class="info">Attendant: <?php echo htmlspecialchars((string)($exchange['username'] ?? 'Staff'), ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="info">Customer: <?php echo htmlspecialchars((string)($exchange['customer_name'] ?? 'Walk-in Customer'), ENT_QUOTES, 'UTF-8'); ?></div>
    </div>

    <div class="section-title">Exchange Summary</div>
    <div class="info">Adjustment: <?php echo htmlspecialchars(get_exchange_adjustment_label((string)$exchange['adjustment_type']), ENT_QUOTES, 'UTF-8'); ?></div>
    <div class="info">Returned Value: ₦<?php echo number_format((float)$exchange['total_old_amount'], 2); ?></div>
    <div class="info">Replacement Value: ₦<?php echo number_format((float)$exchange['total_new_amount'], 2); ?></div>

    <?php foreach ($items as $item): ?>
        <?php $has_returned_item = (float)($item['old_quantity'] ?? 0) > 0; ?>
        <div class="item-block">
            <?php if ($has_returned_item): ?>
                <div><strong>Returned:</strong> <?php echo htmlspecialchars(exchange_receipt_item_label($item['old_product_name'] ?? 'Original Item', $item['old_color_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="item-row">
                    <span><?php echo (float)$item['old_quantity']; ?> x ₦<?php echo number_format((float)$item['old_price'], 2); ?></span>
                    <span>₦<?php echo number_format((float)$item['old_subtotal'], 2); ?></span>
                </div>
                <div style="margin-top:10px;"><strong>Replacement:</strong> <?php echo htmlspecialchars(exchange_receipt_item_label($item['new_product_name'] ?? 'Replacement Item', $item['new_color_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
            <?php else: ?>
                <div><strong>Additional Replacement:</strong> <?php echo htmlspecialchars(exchange_receipt_item_label($item['new_product_name'] ?? 'Replacement Item', $item['new_color_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <div class="item-row">
                <span><?php echo (float)$item['new_quantity']; ?> x ₦<?php echo number_format((float)$item['new_price'], 2); ?></span>
                <span>₦<?php echo number_format((float)$item['new_subtotal'], 2); ?></span>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="total-section">
        Difference: ₦ <?php echo number_format(abs((float)$exchange['amount_difference']), 2); ?>
    </div>

    <?php if (!empty($exchange['note'])): ?>
        <div class="section-title">Note</div>
        <div class="info"><?php echo htmlspecialchars((string)$exchange['note'], ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="footer">
        <p>Thank you for shopping with us.</p>
    </div>
</div>

<div class="no-print">
    <button onclick="window.print()" class="btn btn-print">Print Exchange Receipt</button>
    <button onclick="window.close()" class="btn btn-close">Close</button>
</div>

</body>
</html>
