<?php
require_once '../backend/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: dashboard.php');
    exit();
}

$message = '';
$messageType = '';

function derivePrefix(string $name): string {
    $letters = preg_replace('/[^A-Z]/', '', strtoupper($name));
    return substr(str_pad($letters ?: 'CAT', 3, 'X'), 0, 3);
}

function hasCategoryCodePrefixColumn(mysqli $conn): bool {
    $result = mysqli_query($conn, "SHOW COLUMNS FROM categories LIKE 'code_prefix'");
    return $result && mysqli_num_rows($result) > 0;
}

$hasCategoryCodePrefix = hasCategoryCodePrefixColumn($conn);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $category_name = mysqli_real_escape_string($conn, trim($_POST['category_name']));

    // Sanitise code_prefix: letters only, uppercase, max 3 characters
    $raw_prefix = strtoupper(preg_replace('/[^A-Za-z]/', '', trim($_POST['code_prefix'] ?? '')));
    $raw_prefix = substr($raw_prefix, 0, 3);
    // Auto-generate if blank
    if ($raw_prefix === '') {
        $raw_prefix = derivePrefix($category_name);
    }
    $code_prefix_safe = mysqli_real_escape_string($conn, $raw_prefix);

    if (!empty($category_name)) {
        // Check name uniqueness
        $check_query = "SELECT id FROM categories WHERE category_name = '$category_name'";
        $check_result = mysqli_query($conn, $check_query);

        if (mysqli_num_rows($check_result) > 0) {
            $message = 'Category already exists!';
            $messageType = 'error';
        } else {
            if ($hasCategoryCodePrefix) {
                // Check prefix uniqueness across existing categories
                $check_prefix_query = "SELECT id, category_name FROM categories WHERE code_prefix = '$code_prefix_safe'";
                $check_prefix_result = mysqli_query($conn, $check_prefix_query);
                if ($check_prefix_result && mysqli_num_rows($check_prefix_result) > 0) {
                    $conflict = mysqli_fetch_assoc($check_prefix_result);
                    $message = "Prefix '$raw_prefix' is already used by category '" . htmlspecialchars($conflict['category_name']) . "'. Choose a different prefix.";
                    $messageType = 'error';
                } else {
                    $insert_query = "INSERT INTO categories (category_name, code_prefix) VALUES ('$category_name', '$code_prefix_safe')";
                    if (mysqli_query($conn, $insert_query)) {
                        $message = "Category added with code prefix '$raw_prefix'!";
                        $messageType = 'success';
                    } else {
                        $message = 'Error adding category: ' . mysqli_error($conn);
                        $messageType = 'error';
                    }
                }
            } else {
                $insert_query = "INSERT INTO categories (category_name) VALUES ('$category_name')";
                if (mysqli_query($conn, $insert_query)) {
                    $message = "Category added. (Run DB migration to persist custom prefixes.)";
                    $messageType = 'success';
                } else {
                    $message = 'Error adding category: ' . mysqli_error($conn);
                    $messageType = 'error';
                }
            }
        }
    } else {
        $message = 'Please enter a category name';
        $messageType = 'error';
    }
}

$categories_query = "SELECT * FROM categories ORDER BY category_name ASC";
$categories_result = mysqli_query($conn, $categories_query);

if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    $check_products = "SELECT COUNT(*) as count FROM products WHERE category_id = $delete_id";
    $check_result = mysqli_query($conn, $check_products);
    $check_data = mysqli_fetch_assoc($check_result);
    
    if ($check_data['count'] > 0) {
        $message = 'Cannot delete category with existing products!';
        $messageType = 'error';
    } else {
        $delete_query = "DELETE FROM categories WHERE id = $delete_id";
        if (mysqli_query($conn, $delete_query)) {
            header('Location: add_category.php?success=deleted');
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Category</title>
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
            <a href="manage_users.php">
                <span><i class="fas fa-users"></i></span> Manage Users
            </a>
            <a href="manage_customers.php">
                <span><i class="fas fa-address-book"></i></span> Manage Customers
            </a>
            <a href="add_product.php">
                <span><i class="fas fa-plus-circle"></i></span> Add Products
            </a>
            <a href="add_category.php" class="active">
                <span><i class="fas fa-folder-plus"></i></span> Add Category
            </a>
            <a href="../backend/logout.php">
                <span><i class="fas fa-sign-out-alt"></i></span> Logout
            </a>
        </nav>
    </div>

    <div class="main-content">
        <header class="top-bar">
            <h1>Manage Categories</h1>
            <div class="user-info">
                <span class="username-label">Username</span>
                <span class="username"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <div class="user-avatar"><i class="fas fa-user-circle"></i></div>
            </div>
        </header>

        <div class="content-area">
            <div class="category-management-container">
                <div class="add-category-section">
                    <h2><i class="fas fa-folder-plus"></i> Add New Category</h2>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $messageType; ?>">
                            <?php echo $message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" class="category-form">
                        <div class="form-group">
                            <label for="category_name">Category Name</label>
                            <input type="text" id="category_name" name="category_name"
                                   placeholder="e.g., Snacks, Beverages"
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="code_prefix">Product Code Prefix <span style="font-weight:400;font-size:12px;color:#888;">(3 letters, auto-generated if blank)</span></label>
                            <input type="text" id="code_prefix" name="code_prefix"
                                   placeholder="e.g., SNA"
                                   maxlength="3"
                                   autocomplete="off">
                            <small class="field-hint" id="codePrefixHint">Will be auto-generated from category name if left blank.</small>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-plus"></i> Add Category
                            </button>
                        </div>
                    </form>
                </div>

                <div class="categories-list-section">
                    <h2><i class="fas fa-list"></i> Existing Categories</h2>
                    
                    <?php if (mysqli_num_rows($categories_result) > 0): ?>
                        <div class="categories-grid">
                            <?php while ($category = mysqli_fetch_assoc($categories_result)): ?>
                                <?php
                                $count_query = "SELECT COUNT(*) as count FROM products WHERE category_id = {$category['id']}";
                                $count_result = mysqli_query($conn, $count_query);
                                $count_data = mysqli_fetch_assoc($count_result);
                                ?>
                                <div class="category-card">
                                    <div class="category-card-icon">
                                        <i class="fas fa-folder"></i>
                                    </div>
                                    <div class="category-card-info">
                                        <h3><?php echo htmlspecialchars($category['category_name']); ?></h3>
                                        <?php
                                        $displayPrefix = !empty($category['code_prefix'] ?? '')
                                            ? strtoupper((string)$category['code_prefix'])
                                            : derivePrefix($category['category_name']);
                                        ?>
                                        <p class="category-prefix">Code Prefix: <strong><?php echo htmlspecialchars($displayPrefix); ?></strong> &rarr; e.g. <?php echo htmlspecialchars($displayPrefix); ?>001</p>
                                        <p class="category-count"><?php echo $count_data['count']; ?> product(s)</p>
                                        <p class="category-date">Created: <?php echo date('M d, Y', strtotime($category['created_at'])); ?></p>
                                    </div>
                                    <div class="category-card-actions">
                                        <?php if ($count_data['count'] == 0): ?>
                                            <button onclick="deleteCategory(<?php echo $category['id']; ?>, '<?php echo addslashes($category['category_name']); ?>')" 
                                                    class="btn-delete-category">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        <?php else: ?>
                                            <span class="cannot-delete" title="Cannot delete category with products">
                                                <i class="fas fa-lock"></i> In use
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-categories">
                            <i class="fas fa-folder-open"></i>
                            <p>No categories yet. Add your first category above!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="js/add_category.js"></script>
</body>
</html>
