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
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="cart_id" value="<?php echo $key; ?>">
                    <button class="cart-remove-btn">&times;</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
