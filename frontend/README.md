# 9toFive POS System

A modern Point of Sale (POS) system with user authentication, role-based dashboards, and comprehensive product management built with PHP and MySQL.

## Features

### User Authentication & Roles

- **Secure Login & Signup**: BCrypt password hashing
- **Role-Based Access**: Admin and Regular User roles with different permissions
- **Session Management**: PHP sessions for secure authentication

### Admin Dashboard

- **Product Management**: Full CRUD operations (Create, Read, Update, Delete)
- **Inventory Tracking**: Real-time stock level monitoring with low-stock warnings
- **Search & Sort**: Search products by name/category, sort by name/price/stock/category
- **Activity Logs**: View recent product modifications and sales with timestamps
- **User Management**: View and manage user accounts

### Regular User Dashboard (POS Interface)

- **Product Grid View**: Visual product cards with category icons
- **Category Filters**: Quick filtering by product categories (Tools, Paint, Electrical, Plumbing, Fasteners)
- **Product Search**: Live search functionality for products
- **Shopping Cart**: Add products, adjust quantities, view totals
- **Checkout**: Process sales with automatic stock updates and log entries

### UI/UX

- **Modern Design**: Gradient colors, Font Awesome icons, smooth animations
- **Responsive Layout**: Adapts to different screen sizes
- **Loading States**: Visual feedback for user actions
- **Out-of-Stock Indicators**: Clear visual indicators for unavailable products

## 🛠️ Technologies Used

- **Backend**: PHP 7.x+
- **Database**: MySQL with MySQLi extension
- **Frontend**: HTML5, CSS3 (Grid/Flexbox), JavaScript (Vanilla)
- **Icons**: Font Awesome 6.5.1
- **Security**: BCrypt password hashing, SQL injection prevention, session management

## Project Structure

```
EDM-Finals/
├── backend/
│   ├── config.php           # Database connection & table creation
│   ├── process_sale.php     # API endpoint for processing sales
│   ├── logout.php           # Logout handler
│   ├── generate_hash.php    # Utility script for password hashing
│   └── setup.sql            # Database initialization with sample data
│
└── frontend/
    ├── index.php            # Entry point (redirects to login)
    ├── login.php            # Login page with authentication
    ├── signup.php           # User registration page
    ├── dashboard.php        # Main dashboard (admin table view / user POS view)
    ├── add_product.php      # Add/Edit products page (admin only)
    ├── manage_users.php     # User management page (admin only)
    ├── dashboard.js         # Search and sort functions for admin dashboard
    ├── dashboard.css        # Main dashboard styling
    ├── pos.css              # POS-specific styling
    ├── auth.css             # Login/signup page styling
    ├── style.css            # Global styles
    └── README.md            # This file
```

## Setup Instructions

### 1. Prerequisites

- **Web Server**: XAMPP, WAMP, MAMP, or similar (with Apache and PHP 7.x+)
- **MySQL**: Version 5.7+ or MariaDB
- **PHP Extensions**: mysqli extension enabled

### 2. Database Setup

**Option A: Automatic Setup** (Recommended)

1. Start your MySQL server
2. Access any page - tables will be created automatically via `config.php`

**Option B: Manual Setup**

1. Start your MySQL server
2. Open phpMyAdmin or MySQL CLI
3. Run the SQL script: `backend/setup.sql`
   - Creates database `pos_system`
   - Creates tables: `users`, `products`, `logs`
   - Inserts sample data and default accounts

### 3. Configuration

Edit `backend/config.php` if your MySQL settings differ:

```php
define('DB_HOST', 'localhost');     // Your MySQL host
define('DB_USER', 'root');          // Your MySQL username
define('DB_PASS', '');              // Your MySQL password
define('DB_NAME', 'pos_system');    // Database name
```

### 4. Installation

1. Clone or download this repository
2. Place the `EDM-Finals` folder in your web server directory:
   - XAMPP: `C:\xampp\htdocs\EDM-Finals`
   - WAMP: `C:\wamp64\www\EDM-Finals`
   - MAMP: `/Applications/MAMP/htdocs/EDM-Finals`

### 5. Running the Application

Access the application in your browser:

```
http://localhost/EDM-Finals/frontend/login.php
```

Or simply:

```
http://localhost/EDM-Finals/frontend/
```

## Database Schema

### Users Table

- `id` (INT) - Primary key, auto increment
- `username` (VARCHAR) - Unique username
- `password` (VARCHAR) - BCrypt hashed password
- `role` (ENUM) - 'admin' or 'user'
- `created_at` (TIMESTAMP) - Account creation timestamp

### Products Table

- `id` (INT) - Primary key, auto increment
- `product_name` (VARCHAR) - Product name
- `category` (VARCHAR) - Product category
- `price` (DECIMAL) - Product price
- `stock` (INT) - Stock quantity
- `status` (ENUM) - 'Available' or 'Out of Stock'
- `created_at` (TIMESTAMP) - Creation timestamp

### Logs Table

- `id` (INT) - Primary key, auto increment
- `product_name` (VARCHAR) - Related product
- `quantity` (INT) - Quantity change (negative for sales/deletions)
- `action` (VARCHAR) - Action type (Added, Updated, Sold, Deleted)
- `username` (VARCHAR) - User who performed action
- `log_time` (TIMESTAMP) - Action timestamp

## Security Features

- **Password Hashing**: BCrypt with PHP's `password_hash()`
- **SQL Injection Prevention**: Prepared statements and `mysqli_real_escape_string()`
- **Session Security**: PHP sessions with proper validation
- **Role-Based Access Control**: Admin-only pages check user role
- **XSS Prevention**: HTML special chars escaping on output

## Usage Guide

### For Admins:

1. **View Products**: Main dashboard shows all products in a sortable table
2. **Add Products**: Click "Add Products" in sidebar, fill form, submit
3. **Edit Products**: Click "Update" button on any product row
4. **Delete Products**: Click trash icon to delete a product
5. **Search**: Use search bar to filter products by any field
6. **Sort**: Use dropdown to sort by name, price, stock, or category
7. **View Logs**: Check recent activity in the logs panel (latest first, 12-hour format)
8. **Manage Users**: Access "Manage Users" to view and delete user accounts

### For Regular Users:

1. **Browse Products**: View all available products in grid layout
2. **Filter by Category**: Click category buttons to filter products
3. **Search Products**: Use search bar to find products by name or category
4. **Add to Cart**: Click on product cards to add items to cart
5. **Adjust Quantities**: Use +/- buttons in cart to modify quantities
6. **Remove Items**: Click trash icon to remove items from cart
7. **Checkout**: Click "Checkout" button to complete sale
8. **Clear Cart**: Click "Clear Cart" to empty the cart

## Troubleshooting

### Issue: HTTP 500 Error

**Solution**: Ensure mysqli extension is enabled in `php.ini`:

```ini
extension=mysqli
```

Restart Apache after making changes.

### Issue: Database Connection Failed

**Solution**:

- Verify MySQL is running
- Check `backend/config.php` credentials
- Ensure database `pos_system` exists

### Issue: Search/Sort Not Working

**Solution**:

- Ensure `frontend/dashboard.js` is loaded
- Check browser console for JavaScript errors
- Clear browser cache

### Issue: Can't Login

**Solution**:

- Verify database has user records
- Run `backend/setup.sql` to create default accounts
- Use `backend/generate_hash.php` to create new password hashes

## Recent Updates

- ✅ Added Font Awesome icons throughout the application
- ✅ Implemented search functionality for POS interface
- ✅ Fixed admin dashboard search and sort functions
- ✅ Added 12-hour time format with AM/PM for logs
- ✅ Logs now display most recent entries first

## Notes

- **Stock Management**: Stock automatically decreases on sales
- **Out of Stock**: Products with 0 stock are marked as unavailable and cannot be added to cart
- **Low Stock Warning**: Products with <10 stock show visual warning
- **Log Retention**: Currently shows last 10 log entries
- **Session Timeout**: Sessions expire when browser is closed

## Development

To modify or extend:

1. **Add New Product Categories**: Update category arrays in `dashboard.php` and `add_product.php`
2. **Change UI Colors**: Edit CSS variables in respective `.css` files
3. **Modify Database**: Update schema in `backend/config.php` and `backend/setup.sql`
4. **Add New Pages**: Create new `.php` files in `frontend/`, include `backend/config.php`

## License

This project is open-source and available for educational purposes.

## Support

For issues or questions:

1. Check the Troubleshooting section above
2. Verify all prerequisites are installed correctly
3. Ensure database credentials are correct in `backend/config.php`

---

**Version**: 1.0  
**Last Updated**: March 2026  
**Built with ❤️ using PHP & MySQL**

- Add and edit products
- View activity logs

## Features Overview

### Dashboard

- Products table with search and sort functionality
- Real-time activity logs
- Product status indicators (Available/Out of Stock)
- User profile display

### Product Management

- Add new products with:
  - Product name
  - Category (Tools, Paint, Electrical, Plumbing, Fasteners)
  - Price
  - Stock quantity
- Edit existing products
- Delete products
- Automatic status updates based on stock

### Activity Logs

- Track all product changes
- Display quantity changes (positive/negative)
- Show timestamp and user who made the change
- Real-time updates

## Security Features

- Password hashing using PHP's `password_hash()`
- SQL injection protection using `mysqli_real_escape_string()`
- Session management for user authentication
- Role-based access control

## Technical Details

- **Backend**: PHP with MySQLi
- **Database**: MySQL
- **Frontend**: HTML5, CSS3, JavaScript
- **Sessions**: PHP sessions for authentication
- **Responsive design**: Works on desktop and mobile

## Future Enhancements

- Sales/checkout functionality
- Receipt generation
- Advanced reporting and analytics
- Export data to CSV/PDF
- Multi-currency support
- Barcode scanning
- Email notifications

## License

This project is for educational purposes.

## Support

For issues or questions, please check the code comments or create an issue.
