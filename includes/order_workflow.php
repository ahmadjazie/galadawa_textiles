<?php

if (!defined('GALADAWA_HOLD_DURATION_MINUTES')) {
    // Production setting: 3 hours.
    define('GALADAWA_HOLD_DURATION_MINUTES', 180);
}

if (!defined('GALADAWA_EXCHANGE_WINDOW_HOURS')) {
    define('GALADAWA_EXCHANGE_WINDOW_HOURS', 24);
}

function ensure_order_workflow_schema($conn) {
    ensure_product_image_hold_status($conn);
    ensure_product_image_variant_columns($conn);
    ensure_sale_item_workflow_columns($conn);
    ensure_hold_tables($conn);
    ensure_exchange_tables($conn);
}

function ensure_product_image_variant_columns($conn) {
    $colorCol = $conn->query("SHOW COLUMNS FROM product_images LIKE 'color_name'");
    if ($colorCol && $colorCol->num_rows === 0) {
        $conn->query("ALTER TABLE product_images ADD COLUMN color_name VARCHAR(100) NULL AFTER image_name");
    }

    $yardsCol = $conn->query("SHOW COLUMNS FROM product_images LIKE 'yards'");
    if ($yardsCol && $yardsCol->num_rows === 0) {
        $conn->query("ALTER TABLE product_images ADD COLUMN yards DECIMAL(10,2) NULL AFTER color_name");
    }
}

function ensure_product_image_hold_status($conn) {
    $check = $conn->query("SHOW COLUMNS FROM product_images LIKE 'status'");
    if (!$check || $check->num_rows === 0) {
        return;
    }

    $column = $check->fetch_assoc();
    $type = (string)($column['Type'] ?? '');
    if (strpos($type, "'held'") === false) {
        $conn->query("ALTER TABLE product_images MODIFY COLUMN status ENUM('available','held','sold_out') NOT NULL DEFAULT 'available'");
    }
}

function ensure_sale_item_workflow_columns($conn) {
    $imageCol = $conn->query("SHOW COLUMNS FROM sale_items LIKE 'product_image_id'");
    if ($imageCol && $imageCol->num_rows === 0) {
        $conn->query("ALTER TABLE sale_items ADD COLUMN product_image_id INT(11) NULL AFTER product_id");
    }

    $exchangeCol = $conn->query("SHOW COLUMNS FROM sale_items LIKE 'exchanged_quantity'");
    if ($exchangeCol && $exchangeCol->num_rows === 0) {
        $conn->query("ALTER TABLE sale_items ADD COLUMN exchanged_quantity DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER quantity");
    }

    $buyPriceCol = $conn->query("SHOW COLUMNS FROM sale_items LIKE 'buy_price_at_sale'");
    if ($buyPriceCol && $buyPriceCol->num_rows === 0) {
        $conn->query("ALTER TABLE sale_items ADD COLUMN buy_price_at_sale DECIMAL(10,2) NULL AFTER price");
    }
}

function ensure_hold_tables($conn) {
    $conn->query("
        CREATE TABLE IF NOT EXISTS held_orders (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) NOT NULL,
            customer_name VARCHAR(100) NOT NULL DEFAULT 'Walk-in Customer',
            note VARCHAR(255) DEFAULT NULL,
            status ENUM('active','completed','released','expired') NOT NULL DEFAULT 'active',
            hold_minutes INT(11) NOT NULL DEFAULT 10,
            total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            release_at DATETIME NOT NULL,
            released_at DATETIME DEFAULT NULL,
            completed_at DATETIME DEFAULT NULL,
            completed_sale_id INT(11) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_held_orders_status_release (status, release_at),
            INDEX idx_held_orders_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS held_order_items (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            hold_id INT(11) NOT NULL,
            product_id INT(11) NOT NULL,
            product_image_id INT(11) DEFAULT NULL,
            product_name VARCHAR(150) NOT NULL,
            quantity DECIMAL(10,2) NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            buy_price_at_hold DECIMAL(10,2) DEFAULT NULL,
            subtotal DECIMAL(10,2) NOT NULL,
            image_preview VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_held_order_items_hold (hold_id),
            INDEX idx_held_order_items_product (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function ensure_exchange_tables($conn) {
    $conn->query("
        CREATE TABLE IF NOT EXISTS exchange_transactions (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) NOT NULL,
            commission_user_id INT(11) DEFAULT NULL,
            original_sale_id INT(11) NOT NULL,
            adjustment_type ENUM('customer_adds','balanced','customer_credit') NOT NULL,
            total_old_amount DECIMAL(10,2) NOT NULL,
            total_new_amount DECIMAL(10,2) NOT NULL,
            amount_difference DECIMAL(10,2) NOT NULL,
            note VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_exchange_transactions_sale (original_sale_id),
            INDEX idx_exchange_transactions_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS exchange_items (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            exchange_id INT(11) NOT NULL,
            original_sale_item_id INT(11) NOT NULL,
            old_product_id INT(11) NOT NULL,
            old_product_image_id INT(11) DEFAULT NULL,
            old_quantity DECIMAL(10,2) NOT NULL,
            old_price DECIMAL(10,2) NOT NULL,
            old_buy_price_snapshot DECIMAL(10,2) DEFAULT NULL,
            old_subtotal DECIMAL(10,2) NOT NULL,
            new_product_id INT(11) NOT NULL,
            new_product_image_id INT(11) DEFAULT NULL,
            new_quantity DECIMAL(10,2) NOT NULL,
            new_price DECIMAL(10,2) NOT NULL,
            new_buy_price_snapshot DECIMAL(10,2) DEFAULT NULL,
            new_subtotal DECIMAL(10,2) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_exchange_items_exchange (exchange_id),
            INDEX idx_exchange_items_sale_item (original_sale_item_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $commissionOwnerCol = $conn->query("SHOW COLUMNS FROM exchange_transactions LIKE 'commission_user_id'");
    if ($commissionOwnerCol && $commissionOwnerCol->num_rows === 0) {
        $conn->query("ALTER TABLE exchange_transactions ADD COLUMN commission_user_id INT(11) DEFAULT NULL AFTER user_id");
    }

    $oldBuySnapshotCol = $conn->query("SHOW COLUMNS FROM exchange_items LIKE 'old_buy_price_snapshot'");
    if ($oldBuySnapshotCol && $oldBuySnapshotCol->num_rows === 0) {
        $conn->query("ALTER TABLE exchange_items ADD COLUMN old_buy_price_snapshot DECIMAL(10,2) NULL AFTER old_price");
    }

    $newBuySnapshotCol = $conn->query("SHOW COLUMNS FROM exchange_items LIKE 'new_buy_price_snapshot'");
    if ($newBuySnapshotCol && $newBuySnapshotCol->num_rows === 0) {
        $conn->query("ALTER TABLE exchange_items ADD COLUMN new_buy_price_snapshot DECIMAL(10,2) NULL AFTER new_price");
    }

    $conn->query("
        UPDATE exchange_transactions e
        JOIN sales s ON s.id = e.original_sale_id
        SET e.commission_user_id = s.user_id
        WHERE e.commission_user_id IS NULL
    ");

    $conn->query("
        UPDATE exchange_items ei
        LEFT JOIN sale_items si ON si.id = ei.original_sale_item_id
        LEFT JOIN products old_product ON old_product.id = ei.old_product_id
        LEFT JOIN products new_product ON new_product.id = ei.new_product_id
        SET ei.old_buy_price_snapshot = COALESCE(ei.old_buy_price_snapshot, si.buy_price_at_sale, old_product.buy_price, 0),
            ei.new_buy_price_snapshot = COALESCE(ei.new_buy_price_snapshot, new_product.buy_price, 0)
        WHERE ei.old_buy_price_snapshot IS NULL
           OR ei.new_buy_price_snapshot IS NULL
    ");
}

function get_hold_duration_minutes() {
    return (int)GALADAWA_HOLD_DURATION_MINUTES;
}

function get_hold_duration_label() {
    $minutes = get_hold_duration_minutes();
    if ($minutes % 60 === 0) {
        $hours = (int)($minutes / 60);
        return $hours === 1 ? '1 hour' : $hours . ' hours';
    }

    if ($minutes > 60) {
        $hours = (int)floor($minutes / 60);
        $remaining = $minutes % 60;
        if ($remaining === 0) {
            return $hours === 1 ? '1 hour' : $hours . ' hours';
        }
        return $hours . 'h ' . $remaining . 'm';
    }

    return $minutes === 1 ? '1 minute' : $minutes . ' minutes';
}

function cart_total_amount($cart) {
    $total = 0;
    foreach ($cart as $item) {
        $total += (float)($item['subtotal'] ?? 0);
    }
    return $total;
}

function sync_product_quantity_from_images($conn, $productId) {
    $productId = (int)$productId;
    $res = $conn->query("SELECT COUNT(*) AS c FROM product_images WHERE product_id = '$productId' AND status = 'available'");
    $count = $res ? (int)($res->fetch_assoc()['c'] ?? 0) : 0;
    $conn->query("UPDATE products SET quantity = '$count' WHERE id = '$productId'");
    return $count;
}

function product_uses_image_stock($conn, $productId) {
    $productId = (int)$productId;
    $res = $conn->query("SELECT category FROM products WHERE id = '$productId' LIMIT 1");
    $category = $res && $res->num_rows > 0 ? (string)($res->fetch_assoc()['category'] ?? '') : '';
    return stripos($category, 'cap') !== false;
}

function product_uses_textile_variant_stock($conn, $productId, $productImageId = null) {
    $productId = (int)$productId;
    $productImageId = $productImageId !== null ? (int)$productImageId : null;
    if (!$productImageId) {
        return false;
    }

    $variant = $conn->query("SELECT id FROM product_images WHERE id = '$productImageId' AND product_id = '$productId' AND yards IS NOT NULL LIMIT 1");
    return $variant && $variant->num_rows > 0;
}

function reserve_inventory_for_hold($conn, $productId, $quantity, $productImageId = null) {
    $productId = (int)$productId;
    $quantity = (float)$quantity;
    $productImageId = $productImageId !== null ? (int)$productImageId : null;

    if ($productImageId && product_uses_textile_variant_stock($conn, $productId, $productImageId)) {
        $qtySql = number_format($quantity, 2, '.', '');
        $conn->query("UPDATE product_images SET status = IF(yards - $qtySql <= 0, 'held', status), yards = yards - $qtySql WHERE id = '$productImageId' AND product_id = '$productId' AND status = 'available' AND yards >= $qtySql");
        if ($conn->affected_rows !== 1) {
            return false;
        }
        $conn->query("UPDATE products SET quantity = GREATEST(quantity - $qtySql, 0) WHERE id = '$productId'");
        return $conn->affected_rows === 1;
    }

    if ($productImageId && product_uses_image_stock($conn, $productId)) {
        $conn->query("UPDATE product_images SET status = 'held' WHERE id = '$productImageId' AND product_id = '$productId' AND status = 'available'");
        if ($conn->affected_rows !== 1) {
            return false;
        }
        sync_product_quantity_from_images($conn, $productId);
        return true;
    }

    $conn->query("UPDATE products SET quantity = quantity - $quantity WHERE id = '$productId' AND quantity >= $quantity");
    return $conn->affected_rows === 1;
}

function finalize_direct_sale_inventory($conn, $productId, $quantity, $productImageId = null) {
    $productId = (int)$productId;
    $quantity = (float)$quantity;
    $productImageId = $productImageId !== null ? (int)$productImageId : null;

    if ($productImageId && product_uses_textile_variant_stock($conn, $productId, $productImageId)) {
        $qtySql = number_format($quantity, 2, '.', '');
        $conn->query("UPDATE product_images SET status = IF(yards - $qtySql <= 0, 'sold_out', status), yards = yards - $qtySql WHERE id = '$productImageId' AND product_id = '$productId' AND status = 'available' AND yards >= $qtySql");
        if ($conn->affected_rows !== 1) {
            return false;
        }
        $conn->query("UPDATE products SET quantity = GREATEST(quantity - $qtySql, 0) WHERE id = '$productId'");
        return $conn->affected_rows === 1;
    }

    if ($productImageId && product_uses_image_stock($conn, $productId)) {
        $conn->query("UPDATE product_images SET status = 'sold_out' WHERE id = '$productImageId' AND product_id = '$productId' AND status = 'available'");
        if ($conn->affected_rows !== 1) {
            return false;
        }
        sync_product_quantity_from_images($conn, $productId);
        return true;
    }

    $conn->query("UPDATE products SET quantity = quantity - $quantity WHERE id = '$productId' AND quantity >= $quantity");
    return $conn->affected_rows === 1;
}

function finalize_held_sale_inventory($conn, $productId, $productImageId = null) {
    $productId = (int)$productId;
    $productImageId = $productImageId !== null ? (int)$productImageId : null;

    if ($productImageId && product_uses_textile_variant_stock($conn, $productId, $productImageId)) {
        $conn->query("UPDATE product_images SET status = IF(yards <= 0, 'sold_out', status) WHERE id = '$productImageId' AND product_id = '$productId'");
        return $conn->affected_rows >= 0;
    }

    if ($productImageId && product_uses_image_stock($conn, $productId)) {
        $conn->query("UPDATE product_images SET status = 'sold_out' WHERE id = '$productImageId' AND product_id = '$productId' AND status = 'held'");
        if ($conn->affected_rows !== 1) {
            return false;
        }
        sync_product_quantity_from_images($conn, $productId);
    }

    return true;
}

function release_held_inventory($conn, $productId, $quantity, $productImageId = null) {
    $productId = (int)$productId;
    $quantity = (float)$quantity;
    $productImageId = $productImageId !== null ? (int)$productImageId : null;

    if ($productImageId && product_uses_textile_variant_stock($conn, $productId, $productImageId)) {
        $qtySql = number_format($quantity, 2, '.', '');
        $conn->query("UPDATE product_images SET yards = yards + $qtySql, status = 'available' WHERE id = '$productImageId' AND product_id = '$productId'");
        if ($conn->affected_rows !== 1) {
            return false;
        }
        return (bool)$conn->query("UPDATE products SET quantity = quantity + $qtySql WHERE id = '$productId'");
    }

    if ($productImageId && product_uses_image_stock($conn, $productId)) {
        $conn->query("UPDATE product_images SET status = 'available' WHERE id = '$productImageId' AND product_id = '$productId' AND status = 'held'");
        if ($conn->affected_rows !== 1) {
            return false;
        }
        sync_product_quantity_from_images($conn, $productId);
        return true;
    }

    return (bool)$conn->query("UPDATE products SET quantity = quantity + $quantity WHERE id = '$productId'");
}

function restore_exchanged_item_inventory($conn, $productId, $quantity, $productImageId = null) {
    $productId = (int)$productId;
    $quantity = (float)$quantity;
    $productImageId = $productImageId !== null ? (int)$productImageId : null;

    if ($productImageId && product_uses_textile_variant_stock($conn, $productId, $productImageId)) {
        $qtySql = number_format($quantity, 2, '.', '');
        $conn->query("UPDATE product_images SET yards = yards + $qtySql, status = 'available' WHERE id = '$productImageId' AND product_id = '$productId'");
        if ($conn->affected_rows !== 1) {
            return false;
        }
        return (bool)$conn->query("UPDATE products SET quantity = quantity + $qtySql WHERE id = '$productId'");
    }

    if ($productImageId && product_uses_image_stock($conn, $productId)) {
        $conn->query("UPDATE product_images SET status = 'available' WHERE id = '$productImageId' AND product_id = '$productId' AND status = 'sold_out'");
        if ($conn->affected_rows !== 1) {
            return false;
        }
        sync_product_quantity_from_images($conn, $productId);
        return true;
    }

    if (product_uses_image_stock($conn, $productId)) {
        $units = (int)round($quantity);
        for ($i = 0; $i < $units; $i++) {
            $row = $conn->query("SELECT id FROM product_images WHERE product_id = '$productId' AND status = 'sold_out' ORDER BY id ASC LIMIT 1");
            $imageId = $row && $row->num_rows > 0 ? (int)($row->fetch_assoc()['id'] ?? 0) : 0;
            if ($imageId <= 0) {
                return false;
            }
            $conn->query("UPDATE product_images SET status = 'available' WHERE id = '$imageId' AND status = 'sold_out'");
            if ($conn->affected_rows !== 1) {
                return false;
            }
        }
        sync_product_quantity_from_images($conn, $productId);
        return true;
    }

    return (bool)$conn->query("UPDATE products SET quantity = quantity + $quantity WHERE id = '$productId'");
}

function take_exchange_replacement_inventory($conn, $productId, $quantity, $productImageId = null) {
    return finalize_direct_sale_inventory($conn, $productId, $quantity, $productImageId);
}

function get_hold_items($conn, $holdId) {
    $holdId = (int)$holdId;
    $items = [];
    $res = $conn->query("SELECT * FROM held_order_items WHERE hold_id = '$holdId' ORDER BY id ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $items[] = $row;
        }
    }
    return $items;
}

function get_hold_order($conn, $holdId) {
    $holdId = (int)$holdId;
    $res = $conn->query("SELECT * FROM held_orders WHERE id = '$holdId' LIMIT 1");
    return $res && $res->num_rows > 0 ? $res->fetch_assoc() : null;
}

function user_can_manage_hold($hold, $userId, $isAdmin = false) {
    if ($isAdmin) {
        return true;
    }

    if (!$hold) {
        return false;
    }

    return (int)($hold['user_id'] ?? 0) === (int)$userId;
}

function get_hold_time_left_seconds($releaseAt, $status, $referenceTime = null) {
    if ((string)$status !== 'active') {
        return null;
    }

    $releaseTs = strtotime((string)$releaseAt);
    if ($releaseTs === false) {
        return null;
    }

    $now = $referenceTime !== null ? (int)$referenceTime : time();
    return $releaseTs - $now;
}

function format_hold_time_left_label($releaseAt, $status, $referenceTime = null) {
    if ((string)$status !== 'active') {
        if ($status === 'completed') {
            return 'Completed';
        }
        if ($status === 'released') {
            return 'Released';
        }
        if ($status === 'expired') {
            return 'Expired';
        }
        return 'Inactive';
    }

    $seconds = get_hold_time_left_seconds($releaseAt, $status, $referenceTime);
    if ($seconds === null || $seconds <= 0) {
        return 'Releasing now';
    }

    $hours = (int)floor($seconds / 3600);
    $minutes = (int)floor(($seconds % 3600) / 60);
    $secs = (int)($seconds % 60);

    if ($hours > 0) {
        return sprintf('%dh %02dm %02ds left', $hours, $minutes, $secs);
    }

    if ($minutes > 0) {
        return sprintf('%dm %02ds left', $minutes, $secs);
    }

    return sprintf('%ds left', max(1, $secs));
}

function recalculate_hold_total($conn, $holdId) {
    $holdId = (int)$holdId;
    $res = $conn->query("SELECT COALESCE(SUM(subtotal), 0) AS total FROM held_order_items WHERE hold_id = '$holdId'");
    $total = $res ? (float)($res->fetch_assoc()['total'] ?? 0) : 0.0;
    $totalSql = number_format($total, 2, '.', '');
    $conn->query("UPDATE held_orders SET total_amount = '$totalSql' WHERE id = '$holdId'");
    return $total;
}

function update_hold_order_contents($conn, $holdId, array $updates) {
    $holdId = (int)$holdId;
    $hold = get_hold_order($conn, $holdId);
    if (!$hold) {
        throw new RuntimeException('Hold order not found.');
    }

    if ((string)($hold['status'] ?? '') !== 'active') {
        throw new RuntimeException('Only active holds can be edited.');
    }

    $items = get_hold_items($conn, $holdId);
    if (empty($items)) {
        throw new RuntimeException('This hold has no items to edit.');
    }

    $customerName = trim((string)($updates['customer_name'] ?? $hold['customer_name'] ?? 'Walk-in Customer'));
    if ($customerName === '') {
        $customerName = 'Walk-in Customer';
    }
    $note = trim((string)($updates['note'] ?? ($hold['note'] ?? '')));
    $itemQuantities = isset($updates['quantities']) && is_array($updates['quantities']) ? $updates['quantities'] : [];
    $itemRemovals = isset($updates['remove']) && is_array($updates['remove']) ? $updates['remove'] : [];

    $conn->begin_transaction();

    try {
        foreach ($items as $item) {
            $itemId = (int)$item['id'];
            $productId = (int)$item['product_id'];
            $imageId = $item['product_image_id'] !== null ? (int)$item['product_image_id'] : null;
            $oldQty = (float)$item['quantity'];
            $price = (float)$item['price'];
            $requestedQty = array_key_exists($itemId, $itemQuantities) ? (float)$itemQuantities[$itemId] : $oldQty;
            $removeRequested = isset($itemRemovals[$itemId]) && (string)$itemRemovals[$itemId] === '1';

            if ($removeRequested) {
                $requestedQty = 0.0;
            }

            if ($requestedQty < 0) {
                throw new RuntimeException('Hold quantity cannot be negative.');
            }

            $isFixedImageItem = $imageId !== null && product_uses_image_stock($conn, $productId);

            if ($isFixedImageItem) {
                if ($requestedQty > 0 && abs($requestedQty - $oldQty) > 0.0001) {
                    throw new RuntimeException('Cap/image hold items can only be kept as they are or removed.');
                }

                if ($requestedQty <= 0) {
                    if (!release_held_inventory($conn, $productId, $oldQty, $imageId)) {
                        throw new RuntimeException('Unable to restore held stock for an item you removed.');
                    }
                    $conn->query("DELETE FROM held_order_items WHERE id = '$itemId' AND hold_id = '$holdId'");
                    if ($conn->affected_rows !== 1) {
                        throw new RuntimeException('Unable to remove a held item.');
                    }
                }
                continue;
            }

            if (abs($requestedQty - $oldQty) < 0.0001) {
                continue;
            }

            if ($requestedQty <= 0) {
                if (!release_held_inventory($conn, $productId, $oldQty, $imageId)) {
                    throw new RuntimeException('Unable to restore held stock for an item you removed.');
                }
                $conn->query("DELETE FROM held_order_items WHERE id = '$itemId' AND hold_id = '$holdId'");
                if ($conn->affected_rows !== 1) {
                    throw new RuntimeException('Unable to remove a held item.');
                }
                continue;
            }

            if ($requestedQty > $oldQty) {
                $extraQty = $requestedQty - $oldQty;
                if (!reserve_inventory_for_hold($conn, $productId, $extraQty, $imageId)) {
                    throw new RuntimeException('Stock is no longer enough for one of the updated hold quantities.');
                }
            } else {
                $releaseQty = $oldQty - $requestedQty;
                if (!release_held_inventory($conn, $productId, $releaseQty, $imageId)) {
                    throw new RuntimeException('Unable to restore the reduced hold quantity to stock.');
                }
            }

            $subtotal = $requestedQty * $price;
            $quantitySql = number_format($requestedQty, 2, '.', '');
            $subtotalSql = number_format($subtotal, 2, '.', '');
            $conn->query("UPDATE held_order_items SET quantity = '$quantitySql', subtotal = '$subtotalSql' WHERE id = '$itemId' AND hold_id = '$holdId'");
            if ($conn->affected_rows < 0) {
                throw new RuntimeException('Unable to update a held item.');
            }
        }

        $remainingItems = get_hold_items($conn, $holdId);
        $customerSql = $conn->real_escape_string($customerName);
        $noteSql = $note !== '' ? "'" . $conn->real_escape_string($note) . "'" : "NULL";

        if (empty($remainingItems)) {
            $conn->query("UPDATE held_orders SET customer_name = '$customerSql', note = $noteSql, total_amount = 0, status = 'released', released_at = NOW() WHERE id = '$holdId' AND status = 'active'");
            if ($conn->affected_rows !== 1) {
                throw new RuntimeException('Unable to close the hold after removing all items.');
            }
            $conn->commit();
            return [
                'released' => true,
                'total_amount' => 0.0,
            ];
        }

        $newTotal = recalculate_hold_total($conn, $holdId);
        $totalSql = number_format($newTotal, 2, '.', '');
        $conn->query("UPDATE held_orders SET customer_name = '$customerSql', note = $noteSql, total_amount = '$totalSql' WHERE id = '$holdId' AND status = 'active'");
        if ($conn->affected_rows < 0) {
            throw new RuntimeException('Unable to update hold details.');
        }

        $conn->commit();
        return [
            'released' => false,
            'total_amount' => $newTotal,
        ];
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function release_expired_holds($conn) {
    $expiredIds = [];
    $res = $conn->query("SELECT id FROM held_orders WHERE status = 'active' AND release_at <= NOW() ORDER BY id ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $expiredIds[] = (int)$row['id'];
        }
    }

    $released = 0;
    foreach ($expiredIds as $holdId) {
        $conn->begin_transaction();

        try {
            $items = get_hold_items($conn, $holdId);
            foreach ($items as $item) {
                if (!release_held_inventory(
                    $conn,
                    (int)$item['product_id'],
                    (float)$item['quantity'],
                    $item['product_image_id'] !== null ? (int)$item['product_image_id'] : null
                )) {
                    throw new RuntimeException('Unable to release held inventory.');
                }
            }

            $conn->query("UPDATE held_orders SET status = 'expired', released_at = NOW() WHERE id = '$holdId' AND status = 'active'");
            if ($conn->affected_rows !== 1) {
                throw new RuntimeException('Unable to mark hold as expired.');
            }

            $conn->commit();
            $released++;
        } catch (Throwable $e) {
            $conn->rollback();
        }
    }

    return $released;
}

function get_active_holds_count($conn) {
    $res = $conn->query("SELECT COUNT(*) AS c FROM held_orders WHERE status = 'active'");
    return $res ? (int)($res->fetch_assoc()['c'] ?? 0) : 0;
}

function get_exchange_adjustment_type($difference) {
    $difference = (float)$difference;
    if ($difference > 0) {
        return 'customer_adds';
    }
    if ($difference < 0) {
        return 'customer_credit';
    }
    return 'balanced';
}

function get_exchange_adjustment_label($type) {
    if ($type === 'customer_adds') {
        return 'Customer Adds';
    }
    if ($type === 'customer_credit') {
        return 'Customer Credit';
    }
    return 'Balanced';
}

function get_exchange_window_hours() {
    return (int)GALADAWA_EXCHANGE_WINDOW_HOURS;
}

function get_exchange_window_seconds() {
    return get_exchange_window_hours() * 3600;
}

function get_exchange_deadline_timestamp($saleCreatedAt) {
    $createdAt = strtotime((string)$saleCreatedAt);
    if ($createdAt === false) {
        return 0;
    }
    return $createdAt + get_exchange_window_seconds();
}

function can_exchange_sale($saleCreatedAt, $referenceTime = null) {
    $deadline = get_exchange_deadline_timestamp($saleCreatedAt);
    if ($deadline <= 0) {
        return false;
    }

    $now = $referenceTime !== null ? (int)$referenceTime : time();
    return $now <= $deadline;
}
