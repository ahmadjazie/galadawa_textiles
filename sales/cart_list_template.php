<?php
if(empty($_SESSION['cart'])): ?>
    <div class="cart-empty">
        <i class="fas fa-cart-arrow-down" style="font-size: 40px; margin-bottom: 10px;"></i>
        <span>Cart is Empty</span>
    </div>
<?php else: ?>
    <?php foreach($_SESSION['cart'] as $key => $item): ?>
        <div class="cart-item">
            <div class="cart-item-main">
                <?php if(isset($item['img_preview'])): ?>
                    <img src="<?php echo $item['img_preview']; ?>">
                <?php endif; ?>
                <div class="cart-item-info">
                    <div class="cart-item-name"><?php echo $item['name']; ?></div>
                    <?php if (!empty($item['color_name'])): ?>
                        <small class="cart-item-meta"><?php echo htmlspecialchars((string)$item['color_name'], ENT_QUOTES, 'UTF-8'); ?></small><br>
                    <?php endif; ?>
                    <small class="cart-item-meta"><?php echo $item['qty']; ?> x ₦<?php echo number_format($item['price']); ?></small>
                </div>
            </div>
            <div class="cart-item-side">
                <div class="cart-item-total">₦<?php echo number_format($item['subtotal']); ?></div>
                <form method="POST" class="cart-remove-form" style="display:inline;">
                    <input type="hidden" name="action" value="remove_ajax">
                    <input type="hidden" name="cart_id" value="<?php echo $key; ?>">
                    <input type="hidden" name="product_id" value="<?php echo (int)($item['id'] ?? 0); ?>">
                    <input type="hidden" name="product_image_id" value="<?php echo !empty($item['product_image_id']) ? (int)$item['product_image_id'] : (!empty($item['cap_img_id']) ? (int)$item['cap_img_id'] : ''); ?>">
                    <button class="cart-remove-btn" type="submit" aria-label="Remove item from cart">&times;</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
