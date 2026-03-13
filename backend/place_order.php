<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$cart = json_decode($_POST['cart'] ?? '[]', true);
$total = floatval($_POST['total'] ?? 0);
$paymentMethod = mysqli_real_escape_string($conn, trim($_POST['payment_method'] ?? ''));
$customerId = intval($_POST['customer_id'] ?? 0);
$collectCustomerDetails = ($_POST['collect_customer_details'] ?? '1') === '1';
$cashReceived = isset($_POST['cash_received']) ? floatval($_POST['cash_received']) : null;
$cashChange = isset($_POST['cash_change']) ? floatval($_POST['cash_change']) : null;

if (empty($cart) || $total <= 0 || $paymentMethod === '') {
    echo json_encode(['success' => false, 'error' => 'Invalid order data']);
    exit();
}

$userId = intval($_SESSION['user_id']);
$trackingNo = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

mysqli_begin_transaction($conn);

try {
    if ($collectCustomerDetails) {
        $customerCheck = mysqli_query($conn, "SELECT id FROM customers WHERE id = $customerId LIMIT 1");
        if (!$customerCheck || mysqli_num_rows($customerCheck) !== 1) {
            throw new Exception('Customer not found');
        }
    } else {
        $walkInQuery = "SELECT id FROM customers WHERE phone = 'WALKIN-DEFAULT' LIMIT 1";
        $walkInResult = mysqli_query($conn, $walkInQuery);

        if ($walkInResult && mysqli_num_rows($walkInResult) === 1) {
            $customerId = (int) (mysqli_fetch_assoc($walkInResult)['id'] ?? 0);
        } else {
            $createWalkInQuery = "INSERT INTO customers (full_name, phone, email)
                                  VALUES ('Walk-in Customer', 'WALKIN-DEFAULT', NULL)";
            if (!mysqli_query($conn, $createWalkInQuery)) {
                throw new Exception('Failed to create walk-in customer');
            }
            $customerId = (int) mysqli_insert_id($conn);
        }
    }

    $subtotal = 0;
    foreach ($cart as $item) {
        $subtotal += floatval($item['price']) * intval($item['quantity']);
    }

    if (abs($subtotal - $total) > 0.01) {
        throw new Exception('Order total mismatch');
    }

    $cashReceivedSql = $cashReceived === null ? 'NULL' : $cashReceived;
    $cashChangeSql = $cashChange === null ? 'NULL' : $cashChange;

    $orderQuery = "INSERT INTO orders (tracking_no, customer_id, created_by, payment_method, subtotal, total_amount, cash_received, cash_change)
                   VALUES ('$trackingNo', $customerId, $userId, '$paymentMethod', $subtotal, $total, $cashReceivedSql, $cashChangeSql)";

    if (!mysqli_query($conn, $orderQuery)) {
        throw new Exception('Failed to create order');
    }

    $orderId = mysqli_insert_id($conn);

    foreach ($cart as $item) {
        $productId = intval($item['id']);
        $quantity = intval($item['quantity']);
        $price = floatval($item['price']);
        $itemSubtotal = $price * $quantity;

        if ($productId <= 0 || $quantity <= 0 || $price < 0) {
            throw new Exception('Invalid product item');
        }

        $productResult = mysqli_query($conn, "SELECT product_name, stock FROM products WHERE id = $productId LIMIT 1");
        if (!$productResult || mysqli_num_rows($productResult) !== 1) {
            throw new Exception("Product not found: ID $productId");
        }

        $product = mysqli_fetch_assoc($productResult);
        if (intval($product['stock']) < $quantity) {
            throw new Exception("Insufficient stock for {$product['product_name']}");
        }

        $itemQuery = "INSERT INTO order_items (order_id, product_id, quantity, price, subtotal)
                     VALUES ($orderId, $productId, $quantity, $price, $itemSubtotal)";
        if (!mysqli_query($conn, $itemQuery)) {
            throw new Exception('Failed to save order item');
        }

        $newStock = intval($product['stock']) - $quantity;
        $status = $newStock > 0 ? 'Available' : 'Out of Stock';

        $stockQuery = "UPDATE products SET stock = $newStock, status = '$status' WHERE id = $productId";
        if (!mysqli_query($conn, $stockQuery)) {
            throw new Exception('Failed to update product stock');
        }

        $historyQuery = "INSERT INTO inventory_history (product_id, user_id, change_quantity, reason)
                        VALUES ($productId, $userId, -$quantity, 'Order Placed')";
        if (!mysqli_query($conn, $historyQuery)) {
            throw new Exception('Failed to record inventory history');
        }
    }

    mysqli_commit($conn);

    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'tracking_no' => $trackingNo,
        'order_date' => date('Y-m-d H:i:s'),
        'has_customer_details' => $collectCustomerDetails
    ]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>