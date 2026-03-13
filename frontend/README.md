# Frontend - 9toFive POS

Frontend pages and assets for the POS system.

## Entry Points

- `index.php` -> redirects to login
- `login.php` -> authentication
- `signup.php` -> account registration
- `dashboard.php` -> role-based dashboard (Admin/User)
- `add_product.php` -> add/edit products (admin)
- `add_category.php` -> category management (admin)
- `manage_users.php` -> user management (admin)
- `manage_customers.php` -> customer management (admin)

## Asset Organization

All styles and scripts are now organized into folders:

```
frontend/
├── css/
│   ├── auth.css
│   ├── dashboard.css
│   ├── pos.css
│   ├── manage-customers.css
│   └── style.css
├── js/
│   └── dashboard.js
└── *.php
```

## Dashboard Behavior

`dashboard.php` renders different UI based on role:

- **Admin**:
  - Products table and card views
  - Search/sort/filter
  - Orders panel and order details modal
  - Manage navigation (users/customers/categories/products)
- **User (POS)**:
  - Product browsing and cart
  - Checkout flow:
    - **Official Receipt** (with customer details)
    - **Process Without Details** (walk-in/no customer details)

## Key Frontend Features

- Responsive layout
- Product state indicators (available / out-of-stock)
- Scrollable admin table/card containers
- Order summary modal with print + PDF download
- Customer-aware rendering for receipts and order details

## Setup Notes

1. Ensure backend is configured in `../backend/config.php`.
2. Ensure database schema is created from `../backend/setup.sql`.
3. Start Apache + MySQL.
4. Open `http://localhost/EDM-Finals/frontend/`.

## Troubleshooting

### Styles/scripts not loading

- Verify paths use `css/...` and `js/...`.
- Hard refresh browser cache (`Ctrl+F5`).

### Dashboard interactions not working

- Check browser console for JavaScript errors.
- Confirm `js/dashboard.js` is loading.

### Login issues

- Confirm DB connection in `../backend/config.php`.
- Ensure users exist (run `../backend/create_accounts.php` if needed).

---

Updated: March 2026
