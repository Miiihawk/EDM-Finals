<?php
require_once 'config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Get POST data
$cart = json_decode($_POST['cart'] ?? '[]', true);
$total = floatval($_POST['total'] ?? 0);
$payment_method = mysqli_real_escape_string($conn, $_POST['payment_method'] ?? '');

if (empty($cart) || $total <= 0 || empty($payment_method)) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit();
}

// Start transaction
mysqli_begin_transaction($conn);

try {
    $user_id = $_SESSION['user_id'];
    $customer_id = intval($_POST['customer_id'] ?? 0);

    if ($customer_id <= 0) {
        $walkInQuery = "SELECT id FROM customers WHERE phone = 'WALKIN-DEFAULT' LIMIT 1";
        $walkInResult = mysqli_query($conn, $walkInQuery);

        if ($walkInResult && mysqli_num_rows($walkInResult) === 1) {
            $customer_id = (int) (mysqli_fetch_assoc($walkInResult)['id'] ?? 0);
        } else {
            $createWalkIn = "INSERT INTO customers (full_name, phone, email)
                             VALUES ('Walk-in Customer', 'WALKIN-DEFAULT', NULL)";
            if (!mysqli_query($conn, $createWalkIn)) {
                throw new Exception('Failed to create default customer');
            }
            $customer_id = (int) mysqli_insert_id($conn);
        }
    }

    $tracking_no = 'ORD-' . date('YmdHis') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 5));
    $order_query = "INSERT INTO orders (tracking_no, customer_id, created_by, payment_method, subtotal, total_amount)
                    VALUES ('$tracking_no', $customer_id, $user_id, '$payment_method', $total, $total)";

    if (!mysqli_query($conn, $order_query)) {
        throw new Exception('Failed to create order record');
    }

    $order_id = mysqli_insert_id($conn);
    
    // Process each cart item
    foreach ($cart as $item) {
        $product_id = intval($item['id']);
        $quantity = intval($item['quantity']);
        $price = floatval($item['price']);
        $subtotal = $price * $quantity;
        
        // Get product details
        $product_query = "SELECT product_name, stock FROM products WHERE id = $product_id";
        $result = mysqli_query($conn, $product_query);
        
        if (mysqli_num_rows($result) == 0) {
            throw new Exception("Product not found: ID $product_id");
        }
        
        $product = mysqli_fetch_assoc($result);
        
        // Check if enough stock
        if ($product['stock'] < $quantity) {
            throw new Exception("Insufficient stock for {$product['product_name']}");
        }
        
        $order_items_query = "INSERT INTO order_items (order_id, product_id, quantity, price, subtotal)
                              VALUES ($order_id, $product_id, $quantity, $price, $subtotal)";

        if (!mysqli_query($conn, $order_items_query)) {
            throw new Exception('Failed to add order item');
        }
        
        // Update stock
        $new_stock = $product['stock'] - $quantity;
        $status = $new_stock > 0 ? 'Available' : 'Out of Stock';
        
        $update_query = "UPDATE products SET stock = $new_stock, status = '$status' WHERE id = $product_id";
        
        if (!mysqli_query($conn, $update_query)) {
            throw new Exception('Failed to update stock');
        }
        
        // Record inventory movement for the sale.
        $history_query = "INSERT INTO inventory_history (product_id, user_id, change_quantity, reason)
                          VALUES ($product_id, $user_id, -$quantity, 'Sold')";
        if (!mysqli_query($conn, $history_query)) {
            throw new Exception('Failed to record inventory history');
        }
    }
    
    // Commit transaction
    mysqli_commit($conn);

    echo json_encode([
        'success' => true,
        'order_id' => $order_id,
        'tracking_no' => $tracking_no
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
