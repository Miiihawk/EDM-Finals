<?php
require_once '../backend/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

function buildProductCode(int $productId, string $categoryName = '', string $codePrefix = ''): string {
    if ($codePrefix !== '') {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $codePrefix), 0, 3));
        $prefix = str_pad($prefix, 3, 'X');
    } else {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($categoryName));
        $prefix  = substr(str_pad($letters ?: 'ITM', 3, 'X'), 0, 3);
    }
    return $prefix . str_pad((string)$productId, 3, '0', STR_PAD_LEFT);
}

function hasCategoryCodePrefixColumn(mysqli $conn): bool {
    $result = mysqli_query($conn, "SHOW COLUMNS FROM categories LIKE 'code_prefix'");
    return $result && mysqli_num_rows($result) > 0;
}

function hasProductCodeColumn(mysqli $conn): bool {
    $result = mysqli_query($conn, "SHOW COLUMNS FROM products LIKE 'product_code'");
    return $result && mysqli_num_rows($result) > 0;
}

function hasProductImagePathColumn(mysqli $conn): bool {
    $result = mysqli_query($conn, "SHOW COLUMNS FROM products LIKE 'image_path'");
    return $result && mysqli_num_rows($result) > 0;
}

function hasProductImageBlobColumn(mysqli $conn): bool {
    $result = mysqli_query($conn, "SHOW COLUMNS FROM products LIKE 'image_blob'");
    return $result && mysqli_num_rows($result) > 0;
}

function hasProductImageMimeColumn(mysqli $conn): bool {
    $result = mysqli_query($conn, "SHOW COLUMNS FROM products LIKE 'image_mime'");
    return $result && mysqli_num_rows($result) > 0;
}

function ensureProductImageColumns(mysqli $conn): bool {
    if (!hasProductImagePathColumn($conn)) {
        mysqli_query($conn, "ALTER TABLE products ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER product_code");
    }

    if (!hasProductImageBlobColumn($conn)) {
        mysqli_query($conn, "ALTER TABLE products ADD COLUMN image_blob LONGBLOB DEFAULT NULL AFTER image_path");
    }

    if (!hasProductImageMimeColumn($conn)) {
        mysqli_query($conn, "ALTER TABLE products ADD COLUMN image_mime VARCHAR(50) DEFAULT NULL AFTER image_blob");
    }

    return hasProductImageBlobColumn($conn) && hasProductImageMimeColumn($conn);
}

function saveUploadedProductImageToDatabase(mysqli $conn, array $file, int $productId): bool {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return true;
    }

    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return false;
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        return false;
    }

    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $mime = (string)($imageInfo['mime'] ?? '');
    if (!in_array($mime, $allowedMimes, true)) {
        return false;
    }

    $binary = @file_get_contents($file['tmp_name']);
    if ($binary === false) {
        return false;
    }

    $stmt = mysqli_prepare($conn, "UPDATE products SET image_blob = ?, image_mime = ? WHERE id = ?");
    if (!$stmt) {
        return false;
    }

    $nullBlob = null;
    mysqli_stmt_bind_param($stmt, 'bsi', $nullBlob, $mime, $productId);
    mysqli_stmt_send_long_data($stmt, 0, $binary);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function fetchCategoryCodeContext(mysqli $conn, int $categoryId, bool $hasCategoryCodePrefix): array {
    $categoryId = (int)$categoryId;
    $query = $hasCategoryCodePrefix
        ? "SELECT category_name, code_prefix FROM categories WHERE id = $categoryId LIMIT 1"
        : "SELECT category_name, '' AS code_prefix FROM categories WHERE id = $categoryId LIMIT 1";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) === 1) {
        return mysqli_fetch_assoc($result);
    }

    return [
        'category_name' => '',
        'code_prefix' => ''
    ];
}

$hasCategoryCodePrefix = hasCategoryCodePrefixColumn($conn);
$hasProductCode = hasProductCodeColumn($conn);
$hasProductImage = ensureProductImageColumns($conn);

$edit_mode = false;
$product = null;
$message = '';

$categories_query = $hasCategoryCodePrefix
    ? "SELECT id, category_name, code_prefix FROM categories ORDER BY category_name ASC"
    : "SELECT id, category_name, '' AS code_prefix FROM categories ORDER BY category_name ASC";
$categories_result = mysqli_query($conn, $categories_query);

// Collect categories into an array so the result can be used for both the
// HTML <select> and the JSON data injected for the JS preview.
$categories_list = [];
if ($categories_result) {
    while ($cat_row = mysqli_fetch_assoc($categories_result)) {
        $categories_list[] = [
            'id'            => (int)$cat_row['id'],
            'category_name' => $cat_row['category_name'],
            'code_prefix'   => $cat_row['code_prefix'] ?? '',
        ];
    }
}

if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $edit_query = $hasCategoryCodePrefix
        ? "SELECT p.*, c.category_name, c.code_prefix FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = $edit_id"
        : "SELECT p.*, c.category_name, '' AS code_prefix FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = $edit_id";
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
            if ($hasProductImage) {
                saveUploadedProductImageToDatabase($conn, $_FILES['product_image'] ?? [], $edit_id);
            }

            if ($hasProductCode) {
                $categoryData = fetchCategoryCodeContext($conn, $category_id, $hasCategoryCodePrefix);
                $computedCode = buildProductCode(
                    $edit_id,
                    (string)($categoryData['category_name'] ?? ''),
                    (string)($categoryData['code_prefix'] ?? '')
                );
                $safeCode = mysqli_real_escape_string($conn, $computedCode);
                mysqli_query($conn, "UPDATE products SET product_code = '$safeCode' WHERE id = $edit_id");
            }

            if ($stock_diff != 0) {
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

            if ($hasProductImage) {
                saveUploadedProductImageToDatabase($conn, $_FILES['product_image'] ?? [], $product_id);
            }

            if ($hasProductCode) {
                $categoryData = fetchCategoryCodeContext($conn, $category_id, $hasCategoryCodePrefix);
                $computedCode = buildProductCode(
                    $product_id,
                    (string)($categoryData['category_name'] ?? ''),
                    (string)($categoryData['code_prefix'] ?? '')
                );
                $safeCode = mysqli_real_escape_string($conn, $computedCode);
                mysqli_query($conn, "UPDATE products SET product_code = '$safeCode' WHERE id = $product_id");
            }
            
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
            <?php if ($_SESSION['role'] == 'admin'): ?>
            <a href="manage_users.php">
                <span><i class="fas fa-users"></i></span> Manage Users
            </a>
            <a href="manage_customers.php">
                <span><i class="fas fa-address-book"></i></span> Manage Customers
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

                <form method="POST" action="" class="product-form-wide" enctype="multipart/form-data">
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
                            <?php foreach ($categories_list as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"
                                    <?php echo ($edit_mode && $product['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Product Code Preview</label>
                        <input type="text" id="productCodePreview" readonly
                               class="code-preview-field"
                               placeholder="Select a category first"
                               value="<?php
                                   if ($edit_mode) {
                                       echo htmlspecialchars(buildProductCode(
                                           (int)$product['id'],
                                           (string)($product['category_name'] ?? ''),
                                           (string)($product['code_prefix'] ?? '')
                                       ));
                                   }
                               ?>">
                        <small class="code-preview-hint" id="codePreviewHint">
                            <?php if ($edit_mode): ?>
                                This product&apos;s code (prefix from category + product ID).
                            <?php else: ?>
                                Select a category to preview the product code.
                            <?php endif; ?>
                        </small>
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

                    <div class="form-group">
                        <label for="product_image">Product Photo</label>
                        <input type="file" id="product_image" name="product_image" accept="image/png,image/jpeg,image/webp,image/gif">
                        <?php if ($edit_mode && (!empty($product['image_blob']) || !empty($product['image_path']))): ?>
                            <small class="code-preview-hint">Current photo is stored in the database.</small>
                        <?php else: ?>
                            <small class="code-preview-hint">Optional. Recommended size: 500x500.</small>
                        <?php endif; ?>
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
    // PHP-injected data for the product code preview (used by add_product.js)
    window.PRODUCT_DATA = {
        categories:         <?php echo json_encode($categories_list, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        isEditMode:         <?php echo $edit_mode ? 'true' : 'false'; ?>,
        productId:          <?php echo $edit_mode ? (int)$product['id'] : 0; ?>,
        selectedCategoryId: <?php echo $edit_mode ? (int)$product['category_id'] : 0; ?>
    };
    </script>
    <script src="js/add_product.js"></script>
</body>
</html>
