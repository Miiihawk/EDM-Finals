# CornerMart POS System

PHP + MySQL point-of-sale system with role-based access for Admin and User workflows.

## Quick Start

1. Start Apache + MySQL (XAMPP/WAMP/MAMP).
2. Put this folder in your web root (for example: `C:\xampp\htdocs\EDM-Finals`).
3. Open: `http://localhost/EDM-Finals/frontend/`

## Project Layout

```
EDM-Finals/
├── backend/
│   ├── config.php
│   ├── setup.sql
│   ├── sample_data.sql
│   ├── place_order.php
│   ├── get_order_details.php
│   ├── process_sale.php
│   ├── customer_lookup.php
│   ├── create_customer.php
│   ├── create_accounts.php
│   ├── generate_hash.php
│   └── logout.php
└── frontend/
    ├── index.php
    ├── login.php
    ├── signup.php
    ├── dashboard.php
    ├── add_product.php
    ├── add_category.php
    ├── manage_users.php
    ├── manage_customers.php
    ├── css/
    │   ├── auth.css
    │   ├── dashboard.css
    │   ├── pos.css
    │   ├── manage-customers.css
    │   └── style.css
    ├── js/
    │   └── dashboard.js
    ├── images/
    └── README.md
```

## Core Features

- Authentication with hashed passwords
- Role-based dashboard views
- Product and category management
- Customer management
- POS cart and checkout flow
- DFA product-code validation with cashier quick-add using the `AAA999` pattern
- DFA receipt-line validation using the `ITEMCODE-QTY-PRICE` pattern
- DFA promo validation using the `AAAA##` pattern with discounts from `05` to `30`
- Order history/details (with print and PDF)
- Inventory and activity tracking

## DFA Rules Used In POS

- Product code: 3 uppercase letters followed by 3 digits. Example: `TOO001`
- Receipt line: product code, dash, quantity, dash, price with 2 decimal places. Example: `TOO001-2-149.50`
- Promo code: 4 uppercase letters followed by a 2-digit discount percentage from `05` to `30`. Example: `SAVE10`

## Database

Primary tables from `backend/setup.sql`:

- `users`
- `categories`
- `products`
- `inventory_history`
- `customers`
- `orders`
- `order_items`

## Entity Descriptions

### Users

- Primary Key: `id`
- Important Attributes:
  - `username`
  - `password`
  - `first_name`
  - `last_name`
  - `role`
  - `created_at`
- Purpose:
  - The Users table stores system accounts that are allowed to access the POS platform. Each user is assigned a role such as administrator or regular user (cashier). The table enables authentication, user identification, and role-based access control within the system.

### Categories

- Primary Key: `id`
- Important Attributes:
  - `category_name`
  - `code_prefix`
  - `created_at`
- Purpose:
  - The Categories table groups products into logical classifications. This allows products to be organized into categories such as beverages, snacks, or household items, making product management and navigation easier.

### Products

- Primary Key: `id`
- Foreign Key: `category_id` references `categories(id)`
- Important Attributes:
  - `product_name`
  - `product_code`
  - `image_path`
  - `image_blob`
  - `image_mime`
  - `price`
  - `stock`
  - `status`
  - `created_at`
- Purpose:
  - The Products table stores information about all sellable items available in the store. Each product belongs to a specific category and includes its code, price, stock quantity, product photo, and availability status.

### Customers

- Primary Key: `id`
- Important Attributes:
  - `full_name`
  - `phone`
  - `email`
  - `created_at`
- Purpose:
  - The Customers table stores customer information used during transactions that require official receipts or customer identification. This allows the system to maintain purchase records linked to specific customers.

### Orders

- Primary Key: `id`
- Foreign Keys:
  - `customer_id` references `customers(id)`
  - `created_by` references `users(id)`
- Important Attributes:
  - `tracking_no`
  - `payment_method`
  - `subtotal`
  - `total_amount`
  - `cash_received`
  - `cash_change`
  - `order_date`
- Purpose:
  - The Orders table records transaction headers. Each record represents a completed purchase made by a customer and processed by a system user.

### Order Items

- Primary Key: `id`
- Foreign Keys:
  - `order_id` references `orders(id)`
  - `product_id` references `products(id)`
- Important Attributes:
  - `quantity`
  - `price`
  - `subtotal`
- Purpose:
  - The Order Items table stores the individual products included in each order. This table allows the system to track detailed line-item sales and compute order totals.

### Inventory History

- Primary Key: `id`
- Foreign Keys:
  - `product_id` references `products(id)`
  - `user_id` references `users(id)`
- Important Attributes:
  - `change_quantity`
  - `reason`
  - `created_at`
- Purpose:
  - The Inventory History table logs stock movements caused by product updates, restocking, or completed sales. This table provides traceability and allows administrators to monitor inventory changes.

### Relationship Summary

- One category can have many products.
- One product can appear in many order items.
- One order can contain many order items.
- One customer can have many orders.
- One user can create many orders.
- One product can have many inventory history records.
- One user can generate many inventory history records.

This relational structure ensures that every product sale, stock movement, and user activity can be traced through the system.

## Configuration

Update `backend/config.php` if needed:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'pos_system');
```

## Documentation

Detailed frontend usage and setup are in [frontend/README.md](frontend/README.md).

---

Updated: March 2026
