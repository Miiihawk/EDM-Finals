# 9toFive POS System

A modern Point of Sale (POS) system with user authentication, role-based dashboards, and comprehensive product management.

## Quick Start

1. **Start MySQL Server** (XAMPP/WAMP/MAMP)
2. **Access Application**: `http://localhost/EDM-Finals/frontend/login.php`

## Project Structure

```
EDM-Finals/
├── backend/           # PHP backend logic & database
│   ├── config.php           # Database connection
│   ├── process_sale.php     # Sale processing API
│   ├── logout.php           # Logout handler
│   ├── generate_hash.php    # Password utility
│   └── setup.sql            # Database setup
│
└── frontend/          # User interface & views
    ├── *.php                # Page views
    ├── *.css                # Stylesheets
    ├── *.js                 # Client scripts
    └── README.md            # Full documentation
```

## Features

- **Secure Authentication** - BCrypt password hashing
- **Role-Based Access** - Admin dashboard vs POS interface
- **Shopping Cart** - Full cart functionality with checkout
- **Inventory Management** - Real-time stock tracking
- **Search & Filter** - Find products quickly
- **Activity Logs** - Track all modifications
- **Modern UI** - Font Awesome icons, animations

## Full Documentation

For complete setup instructions, features, and troubleshooting, see:
**[frontend/README.md](frontend/README.md)**

## Tech Stack

- **Backend**: PHP 7.x+ with MySQLi
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, Vanilla JavaScript
- **Icons**: Font Awesome 6.5.1

## Configuration

Edit `backend/config.php` to customize database settings:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'pos_system');
```

---

**Version**: 1.0 | **Updated**: March 2026
