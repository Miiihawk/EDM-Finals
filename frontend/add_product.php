<?php
require_once '../backend/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$edit_mode = false;
$product = null;
$message = '';

$categories_query = "SELECT * FROM categories ORDER BY category_name ASC";
$categories_result = mysqli_query($conn, $categories_query);

if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $edit_query = "SELECT p.*, c.category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = $edit_id";
    $edit_result = mysqli_query($conn, $edit_query);
    
    if (mysqli_num_rows($edit_result) == 1) {
        $product = mysqli_fetch_assoc($edit_result);
        $edit_mode = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_name = mysqli_real_escape_string($conn, $_POST['product_name']);
    $category_id = intval($_POST['category_id']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $status = $stock > 0 ? 'Available' : 'Out of Stock';
    
    if (isset($_POST['edit_id']) && !empty($_POST['edit_id'])) {
        $edit_id = intval($_POST['edit_id']);
        
        $old_query = "SELECT stock, product_name FROM products WHERE id = $edit_id";
        $old_result = mysqli_query($conn, $old_query);
        $old_product = mysqli_fetch_assoc($old_result);
        $stock_diff = $stock - $old_product['stock'];
        
        $update_query = "UPDATE products SET 
                        product_name = '$product_name', 
                        category_id = $category_id, 
                        price = $price, 
                        stock = $stock, 
                        status = '$status' 
                        WHERE id = $edit_id";
        
        if (mysqli_query($conn, $update_query)) {
            if ($stock_diff != 0) {
                $log_query = "INSERT INTO logs (product_name, quantity, action, username) 
                             VALUES ('$product_name', $stock_diff, 'Updated', '{$_SESSION['username']}')";
                mysqli_query($conn, $log_query);
                
                $history_query = "INSERT INTO inventory_history (product_id, user_id, change_quantity, reason) 
                                 VALUES ($edit_id, {$_SESSION['user_id']}, $stock_diff, 'Product Updated')";
                mysqli_query($conn, $history_query);
            }
            
            $message = 'Product updated successfully!';
            header('Location: dashboard.php');
            exit();
        } else {
            $message = 'Error updating product';
        }
    } else {
        $insert_query = "INSERT INTO products (product_name, category_id, price, stock, status) 
                        VALUES ('$product_name', $category_id, $price, $stock, '$status')";
        
        if (mysqli_query($conn, $insert_query)) {
            $product_id = mysqli_insert_id($conn);
            
            $log_query = "INSERT INTO logs (product_name, quantity, action, username) 
                         VALUES ('$product_name', $stock, 'Added', '{$_SESSION['username']}')";
            mysqli_query($conn, $log_query);
            
            $history_query = "INSERT INTO inventory_history (product_id, user_id, change_quantity, reason) 
                             VALUES ($product_id, {$_SESSION['user_id']}, $stock, 'Product Added')";
            mysqli_query($conn, $history_query);
            
            $message = 'Product added successfully!';
            header('Location: dashboard.php');
            exit();
        } else {
            $message = 'Error adding product';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $edit_mode ? 'Edit' : 'Add'; ?> Product</title>
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
            <?php if ($_SESSION['role'] == 'admin'): ?>
            <a href="manage_users.php">
                <span><i class="fas fa-users"></i></span> Manage Users
            </a>
            <?php endif; ?>
            <a href="add_product.php" class="active">
                <span><i class="fas fa-plus-circle"></i></span> Add Products
            </a>
            <?php if ($_SESSION['role'] == 'admin'): ?>
            <a href="add_category.php">
                <span><i class="fas fa-folder-plus"></i></span> Add Category
            </a>
            <?php endif; ?>
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
            <h1><?php echo $edit_mode ? 'Edit Product' : 'Add Product'; ?></h1>
            <div class="user-info">
                <span class="username-label">Username</span>
                <span class="username"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <div class="user-avatar"><i class="fas fa-user-circle"></i></div>
            </div>
        </header>

        <div class="content-area">
            <div class="form-section-wide">
                <?php if ($message): ?>
                    <div class="alert"><?php echo $message; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="" class="product-form-wide">
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="edit_id" value="<?php echo $product['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="product_name">Product Name</label>
                        <input type="text" id="product_name" name="product_name" 
                               value="<?php echo $edit_mode ? htmlspecialchars($product['product_name']) : ''; ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="category_id">Category</label>
                        <select id="category_id" name="category_id" required>
                            <option value="">Select Category</option>
                            <?php 
                            mysqli_data_seek($categories_result, 0);
                            while ($cat = mysqli_fetch_assoc($categories_result)): 
                            ?>
                                <option value="<?php echo $cat['id']; ?>" 
                                    <?php echo ($edit_mode && $product['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="price">Price (₱)</label>
                        <input type="number" id="price" name="price" step="0.01" min="0" 
                               value="<?php echo $edit_mode ? $product['price'] : ''; ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="stock">Stock</label>
                        <input type="number" id="stock" name="stock" min="0" 
                               value="<?php echo $edit_mode ? $product['stock'] : ''; ?>" 
                               required>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            <?php echo $edit_mode ? 'Update Product' : 'Add Product'; ?>
                        </button>
                        <a href="dashboard.php" class="btn-secondary">Cancel</a>
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
    </script>
</body>
</html>
