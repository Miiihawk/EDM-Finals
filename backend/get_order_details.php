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

function buildProductCode(int $productId, string $categoryName = '', string $codePrefix = ''): string {
    if ($codePrefix !== '') {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $codePrefix), 0, 3));
        $prefix = str_pad($prefix, 3, 'X');
    } else {
        $lettersOnly = preg_replace('/[^A-Z]/', '', strtoupper((string)$categoryName));
        if ($lettersOnly === '') {
            $lettersOnly = 'ITM';
        }
        $prefix = substr(str_pad($lettersOnly, 3, 'X'), 0, 3);
    }

    return $prefix . str_pad((string)$productId, 3, '0', STR_PAD_LEFT);
}

function hasCategoryCodePrefixColumn(mysqli $conn): bool {
    $result = mysqli_query($conn, "SHOW COLUMNS FROM categories LIKE 'code_prefix'");
    return $result && mysqli_num_rows($result) > 0;
}

function hasProductCodeColumn(mysqli $conn): bool {
    $result = mysqli_query($conn, "SHOW COLUMNS FROM products LIKE 'product_code'");
    return $result && mysqli_num_rows($result) > 0;
}

$hasCategoryCodePrefix = hasCategoryCodePrefixColumn($conn);
$hasProductCode = hasProductCodeColumn($conn);

$hasCustomerDetails = !empty($order['phone']) && $order['phone'] !== 'WALKIN-DEFAULT';
if (!$hasCustomerDetails) {
    $order['full_name'] = null;
    $order['phone'] = null;
    $order['email'] = null;
}

$order['has_customer_details'] = $hasCustomerDetails;

$itemsQuery = $hasCategoryCodePrefix
     ? ($hasProductCode
         ? "SELECT oi.product_id, p.product_name, p.product_code, c.category_name, c.code_prefix, oi.quantity, oi.price, oi.subtotal
         FROM order_items oi
         LEFT JOIN products p ON oi.product_id = p.id
         LEFT JOIN categories c ON p.category_id = c.id
         WHERE oi.order_id = $orderId
         ORDER BY oi.id ASC"
         : "SELECT oi.product_id, p.product_name, '' AS product_code, c.category_name, c.code_prefix, oi.quantity, oi.price, oi.subtotal
         FROM order_items oi
         LEFT JOIN products p ON oi.product_id = p.id
         LEFT JOIN categories c ON p.category_id = c.id
         WHERE oi.order_id = $orderId
         ORDER BY oi.id ASC")
     : ($hasProductCode
         ? "SELECT oi.product_id, p.product_name, p.product_code, c.category_name, '' AS code_prefix, oi.quantity, oi.price, oi.subtotal
         FROM order_items oi
         LEFT JOIN products p ON oi.product_id = p.id
         LEFT JOIN categories c ON p.category_id = c.id
         WHERE oi.order_id = $orderId
         ORDER BY oi.id ASC"
         : "SELECT oi.product_id, p.product_name, '' AS product_code, c.category_name, '' AS code_prefix, oi.quantity, oi.price, oi.subtotal
         FROM order_items oi
         LEFT JOIN products p ON oi.product_id = p.id
         LEFT JOIN categories c ON p.category_id = c.id
         WHERE oi.order_id = $orderId
         ORDER BY oi.id ASC");
$itemsResult = mysqli_query($conn, $itemsQuery);
$items = [];
if ($itemsResult) {
    while ($row = mysqli_fetch_assoc($itemsResult)) {
        $row['product_code'] = !empty($row['product_code'] ?? '')
            ? strtoupper((string)$row['product_code'])
            : buildProductCode(
                (int)($row['product_id'] ?? 0),
                (string)($row['category_name'] ?? ''),
                (string)($row['code_prefix'] ?? '')
            );
        $items[] = $row;
    }
}

echo json_encode([
    'success' => true,
    'order' => $order,
    'items' => $items
]);
?>