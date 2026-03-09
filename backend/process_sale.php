<?php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

// Get POST data
$product_id = intval($_POST['product_id']);
$quantity = intval($_POST['quantity']);

if ($product_id <= 0 || $quantity <= 0) {
    http_response_code(400);
    exit('Invalid data');
}

// Get product details
$product_query = "SELECT product_name, stock FROM products WHERE id = $product_id";
$result = mysqli_query($conn, $product_query);

if (mysqli_num_rows($result) == 0) {
    http_response_code(404);
    exit('Product not found');
}

$product = mysqli_fetch_assoc($result);

// Check if enough stock
if ($product['stock'] < $quantity) {
    http_response_code(400);
    exit('Insufficient stock');
}

// Update stock (reduce)
$new_stock = $product['stock'] - $quantity;
$status = $new_stock > 0 ? 'Available' : 'Out of Stock';

$update_query = "UPDATE products SET stock = $new_stock, status = '$status' WHERE id = $product_id";
mysqli_query($conn, $update_query);

// Log the sale
$log_query = "INSERT INTO logs (product_name, quantity, action, username) 
              VALUES ('{$product['product_name']}', -$quantity, 'Sold', '{$_SESSION['username']}')";
mysqli_query($conn, $log_query);

http_response_code(200);
echo 'Success';
?>
