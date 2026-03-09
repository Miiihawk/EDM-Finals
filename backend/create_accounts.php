<?php
require_once 'config.php';

// Generate password hashes
$admin_hash = password_hash('admin123', PASSWORD_DEFAULT);
$user_hash = password_hash('user123', PASSWORD_DEFAULT);

echo "<h2>Creating Admin and User Accounts</h2>";

// Delete existing accounts
mysqli_query($conn, "DELETE FROM users WHERE username IN ('admin_user', 'regular_user')");

// Insert admin account
$admin_sql = "INSERT INTO users (username, password, role) VALUES ('admin_user', ?, 'admin')";
$stmt = mysqli_prepare($conn, $admin_sql);
mysqli_stmt_bind_param($stmt, "s", $admin_hash);
$admin_result = mysqli_stmt_execute($stmt);

// Insert regular user account
$user_sql = "INSERT INTO users (username, password, role) VALUES ('regular_user', ?, 'user')";
$stmt = mysqli_prepare($conn, $user_sql);
mysqli_stmt_bind_param($stmt, "s", $user_hash);
$user_result = mysqli_stmt_execute($stmt);

if ($admin_result && $user_result) {
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px; color: #155724; margin: 20px;'>";
    echo "<h3>Success! Accounts created successfully!</h3>";
    echo "<p><strong>Admin Account:</strong><br>";
    echo "Username: <code>admin_user</code><br>";
    echo "Password: <code>admin123</code></p>";
    echo "<p><strong>Regular User Account:</strong><br>";
    echo "Username: <code>regular_user</code><br>";
    echo "Password: <code>user123</code></p>";
    echo "<hr>";
    echo "<p>Admin Hash: <code>$admin_hash</code></p>";
    echo "<p>User Hash: <code>$user_hash</code></p>";
    echo "<hr>";
    echo "<p><a href='../frontend/login.php' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login Page</a></p>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 20px; border-radius: 8px; color: #721c24; margin: 20px;'>";
    echo "<h3>Error creating accounts</h3>";
    echo "<p>" . mysqli_error($conn) . "</p>";
    echo "</div>";
}

mysqli_close($conn);
?>
