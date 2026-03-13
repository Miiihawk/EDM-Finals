<?php
require_once '../backend/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: dashboard.php');
    exit();
}

$success_msg = '';
$error_msg = '';

if (isset($_GET['delete_user'])) {
    $delete_id = intval($_GET['delete_user']);
    if ($delete_id != $_SESSION['user_id']) {
        mysqli_query($conn, "DELETE FROM users WHERE id = $delete_id");
    }
    header('Location: manage_users.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_user') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name'] ?? '');
        $username   = trim($_POST['username'] ?? '');
        $password   = $_POST['password'] ?? '';
        $role       = in_array($_POST['role'] ?? '', ['admin', 'user']) ? $_POST['role'] : 'user';

        if (!$first_name || !$last_name || !$username || !$password) {
            $error_msg = 'All fields are required.';
        } else {
            $check = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
            mysqli_stmt_bind_param($check, 's', $username);
            mysqli_stmt_execute($check);
            mysqli_stmt_store_result($check);
            if (mysqli_stmt_num_rows($check) > 0) {
                $error_msg = 'Username already exists.';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($conn, "INSERT INTO users (username, password, first_name, last_name, role) VALUES (?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, 'sssss', $username, $hashed, $first_name, $last_name, $role);
                if (mysqli_stmt_execute($stmt)) {
                    $success_msg = 'User added successfully.';
                } else {
                    $error_msg = 'Failed to add user: ' . mysqli_error($conn);
                }
            }
        }
    } elseif ($_POST['action'] === 'edit_user') {
        $edit_id    = intval($_POST['user_id'] ?? 0);
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name'] ?? '');
        $role       = in_array($_POST['role'] ?? '', ['admin', 'user']) ? $_POST['role'] : 'user';

        if (!$first_name || !$last_name || !$edit_id) {
            $error_msg = 'First name and last name are required.';
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE users SET first_name=?, last_name=?, role=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'sssi', $first_name, $last_name, $role, $edit_id);
            if (mysqli_stmt_execute($stmt)) {
                $success_msg = 'User updated successfully.';
            } else {
                $error_msg = 'Failed to update user.';
            }
        }
    }
}

$users_query = "SELECT * FROM users ORDER BY id ASC";
$users_result = mysqli_query($conn, $users_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link rel="icon" type="image/x-icon" href="images/logo.ico" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <img src="images/logo.jpg" alt="FixFlo Logo" class="app-logo">
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
            <a href="manage_customers.php">
                <span><i class="fas fa-address-book"></i></span> Manage Customers
            </a>
            <a href="add_product.php">
                <span><i class="fas fa-plus-circle"></i></span> Add Products
            </a>
            <a href="add_category.php">
                <span><i class="fas fa-folder-plus"></i></span> Add Category
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
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
                        <h2 style="margin:0;">Users Table</h2>
                        <button class="btn-add-product" onclick="openAddUserModal()">
                            <i class="fas fa-user-plus"></i> Add User
                        </button>
                    </div>

                    <?php if ($success_msg): ?>
                        <div class="alert alert-success" style="margin-bottom:12px;"><?php echo htmlspecialchars($success_msg); ?></div>
                    <?php endif; ?>
                    <?php if ($error_msg): ?>
                        <div class="alert alert-error" style="margin-bottom:12px;"><?php echo htmlspecialchars($error_msg); ?></div>
                    <?php endif; ?>

                    <div class="admin-table-view">
                        <table class="products-table" id="usersTable">
                            <thead>
                                <tr>
                                    <th>User ID</th>
                                    <th>First Name</th>
                                    <th>Last Name</th>
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
                                            <td><?php echo htmlspecialchars($user['first_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo ucfirst($user['role']); ?></td>
                                            <td><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                                            <td style="white-space:nowrap; vertical-align:middle;">
                                                <button class="btn-update"
                                                    onclick="openEditUserModal(<?php echo $user['id']; ?>, '<?php echo addslashes($user['first_name']); ?>', '<?php echo addslashes($user['last_name']); ?>', '<?php echo $user['role']; ?>')">
                                                    Update
                                                </button>
                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                    <a href="?delete_user=<?php echo $user['id']; ?>"
                                                       onclick="return confirm('Are you sure you want to delete this user?')"
                                                       class="btn-delete">Delete</a>
                                                <?php else: ?>
                                                    <span style="color:#999; vertical-align:middle;">Current User</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align:center;">No users found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add User Modal -->
        <div id="addUserModal" class="modal-overlay" style="display:none;">
            <div class="modal-box">
                <div class="modal-header">
                    <h3>Add New User</h3>
                    <button class="modal-close" onclick="closeAddUserModal()">&times;</button>
                </div>
                <form method="POST" action="manage_users.php">
                    <input type="hidden" name="action" value="add_user">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" class="form-control" required placeholder="First name">
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" class="form-control" required placeholder="Last name">
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control" required placeholder="Username">
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" required placeholder="Password">
                    </div>
                    <div class="form-group">
                        <label>Role</label>
                        <select name="role" class="form-control">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn-cancel" onclick="closeAddUserModal()">Cancel</button>
                        <button type="submit" class="btn-save">Add User</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit User Modal -->
        <div id="editUserModal" class="modal-overlay" style="display:none;">
            <div class="modal-box">
                <div class="modal-header">
                    <h3>Edit User</h3>
                    <button class="modal-close" onclick="closeEditUserModal()">&times;</button>
                </div>
                <form method="POST" action="manage_users.php">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="user_id" id="editUserId">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" id="editFirstName" class="form-control" required placeholder="First name">
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" id="editLastName" class="form-control" required placeholder="Last name">
                    </div>
                    <div class="form-group">
                        <label>Role</label>
                        <select name="role" id="editRole" class="form-control">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn-cancel" onclick="closeEditUserModal()">Cancel</button>
                        <button type="submit" class="btn-save">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleMobileMenu() {
            const nav = document.getElementById('mobileNav');
            nav.classList.toggle('active');
        }

        function openAddUserModal() {
            document.getElementById('addUserModal').style.display = 'flex';
        }
        function closeAddUserModal() {
            document.getElementById('addUserModal').style.display = 'none';
        }

        function openEditUserModal(id, firstName, lastName, role) {
            document.getElementById('editUserId').value = id;
            document.getElementById('editFirstName').value = firstName;
            document.getElementById('editLastName').value = lastName;
            document.getElementById('editRole').value = role;
            document.getElementById('editUserModal').style.display = 'flex';
        }
        function closeEditUserModal() {
            document.getElementById('editUserModal').style.display = 'none';
        }

        // Close modals on backdrop click
        document.getElementById('addUserModal').addEventListener('click', function(e) {
            if (e.target === this) closeAddUserModal();
        });
        document.getElementById('editUserModal').addEventListener('click', function(e) {
            if (e.target === this) closeEditUserModal();
        });
    </script>
</body>
</html>
