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

$user_orders_query = "SELECT o.id, o.tracking_no, o.payment_method, o.total_amount, o.order_date
                      FROM orders o
                      WHERE o.created_by = {$_SESSION['user_id']}
                      ORDER BY o.order_date DESC
                      LIMIT 20";
$user_orders_result = mysqli_query($conn, $user_orders_query);

function getOrderItems($conn, $order_id) {
    $items_query = "SELECT oi.quantity, oi.subtotal, p.product_name
                    FROM order_items oi
                    LEFT JOIN products p ON oi.product_id = p.id
                    WHERE oi.order_id = $order_id";
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

$admin_total_products = 0;
$admin_total_categories = 0;
$admin_total_customers = 0;
$admin_today_orders = 0;
$admin_orders_result = false;

if ($_SESSION['role'] == 'admin') {
    $countProductsResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM products");
    if ($countProductsResult) {
        $admin_total_products = (int)(mysqli_fetch_assoc($countProductsResult)['total'] ?? 0);
    }

    $countCategoriesResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM categories");
    if ($countCategoriesResult) {
        $admin_total_categories = (int)(mysqli_fetch_assoc($countCategoriesResult)['total'] ?? 0);
    }

    $countCustomersResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM customers");
    if ($countCustomersResult) {
        $admin_total_customers = (int)(mysqli_fetch_assoc($countCustomersResult)['total'] ?? 0);
    }

    $todayOrdersResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM orders WHERE DATE(order_date) = CURDATE()");
    if ($todayOrdersResult) {
        $admin_today_orders = (int)(mysqli_fetch_assoc($todayOrdersResult)['total'] ?? 0);
    }

    $admin_orders_query = "SELECT o.id, o.tracking_no, o.order_date, o.payment_method, o.total_amount,
                                  c.full_name, c.phone, u.username AS created_by
                           FROM orders o
                           LEFT JOIN customers c ON o.customer_id = c.id
                           LEFT JOIN users u ON o.created_by = u.id
                           ORDER BY o.order_date DESC
                           LIMIT 300";
    $admin_orders_result = mysqli_query($conn, $admin_orders_query);
}

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
    <link rel="stylesheet" href="css/dashboard.css">
    <?php if ($isRegularUser): ?>
    <link rel="stylesheet" href="css/pos.css">
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
            <a href="manage_customers.php">
                <span><i class="fas fa-address-book"></i></span> Manage Customers
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
        <div class="transaction-history-overlay" id="transactionHistoryOverlay" style="display: none;" onclick="if(event.target===this){toggleTransactionHistory();}">
            <div class="transaction-history-panel">
                <div class="history-header">
                    <h2><i class="fas fa-history"></i> Order History</h2>
                    <button class="btn-close-history" onclick="toggleTransactionHistory()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="history-content">
                    <?php if ($user_orders_result && mysqli_num_rows($user_orders_result) > 0): ?>
                        <?php while ($order = mysqli_fetch_assoc($user_orders_result)): ?>
                            <div class="history-item">
                                <div class="history-item-header">
                                    <span class="history-id"><?php echo htmlspecialchars($order['tracking_no']); ?></span>
                                    <span class="history-date"><?php echo date('M d, Y - h:i A', strtotime($order['order_date'])); ?></span>
                                </div>
                                <div class="history-item-body">
                                    <div class="history-items-list">
                                        <?php 
                                        $order_items_result = getOrderItems($conn, $order['id']);
                                        while ($item = mysqli_fetch_assoc($order_items_result)): 
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
                                            <span class="detail-value total-amount">₱<?php echo number_format($order['total_amount'], 2); ?></span>
                                        </div>
                                        <div class="history-detail">
                                            <span class="detail-label">Payment:</span>
                                            <span class="detail-value payment-method"><?php echo htmlspecialchars($order['payment_method'] ?? 'N/A'); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="no-history">
                            <i class="fas fa-inbox"></i>
                            <p>No order history yet</p>
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
                <div class="cart-checkout-panel">
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
                        <div class="customer-phone-row">
                            <label class="cash-label" for="customerPhone">Customer Phone for Official Receipt:</label>
                            <input type="text" id="customerPhone" placeholder="Enter customer phone number">
                        </div>
                    </div>
                    <div class="checkout-actions">
                        <button class="checkout-btn" id="officialReceiptBtn" onclick="checkoutWithReceipt()" disabled>
                            Official Receipt
                        </button>
                        <button class="checkout-btn checkout-btn-secondary" id="quickProcessBtn" onclick="checkoutWithoutReceipt()" disabled>
                            Process Without Details
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="order-modal" id="createCustomerModal" style="display: none;" onclick="if(event.target===this){closeCreateCustomerModal();}">
            <div class="order-modal-card">
                <h3>Create Customer</h3>
                <p class="modal-note">Phone number is not in the customer record.</p>
                <div class="modal-form-grid">
                    <input type="text" id="newCustomerName" placeholder="Full Name">
                    <input type="text" id="newCustomerPhone" placeholder="Phone">
                    <input type="email" id="newCustomerEmail" placeholder="Email (optional)">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeCreateCustomerModal()">Cancel</button>
                    <button type="button" class="btn-primary" onclick="createCustomerAndContinue()">Save Customer</button>
                </div>
            </div>
        </div>

        <div class="order-modal" id="orderSummaryModal" style="display: none;" onclick="if(event.target===this){closeOrderSummary();}">
            <div class="order-modal-card order-summary-card">
                <h3>Order Summary</h3>
                <div class="summary-block" id="summaryCustomerBlock"></div>
                <div class="summary-block" id="summaryInvoiceBlock"></div>
                <div class="summary-block" id="summaryProductsBlock"></div>
                <div class="modal-actions summary-actions">
                    <button type="button" class="btn-secondary" id="printOrderBtn" onclick="printOrderSummary()" disabled>Print</button>
                    <button type="button" class="btn-secondary" id="downloadOrderBtn" onclick="downloadOrderPdf()" disabled>Download PDF</button>
                    <button type="button" class="btn-primary" id="doneOrderBtn" onclick="completeOrder()">Done (Process Order)</button>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- ADMIN DASHBOARD -->
        <div class="content-area">
            <div class="products-section">
                <div class="admin-main-tabs">
                    <button type="button" class="admin-main-tab-btn active" id="adminTabProducts" onclick="setAdminSection('products')">Products</button>
                    <button type="button" class="admin-main-tab-btn" id="adminTabOrders" onclick="setAdminSection('orders')">Order History</button>
                </div>

                <div id="adminProductsSection">
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

                <div id="adminOrdersSection" style="display: none;">

                <div class="admin-analytics-grid">
                    <div class="analytics-card">
                        <div class="analytics-label">Today's Orders</div>
                        <div class="analytics-value"><?php echo $admin_today_orders; ?></div>
                    </div>
                    <div class="analytics-card">
                        <div class="analytics-label">Total Categories</div>
                        <div class="analytics-value"><?php echo $admin_total_categories; ?></div>
                    </div>
                    <div class="analytics-card">
                        <div class="analytics-label">Total Products</div>
                        <div class="analytics-value"><?php echo $admin_total_products; ?></div>
                    </div>
                    <div class="analytics-card">
                        <div class="analytics-label">Total Customers</div>
                        <div class="analytics-value"><?php echo $admin_total_customers; ?></div>
                    </div>
                </div>

                <div class="admin-orders-panel">
                    <div class="orders-panel-header">
                        <h2>Orders</h2>
                        <div class="orders-filters">
                            <input type="date" id="orderDateFilter" onchange="filterOrders()">
                            <select id="orderPaymentFilter" onchange="filterOrders()">
                                <option value="">All Payments</option>
                                <option value="Cash">Cash</option>
                                <option value="Card">Card</option>
                                <option value="GCash">GCash</option>
                            </select>
                        </div>
                    </div>
                    <div class="orders-table-wrap">
                        <table class="products-table" id="ordersTable">
                            <thead>
                                <tr>
                                    <th>Tracking No</th>
                                    <th>Order Date</th>
                                    <th>Payment</th>
                                    <th>Customer</th>
                                    <th>Phone</th>
                                    <th>Total</th>
                                    <th>Created By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($admin_orders_result && mysqli_num_rows($admin_orders_result) > 0): ?>
                                    <?php while ($order = mysqli_fetch_assoc($admin_orders_result)): ?>
                                        <tr data-order-date="<?php echo date('Y-m-d', strtotime($order['order_date'])); ?>" data-payment-method="<?php echo htmlspecialchars($order['payment_method']); ?>">
                                            <td><?php echo htmlspecialchars($order['tracking_no']); ?></td>
                                            <td><?php echo date('M d, Y h:i A', strtotime($order['order_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($order['payment_method']); ?></td>
                                            <td><?php echo htmlspecialchars($order['full_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($order['phone'] ?? 'N/A'); ?></td>
                                            <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($order['created_by'] ?? 'N/A'); ?></td>
                                            <td><button type="button" class="btn-update" onclick="viewOrderDetails(<?php echo (int)$order['id']; ?>)">View</button></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="8" style="text-align: center;">No orders found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                </div>
            </div>

            <div class="logs-section" id="adminLogsSection">
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

        <div class="order-modal" id="adminOrderModal" style="display: none;" onclick="if(event.target===this){closeAdminOrderModal();}">
            <div class="order-modal-card order-summary-card">
                <h3>Order Details</h3>
                <div class="summary-block" id="adminOrderSummaryBlock"></div>
                <div class="modal-actions summary-actions">
                    <button type="button" class="btn-secondary" onclick="closeAdminOrderModal()">Close</button>
                    <button type="button" class="btn-secondary" onclick="printAdminOrder()">Print</button>
                    <button type="button" class="btn-secondary" onclick="downloadAdminOrderPdf()">Download PDF</button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($isRegularUser): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
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
        let selectedCustomer = null;
        let savedOrder = null;
        let isCompletingOrder = false;
        let collectCustomerDetails = false;

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

            updateCheckoutButtons();
        }

        function updateCheckoutButtons() {
            const officialReceiptBtn = document.getElementById('officialReceiptBtn');
            const quickProcessBtn = document.getElementById('quickProcessBtn');
            const isDisabled = cart.length === 0 || !selectedPaymentMethod;

            if (officialReceiptBtn) {
                officialReceiptBtn.disabled = isDisabled;
            }

            if (quickProcessBtn) {
                quickProcessBtn.disabled = isDisabled;
            }
        }

        function hasCustomerDetails(orderData = savedOrder, customerData = selectedCustomer) {
            if (orderData && typeof orderData.has_customer_details !== 'undefined') {
                return !!orderData.has_customer_details;
            }

            return !!(customerData && (customerData.full_name || customerData.phone || customerData.email));
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

        function closeCreateCustomerModal() {
            const modal = document.getElementById('createCustomerModal');
            if (modal) modal.style.display = 'none';
        }

        function closeOrderSummary() {
            const modal = document.getElementById('orderSummaryModal');
            if (modal) modal.style.display = 'none';
        }

        function updateCart() {
            const cartItemsContainer = document.getElementById('cartItems');
            const cartSummary = document.getElementById('cartSummary');
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
                selectedPaymentMethod = null;
                collectCustomerDetails = false;
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
                const customerPhoneInput = document.getElementById('customerPhone');
                if (customerPhoneInput) customerPhoneInput.value = '';
                selectedCustomer = null;
                savedOrder = null;
                updateCheckoutButtons();
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
                updateCheckoutButtons();

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

        function validateCheckout(requireCustomerDetails) {
            if (cart.length === 0) return;
            if (!selectedPaymentMethod) {
                alert('Please select a payment method.');
                return false;
            }

            if (requireCustomerDetails) {
                const customerPhoneInput = document.getElementById('customerPhone');
                const phone = (customerPhoneInput?.value || '').trim();
                if (!phone) {
                    alert('Please enter the customer phone number for the official receipt.');
                    return false;
                }
            }

            if (selectedPaymentMethod === 'Cash') {
                const cashAmountInput = document.getElementById('cashAmount');
                const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                const cashReceived = parseFloat(cashAmountInput?.value || '0');
                if (!cashReceived || cashReceived < total) {
                    alert('Cash received must be greater than or equal to the total amount.');
                    return false;
                }
            }

            return true;
        }

        async function checkoutWithReceipt() {
            if (!validateCheckout(true)) {
                return;
            }

            collectCustomerDetails = true;
            const customerPhoneInput = document.getElementById('customerPhone');
            const phone = (customerPhoneInput?.value || '').trim();

            try {
                const response = await fetch(`../backend/customer_lookup.php?phone=${encodeURIComponent(phone)}`);
                const data = await response.json();
                if (!data.success) {
                    alert(data.error || 'Unable to validate customer phone.');
                    return;
                }

                if (data.exists) {
                    selectedCustomer = data.customer;
                    await saveOrder(false);
                    return;
                }

                document.getElementById('newCustomerPhone').value = phone;
                document.getElementById('createCustomerModal').style.display = 'flex';
            } catch (error) {
                console.error(error);
                alert('Error checking customer phone.');
            }
        }

        async function checkoutWithoutReceipt() {
            if (!validateCheckout(false)) {
                return;
            }

            collectCustomerDetails = false;
            selectedCustomer = null;
            await saveOrder(false);
        }

        async function checkout() {
            if (!validateCheckout(true)) {
                return;
            }

            const customerPhoneInput = document.getElementById('customerPhone');
            const phone = (customerPhoneInput?.value || '').trim();
            if (!phone) {
                alert('Please enter the customer phone number.');
                return;
            }

            try {
                const response = await fetch(`../backend/customer_lookup.php?phone=${encodeURIComponent(phone)}`);
                const data = await response.json();
                if (!data.success) {
                    alert(data.error || 'Unable to validate customer phone.');
                    return;
                }

                if (data.exists) {
                    selectedCustomer = data.customer;
                    await saveOrder(false);
                    return;
                }

                document.getElementById('newCustomerPhone').value = phone;
                document.getElementById('createCustomerModal').style.display = 'flex';
            } catch (error) {
                console.error(error);
                alert('Error checking customer phone.');
            }
        }

        async function createCustomerAndContinue() {
            const fullName = (document.getElementById('newCustomerName').value || '').trim();
            const phone = (document.getElementById('newCustomerPhone').value || '').trim();
            const email = (document.getElementById('newCustomerEmail').value || '').trim();

            if (!fullName || !phone) {
                alert('Full name and phone are required.');
                return;
            }

            const formData = new FormData();
            formData.append('full_name', fullName);
            formData.append('phone', phone);
            formData.append('email', email);

            try {
                const response = await fetch('../backend/create_customer.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (!data.success) {
                    alert(data.error || 'Failed to create customer.');
                    return;
                }

                selectedCustomer = data.customer;
                closeCreateCustomerModal();
                await saveOrder(false);
            } catch (error) {
                console.error(error);
                alert('Error creating customer.');
            }
        }

        function buildSummaryHtml(orderRefText, orderDateText) {
            const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            const includeCustomerDetails = hasCustomerDetails();
            const itemsRows = cart.map(item => `
                <tr>
                    <td>${item.name}</td>
                    <td>${item.quantity}</td>
                    <td>₱${item.price.toFixed(2)}</td>
                    <td>₱${(item.price * item.quantity).toFixed(2)}</td>
                </tr>
            `).join('');

            let paymentExtra = '';
            if (selectedPaymentMethod === 'Cash') {
                const cashReceived = parseFloat(document.getElementById('cashAmount')?.value || '0');
                const change = Math.max(cashReceived - total, 0);
                paymentExtra = `<div>Cash Received: ₱${cashReceived.toFixed(2)}</div><div>Change: ₱${change.toFixed(2)}</div>`;
            }

            return {
                customerHtml: includeCustomerDetails ? `
                    <h4>Customer Details</h4>
                    <div>Name: ${selectedCustomer?.full_name || 'N/A'}</div>
                    <div>Phone: ${selectedCustomer?.phone || 'N/A'}</div>
                    <div>Email: ${selectedCustomer?.email || 'N/A'}</div>
                ` : '',
                invoiceHtml: `
                    <h4>Invoice Details</h4>
                    <div>Invoice Number: ${orderRefText}</div>
                    <div>Invoice Date: ${orderDateText}</div>
                    <div>Payment Mode: ${selectedPaymentMethod}</div>
                    ${paymentExtra}
                `,
                productsHtml: `
                    <h4>Products</h4>
                    <table class="summary-products-table">
                        <thead><tr><th>Item</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr></thead>
                        <tbody>${itemsRows}</tbody>
                    </table>
                    <div class="summary-total-row">Total: ₱${total.toFixed(2)}</div>
                `
            };
        }

        function showOrderSummary() {
            if (collectCustomerDetails && !selectedCustomer) {
                alert('Customer is required before order summary.');
                return;
            }

            const invoiceNo = savedOrder?.tracking_no || 'Pending (save to generate)';
            const invoiceDate = savedOrder?.order_date || new Date().toLocaleString();
            const blocks = buildSummaryHtml(invoiceNo, invoiceDate);

            const summaryCustomerBlock = document.getElementById('summaryCustomerBlock');
            if (summaryCustomerBlock) {
                summaryCustomerBlock.innerHTML = blocks.customerHtml;
                summaryCustomerBlock.style.display = blocks.customerHtml ? 'block' : 'none';
            }
            document.getElementById('summaryInvoiceBlock').innerHTML = blocks.invoiceHtml;
            document.getElementById('summaryProductsBlock').innerHTML = blocks.productsHtml;

            const saveBtn = document.getElementById('saveOrderBtn');
            const printBtn = document.getElementById('printOrderBtn');
            const downloadBtn = document.getElementById('downloadOrderBtn');
            if (saveBtn) {
                saveBtn.disabled = !!savedOrder;
                saveBtn.textContent = savedOrder ? 'Order Auto-Saved' : 'Save Order';
            }
            if (printBtn) printBtn.disabled = !savedOrder;
            if (downloadBtn) downloadBtn.disabled = !savedOrder;

            document.getElementById('orderSummaryModal').style.display = 'flex';
        }

        async function saveOrder(showSuccessAlert = true) {
            if (collectCustomerDetails && !selectedCustomer) {
                alert('Customer is required.');
                return false;
            }

            if (savedOrder) {
                showOrderSummary();
                if (showSuccessAlert) {
                    alert(`Order already saved with invoice number ${savedOrder.tracking_no}.`);
                }
                return true;
            }

            const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            const formData = new FormData();
            formData.append('cart', JSON.stringify(cart));
            formData.append('total', total.toFixed(2));
            formData.append('payment_method', selectedPaymentMethod);
            formData.append('collect_customer_details', collectCustomerDetails ? '1' : '0');

            if (selectedCustomer?.id) {
                formData.append('customer_id', selectedCustomer.id);
            }

            if (selectedPaymentMethod === 'Cash') {
                const cashReceived = parseFloat(document.getElementById('cashAmount')?.value || '0');
                const change = Math.max(cashReceived - total, 0);
                formData.append('cash_received', cashReceived.toFixed(2));
                formData.append('cash_change', change.toFixed(2));
            }

            try {
                const response = await fetch('../backend/place_order.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (!data.success) {
                    alert(data.error || 'Failed to save order.');
                    return false;
                }

                savedOrder = data;
                showOrderSummary();
                if (showSuccessAlert) {
                    alert('Order saved successfully. You can now print or download PDF.');
                }
                return true;
            } catch (error) {
                console.error(error);
                alert('Error saving order.');
                return false;
            }
        }

        async function completeOrder() {
            if (isCompletingOrder) {
                return;
            }

            isCompletingOrder = true;
            const doneBtn = document.getElementById('doneOrderBtn');
            if (doneBtn) doneBtn.disabled = true;

            try {
                let processed = !!savedOrder;
                if (!processed) {
                    processed = await saveOrder(false);
                }

                if (!processed) {
                    return;
                }

                alert('Order processed successfully. Inventory has been updated.');
                window.location.reload();
            } finally {
                isCompletingOrder = false;
                if (doneBtn) doneBtn.disabled = false;
            }
        }

        function formatCurrency(value) {
            return Number(value || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function escapeHtml(text) {
            return String(text ?? 'N/A')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function getCompanyDetails() {
            return {
                name: '9toFive Convenience Store',
                address1: '94 Kamuning Road, Brgy. Kamuning, Quezon City, 1103 Metro Manila, Philippines',
                address2: '9toFive Retail Solutions Inc.'
            };
        }

        function buildInvoiceHtml(order, customer, items) {
            const company = getCompanyDetails();
            const includeCustomerDetails = hasCustomerDetails(order, customer);
            const grandTotal = items.reduce((sum, item) => sum + (Number(item.price) * Number(item.quantity)), 0);
            const rows = items.map((item, index) => {
                const subtotal = Number(item.price) * Number(item.quantity);
                return `
                    <tr>
                        <td>${index + 1}</td>
                        <td>${escapeHtml(item.name)}</td>
                        <td>${formatCurrency(item.price)}</td>
                        <td>${item.quantity}</td>
                        <td><strong>${formatCurrency(subtotal)}</strong></td>
                    </tr>
                `;
            }).join('');

            return `
                <html>
                <head>
                    <title>Order ${escapeHtml(order.tracking_no)}</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 18px; color: #222; }
                        .header { text-align: center; margin-bottom: 14px; }
                        .header h2 { margin: 0 0 6px; }
                        .header p { margin: 2px 0; color: #444; font-size: 14px; }
                        .top-grid { display: grid; grid-template-columns: 1fr 1fr; margin: 18px 0 10px; }
                        .top-grid h3 { margin: 0 0 6px; font-size: 20px; }
                        .top-grid p { margin: 3px 0; font-size: 14px; }
                        .top-grid .right { text-align: right; }
                        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
                        thead th { text-align: left; border-bottom: 1px solid #999; padding: 8px 6px; }
                        td { border-bottom: 1px solid #ddd; padding: 8px 6px; }
                        .bottom { display: flex; justify-content: space-between; margin-top: 12px; font-size: 20px; }
                        .grand { font-weight: 700; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h2>${escapeHtml(company.name)}</h2>
                        <p>${escapeHtml(company.address1)}</p>
                        <p>${escapeHtml(company.address2)}</p>
                    </div>

                    <div class="top-grid" style="grid-template-columns: ${includeCustomerDetails ? '1fr 1fr' : '1fr'};">
                        ${includeCustomerDetails ? `<div>
                            <h3>Customer Details</h3>
                            <p>Customer Name: ${escapeHtml(customer.full_name)}</p>
                            <p>Customer Phone No: ${escapeHtml(customer.phone)}</p>
                            <p>Customer Email Id: ${escapeHtml(customer.email || 'N/A')}</p>
                        </div>` : ''}
                        <div class="right">
                            <h3>Invoice Details</h3>
                            <p>Invoice No: ${escapeHtml(order.tracking_no)}</p>
                            <p>Invoice Date: ${escapeHtml(order.order_date)}</p>
                        </div>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Product Name</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Total Price</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>

                    <div class="bottom">
                        <div>Payment Mode: ${escapeHtml(order.payment_method)}</div>
                        <div class="grand">Grand Total: ${formatCurrency(grandTotal)}</div>
                    </div>
                </body>
                </html>
            `;
        }

        function printOrderSummary() {
            if (!savedOrder) {
                alert('Please save the order first.');
                return;
            }
            const printWindow = window.open('', '_blank');
            if (!printWindow) return;
            const orderData = {
                tracking_no: savedOrder?.tracking_no || 'N/A',
                order_date: savedOrder?.order_date || new Date().toLocaleString(),
                payment_method: selectedPaymentMethod || 'N/A',
                has_customer_details: savedOrder?.has_customer_details ?? hasCustomerDetails(savedOrder, selectedCustomer)
            };
            printWindow.document.write(buildInvoiceHtml(orderData, selectedCustomer || {}, cart));
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
        }

        function downloadOrderPdf() {
            if (!savedOrder) {
                alert('Please save the order first.');
                return;
            }

            const jspdfRef = window.jspdf;
            if (!jspdfRef || !jspdfRef.jsPDF) {
                alert('PDF library is not loaded.');
                return;
            }

            const doc = new jspdfRef.jsPDF();
            const company = getCompanyDetails();
            const pageWidth = doc.internal.pageSize.getWidth();
            const leftX = 14;
            const rightX = pageWidth - 14;
            const includeCustomerDetails = hasCustomerDetails(savedOrder, selectedCustomer);
            const total = cart.reduce((sum, item) => sum + (Number(item.price) * Number(item.quantity)), 0);
            let y = 16;

            doc.setFontSize(17);
            doc.text(company.name, pageWidth / 2, y, { align: 'center' });
            y += 7;
            doc.setFontSize(10);
            doc.text(company.address1, pageWidth / 2, y, { align: 'center' });
            y += 5;
            doc.text(company.address2, pageWidth / 2, y, { align: 'center' });
            y += 10;

            doc.setFontSize(12);
            if (includeCustomerDetails) {
                doc.text('Customer Details', leftX, y);
                doc.text('Invoice Details', rightX, y, { align: 'right' });
            } else {
                doc.text('Invoice Details', leftX, y);
            }
            y += 6;

            doc.setFontSize(10);
            if (includeCustomerDetails) {
                doc.text(`Customer Name: ${selectedCustomer?.full_name || 'N/A'}`, leftX, y);
                doc.text(`Invoice No: ${savedOrder?.tracking_no || 'N/A'}`, rightX, y, { align: 'right' });
            } else {
                doc.text(`Invoice No: ${savedOrder?.tracking_no || 'N/A'}`, leftX, y);
            }
            y += 5;
            if (includeCustomerDetails) {
                doc.text(`Customer Phone No: ${selectedCustomer?.phone || 'N/A'}`, leftX, y);
                doc.text(`Invoice Date: ${savedOrder?.order_date || new Date().toLocaleString()}`, rightX, y, { align: 'right' });
            } else {
                doc.text(`Invoice Date: ${savedOrder?.order_date || new Date().toLocaleString()}`, leftX, y);
            }
            y += 5;
            if (includeCustomerDetails) {
                doc.text(`Customer Email Id: ${selectedCustomer?.email || 'N/A'}`, leftX, y);
                y += 8;
            } else {
                y += 3;
            }

            const col = { id: 14, name: 26, price: 132, qty: 158, total: 181 };
            doc.setLineWidth(0.2);
            doc.line(leftX, y - 3, rightX, y - 3);
            doc.text('ID', col.id, y);
            doc.text('Product Name', col.name, y);
            doc.text('Price', col.price, y);
            doc.text('Quantity', col.qty, y);
            doc.text('Total Price', col.total, y);
            y += 4;
            doc.line(leftX, y, rightX, y);
            y += 5;

            cart.forEach((item, index) => {
                const rowTotal = Number(item.price) * Number(item.quantity);
                doc.text(String(index + 1), col.id, y);
                doc.text(String(item.name || 'N/A'), col.name, y);
                doc.text(formatCurrency(item.price), col.price, y);
                doc.text(String(item.quantity), col.qty, y);
                doc.text(formatCurrency(rowTotal), col.total, y);
                y += 6;

                if (y > 272) {
                    doc.addPage();
                    y = 16;
                }
            });

            doc.line(leftX, y - 2, rightX, y - 2);
            y += 6;
            doc.setFontSize(11);
            doc.text(`Payment Mode: ${selectedPaymentMethod || 'N/A'}`, leftX, y);
            doc.setFont(undefined, 'bold');
            doc.text(`Grand Total: ${formatCurrency(total)}`, rightX, y, { align: 'right' });
            doc.save(`${savedOrder.tracking_no}.pdf`);
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="js/dashboard.js"></script>
    <script>
        function toggleMobileMenu() {
            const nav = document.getElementById('mobileNav');
            nav.classList.toggle('active');
        }
    </script>
    <?php endif; ?>
</body>
</html>
