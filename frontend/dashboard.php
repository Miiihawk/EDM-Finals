<?php
require_once '../backend/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$isRegularUser = ($_SESSION['role'] != 'admin');

// Backward compatibility: old databases may not have categories.code_prefix yet.
function hasCategoryCodePrefixColumn(mysqli $conn): bool {
     $result = mysqli_query($conn, "SHOW COLUMNS FROM categories LIKE 'code_prefix'");
     return $result && mysqli_num_rows($result) > 0;
}

function hasProductCodeColumn(mysqli $conn): bool {
    $result = mysqli_query($conn, "SHOW COLUMNS FROM products LIKE 'product_code'");
    return $result && mysqli_num_rows($result) > 0;
}

$hasCategoryCodePrefix = hasCategoryCodePrefixColumn($conn);
$hasProductCode = hasProductCodeColumn($conn);

$products_query = $hasCategoryCodePrefix
     ? ($hasProductCode
         ? "SELECT p.*, c.category_name, c.code_prefix
         FROM products p
         LEFT JOIN categories c ON p.category_id = c.id
         ORDER BY p.id ASC"
         : "SELECT p.*, '' AS product_code, c.category_name, c.code_prefix
         FROM products p
         LEFT JOIN categories c ON p.category_id = c.id
         ORDER BY p.id ASC")
     : ($hasProductCode
         ? "SELECT p.*, c.category_name, '' AS code_prefix
         FROM products p
         LEFT JOIN categories c ON p.category_id = c.id
         ORDER BY p.id ASC"
         : "SELECT p.*, '' AS product_code, c.category_name, '' AS code_prefix
         FROM products p
         LEFT JOIN categories c ON p.category_id = c.id
         ORDER BY p.id ASC");
$products_result = mysqli_query($conn, $products_query);

$categories_query = $hasCategoryCodePrefix
     ? "SELECT c.id, c.category_name, c.code_prefix, COUNT(p.id) AS product_count
         FROM categories c
         LEFT JOIN products p ON c.id = p.category_id
         GROUP BY c.id, c.category_name, c.code_prefix
         ORDER BY c.category_name"
     : "SELECT c.id, c.category_name, NULL AS code_prefix, COUNT(p.id) AS product_count
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

$registered_customers = [];
if ($isRegularUser) {
    $customers_query = "SELECT id, full_name, phone, email
                        FROM customers
                        WHERE phone <> 'WALKIN-DEFAULT'
                        ORDER BY full_name ASC";
    $customers_result = mysqli_query($conn, $customers_query);
    if ($customers_result) {
        while ($customer = mysqli_fetch_assoc($customers_result)) {
            $registered_customers[] = $customer;
        }
    }
}

function getOrderItems($conn, $order_id) {
    $items_query = "SELECT oi.quantity, oi.subtotal, p.product_name
                    FROM order_items oi
                    LEFT JOIN products p ON oi.product_id = p.id
                    WHERE oi.order_id = $order_id";
    return mysqli_query($conn, $items_query);
}

function buildProductCode(int $productId, string $categoryName = '', string $codePrefix = ''): string {
    if ($codePrefix !== '') {
        // Use the category's stored prefix; strip non-letters, uppercase, pad to 3.
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $codePrefix), 0, 3));
        $prefix = str_pad($prefix, 3, 'X');
    } else {
        // Auto-derive prefix from the first 3 uppercase letters of the category name.
        $lettersOnly = preg_replace('/[^A-Z]/', '', strtoupper((string)$categoryName));
        if ($lettersOnly === '') {
            $lettersOnly = 'ITM';
        }
        $prefix = substr(str_pad($lettersOnly, 3, 'X'), 0, 3);
    }
    $suffix = str_pad((string)((int)$productId), 3, '0', STR_PAD_LEFT);
    return $prefix . $suffix;
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
                    <div class="pos-search-bar pos-code-bar">
                        <i class="fas fa-barcode"></i>
                        <input type="text" id="productCodeInput" placeholder="Scan or Enter Product Code" maxlength="6" oninput="handleProductCodeInput()" onkeydown="if(event.key==='Enter'){event.preventDefault();addProductByCode();}">
                    </div>
                    <input type="number" id="productCodeQuantity" class="pos-qty-input" min="1" max="99" value="1" aria-label="Quantity">
                </div>
                <div class="product-code-feedback idle" id="productCodeStatus">Enter code in AAA999 format.</div>
                
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
                        $productCode = !empty($product['product_code'] ?? '')
                            ? strtoupper((string)$product['product_code'])
                            : buildProductCode($product['id'], $category, (string)($product['code_prefix'] ?? ''));
                    ?>
                        <div class="product-card <?php echo $isOutOfStock ? 'out-of-stock' : ''; ?>"
                             data-id="<?php echo (int)$product['id']; ?>"
                             data-category="<?php echo htmlspecialchars($category); ?>"
                             data-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                             data-price="<?php echo (float)$product['price']; ?>"
                             data-code="<?php echo htmlspecialchars($productCode); ?>"
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
                            <div class="product-code">Code: <?php echo htmlspecialchars($productCode); ?></div>
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
                    <div class="cart-promo-panel">
                        <div class="cart-panel-heading">
                            <h4><i class="fas fa-tags"></i> Apply Promo</h4>
                            <span>Pattern: <strong>AAAA##</strong></span>
                        </div>
                        <div class="cart-promo-form">
                            <input type="text" id="promoCodeInput" placeholder="Example: SAVE10" maxlength="6" oninput="validatePromoCodeField()">
                            <button type="button" class="dfa-action-btn cart-promo-btn" onclick="applyPromoCode()">Apply</button>
                        </div>
                        <div class="dfa-status idle" id="promoCodeStatus">Waiting for a promo code.</div>
                        <div class="active-promo-badge" id="activePromoBadge" style="display: none;"></div>
                    </div>

                    <div class="cart-summary" id="cartSummary" style="display: none;">
                        <div class="summary-row">
                            <span>Subtotal:</span>
                            <span id="subtotal">₱0.00</span>
                        </div>
                        <div class="summary-row" id="promoRow" style="display: none;">
                            <span>Promo:</span>
                            <span id="promoLabel">None</span>
                        </div>
                        <div class="summary-row" id="discountRow" style="display: none;">
                            <span>Discount:</span>
                            <span id="discount">-₱0.00</span>
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

        <div class="order-modal" id="selectCustomerModal" style="display: none;" onclick="if(event.target===this){closeCustomerPickerModal();}">
            <div class="order-modal-card customer-picker-card">
                <h3>Select Customer for Official Receipt</h3>
                <p class="modal-note">Choose a registered customer or add a new one.</p>
                <input type="text" id="customerPickerSearch" class="customer-picker-search" placeholder="Search by name, phone, or email" oninput="filterCustomerPickerList()">
                <div class="customer-picker-list" id="customerPickerList"></div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="openCreateCustomerFromPicker()"><i class="fas fa-plus"></i> Add New Customer</button>
                    <button type="button" class="btn-secondary" onclick="closeCustomerPickerModal()">Cancel</button>
                    <button type="button" class="btn-primary" id="proceedReceiptBtn" onclick="proceedOfficialReceiptCheckout()" disabled>Save & Print Receipt</button>
                </div>
            </div>
        </div>

        <div class="order-modal" id="createCustomerModal" style="display: none;" onclick="if(event.target===this){closeCreateCustomerModal();}">
            <div class="order-modal-card">
                <h3>Create Customer</h3>
                <p class="modal-note">Add customer details to continue with official receipt.</p>
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
                                    <th>Product Code</th>
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
                                        $tableCategory = $product['category_name'] ?? 'N/A';
                                        $tableCode = !empty($product['product_code'] ?? '')
                                            ? strtoupper((string)$product['product_code'])
                                            : buildProductCode($product['id'], $tableCategory, (string)($product['code_prefix'] ?? ''));
                                        ?>
                                        <tr>
                                            <td><?php echo $product['id']; ?></td>
                                            <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                            <td><?php echo htmlspecialchars($tableCategory); ?></td>
                                            <td><span class="product-code-badge"><?php echo htmlspecialchars($tableCode); ?></span></td>
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
                                        <td colspan="9" style="text-align: center;">No products found. <a href="add_product.php">Add a product</a></td>
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
                                    $adminCode = !empty($product['product_code'] ?? '')
                                        ? strtoupper((string)$product['product_code'])
                                        : buildProductCode($product['id'], $adminCategory, (string)($product['code_prefix'] ?? ''));
                            ?>
                                <div class="admin-product-card"
                                     data-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                                     data-category="<?php echo htmlspecialchars($adminCategory); ?>"
                                     data-code="<?php echo htmlspecialchars($adminCode); ?>"
                                     data-price="<?php echo (float)$product['price']; ?>"
                                     data-stock="<?php echo (int)$product['stock']; ?>">
                                    <div class="admin-card-title"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                    <div class="admin-card-meta">ID: <?php echo $product['id']; ?></div>
                                    <div class="admin-card-meta">Category: <?php echo htmlspecialchars($adminCategory); ?></div>
                                    <div class="admin-card-meta">Code: <span class="product-code-badge"><?php echo htmlspecialchars($adminCode); ?></span></div>
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
        var customerRegistry = <?php echo json_encode($registered_customers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    </script>
    <script src="js/pos.js"></script>
    <?php else: ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="js/dashboard.js"></script>
    <?php endif; ?>
</body>
</html>