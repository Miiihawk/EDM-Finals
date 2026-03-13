<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$phone = trim($_GET['phone'] ?? '');
$phone = mysqli_real_escape_string($conn, $phone);

if ($phone === '') {
    echo json_encode(['success' => false, 'error' => 'Phone is required']);
    exit();
}

$query = "SELECT id, full_name, phone, email FROM customers WHERE phone = '$phone' LIMIT 1";
$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) === 1) {
    echo json_encode([
        'success' => true,
        'exists' => true,
        'customer' => mysqli_fetch_assoc($result)
    ]);
    exit();
}

echo json_encode([
    'success' => true,
    'exists' => false
]);
?>