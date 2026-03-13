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

$categories_query = "SELECT c.category_name, COUNT(p.id) AS product_count
                     FROM categories c
                     LEFT JOIN products p ON c.id = p.category_id
                     GROUP BY c.id, c.category_name
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

$admin_inventory_logs_query = "SELECT 
                                    p.product_name,
                                    ih.change_quantity,
                                    u.username,
                                    ih.created_at as timestamp,
                                    ih.reason
                                FROM inventory_history ih
                                LEFT JOIN products p ON ih.product_id = p.id
                                LEFT JOIN users u ON ih.user_id = u.id
                                WHERE ih.change_quantity != 0
                                ORDER BY ih.created_at DESC
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
            <img src="images/logo.jpg" alt="FixFlo Logo" class="app-logo">
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
                </div>
                
                <div class="category-filter">
                    <button type="button" class="category-btn active" onclick="filterCategory('all', this)">All</button>
                    <?php 
                    mysqli_data_seek($categories_result, 0);
                    while ($cat = mysqli_fetch_assoc($categories_result)): 
                        $rawCategory = trim((string)($cat['category_name'] ?? ''));
                        $categoryLabel = $rawCategory !== '' ? $rawCategory : 'Uncategorized';
                    ?>
                        <button type="button" class="category-btn" onclick='filterCategory(<?php echo json_encode($categoryLabel); ?>, this)' data-product-count="<?php echo (int)($cat['product_count'] ?? 0); ?>">
                            <?php echo strtoupper(htmlspecialchars($categoryLabel)); ?>
                        </button>
                    <?php endwhile; ?>
                </div>
                
                <div class="products-grid" id="productsGrid">
                    <?php 
                    mysqli_data_seek($products_result, 0);
                    while ($product = mysqli_fetch_assoc($products_result)): 
                        $isOutOfStock = $product['stock'] <= 0;
                        $category = $product['category_name'] ?? 'Uncategorized';
                    ?>
                        <div class="product-card <?php echo $isOutOfStock ? 'out-of-stock' : ''; ?>"
                             data-id="<?php echo (int)$product['id']; ?>"
                             data-category="<?php echo htmlspecialchars($category); ?>"
                             data-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                             data-price="<?php echo (float)$product['price']; ?>"
                                data-stock="<?php echo (int)$product['stock']; ?>"
                             data-clickable="<?php echo $isOutOfStock ? '0' : '1'; ?>">
                            <div class="product-icon">
                                <?php
                                $icons = [
                                    'Tools' => '<i class="fas fa-hammer"></i>',
                                    'Paint' => '<i class="fas fa-paint-roller"></i>',
                                    'Electrical' => '<i class="fas fa-bolt"></i>',
                                    'Plumbing' => '<i class="fas fa-wrench"></i>',
                                    'Fasteners' => '<i class="fas fa-screwdriver"></i>'
                                ];
                                echo $icons[$category] ?? '<i class="fas fa-box"></i>';
                                ?>
                            </div>
                            <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                            <div class="product-category"><?php echo htmlspecialchars($category); ?></div>
                            <div class="product-price">₱<?php echo number_format($product['price'], 2); ?></div>
                            <div class="product-stock <?php echo $product['stock'] < 10 ? 'low' : ''; ?>">
                                <?php echo $isOutOfStock ? 'Out of Stock' : $product['stock'] . ' in stock'; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                <div class="no-products-message" id="noProductsMessage" style="display: none;">No Products Available.</div>
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
                    <div class="cash-payment-details" id="cashPaymentDetails" style="display: none;">
                        <label class="cash-label" for="cashAmount">Cash Received:</label>
                        <input type="number" id="cashAmount" min="0" step="0.01" placeholder="Enter amount" oninput="updateCashChange()">
                        <div class="cash-change-row">
                            <span>Change:</span>
                            <span id="cashChange">₱0.00</span>
                        </div>
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
                    <div class="admin-view-toggle">
                        <button type="button" class="admin-view-btn active" id="tableViewBtn" onclick="setAdminView('table')">
                            <i class="fas fa-table"></i> Table
                        </button>
                        <button type="button" class="admin-view-btn" id="cardViewBtn" onclick="setAdminView('card')">
                            <i class="fas fa-th-large"></i> Cards
                        </button>
                    </div>
                </div>

                <div class="products-table-container">
                    <div class="admin-table-view" id="adminTableView">
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
                                        <?php
                                        $statusClass = strtolower(str_replace(' ', '-', (string)$product['status']));
                                        $isLowStock = ((int)$product['stock']) < 10;
                                        ?>
                                        <tr>
                                            <td><?php echo $product['id']; ?></td>
                                            <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                            <td><?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?></td>
                                            <td>₱<?php echo number_format($product['price'], 2); ?></td>
                                            <td>
                                                <span class="status-pill status-<?php echo $statusClass; ?>">
                                                    <?php echo strtoupper(htmlspecialchars($product['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="stock-value <?php echo $isLowStock ? 'low-stock' : ''; ?>">
                                                    <?php echo (int)$product['stock']; ?>
                                                </span>
                                            </td>
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

                    <div class="admin-card-view" id="adminCardView" style="display: none;">
                        <h2>Products Cards</h2>
                        <div class="admin-products-grid" id="adminProductsGrid">
                            <?php 
                            mysqli_data_seek($products_result, 0);
                            if (mysqli_num_rows($products_result) > 0):
                                while ($product = mysqli_fetch_assoc($products_result)):
                                    $adminCategory = $product['category_name'] ?? 'N/A';
                                    $statusClass = strtolower(str_replace(' ', '-', (string)$product['status']));
                                    $isLowStock = ((int)$product['stock']) < 10;
                            ?>
                                <div class="admin-product-card"
                                     data-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                                     data-category="<?php echo htmlspecialchars($adminCategory); ?>"
                                     data-price="<?php echo (float)$product['price']; ?>"
                                     data-stock="<?php echo (int)$product['stock']; ?>">
                                    <div class="admin-card-title"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                    <div class="admin-card-meta">ID: <?php echo $product['id']; ?></div>
                                    <div class="admin-card-meta">Category: <?php echo htmlspecialchars($adminCategory); ?></div>
                                    <div class="admin-card-price">₱<?php echo number_format($product['price'], 2); ?></div>
                                    <div class="admin-card-stock">Stock: <span class="stock-value <?php echo $isLowStock ? 'low-stock' : ''; ?>"><?php echo (int)$product['stock']; ?></span></div>
                                    <div class="admin-card-status status-<?php echo $statusClass; ?>"><?php echo strtoupper(htmlspecialchars($product['status'])); ?></div>
                                    <div class="admin-card-actions">
                                        <a href="add_product.php?edit_id=<?php echo $product['id']; ?>" class="btn-update">Update</a>
                                        <a href="?delete_id=<?php echo $product['id']; ?>" onclick="return confirm('Are you sure you want to delete this product?')" class="btn-delete"><i class="fas fa-trash"></i></a>
                                    </div>
                                </div>
                            <?php
                                endwhile;
                            else:
                            ?>
                                <div style="text-align: center; color: #777;">No products found. <a href="add_product.php">Add a product</a></div>
                            <?php endif; ?>
                        </div>
                    </div>
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
        let activeCategory = 'all';
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
            const dateTimeEl = document.getElementById('currentDateTime');
            if (dateTimeEl) {
                dateTimeEl.textContent = now.toLocaleString('en-US', options);
            }
        }

        function toggleTransactionHistory() {
            const overlay = document.getElementById('transactionHistoryOverlay');
            if (!overlay) return;

            if (overlay.style.display === 'none' || overlay.style.display === '') {
                overlay.style.display = 'flex';
            } else {
                overlay.style.display = 'none';
            }
        }

        let cart = [];
        let selectedPaymentMethod = null;

        function normalizeCategory(value) {
            return String(value || '').trim().toLowerCase();
        }

        function initializePOSCardClicks() {
            const cards = document.querySelectorAll('.product-card[data-clickable="1"]');
            cards.forEach(card => {
                card.addEventListener('click', () => {
                    const id = parseInt(card.dataset.id, 10);
                    const name = card.dataset.name;
                    const price = parseFloat(card.dataset.price);
                    const category = card.dataset.category;
                    const stock = parseInt(card.dataset.stock, 10);
                    addToCart(id, name, price, category, stock);
                });
            });
        }

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

            const cashDetails = document.getElementById('cashPaymentDetails');
            if (cashDetails) {
                cashDetails.style.display = method === 'Cash' ? 'block' : 'none';
            }

            updateCashChange();

            const checkoutBtn = document.getElementById('checkoutBtn');
            if (checkoutBtn && cart.length > 0) {
                checkoutBtn.disabled = false;
            }
        }

        function updateCashChange() {
            const cashAmountInput = document.getElementById('cashAmount');
            const cashChange = document.getElementById('cashChange');

            if (!cashAmountInput || !cashChange) return;

            const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            const cashReceived = parseFloat(cashAmountInput.value || '0');
            const change = cashReceived - total;

            if (change >= 0) {
                cashChange.textContent = '₱' + change.toFixed(2);
                cashChange.classList.remove('insufficient');
            } else {
                cashChange.textContent = '₱0.00';
                cashChange.classList.add('insufficient');
            }
        }

        function addToCart(id, name, price, category, stock) {
            const maxStock = Number.isFinite(stock) ? stock : parseInt(stock, 10);
            const existingItem = cart.find(item => item.id === id);
            
            if (existingItem) {
                if (existingItem.quantity >= existingItem.stock) {
                    alert('Cannot add more. Stock limit reached for this product.');
                    return;
                }
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
                    stock: maxStock,
                    icon: icons[category] || '<i class="fas fa-box"></i>'
                });
            }
            
            updateCart();
        }

        function updateQuantity(id, change) {
            const item = cart.find(item => item.id === id);
            if (item) {
                if (change > 0 && item.quantity >= item.stock) {
                    alert('Cannot add more. Stock limit reached for this product.');
                    return;
                }
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
            const cashDetails = document.getElementById('cashPaymentDetails');
            const cashAmountInput = document.getElementById('cashAmount');
            const cashChange = document.getElementById('cashChange');
            
            if (cart.length === 0) {
                cartItemsContainer.innerHTML = `
                    <div class="cart-empty">
                        <i class="fas fa-shopping-cart"></i>
                        <p>Your cart is empty</p>
                    </div>
                `;
                cartSummary.style.display = 'none';
                checkoutBtn.disabled = true;
                selectedPaymentMethod = null;
                if (paymentSelector) {
                    paymentSelector.style.display = 'none';
                }
                document.querySelectorAll('.payment-option').forEach(btn => btn.classList.remove('selected'));

                if (cashDetails) cashDetails.style.display = 'none';
                if (cashAmountInput) cashAmountInput.value = '';
                if (cashChange) {
                    cashChange.textContent = '₱0.00';
                    cashChange.classList.remove('insufficient');
                }
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
                if (paymentSelector) {
                    paymentSelector.style.display = 'block';
                }
                checkoutBtn.disabled = !selectedPaymentMethod;

                updateCashChange();
            }
        }

        function searchPOSProducts() {
            applyPOSFilters();
        }

        function applyPOSFilters() {
            const input = document.getElementById('posSearchInput');
            const filter = input.value.toLowerCase();
            const cards = document.querySelectorAll('.product-card');
            const noProductsMessage = document.getElementById('noProductsMessage');
            let visibleCount = 0;
            
            cards.forEach(card => {
                const itemName = card.querySelector('.product-name').textContent.toLowerCase();
                const itemCategory = card.querySelector('.product-category').textContent.toLowerCase();
                const categoryMatch = activeCategory === 'all' || normalizeCategory(card.dataset.category) === activeCategory;
                const searchMatch = itemName.includes(filter) || itemCategory.includes(filter);
                
                if (categoryMatch && searchMatch) {
                    card.style.display = '';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            if (noProductsMessage) {
                if (visibleCount === 0) {
                    noProductsMessage.textContent = activeCategory === 'all'
                        ? 'No Products Available.'
                        : 'No Products Available in this category.';
                    noProductsMessage.style.display = 'block';
                } else {
                    noProductsMessage.style.display = 'none';
                }
            }
        }

        function filterCategory(category, element) {
            const buttons = document.querySelectorAll('.category-btn');
            
            buttons.forEach(btn => btn.classList.remove('active'));
            if (element) {
                element.classList.add('active');
            }
            activeCategory = category === 'all' ? 'all' : normalizeCategory(category);
            searchPOSProducts();
        }

        function checkout() {
            if (cart.length === 0) return;
            if (!selectedPaymentMethod) {
                alert('Please select a payment method.');
                return;
            }
            
            const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            let cashReceived = null;
            let change = null;

            if (selectedPaymentMethod === 'Cash') {
                const cashAmountInput = document.getElementById('cashAmount');
                cashReceived = parseFloat(cashAmountInput?.value || '0');

                if (!cashReceived || cashReceived < total) {
                    alert('Cash received must be greater than or equal to the total amount.');
                    return;
                }

                change = cashReceived - total;
            }
            
            const cashDetails = selectedPaymentMethod === 'Cash'
                ? `\nCash Received: ₱${cashReceived.toFixed(2)}\nChange: ₱${change.toFixed(2)}`
                : '';

            if (confirm(`Proceed with checkout?\n\nTotal: ₱${total.toFixed(2)}\nItems: ${cart.length}\nPayment Method: ${selectedPaymentMethod}${cashDetails}`)) {
                const formData = new FormData();
                formData.append('cart', JSON.stringify(cart));
                formData.append('total', total);
                formData.append('payment_method', selectedPaymentMethod);

                if (selectedPaymentMethod === 'Cash') {
                    formData.append('cash_received', cashReceived.toFixed(2));
                    formData.append('cash_change', change.toFixed(2));
                }
                
                fetch('../backend/process_sale.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Sale completed successfully!');
                        cart = [];
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

        updateDateTime();
        setInterval(updateDateTime, 1000);
        initializePOSCardClicks();
        searchPOSProducts();
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
