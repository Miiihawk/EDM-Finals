# 9toFive POS System

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
