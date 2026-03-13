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
    // Insert into sales table
    $user_id = $_SESSION['user_id'];
    $sales_query = "INSERT INTO sales (user_id, total_amount, payment_method) 
                    VALUES ($user_id, $total, '$payment_method')";
    
    if (!mysqli_query($conn, $sales_query)) {
        throw new Exception('Failed to create sale record');
    }
    
    $sale_id = mysqli_insert_id($conn);
    
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
        
        // Insert into sale_items
        $sale_items_query = "INSERT INTO sale_items (sale_id, product_id, quantity, price, subtotal) 
                            VALUES ($sale_id, $product_id, $quantity, $price, $subtotal)";
        
        if (!mysqli_query($conn, $sale_items_query)) {
            throw new Exception('Failed to add sale item');
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
    
    echo json_encode(['success' => true, 'sale_id' => $sale_id]);
    
} catch (Exception $e) {
    // Rollback on error
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
