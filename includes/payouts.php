<?php
function galadawa_table_exists($conn, $tableName) {
    $tableName = $conn->real_escape_string($tableName);
    $res = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $res && $res->num_rows > 0;
}

function galadawa_column_exists($conn, $tableName, $columnName) {
    $tableName = $conn->real_escape_string($tableName);
    $columnName = $conn->real_escape_string($columnName);
    $res = $conn->query("SHOW COLUMNS FROM `$tableName` LIKE '$columnName'");
    return $res && $res->num_rows > 0;
}

function ensure_payout_table($conn) {
    $conn->query(
        "CREATE TABLE IF NOT EXISTS payout_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reviewed_at DATETIME NULL,
            reviewed_by INT NULL,
            note VARCHAR(255) NULL,
            review_note VARCHAR(255) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    ensure_payout_columns($conn);
}

function ensure_payout_columns($conn) {
    $res = $conn->query("SHOW COLUMNS FROM payout_requests LIKE 'user_unread'");
    if ($res && $res->num_rows === 0) {
        $conn->query("ALTER TABLE payout_requests ADD COLUMN user_unread TINYINT(1) NOT NULL DEFAULT 0");
    }
    $res = $conn->query("SHOW COLUMNS FROM payout_requests LIKE 'admin_unread'");
    if ($res && $res->num_rows === 0) {
        $conn->query("ALTER TABLE payout_requests ADD COLUMN admin_unread TINYINT(1) NOT NULL DEFAULT 0");
    }
}

function get_commission_totals($conn, $user_id, $rate = 0.05) {
    $res_sales = $conn->query("SELECT SUM(total_amount) as total FROM sales WHERE user_id = '$user_id'");
    $gross_sales = $res_sales ? (float)($res_sales->fetch_assoc()['total'] ?? 0) : 0.0;

    $exchange_adjustment = 0.0;
    $first_exchange_at = null;
    if (galadawa_table_exists($conn, 'exchange_transactions')) {
        $exchangeOwnerColumn = galadawa_column_exists($conn, 'exchange_transactions', 'commission_user_id')
            ? 'commission_user_id'
            : 'user_id';

        $res_exchange = $conn->query("SELECT SUM(amount_difference) as total FROM exchange_transactions WHERE $exchangeOwnerColumn = '$user_id'");
        $exchange_adjustment = $res_exchange ? (float)($res_exchange->fetch_assoc()['total'] ?? 0) : 0.0;

        $res_exchange_baseline = $conn->query("SELECT MIN(created_at) as first_exchange_at FROM exchange_transactions WHERE $exchangeOwnerColumn = '$user_id'");
        $first_exchange_at = $res_exchange_baseline ? ($res_exchange_baseline->fetch_assoc()['first_exchange_at'] ?? null) : null;
    }

    $total_sales = $gross_sales + $exchange_adjustment;
    if ($total_sales < 0) {
        $total_sales = 0.0;
    }

    $total_commission = $total_sales * $rate;

    // If transactions were reset but payout history remained, old payouts should not
    // block new commission forever. Use the user's first current sale as baseline.
    $res_baseline = $conn->query("SELECT MIN(created_at) as first_sale_at FROM sales WHERE user_id = '$user_id'");
    $first_sale_at = $res_baseline ? ($res_baseline->fetch_assoc()['first_sale_at'] ?? null) : null;
    $activity_points = array_filter([$first_sale_at, $first_exchange_at]);
    $first_activity_at = !empty($activity_points) ? min($activity_points) : null;

    if (!empty($first_activity_at)) {
        $first_activity_at_safe = $conn->real_escape_string($first_activity_at);
        $date_filter = " AND requested_at >= '$first_activity_at_safe'";

        $res_paid = $conn->query("SELECT SUM(amount) as total FROM payout_requests WHERE user_id = '$user_id' AND status = 'approved'$date_filter");
        $paid_out = $res_paid ? (float)($res_paid->fetch_assoc()['total'] ?? 0) : 0.0;

        $res_pending = $conn->query("SELECT SUM(amount) as total FROM payout_requests WHERE user_id = '$user_id' AND status = 'pending'$date_filter");
        $pending_out = $res_pending ? (float)($res_pending->fetch_assoc()['total'] ?? 0) : 0.0;
    } else {
        $paid_out = 0.0;
        $pending_out = 0.0;
    }

    $balance = $total_commission - $paid_out - $pending_out;
    $available = $balance > 0 ? $balance : 0.0;
    $outstanding = $balance < 0 ? abs($balance) : 0.0;

    return [
        'total_sales' => $total_sales,
        'gross_sales' => $gross_sales,
        'exchange_adjustment' => $exchange_adjustment,
        'total_commission' => $total_commission,
        'paid_out' => $paid_out,
        'pending_out' => $pending_out,
        'balance' => $balance,
        'available' => $available,
        'outstanding' => $outstanding
    ];
}

function get_user_unread_count($conn, $user_id) {
    $res = $conn->query("SELECT COUNT(*) as c FROM payout_requests WHERE user_id = '$user_id' AND user_unread = 1");
    return $res ? (int)($res->fetch_assoc()['c'] ?? 0) : 0;
}

function get_admin_unread_count($conn) {
    $res = $conn->query(
        "SELECT COUNT(*) as c
         FROM payout_requests
         WHERE admin_unread = 1
            OR status = 'pending'"
    );
    return $res ? (int)($res->fetch_assoc()['c'] ?? 0) : 0;
}
