<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$orderId = intval($_GET['order_id'] ?? 0);
if ($orderId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid order id']);
    exit();
}

$orderQuery = "SELECT o.id, o.tracking_no, o.payment_method, o.subtotal, o.total_amount, o.cash_received, o.cash_change,
                      o.order_date, c.full_name, c.phone, c.email, u.username AS created_by
               FROM orders o
               LEFT JOIN customers c ON o.customer_id = c.id
               LEFT JOIN users u ON o.created_by = u.id
               WHERE o.id = $orderId
               LIMIT 1";
$orderResult = mysqli_query($conn, $orderQuery);
if (!$orderResult || mysqli_num_rows($orderResult) !== 1) {
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit();
}

$order = mysqli_fetch_assoc($orderResult);

$hasCustomerDetails = !empty($order['phone']) && $order['phone'] !== 'WALKIN-DEFAULT';
if (!$hasCustomerDetails) {
    $order['full_name'] = null;
    $order['phone'] = null;
    $order['email'] = null;
}

$order['has_customer_details'] = $hasCustomerDetails;

$itemsQuery = "SELECT oi.product_id, p.product_name, oi.quantity, oi.price, oi.subtotal
               FROM order_items oi
               LEFT JOIN products p ON oi.product_id = p.id
               WHERE oi.order_id = $orderId
               ORDER BY oi.id ASC";
$itemsResult = mysqli_query($conn, $itemsQuery);
$items = [];
if ($itemsResult) {
    while ($row = mysqli_fetch_assoc($itemsResult)) {
        $items[] = $row;
    }
}

echo json_encode([
    'success' => true,
    'order' => $order,
    'items' => $items
]);
?>