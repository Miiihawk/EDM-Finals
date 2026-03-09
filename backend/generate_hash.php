<?php
// Password Hash Generator
echo "<h2>Password Hash Generator</h2>";

// Generate hashes
$admin_password = "admin123";
$user_password = "user123";

$admin_hash = password_hash($admin_password, PASSWORD_DEFAULT);
$user_hash = password_hash($user_password, PASSWORD_DEFAULT);

echo "<p><strong>For admin123:</strong><br><code>$admin_hash</code></p>";
echo "<p><strong>For user123:</strong><br><code>$user_hash</code></p>";

echo "<hr>";
echo "<h3>Update your database with this SQL:</h3>";
echo "<pre>";
echo "UPDATE users SET password = '$admin_hash' WHERE username = 'admin_user';\n";
echo "UPDATE users SET password = '$user_hash' WHERE username = 'regular_user';";
echo "</pre>";
?>
