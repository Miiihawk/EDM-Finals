<?php
require_once '../backend/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get all products - default sort by ID
$products_query = "SELECT * FROM products ORDER BY id ASC";
$products_result = mysqli_query($conn, $products_query);

// Get recent logs - most recent first
$logs_query = "SELECT * FROM logs ORDER BY id DESC, log_time DESC LIMIT 10";
$logs_result = mysqli_query($conn, $logs_query);

// Handle product deletion (admin only)
if (isset($_GET['delete_id']) && $_SESSION['role'] == 'admin') {
    $delete_id = intval($_GET['delete_id']);
    mysqli_query($conn, "DELETE FROM products WHERE id = $delete_id");
    header('Location: dashboard.php');
    exit();
}

// Check if user is regular user or admin
$isRegularUser = ($_SESSION['role'] != 'admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isRegularUser ? 'POS System' : 'Dashboard'; ?> - FixFlo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
    <?php if ($isRegularUser): ?>
    <link rel="stylesheet" href="pos.css">
    <?php endif; ?>
</head>
<body>
    <div class="sidebar <?php echo $isRegularUser ? 'pos-sidebar' : ''; ?>">
        <div class="logo">
            <h2>FixFlo</h2>
        </div>
        <button class="menu-toggle" onclick="toggleMobileMenu()">
            <i class="fas fa-bars"></i>
        </button>
        <nav id="mobileNav">
            <a href="dashboard.php" class="active">
                <span><i class="fas fa-<?php echo $isRegularUser ? 'cash-register' : 'chart-line'; ?>"></i></span> <?php echo $isRegularUser ? 'POS' : 'Dashboard'; ?>
            </a>
            <?php if ($_SESSION['role'] == 'admin'): ?>
            <a href="manage_users.php">
                <span><i class="fas fa-users"></i></span> Manage Users
            </a>
            <a href="add_product.php">
                <span><i class="fas fa-plus-circle"></i></span> Add Products
            </a>
            <a href="add_product.php">
                <span><i class="fas fa-edit"></i></span> Edit
            </a>
            <?php endif; ?>
            <a href="../backend/logout.php">
                <span><i class="fas fa-sign-out-alt"></i></span> Logout
            </a>
        </nav>
    </div>

    <div class="main-content">
        <header class="top-bar">
            <h1><?php echo $isRegularUser ? 'Point of Sale' : 'Dashboard'; ?></h1>
            <div class="user-info">
                <span class="username-label">Username</span>
                <span class="username"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <div class="user-avatar"><i class="fas fa-user-circle"></i></div>
            </div>
        </header>

        <?php if ($isRegularUser): ?>
        <!-- POS SYSTEM FOR REGULAR USERS -->
        <div class="pos-container">
            <div class="pos-products">
                <div class="pos-search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" id="posSearchInput" placeholder="Search products..." onkeyup="searchPOSProducts()">
                </div>
                
                <div class="category-filter">
                    <button class="category-btn active" onclick="filterCategory('all')">All</button>
                    <button class="category-btn" onclick="filterCategory('Tools')">Tools</button>
                    <button class="category-btn" onclick="filterCategory('Paint')">Paint</button>
                    <button class="category-btn" onclick="filterCategory('Electrical')">Electrical</button>
                    <button class="category-btn" onclick="filterCategory('Plumbing')">Plumbing</button>
                    <button class="category-btn" onclick="filterCategory('Fasteners')">Fasteners</button>
                </div>
                
                <div class="products-grid" id="productsGrid">
                    <?php 
                    mysqli_data_seek($products_result, 0);
                    while ($product = mysqli_fetch_assoc($products_result)): 
                        $isOutOfStock = $product['stock'] <= 0;
                    ?>
                        <div class="product-card <?php echo $isOutOfStock ? 'out-of-stock' : ''; ?>" 
                             data-category="<?php echo htmlspecialchars($product['category']); ?>"
                             onclick="<?php echo !$isOutOfStock ? 'addToCart(' . $product['id'] . ', \'' . addslashes($product['product_name']) . '\', ' . $product['price'] . ', \'' . htmlspecialchars($product['category']) . '\')' : ''; ?>">
                            <div class="product-icon">
                                <?php 
                                $icons = [
                                    'Tools' => '<i class="fas fa-hammer"></i>',
                                    'Paint' => '<i class="fas fa-paint-roller"></i>',
                                    'Electrical' => '<i class="fas fa-bolt"></i>',
                                    'Plumbing' => '<i class="fas fa-wrench"></i>',
                                    'Fasteners' => '<i class="fas fa-screwdriver"></i>'
                                ];
                                echo $icons[$product['category']] ?? '<i class="fas fa-box"></i>';
                                ?>
                            </div>
                            <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                            <div class="product-category"><?php echo htmlspecialchars($product['category']); ?></div>
                            <div class="product-price">₱<?php echo number_format($product['price'], 2); ?></div>
                            <div class="product-stock <?php echo $product['stock'] < 10 ? 'low' : ''; ?>">
                                <?php echo $isOutOfStock ? 'Out of Stock' : $product['stock'] . ' in stock'; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            
            <div class="pos-cart">
                <div class="cart-header">
                    <i class="fas fa-shopping-cart"></i> Cart
                </div>
                <div class="cart-items" id="cartItems">
                    <div class="cart-empty">
                        <i class="fas fa-shopping-cart"></i>
                        <p>Your cart is empty</p>
                    </div>
                </div>
                <div class="cart-summary" id="cartSummary" style="display: none;">
                    <div class="summary-row">
                        <span>Subtotal:</span>
                        <span id="subtotal">₱0.00</span>
                    </div>
                    <div class="summary-row total">
                        <span>Total:</span>
                        <span id="total">₱0.00</span>
                    </div>
                </div>
                <button class="checkout-btn" id="checkoutBtn" onclick="checkout()" disabled>
                    Checkout
                </button>
            </div>
        </div>

        <?php else: ?>
        <!-- ADMIN DASHBOARD -->
        <div class="content-area">
            <div class="products-section">
                <div class="search-bar">
                    <input type="text" id="searchInput" placeholder="Search..." onkeyup="searchProducts()">
                    <select id="sortBy" onchange="sortProducts()">
                        <option value="">Sort By</option>
                        <option value="name">Name</option>
                        <option value="price">Price</option>
                        <option value="stock">Stock</option>
                        <option value="category">Category</option>
                    </select>
                </div>

                <div class="products-table-container">
                    <h2>Products Table</h2>
                    <table class="products-table" id="productsTable">
                        <thead>
                            <tr>
                                <th>Product ID</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Payments</th>
                                <th>Status</th>
                                <th>Stock</th>
                                <th colspan="2">Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            mysqli_data_seek($products_result, 0);
                            if (mysqli_num_rows($products_result) > 0): 
                            ?>
                                <?php while ($product = mysqli_fetch_assoc($products_result)): ?>
                                    <tr>
                                        <td><?php echo $product['id']; ?></td>
                                        <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                        <td><?php echo htmlspecialchars($product['category']); ?></td>
                                        <td>₱<?php echo number_format($product['price'], 2); ?></td>
                                        <td><?php echo $product['status']; ?></td>
                                        <td><?php echo $product['stock']; ?></td>
                                        <td>
                                            <a href="add_product.php?edit_id=<?php echo $product['id']; ?>" class="btn-update">Update</a>
                                        </td>
                                        <td>
                                            <a href="?delete_id=<?php echo $product['id']; ?>" 
                                               onclick="return confirm('Are you sure you want to delete this product?')" 
                                               class="btn-delete"><i class="fas fa-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center;">No products found. <a href="add_product.php">Add a product</a></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="logs-section">
                <h2>Logs</h2>
                <div class="logs-container">
                    <?php if (mysqli_num_rows($logs_result) > 0): ?>
                        <?php while ($log = mysqli_fetch_assoc($logs_result)): ?>
                            <div class="log-item">
                                <div class="log-header">
                                    <strong>Product</strong> 
                                    <strong>Quantity</strong> 
                                    <strong>Time</strong>
                                </div>
                                <div class="log-body">
                                    <span><?php echo htmlspecialchars($log['product_name']); ?></span>
                                    <span class="<?php echo $log['quantity'] < 0 ? 'qty-negative' : 'qty-positive'; ?>">
                                        <?php echo $log['quantity'] > 0 ? '+' : ''; ?><?php echo $log['quantity']; ?>
                                    </span>
                                    <span class="log-time">
                                        <?php echo date('h:i A', strtotime($log['log_time'])); ?><br>
                                        <?php echo date('d.m.y', strtotime($log['log_time'])); ?>
                                    </span>
                                </div>
                                <div class="log-footer">
                                    by <?php echo htmlspecialchars($log['username']); ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: #777;">No logs available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($isRegularUser): ?>
    <script>
        // POS System JavaScript
        let cart = [];

        function addToCart(id, name, price, category) {
            const existingItem = cart.find(item => item.id === id);
            
            if (existingItem) {
                existingItem.quantity++;
            } else {
                const icons = {
                    'Tools': '<i class="fas fa-hammer"></i>',
                    'Paint': '<i class="fas fa-paint-roller"></i>',
                    'Electrical': '<i class="fas fa-bolt"></i>',
                    'Plumbing': '<i class="fas fa-wrench"></i>',
                    'Fasteners': '<i class="fas fa-screwdriver"></i>'
                };
                cart.push({
                    id: id,
                    name: name,
                    price: price,
                    quantity: 1,
                    icon: icons[category] || '<i class="fas fa-box"></i>'
                });
            }
            
            updateCart();
        }

        function updateQuantity(id, change) {
            const item = cart.find(item => item.id === id);
            if (item) {
                item.quantity += change;
                if (item.quantity <= 0) {
                    removeFromCart(id);
                } else {
                    updateCart();
                }
            }
        }

        function removeFromCart(id) {
            cart = cart.filter(item => item.id !== id);
            updateCart();
        }

        function updateCart() {
            const cartItemsContainer = document.getElementById('cartItems');
            const cartSummary = document.getElementById('cartSummary');
            const checkoutBtn = document.getElementById('checkoutBtn');
            
            if (cart.length === 0) {
                cartItemsContainer.innerHTML = `
                    <div class="cart-empty">
                        <i class="fas fa-shopping-cart"></i>
                        <p>Your cart is empty</p>
                    </div>
                `;
                cartSummary.style.display = 'none';
                checkoutBtn.disabled = true;
            } else {
                let html = '';
                let subtotal = 0;
                
                cart.forEach(item => {
                    const itemTotal = item.price * item.quantity;
                    subtotal += itemTotal;
                    
                    html += `
                        <div class="cart-item">
                            <div class="cart-item-icon">${item.icon}</div>
                            <div class="cart-item-details">
                                <div class="cart-item-name">${item.name}</div>
                                <div class="cart-item-price">₱${item.price.toFixed(2)}</div>
                            </div>
                            <div class="cart-item-controls">
                                <button class="qty-btn" onclick="updateQuantity(${item.id}, -1)">−</button>
                                <span class="qty-display">${item.quantity}</span>
                                <button class="qty-btn" onclick="updateQuantity(${item.id}, 1)">+</button>
                                <button class="remove-btn" onclick="removeFromCart(${item.id})">×</button>
                            </div>
                        </div>
                    `;
                });
                
                cartItemsContainer.innerHTML = html;
                document.getElementById('subtotal').textContent = '₱' + subtotal.toFixed(2);
                document.getElementById('total').textContent = '₱' + subtotal.toFixed(2);
                cartSummary.style.display = 'block';
                checkoutBtn.disabled = false;
            }
        }

        function searchPOSProducts() {
            const input = document.getElementById('posSearchInput');
            const filter = input.value.toLowerCase();
            const cards = document.querySelectorAll('.product-card');
            
            cards.forEach(card => {
                const productName = card.querySelector('.product-name').textContent.toLowerCase();
                const productCategory = card.querySelector('.product-category').textContent.toLowerCase();
                
                if (productName.includes(filter) || productCategory.includes(filter)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function filterCategory(category) {
            const cards = document.querySelectorAll('.product-card');
            const buttons = document.querySelectorAll('.category-btn');
            
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            cards.forEach(card => {
                if (category === 'all' || card.dataset.category === category) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function checkout() {
            if (cart.length === 0) return;
            
            const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            
            if (confirm(`Proceed with checkout?\n\nTotal: ₱${total.toFixed(2)}\nItems: ${cart.length}`)) {
                // Log the sale
                cart.forEach(item => {
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', '../backend/process_sale.php', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.send(`product_id=${item.id}&quantity=${item.quantity}`);
                });
                
                alert('Sale completed successfully!');
                cart = [];
                updateCart();
                
                // Reload page to update stock
                setTimeout(() => location.reload(), 500);
            }
        }

        // Mobile menu toggle
        function toggleMobileMenu() {
            const nav = document.getElementById('mobileNav');
            nav.classList.toggle('active');
        }
    </script>
    <?php else: ?>
    <script src="dashboard.js"></script>
    <script>
        // Mobile menu toggle for admin view
        function toggleMobileMenu() {
            const nav = document.getElementById('mobileNav');
            nav.classList.toggle('active');
        }
    </script>
    <?php endif; ?>
</body>
</html>
