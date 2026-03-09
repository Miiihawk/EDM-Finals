<?php
require_once '../backend/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$products_query = "SELECT p.*, c.category_name 
                   FROM products p 
                   LEFT JOIN categories c ON p.category_id = c.id 
                   ORDER BY p.id ASC";
$products_result = mysqli_query($conn, $products_query);

$categories_query = "SELECT DISTINCT c.category_name 
                     FROM categories c 
                     INNER JOIN products p ON c.id = p.category_id 
                     WHERE c.category_name IS NOT NULL 
                     ORDER BY c.category_name";
$categories_result = mysqli_query($conn, $categories_query);

$sales_query = "SELECT s.*, u.username 
                FROM sales s 
                LEFT JOIN users u ON s.user_id = u.id 
                WHERE s.user_id = {$_SESSION['user_id']} 
                ORDER BY s.created_at DESC 
                LIMIT 20";
$sales_result = mysqli_query($conn, $sales_query);

function getSaleItems($conn, $sale_id) {
    $items_query = "SELECT si.*, p.product_name 
                    FROM sale_items si 
                    LEFT JOIN products p ON si.product_id = p.id 
                    WHERE si.sale_id = $sale_id";
    return mysqli_query($conn, $items_query);
}

$admin_inventory_logs_query = "(SELECT 
                                    l.product_name,
                                    l.quantity as change_quantity,
                                    l.username,
                                    l.log_time as timestamp,
                                    l.action as reason
                                FROM logs l)
                                UNION ALL
                                (SELECT 
                                    p.product_name,
                                    ih.change_quantity,
                                    u.username,
                                    ih.created_at as timestamp,
                                    ih.reason
                                FROM inventory_history ih
                                LEFT JOIN products p ON ih.product_id = p.id
                                LEFT JOIN users u ON ih.user_id = u.id
                                WHERE ih.reason != 'Product Updated')
                                ORDER BY timestamp DESC
                                LIMIT 100";
$admin_inventory_logs_result = mysqli_query($conn, $admin_inventory_logs_query);

$grouped_logs = [];
if ($admin_inventory_logs_result) {
    while ($log = mysqli_fetch_assoc($admin_inventory_logs_result)) {
        $timestamp = date('Y-m-d H:i:s', strtotime($log['timestamp']));
        $key = $timestamp . '|' . $log['username'];
        
        if (!isset($grouped_logs[$key])) {
            $grouped_logs[$key] = [
                'timestamp' => $log['timestamp'],
                'username' => $log['username'],
                'items' => []
            ];
        }
        
        $grouped_logs[$key]['items'][] = [
            'product_name' => $log['product_name'],
            'change_quantity' => $log['change_quantity'],
            'reason' => $log['reason']
        ];
    }
}

if (isset($_GET['delete_id']) && $_SESSION['role'] == 'admin') {
    $delete_id = intval($_GET['delete_id']);
    mysqli_query($conn, "DELETE FROM products WHERE id = $delete_id");
    header('Location: dashboard.php');
    exit();
}

$isRegularUser = ($_SESSION['role'] != 'admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isRegularUser ? 'POS System' : 'Dashboard'; ?></title>
    <link rel="icon" type="image/x-icon" href="images/logo.ico" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
    <?php if ($isRegularUser): ?>
    <link rel="stylesheet" href="pos.css">
    <?php endif; ?>
</head>
<body>
    <div class="sidebar <?php echo $isRegularUser ? 'pos-sidebar' : ''; ?>">
        <div class="logo">
            <img src="images/logo.jpg" alt="FixFlo Logo">
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
            <h1><?php echo $isRegularUser ? 'Point of Sale' : 'Dashboard'; ?></h1>
            <?php if ($isRegularUser): ?>
            <div class="header-datetime">
                <div class="datetime-display">
                    <i class="fas fa-calendar-alt"></i>
                    <span id="currentDateTime"></span>
                </div>
                <button class="btn-history-toggle" onclick="toggleTransactionHistory()">
                    <i class="fas fa-history"></i> History
                </button>
            </div>
            <?php endif; ?>
            <div class="user-info">
                <span class="username-label">Username</span>
                <span class="username"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <div class="user-avatar"><i class="fas fa-user-circle"></i></div>
            </div>
        </header>

        <?php if ($isRegularUser): ?>
        <!-- TRANSACTION HISTORY OVERLAY -->
        <div class="transaction-history-overlay" id="transactionHistoryOverlay" style="display: none;">
            <div class="transaction-history-panel">
                <div class="history-header">
                    <h2><i class="fas fa-history"></i> Transaction History</h2>
                    <button class="btn-close-history" onclick="toggleTransactionHistory()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="history-content">
                    <?php if (mysqli_num_rows($sales_result) > 0): ?>
                        <?php while ($sale = mysqli_fetch_assoc($sales_result)): ?>
                            <div class="history-item">
                                <div class="history-item-header">
                                    <span class="history-id">#<?php echo $sale['id']; ?></span>
                                    <span class="history-date"><?php echo date('M d, Y - h:i A', strtotime($sale['created_at'])); ?></span>
                                </div>
                                <div class="history-item-body">
                                    <div class="history-items-list">
                                        <?php 
                                        $sale_items_result = getSaleItems($conn, $sale['id']);
                                        while ($item = mysqli_fetch_assoc($sale_items_result)): 
                                        ?>
                                            <div class="history-product-item">
                                                <div class="history-product-info">
                                                    <span class="history-product-name"><?php echo htmlspecialchars($item['product_name']); ?></span>
                                                    <span class="history-product-qty">x<?php echo $item['quantity']; ?></span>
                                                </div>
                                                <span class="history-product-price">₱<?php echo number_format($item['subtotal'], 2); ?></span>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                    <div class="history-summary">
                                        <div class="history-detail">
                                            <span class="detail-label">Total:</span>
                                            <span class="detail-value total-amount">₱<?php echo number_format($sale['total_amount'], 2); ?></span>
                                        </div>
                                        <div class="history-detail">
                                            <span class="detail-label">Payment:</span>
                                            <span class="detail-value payment-method"><?php echo htmlspecialchars($sale['payment_method'] ?? 'N/A'); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="no-history">
                            <i class="fas fa-inbox"></i>
                            <p>No transaction history yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- POS SYSTEM FOR REGULAR USERS -->
        <div class="pos-container">
            <div class="pos-products">
                <div class="pos-controls">
                    <div class="pos-search-bar">
                        <i class="fas fa-search"></i>
                        <input type="text" id="posSearchInput" placeholder="Search products..." onkeyup="searchPOSProducts()">
                    </div>
                    
                    <select id="sortBy" class="pos-sort" onchange="sortPOSProducts()">
                        <option value="">Sort By</option>
                        <option value="name">Name</option>
                        <option value="price">Price</option>
                        <option value="stock">Stock</option>
                        <option value="category">Category</option>
                    </select>
                </div>
                
                <div class="category-filter">
                    <button class="category-btn active" onclick="filterCategory('all')">All</button>
                    <?php 
                    mysqli_data_seek($categories_result, 0);
                    while ($cat = mysqli_fetch_assoc($categories_result)): 
                    ?>
                        <button class="category-btn" onclick="filterCategory('<?php echo htmlspecialchars($cat['category_name']); ?>')">
                            <?php echo strtoupper(htmlspecialchars($cat['category_name'])); ?>
                        </button>
                    <?php endwhile; ?>
                </div>
                
                <div class="products-table-wrapper">
                    <table class="pos-products-table" id="posProductsTable">
                        <thead>
                            <tr>
                                <th>ITEM</th>
                                <th>CATEGORY</th>
                                <th>PRICE</th>
                                <th>ON-HAND QUANTITY</th>
                                <th>STATUS</th>
                                <th>ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            mysqli_data_seek($products_result, 0);
                            while ($product = mysqli_fetch_assoc($products_result)): 
                                $isOutOfStock = $product['stock'] <= 0;
                                $category = $product['category_name'] ?? 'Uncategorized';
                            ?>
                                <tr class="product-row <?php echo $isOutOfStock ? 'out-of-stock' : ''; ?>" 
                                    data-category="<?php echo htmlspecialchars($category); ?>"
                                    data-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                                    data-price="<?php echo $product['price']; ?>"
                                    data-stock="<?php echo $product['stock']; ?>">
                                    <td class="item-name"><?php echo htmlspecialchars($product['product_name']); ?></td>
                                    <td class="item-category"><?php echo htmlspecialchars($category); ?></td>
                                    <td class="item-price">₱<?php echo number_format($product['price'], 2); ?></td>
                                    <td class="item-quantity <?php echo $product['stock'] < 10 && $product['stock'] > 0 ? 'low-stock' : ''; ?>">
                                        <?php echo $product['stock']; ?>
                                    </td>
                                    <td class="item-status">
                                        <span class="status-badge <?php echo strtolower(str_replace(' ', '-', $product['status'])); ?>">
                                            <?php echo $product['status']; ?>
                                        </span>
                                    </td>
                                    <td class="item-action">
                                        <?php if (!$isOutOfStock): ?>
                                            <button class="btn-add-cart" onclick="addToCart(<?php echo $product['id']; ?>, '<?php echo addslashes($product['product_name']); ?>', <?php echo $product['price']; ?>, '<?php echo htmlspecialchars($category); ?>')">
                                                <i class="fas fa-cart-plus"></i> Add
                                            </button>
                                        <?php else: ?>
                                            <button class="btn-add-cart disabled" disabled>
                                                <i class="fas fa-ban"></i> N/A
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
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
                <div class="payment-method-selector" id="paymentMethodSelector" style="display: none;">
                    <label class="payment-label">Payment Method:</label>
                    <div class="payment-options">
                        <button class="payment-option" data-method="Cash" onclick="selectPaymentMethod('Cash')">
                            <i class="fas fa-money-bill-wave"></i> Cash
                        </button>
                        <button class="payment-option" data-method="Card" onclick="selectPaymentMethod('Card')">
                            <i class="fas fa-credit-card"></i> Card
                        </button>
                        <button class="payment-option" data-method="GCash" onclick="selectPaymentMethod('GCash')">
                            <i class="fas fa-mobile-alt"></i> GCash
                        </button>
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
                                <th>Price</th>
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
                                        <td><?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?></td>
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
                <h2>Inventory Logs</h2>
                <div class="logs-container">
                    <?php if (!empty($grouped_logs)): ?>
                        <?php foreach ($grouped_logs as $group): ?>
                            <div class="log-item">
                                <div class="log-header">
                                    <div class="log-header-left">
                                        <span class="log-user">by <?php echo htmlspecialchars($group['username']); ?></span>
                                    </div>
                                    <span class="log-time">
                                        <?php echo date('M d, Y - h:i A', strtotime($group['timestamp'])); ?>
                                    </span>
                                </div>
                                <div class="log-body">
                                    <div class="log-items-list">
                                        <?php foreach ($group['items'] as $item): ?>
                                            <div class="log-product-item">
                                                <div class="log-product-info">
                                                    <span class="log-product-name"><?php echo htmlspecialchars($item['product_name']); ?></span>
                                                    <span class="log-product-qty <?php echo $item['change_quantity'] > 0 ? 'positive' : 'negative'; ?>">
                                                        <?php echo $item['change_quantity'] > 0 ? '+' : ''; ?><?php echo $item['change_quantity']; ?>
                                                    </span>
                                                </div>
                                                <span class="log-product-reason"><?php echo htmlspecialchars($item['reason']); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: #777;">No inventory changes available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($isRegularUser): ?>
    <script>
        function updateDateTime() {
            const now = new Date();
            const options = { 
                weekday: 'short', 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            };
            document.getElementById('currentDateTime').textContent = now.toLocaleString('en-US', options);
        }
        
        updateDateTime();
        setInterval(updateDateTime, 1000);

        function toggleTransactionHistory() {
            const overlay = document.getElementById('transactionHistoryOverlay');
            if (overlay.style.display === 'none' || overlay.style.display === '') {
                overlay.style.display = 'flex';
            } else {
                overlay.style.display = 'none';
            }
        }

        let cart = [];
        let selectedPaymentMethod = null;

        function selectPaymentMethod(method) {
            selectedPaymentMethod = method;
            const buttons = document.querySelectorAll('.payment-option');
            buttons.forEach(btn => {
                if (btn.dataset.method === method) {
                    btn.classList.add('selected');
                } else {
                    btn.classList.remove('selected');
                }
            });
        }

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
            const paymentSelector = document.getElementById('paymentMethodSelector');
            
            if (cart.length === 0) {
                cartItemsContainer.innerHTML = `
                    <div class="cart-empty">
                        <i class="fas fa-shopping-cart"></i>
                        <p>Your cart is empty</p>
                    </div>
                `;
                cartSummary.style.display = 'none';
                paymentSelector.style.display = 'none';
                checkoutBtn.disabled = true;
                selectedPaymentMethod = null;
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
                paymentSelector.style.display = 'block';
                checkoutBtn.disabled = false;
            }
        }

        function searchPOSProducts() {
            const input = document.getElementById('posSearchInput');
            const filter = input.value.toLowerCase();
            const rows = document.querySelectorAll('.product-row');
            
            rows.forEach(row => {
                const itemName = row.querySelector('.item-name').textContent.toLowerCase();
                const itemCategory = row.querySelector('.item-category').textContent.toLowerCase();
                
                if (itemName.includes(filter) || itemCategory.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function filterCategory(category) {
            const rows = document.querySelectorAll('.product-row');
            const buttons = document.querySelectorAll('.category-btn');
            
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            rows.forEach(row => {
                const rowCategory = row.dataset.category;
                if (category === 'all' || rowCategory === category) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function sortPOSProducts() {
            const sortBy = document.getElementById('sortBy').value;
            const tbody = document.querySelector('.pos-products-table tbody');
            const rows = Array.from(tbody.querySelectorAll('.product-row'));
            
            if (!sortBy) return;
            
            rows.sort((a, b) => {
                let aVal, bVal;
                
                switch(sortBy) {
                    case 'name':
                        aVal = a.dataset.name.toLowerCase();
                        bVal = b.dataset.name.toLowerCase();
                        return aVal.localeCompare(bVal);
                    case 'price':
                        aVal = parseFloat(a.dataset.price);
                        bVal = parseFloat(b.dataset.price);
                        return aVal - bVal;
                    case 'stock':
                        aVal = parseInt(a.dataset.stock);
                        bVal = parseInt(b.dataset.stock);
                        return bVal - aVal;
                    case 'category':
                        aVal = a.dataset.category.toLowerCase();
                        bVal = b.dataset.category.toLowerCase();
                        return aVal.localeCompare(bVal);
                    default:
                        return 0;
                }
            });
            
            rows.forEach(row => tbody.appendChild(row));
        }

        function checkout() {
            if (cart.length === 0) return;
            
            if (!selectedPaymentMethod) {
                alert('Please select a payment method before checking out.');
                return;
            }
            
            const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            
            if (confirm(`Proceed with checkout?\n\nTotal: ₱${total.toFixed(2)}\nItems: ${cart.length}\nPayment Method: ${selectedPaymentMethod}`)) {
                const formData = new FormData();
                formData.append('cart', JSON.stringify(cart));
                formData.append('total', total);
                formData.append('payment_method', selectedPaymentMethod);
                
                fetch('../backend/process_sale.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Sale completed successfully!');
                        cart = [];
                        selectedPaymentMethod = null;
                        updateCart();
                        setTimeout(() => location.reload(), 500);
                    } else {
                        alert('Error processing sale: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error processing sale. Please try again.');
                });
            }
        }

        function toggleMobileMenu() {
            const nav = document.getElementById('mobileNav');
            nav.classList.toggle('active');
        }
    </script>
    <?php else: ?>
    <script src="dashboard.js"></script>
    <script>
        function toggleMobileMenu() {
            const nav = document.getElementById('mobileNav');
            nav.classList.toggle('active');
        }
    </script>
    <?php endif; ?>
</body>
</html>
