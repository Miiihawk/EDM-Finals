<?php
require_once '../backend/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: dashboard.php');
    exit();
}

$message = '';
$messageType = '';
$editCustomer = null;

if (isset($_GET['delete_customer'])) {
    $deleteId = intval($_GET['delete_customer']);
    if ($deleteId > 0) {
        $deleteQuery = "DELETE FROM customers WHERE id = $deleteId";
        if (mysqli_query($conn, $deleteQuery)) {
            $message = 'Customer deleted successfully.';
            $messageType = 'success';
        } else {
            $message = 'Cannot delete customer with existing orders.';
            $messageType = 'error';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = mysqli_real_escape_string($conn, trim($_POST['full_name'] ?? ''));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone'] ?? ''));
    $emailRaw = trim($_POST['email'] ?? '');
    $email = $emailRaw !== '' ? mysqli_real_escape_string($conn, $emailRaw) : null;

    if ($fullName === '' || $phone === '') {
        $message = 'Full name and phone are required.';
        $messageType = 'error';
    } else {
        $editId = intval($_POST['edit_customer_id'] ?? 0);

        if ($editId > 0) {
            $checkPhoneQuery = "SELECT id FROM customers WHERE phone = '$phone' AND id != $editId LIMIT 1";
            $checkPhoneResult = mysqli_query($conn, $checkPhoneQuery);

            if ($checkPhoneResult && mysqli_num_rows($checkPhoneResult) > 0) {
                $message = 'Phone number already exists for another customer.';
                $messageType = 'error';
            } else {
                $emailValue = $email !== null ? "'$email'" : "NULL";

                $updateQuery = "UPDATE customers
                                SET full_name = '$fullName',
                                    phone = '$phone',
                                    email = $emailValue
                                WHERE id = $editId";

                if (mysqli_query($conn, $updateQuery)) {
                    $message = 'Customer updated successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Error updating customer.';
                    $messageType = 'error';
                }
            }
        } else {
            $checkPhoneQuery = "SELECT id FROM customers WHERE phone = '$phone' LIMIT 1";
            $checkPhoneResult = mysqli_query($conn, $checkPhoneQuery);

            if ($checkPhoneResult && mysqli_num_rows($checkPhoneResult) > 0) {
                $message = 'Phone number already exists.';
                $messageType = 'error';
            } else {
                $emailValue = $email !== null ? "'$email'" : "NULL";

                $insertQuery = "INSERT INTO customers (full_name, phone, email)
                                VALUES ('$fullName', '$phone', $emailValue)";

                if (mysqli_query($conn, $insertQuery)) {
                    $message = 'Customer added successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Error adding customer.';
                    $messageType = 'error';
                }
            }
        }
    }
}

if (isset($_GET['edit_customer'])) {
    $editId = intval($_GET['edit_customer']);
    if ($editId > 0) {
        $editQuery = "SELECT * FROM customers WHERE id = $editId LIMIT 1";
        $editResult = mysqli_query($conn, $editQuery);
        if ($editResult && mysqli_num_rows($editResult) === 1) {
            $editCustomer = mysqli_fetch_assoc($editResult);
        }
    }
}

$customersQuery = "SELECT c.*, COUNT(o.id) AS total_orders
                   FROM customers c
                   LEFT JOIN orders o ON o.customer_id = c.id
                   GROUP BY c.id
                   ORDER BY c.created_at DESC";
$customersResult = mysqli_query($conn, $customersQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Customers</title>
    <link rel="icon" type="image/x-icon" href="images/logo.ico" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/manage-customers.css">
</head>
<body class="manage-customers-page">
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
            <a href="manage_users.php">
                <span><i class="fas fa-users"></i></span> Manage Users
            </a>
            <a href="manage_customers.php" class="active">
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
            <h1>Manage Customers</h1>
            <div class="user-info">
                <span class="username-label">Username</span>
                <span class="username"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <div class="user-avatar"><i class="fas fa-user-circle"></i></div>
            </div>
        </header>

        <div class="content-area">
            <div class="products-section full-width">
                <div class="products-table-container customer-form-panel">
                    <h2><?php echo $editCustomer ? 'Edit Customer' : 'Add Customer'; ?></h2>

                    <?php if ($message): ?>
                        <div class="alert <?php echo $messageType === 'error' ? 'alert-error' : ''; ?>">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" class="product-form" autocomplete="off">
                        <?php if ($editCustomer): ?>
                            <input type="hidden" name="edit_customer_id" value="<?php echo (int)$editCustomer['id']; ?>">
                        <?php endif; ?>

                        <div class="product-form customer-form-grid">
                            <div class="form-group full-row">
                                <label for="full_name">Full Name</label>
                                <input class="compact-input" type="text" id="full_name" name="full_name" required
                                       value="<?php echo htmlspecialchars($editCustomer['full_name'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label for="phone">Phone</label>
                                <input class="compact-input" type="text" id="phone" name="phone" required
                                       value="<?php echo htmlspecialchars($editCustomer['phone'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label for="email">Email</label>
                                <input class="compact-input" type="email" id="email" name="email"
                                       value="<?php echo htmlspecialchars($editCustomer['email'] ?? ''); ?>">
                            </div>

                            <div class="form-actions full-row">
                                <button type="submit" class="btn-primary">
                                    <i class="fas fa-plus"></i> <?php echo $editCustomer ? 'Update Customer' : 'Add Customer'; ?>
                                </button>
                                <?php if ($editCustomer): ?>
                                    <a href="manage_customers.php" class="btn-secondary">Cancel Edit</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="products-table-container customers-table-panel">
                    <div class="search-bar">
                        <input type="text" id="customerSearchInput" placeholder="Search customer name, phone, email..." onkeyup="searchCustomers()">
                    </div>

                    <h2 style="margin-top: 15px;">Customers Table</h2>
                    <table class="products-table" id="customersTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Full Name</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Orders</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($customersResult && mysqli_num_rows($customersResult) > 0): ?>
                                <?php while ($customer = mysqli_fetch_assoc($customersResult)): ?>
                                    <tr>
                                        <td><?php echo (int)$customer['id']; ?></td>
                                        <td><?php echo htmlspecialchars($customer['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                                        <td><?php echo htmlspecialchars($customer['email'] ?? 'N/A'); ?></td>
                                        <td><span class="orders-badge"><?php echo (int)$customer['total_orders']; ?></span></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($customer['created_at'])); ?></td>
                                        <td>
                                            <div class="action-group">
                                                <a class="btn-inline btn-edit" href="?edit_customer=<?php echo (int)$customer['id']; ?>">Edit</a>
                                                <a class="btn-delete" href="?delete_customer=<?php echo (int)$customer['id']; ?>"
                                                   onclick="return confirm('Delete this customer? This will fail if they already have orders.')">Delete</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center;">No customers found.</td>
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

        function searchCustomers() {
            const input = document.getElementById('customerSearchInput');
            const filter = (input.value || '').toLowerCase();
            const rows = document.querySelectorAll('#customersTable tbody tr');

            rows.forEach((row) => {
                const rowText = row.textContent.toLowerCase();
                row.style.display = rowText.includes(filter) ? '' : 'none';
            });
        }
    </script>
</body>
</html>
