<?php
require_once '../backend/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: dashboard.php');
    exit();
}

$users_query = "SELECT * FROM users ORDER BY id ASC";
$users_result = mysqli_query($conn, $users_query);

if (isset($_GET['delete_user'])) {
    $delete_id = intval($_GET['delete_user']);
    if ($delete_id != $_SESSION['user_id']) {
        mysqli_query($conn, "DELETE FROM users WHERE id = $delete_id");
    }
    header('Location: manage_users.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link rel="icon" type="image/x-icon" href="images/logo.ico" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <img src="images/logo.jpg" alt="FixFlo Logo">
        </div>
        <button class="menu-toggle" onclick="toggleMobileMenu()">
            <i class="fas fa-bars"></i>
        </button>
        <nav id="mobileNav">
            <a href="dashboard.php">
                <span><i class="fas fa-chart-line"></i></span> Dashboard
            </a>
            <a href="manage_users.php" class="active">
                <span><i class="fas fa-users"></i></span> Manage Users
            </a>
            <a href="add_product.php">
                <span><i class="fas fa-plus-circle"></i></span> Add Products
            </a>
            <a href="add_product.php">
                <span><i class="fas fa-edit"></i></span> Edit
            </a>
            <a href="../backend/logout.php">
                <span><i class="fas fa-sign-out-alt"></i></span> Logout
            </a>
        </nav>
    </div>

    <div class="main-content">
        <header class="top-bar">
            <h1>Manage Users</h1>
            <div class="user-info">
                <span class="username-label">Username</span>
                <span class="username"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <div class="user-avatar"><i class="fas fa-user-circle"></i></div>
            </div>
        </header>

        <div class="content-area">
            <div class="products-section full-width">
                <div class="products-table-container">
                    <h2>Users Table</h2>
                    <table class="products-table">
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($users_result) > 0): ?>
                                <?php while ($user = mysqli_fetch_assoc($users_result)): ?>
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo ucfirst($user['role']); ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <a href="?delete_user=<?php echo $user['id']; ?>" 
                                                   onclick="return confirm('Are you sure you want to delete this user?')" 
                                                   class="btn-delete">Delete</a>
                                            <?php else: ?>
                                                <span style="color: #999;">Current User</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center;">No users found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleMobileMenu() {
            const nav = document.getElementById('mobileNav');
            nav.classList.toggle('active');
        }
    </script>
</body>
</html>
