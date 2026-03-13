<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$fullName = trim($_POST['full_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');

if ($fullName === '' || $phone === '') {
    echo json_encode(['success' => false, 'error' => 'Full name and phone are required']);
    exit();
}

$fullNameEsc = mysqli_real_escape_string($conn, $fullName);
$phoneEsc = mysqli_real_escape_string($conn, $phone);
$emailEsc = mysqli_real_escape_string($conn, $email);

$existingQuery = "SELECT id, full_name, phone, email FROM customers WHERE phone = '$phoneEsc' LIMIT 1";
$existingResult = mysqli_query($conn, $existingQuery);
if ($existingResult && mysqli_num_rows($existingResult) === 1) {
    echo json_encode([
        'success' => true,
        'customer' => mysqli_fetch_assoc($existingResult),
        'message' => 'Customer already exists'
    ]);
    exit();
}

$insertQuery = "INSERT INTO customers (full_name, phone, email)
                VALUES ('$fullNameEsc', '$phoneEsc', " . ($emailEsc === '' ? 'NULL' : "'$emailEsc'") . ")";

if (!mysqli_query($conn, $insertQuery)) {
    echo json_encode(['success' => false, 'error' => 'Failed to create customer']);
    exit();
}

$customerId = mysqli_insert_id($conn);
$getQuery = "SELECT id, full_name, phone, email FROM customers WHERE id = $customerId LIMIT 1";
$getResult = mysqli_query($conn, $getQuery);

if (!$getResult || mysqli_num_rows($getResult) !== 1) {
    echo json_encode(['success' => false, 'error' => 'Customer created but cannot be retrieved']);
    exit();
}

echo json_encode([
    'success' => true,
    'customer' => mysqli_fetch_assoc($getResult)
]);
?>