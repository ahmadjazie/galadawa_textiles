<?php
session_start();

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

include 'db_connect.php';
include 'payouts.php';
include 'order_workflow.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit();
}

ensure_payout_table($conn);
ensure_order_workflow_schema($conn);
release_expired_holds($conn);

function fetch_live_signature($conn, $sql) {
    $res = $conn->query($sql);
    if (!$res || $res->num_rows === 0) {
        return '0';
    }

    $row = $res->fetch_assoc();
    return (string)($row['sig'] ?? '0');
}

function build_live_signatures($conn) {
    return [
        'products' => fetch_live_signature($conn, "
            SELECT CONCAT(
                COALESCE((SELECT COUNT(*) FROM products), 0), ':',
                COALESCE((SELECT SUM(CRC32(CONCAT_WS('|', id, name, category, buy_price, sell_price, quantity, min_stock))) FROM products), 0), ':',
                COALESCE((SELECT MAX(id) FROM products), 0), ':',
                COALESCE((SELECT SUM(CRC32(CONCAT_WS('|', id, product_id, image_name, status))) FROM product_images), 0), ':',
                COALESCE((SELECT COUNT(*) FROM product_images), 0)
            ) AS sig
        "),
        'users' => fetch_live_signature($conn, "
            SELECT CONCAT(
                COALESCE(COUNT(*), 0), ':',
                COALESCE(SUM(CRC32(CONCAT_WS('|', id, fullname, username, role))), 0), ':',
                COALESCE(MAX(id), 0)
            ) AS sig
            FROM users
        "),
        'sales' => fetch_live_signature($conn, "
            SELECT CONCAT(
                COALESCE((SELECT COUNT(*) FROM sales), 0), ':',
                COALESCE((SELECT SUM(CRC32(CONCAT_WS('|', id, user_id, customer_name, total_amount, payment_method, created_at))) FROM sales), 0), ':',
                COALESCE((SELECT MAX(id) FROM sales), 0), ':',
                COALESCE((SELECT SUM(CRC32(CONCAT_WS('|', id, sale_id, product_id, product_image_id, quantity, exchanged_quantity, price, subtotal))) FROM sale_items), 0), ':',
                COALESCE((SELECT COUNT(*) FROM sale_items), 0)
            ) AS sig
        "),
        'holds' => fetch_live_signature($conn, "
            SELECT CONCAT(
                COALESCE((SELECT COUNT(*) FROM held_orders), 0), ':',
                COALESCE((SELECT SUM(CRC32(CONCAT_WS('|', id, user_id, customer_name, note, status, hold_minutes, total_amount, release_at, released_at, completed_at, completed_sale_id, created_at))) FROM held_orders), 0), ':',
                COALESCE((SELECT MAX(id) FROM held_orders), 0), ':',
                COALESCE((SELECT SUM(CRC32(CONCAT_WS('|', id, hold_id, product_id, product_image_id, product_name, quantity, price, subtotal))) FROM held_order_items), 0), ':',
                COALESCE((SELECT COUNT(*) FROM held_order_items), 0)
            ) AS sig
        "),
        'exchanges' => fetch_live_signature($conn, "
            SELECT CONCAT(
                COALESCE((SELECT COUNT(*) FROM exchange_transactions), 0), ':',
                COALESCE((SELECT SUM(CRC32(CONCAT_WS('|', id, user_id, commission_user_id, original_sale_id, adjustment_type, total_old_amount, total_new_amount, amount_difference, note, created_at))) FROM exchange_transactions), 0), ':',
                COALESCE((SELECT MAX(id) FROM exchange_transactions), 0), ':',
                COALESCE((SELECT SUM(CRC32(CONCAT_WS('|', id, exchange_id, original_sale_item_id, old_product_id, old_product_image_id, old_quantity, old_price, old_subtotal, new_product_id, new_product_image_id, new_quantity, new_price, new_subtotal))) FROM exchange_items), 0), ':',
                COALESCE((SELECT COUNT(*) FROM exchange_items), 0)
            ) AS sig
        "),
        'payouts' => fetch_live_signature($conn, "
            SELECT CONCAT(
                COALESCE(COUNT(*), 0), ':',
                COALESCE(SUM(CRC32(CONCAT_WS('|', id, user_id, amount, status, requested_at, reviewed_at, reviewed_by, note, review_note, user_unread, admin_unread))), 0), ':',
                COALESCE(MAX(id), 0)
            ) AS sig
            FROM payout_requests
        "),
    ];
}

$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';
$signatures = build_live_signatures($conn);

$response = [
    'ok' => true,
    'role' => $role,
    'timestamp' => time(),
    'signatures' => $signatures,
];

if ($role === 'admin') {
    $pending_res = $conn->query("SELECT COUNT(*) AS c FROM payout_requests WHERE status = 'pending'");
    $pending_count = $pending_res ? (int)($pending_res->fetch_assoc()['c'] ?? 0) : 0;

    $latest_payout_res = $conn->query("SELECT id FROM payout_requests ORDER BY id DESC LIMIT 1");
    $latest_payout_id = $latest_payout_res && $latest_payout_res->num_rows > 0
        ? (int)($latest_payout_res->fetch_assoc()['id'] ?? 0)
        : 0;

    $latest_sales = [];
    $sales_res = $conn->query(
        "SELECT s.id, s.total_amount, s.created_at, u.username
         FROM sales s
         JOIN users u ON u.id = s.user_id
         ORDER BY s.created_at DESC, s.id DESC
         LIMIT 5"
    );

    if ($sales_res) {
        while ($row = $sales_res->fetch_assoc()) {
            $created_at = strtotime($row['created_at']);
            $latest_sales[] = [
                'id' => (int)$row['id'],
                'username' => ucfirst((string)$row['username']),
                'total_amount' => (float)$row['total_amount'],
                'amount_display' => number_format((float)$row['total_amount'], 2),
                'amount_whole_display' => number_format((float)$row['total_amount'], 0),
                'datetime_display' => date('M d, Y h:i A', $created_at),
                'date_display' => date('M d, Y', $created_at),
                'time_display' => date('h:i A', $created_at),
            ];
        }
    }

    $response['admin'] = [
        'unread_count' => get_admin_unread_count($conn),
        'pending_count' => $pending_count,
        'active_hold_count' => get_active_holds_count($conn),
        'latest_payout_request_id' => $latest_payout_id,
        'latest_sale_id' => count($latest_sales) > 0 ? (int)$latest_sales[0]['id'] : 0,
        'latest_sales' => $latest_sales,
    ];
} else {
    $latest_notice = null;
    $notice_res = $conn->query(
        "SELECT id, amount, status
         FROM payout_requests
         WHERE user_id = '$user_id' AND user_unread = 1
         ORDER BY id DESC
         LIMIT 1"
    );

    if ($notice_res && $notice_res->num_rows > 0) {
        $row = $notice_res->fetch_assoc();
        $latest_notice = [
            'id' => (int)$row['id'],
            'amount' => (float)$row['amount'],
            'amount_display' => number_format((float)$row['amount'], 2),
            'status' => (string)$row['status'],
        ];
    }

    $response['sales'] = [
        'unread_count' => get_user_unread_count($conn, $user_id),
        'active_hold_count' => get_active_holds_count($conn),
        'latest_notice' => $latest_notice,
    ];
}

echo json_encode($response);
