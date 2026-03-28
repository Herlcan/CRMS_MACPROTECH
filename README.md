# MACPROTECH

A comprehensive management system for technical services, built with PHP and MySQL.

## Overview

MACPROTECH is a web-based platform designed to manage technical services operations including client management, work orders, inventory, technician assignments, and payment processing.

## Features

- **Client Management** - Add, edit, and manage customer information
- **Work Orders** - Create and track service requests with status management
- **Technician Management** - Manage technician profiles and assignments
- **Inventory Management** - Track items and categorize inventory
- **Payment Processing** - Record and manage customer payments
- **Services Management** - Define and manage service offerings
- **User & Group Management** - Configure user accounts and permission groups
- **Analytics** - View business insights with charts and reporting

## Project Structure

```
├── auth_check.php              # Authentication verification
├── login.php                   # User login page
├── logout.php                  # User logout handler
├── index.php                   # Dashboard/Home page
├── header.php                  # Page header component
├── footer.php                  # Page footer component
├── sidebar.php                 # Navigation sidebar
│
├── clients.php                 # Client management interface
├── customer.php                # Customer details
├── customer-work-order.php     # Customer-specific work orders
│
├── work-order.php              # Work order management
├── work-order-status.php       # Work order status tracking
│
├── technician.php              # Technician management
├── services.php                # Services management
├── items.php                   # Inventory items
├── item-category.php           # Item categorization
├── payment.php                 # Payment processing
├── user.php                    # User account management
├── user-group.php              # User group configuration
├── settings.php                # Application settings
│
├── src/
│   ├── db/
│   │   └── connection.php      # Database connection handler
│   ├── handlers/
│   │   ├── add_user.php
│   │   ├── edit_user.php
│   │   ├── delete_user.php
│   │   ├── add_client.php
│   │   ├── edit_client.php
│   │   ├── delete_client.php
│   │   ├── add_category.php
│   │   ├── update_profile.php
│   │   └── ...
│   ├── images/                 # Image assets
│   └── styles/
│       ├── style.css           # Main stylesheet
│       └── style-improved.css  # Enhanced styles
│
└── README.md                   # This file
```

## Requirements

- PHP 7.0 or higher
- MySQL 5.7 or higher
- Apache/XAMPP server (recommended)
- Modern web browser

## Installation

1. **Clone or extract the project** into your web server's document root:
   ```bash
   /opt/lampp/htdocs/MACPROTECH/
   ```

2. **Configure the database connection** in `src/db/connection.php`:
   - Update the database credentials (host, username, password, database name)

3. **Create the database** with necessary tables:
   - Import your database schema (if provided)
   - Or manually create required tables for users, clients, work orders, etc.

4. **Access the application**:
   - Open your browser and navigate to `http://localhost/MACPROTECH/`

## Usage

### Logging In
1. Go to the login page
2. Enter your credentials
3. Upon successful authentication, you'll be redirected to the dashboard

### Managing Clients
- Navigate to **Clients** to view all customers
- Click **Add Client** to register new customers
- Edit or delete client information as needed

### Creating Work Orders
- Go to **Work Orders**
- Create new work orders and assign to technicians
- Track status and updates throughout the service lifecycle

### Managing Inventory
- Access **Items** for inventory management
- Organize items using **Item Categories**
- Track stock levels and product information

### Processing Payments
- Navigate to **Payment** section
- Record customer payments and view transaction history

## File Descriptions

| File | Purpose |
|------|---------|
| `auth_check.php` | Validates user sessions and permissions |
| `login.php` | User authentication interface |
| `index.php` | Main dashboard page |
| `clients.php` | Client listing and management |
| `work-order.php` | Work order creation and tracking |
| `technician.php` | Technician profile management |
| `payment.php` | Payment recording and tracking |
| `settings.php` | Application configuration |

## Database Connection

The application connects to MySQL through `src/db/connection.php`. Ensure your database credentials are properly configured before running the application.

## Security Features

- **Authentication Check** - All pages verify user login status
- **Session Management** - Secure session handling
- **Input Validation** - Data validation in form handlers

## Support

For issues or questions, please review the code comments or refer to the individual PHP files.

## License

This project is proprietary software for MACPROTECH.

---

**Version:** 1.0  
**Last Updated:** February 2026