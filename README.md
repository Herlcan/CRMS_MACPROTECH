# MACPROTECH

MACPROTECH is a PHP and MySQL repair service management system for a computer repair shop. It manages the full shop workflow: customer intake, work orders, technician assignment, repair status tracking, inventory and parts, payments, refunds, receipts, notifications, reports, and configurable email/SMS communication.

The application is built as a classic PHP web app with MySQL/MariaDB, root-level page controllers, reusable handlers under `src/handlers/`, shared partials under `src/partials/`, and PHPMailer under `vendor/PHPMailer-master/`.

---

## What The System Does

MACPROTECH supports these main operational areas:

- Secure staff/admin login and role-based navigation.
- Customer records with searchable, paginated customer lists.
- Work order creation from a customer profile, including device details, repair issue, fees, priority, technician assignment, and parts.
- Repair lifecycle tracking from intake through diagnosis, repair, release, or cancellation.
- Technician reassignment with reasons and assignment history.
- Inventory item management, categories, item images, product codes, stock-in records, stock-out records, weighted average pricing, and low-stock alerts.
- Payment management with partial payments, discounts, digital payment references, refunds, transaction history, receipt printing, receipt email, and optional SMS alerts.
- Role-specific dashboards for administrators, front desk/cashier staff, and technicians.
- In-app notifications for work assignments, reassignment, status changes, low-stock conditions, payment events, and sent SMS messages.
- Administrator reports for work orders, revenue, customers, payments, inventory, technicians, activity logs, and CSV exports.
- System settings for business profile, receipt text, service policy, automatic release behavior, SMTP email delivery, and httpSMS delivery.

---

## User Roles

The active roles in the codebase are:

- `Administrator`
- `Cashier/Front Desk`
- `Cashier/Front Desk Staff`
- `Technician`

### Administrator

Administrators can access the main operational dashboard, customers, work orders, inventory, payments, notifications, reports, users, and system settings. Administrators can manage users, manage business/communication settings, delete customers, view reports, export reports, and oversee all work orders.

Administrators sign in through `admin-login.php`. The regular `login.php` rejects administrator accounts and is intended for staff/technician login.

### Cashier / Front Desk

Cashier/front-desk users can access customer intake, work orders, inventory, payments, and notifications. Their dashboard focuses on pickup/release queues, today's intakes, pending payments, and unassigned work orders.

Front-desk roles can reassign eligible work orders to a different technician.

### Technician

Technicians get a focused dashboard showing assigned active repairs, waiting-parts jobs, completions for the day, aged jobs, and parts readiness. The work order list is filtered to the logged-in technician's assigned work orders.

Technicians can update work order status through the status controls. The UI limits their list to assigned work orders; the status handler validates the role before accepting status updates.

---

## Authentication And Sessions

Important files:

- `login.php`
- `admin-login.php`
- `auth_check.php`
- `logout.php`
- `src/db/connection.php`

Implemented behavior:

- Passwords are verified with `password_verify()`.
- Successful login regenerates the session ID.
- Session cookies are configured with strict mode, cookie-only sessions, `HttpOnly`, `SameSite=Lax`, and `secure` when HTTPS is detected.
- `auth_check.php` protects authenticated pages and handlers.
- If a logged-in user has been deleted from the `users` table, the session is cleared and the user is redirected to login.

---

## Dashboards

Important files:

- `index.php`
- `src/partials/dashboard_cashier.php`
- `src/partials/dashboard_technician.php`
- `src/scripts/dashboard-charts.js`
- `src/scripts/chart.js`

### Administrator Dashboard

The administrator dashboard shows:

- Total work orders.
- Open repairs.
- Pending and aged pending work orders.
- Waiting-for-parts count.
- Low-stock and out-of-stock inventory.
- Outstanding balances and unpaid/partial payments.
- Total revenue after refunds.
- Monthly work order and revenue trends.
- Work order status distribution.
- Recent work orders.
- Low-stock item list.
- Technician workload summary.

### Cashier / Front-Desk Dashboard

The cashier dashboard shows:

- Ready-for-release queue.
- Today's intakes.
- Pending payment count and outstanding balance.
- Unassigned work orders.
- Customer pickup and release queue.
- Quick links to register a customer and search invoices/payments.

### Technician Dashboard

The technician dashboard shows:

- Active assigned repairs.
- Jobs waiting for parts.
- Completed jobs today.
- Aged assigned jobs.
- Active work queue sorted by rush priority and request date.
- Ordered-parts tracker with stock readiness.

---

## Customers

Important files:

- `clients.php`
- `client-view.php`
- `src/handlers/add_client.php`
- `src/handlers/edit_client.php`
- `src/handlers/delete_client.php`

Customer management includes:

- Add, edit, view, search, and paginate customers.
- Store customer name, email, contact number, address, and registration date.
- Open a customer profile to create and manage that customer's work orders.
- Administrator-only customer deletion.
- Deletion guard that blocks deleting customers with repaired work orders that still have unpaid or partial payment.
- Paid payment records are retained for revenue reporting when deleting a customer.

---

## Work Orders

Important files:

- `work-order.php`
- `client-view.php`
- `src/handlers/add_work_order.php`
- `src/handlers/update_work_order.php`
- `src/handlers/update_status.php`
- `src/handlers/get_work_order.php`
- `src/handlers/delete_work_order.php`
- `src/handlers/reassign_work_order.php`
- `src/handlers/work_order_schema.php`
- `src/handlers/work_order_assignment_schema.php`
- `src/handlers/ordered_part_schema.php`
- `src/partials/workorder_row_template.php`

### Work Order Creation

Work orders are created from `client-view.php` using a multi-step form.

Captured data includes:

- Customer ID.
- Unit type, with support for adding a custom unit type.
- Brand and model.
- Specifications/accessories.
- Request date.
- Problem/findings.
- Diagnostic fee.
- Work order cost.
- Priority: `In Que` or `Rush`.
- Initial status.
- Assigned technician.
- Notes.
- Purchased inventory parts.
- Ordered parts.
- Customer-provided parts.

When a work order is created:

- A `WO-0000` style code is generated from the inserted ID.
- Purchased inventory parts are saved and deducted from inventory.
- Ordered parts are saved with name, brand, category, description, quantity, and price.
- Customer-provided parts are saved separately.
- A payment record is automatically created with a `PMT-0000` style code.
- Payment totals include diagnostic fee, work order cost, purchased parts, and ordered parts.
- Technician assignment is recorded.
- Notifications and activity log entries are created.

### Work Order List And Details

`work-order.php` provides:

- Search.
- Pagination.
- Status filter.
- Priority filter.
- Technician-only filtering for assigned work orders.
- Rush priority sorting for active work.
- Status badges and status update controls.
- A detail modal with device information, parts used/ordered/provided, cost summary, payment records, progress stepper, and activity timeline.

### Status Workflow

Current statuses:

- `Pending`
- `Diagnosing`
- `Waiting for Parts`
- `In Progress`
- `Repaired`
- `Released`
- `Cancelled`

Notes:

- Older `Ready for Release` values are displayed as `Repaired`.
- Older `Completed` logic has been replaced by the `Repaired`/`Released` flow.
- Setting a work order to `Repaired` or `Released` preserves or sets `completion_date`.
- Moving back to an active state clears `completion_date`.
- A work order can only be marked `Released` when its latest payment is fully settled.
- Status changes are logged in `activity_logs`.
- Status changes can notify the assigned technician.
- Customer SMS status updates can be sent when enabled. `Released` status SMS is skipped because receipt email/SMS handles final release communication.

### Reassignment

Eligible work orders can be reassigned by administrators and front-desk roles.

Reassignment includes:

- Selecting a new technician.
- Choosing a reason such as sick leave, workload balancing, expertise required, scheduling conflict, resignation, emergency redistribution, or other.
- Recording assignment history in `work_order_assignments`.
- Logging the action in `activity_logs`.
- Notifying the new technician and, when applicable, the previous technician.

Completed, repaired, released, and cancelled work orders cannot be reassigned.

---

## Parts Handling

MACPROTECH separates parts into three groups:

- Purchased parts: inventory items used on a work order. These deduct item stock and affect stock-out history.
- Ordered parts: parts not yet in inventory but needed for a work order. These are included in payment totals and reports.
- Customer-provided parts: parts supplied by the customer. These are tracked on the work order but do not affect inventory stock.

---

## Inventory

Important files:

- `items.php`
- `stock_transaction.php`
- `item-category.php`
- `src/handlers/add_item.php`
- `src/handlers/edit_item.php`
- `src/handlers/delete_item.php`
- `src/handlers/search_items.php`
- `src/handlers/add_inventory_transaction.php`
- `src/handlers/edit_inventory_transaction.php`
- `src/handlers/delete_inventory_transaction.php`
- `src/handlers/inventory_transaction_schema.php`
- `src/handlers/item_schema.php`
- `src/handlers/category_schema.php`

Inventory supports:

- Product item creation with optional image upload.
- Product code generation using category, brand, and item ID.
- Category management, including ad-hoc category creation from item and ordered-part forms.
- Search, category filter, pagination, and item editing.
- Stock-in transactions with capital cost, stock-in quantity, markup percentage, and calculated average price.
- Stock-out tracking from work order purchased parts.
- Weighted average inventory calculation across stock-in and stock-out movement.
- Current stock, total stock-in, total stock-out, and average price summaries.
- Stock-in transaction edit/delete.
- Stock-out work order history per inventory item.
- Low-stock and out-of-stock notifications to administrators and front-desk roles.

Inventory status is derived from stock movement:

- Empty when there is no movement yet.
- `In Stock` when stock remains.
- `Out of Stock` when stock-in minus stock-out is zero or less.

---

## Payments, Refunds, And Receipts

Important files:

- `payment.php`
- `src/handlers/payment_schema.php`
- `src/handlers/get_payment_details.php`
- `src/handlers/confirm_payment.php`
- `src/handlers/create_refund.php`
- `src/handlers/send_payment_receipt.php`
- `src/handlers/update_payment_status.php`

Payment features include:

- Automatic payment record creation for each new work order.
- Search, status filter, pagination, and payment detail modal.
- Cost breakdown for diagnostic fee, work order cost, purchased parts, and ordered parts.
- Payment methods: `Cash`, `GCash`, `Maya`, and `Bank Transfer`.
- Required reference number for digital payments.
- Discounts.
- Partial payments.
- Total paid, refunded amount, net paid, change, and remaining balance.
- Payment statuses: `Unpaid`, `Partial`, `Paid`, `Partially Refunded`, and `Refunded`.
- Transaction ledger stored in `payment_transaction`.
- Refund flow with method and required reason.
- Printable receipts.
- Email receipts using PHPMailer and configured SMTP settings.
- Optional SMS alert after receipt email is sent.
- Optional automatic release of repaired work orders after full payment.

Payment summary values are recalculated from work order costs, parts, payment transactions, discounts, and refunds.

---

## Notifications

Important files:

- `notifications.php`
- `src/scripts/notifications.js`
- `src/handlers/notification_schema.php`
- `src/handlers/notification_helpers.php`
- `src/handlers/get_notifications.php`
- `src/handlers/mark_notification_read.php`

The system has an in-app notification bell and a full notification page.

Notification features:

- Unread badge in the header.
- Dropdown preview.
- Full notification history page.
- Filters for all, unread, read, and archived notifications.
- Mark single notification read.
- Mark all read.
- Archive and restore.

Events that can create notifications:

- New work order assignment.
- Work order update or status change.
- Technician reassignment.
- Low stock or out of stock item.
- Payment received.
- SMS sent by the system.

---

## Email And SMS Communication

Important files:

- `settings.php`
- `src/handlers/settings_helpers.php`
- `src/handlers/communication_helpers.php`
- `src/handlers/config.php`
- `vendor/PHPMailer-master/`

Communication features:

- Business profile settings used in receipts and messages.
- SMTP email configuration.
- PHPMailer-based receipt email.
- httpSMS integration for SMS delivery.
- SMS phone number normalization with default country code.
- SMS deduplication through `sms_delivery_log`.
- Status SMS for work order updates when enabled.
- Receipt SMS after receipt email when enabled.
- Encrypted storage for SMTP password and httpSMS API key.

Settings include:

- Business name, owner name, email, phone, address, Facebook page, hours, timezone.
- Receipt footer and service policy.
- Auto-release repaired work orders after full payment.
- Email enable/disable, SMTP host, port, username, password, encryption, from email, and from name.
- SMS enable/disable, sender number, default country code, httpSMS API key, status SMS toggle, and receipt SMS toggle.

`MACPROTECH_APP_KEY` is used for encrypted settings. It can be provided as an environment variable or via `src/handlers/config.php`.

---

## Reports And Exports

Important files:

- `reports.php`
- `export-reports.php`

Reports are administrator-only.

The reports page includes:

- Date range filtering.
- Work order totals, open work orders, aged work orders, cancellations, completions, and average cycle time.
- Customer totals, new customers, repeat customers, and top customers.
- Revenue, refunds, net revenue, gross billed, discounts, outstanding balance, and pending payments.
- Payment status and payment method summaries.
- Inventory totals, low stock, out of stock, inventory value, stock-in, stock-out, most-used parts, and ordered part spend.
- Technician workload and performance.
- Recent activity log entries.
- Charts for work order status, payment methods, customer intake, and inventory usage.

CSV exports are available for:

- `work_orders`
- `payments`
- `customers`
- `financial`
- `inventory`

Exports can use all data or filtered data and support additional filters for statuses, technicians, payment methods, customer balances, and stock state.

---

## User Accounts And Profile

Important files:

- `user.php`
- `settings.php`
- `header.php`
- `src/handlers/add_user.php`
- `src/handlers/edit_user.php`
- `src/handlers/delete_user.php`
- `src/handlers/update_profile.php`

User account features:

- Administrator-managed users.
- Searchable and paginated user list.
- Add/edit/delete users.
- Role badges.
- Password hashing for new/changed passwords.
- Protection against deleting the last administrator.
- User profile modal in the header.
- Profile/security panel in settings.
- Users can update their own username, name, contact number, email, and password.

---

## Activity Logging

Important files:

- `src/handlers/activity_log_helper.php`
- `activity_logs` table

The system records operational activity for:

- Work order creation, update, deletion, and status changes.
- Technician assignment and reassignment.
- Payment and refund transactions.
- Product item and category changes.
- Customer and user deletion.
- Settings updates.

Activity logs are used in work order timelines and reports.

---

## Database

The main schema dump is:

- `crms_macprotech.sql`

Core tables included in the dump:

- `activity_logs`
- `client`
- `customer_provided_component`
- `items`
- `inventory_transaction`
- `stock_in_transaction`
- `stock_out_transaction`
- `item_category`
- `ordered_parts`
- `payments`
- `payment_transaction`
- `refunds`
- `purchased_item`
- `unit_type`
- `users`
- `work_order`
- `work_order_assignments`

Tables created or updated at runtime by schema helpers:

- `app_settings`
- `notifications`
- `sms_delivery_log`

Many handlers call schema helper functions before use. These helpers add missing columns/tables for newer features such as payment details, ordered parts, stock movement, work order priority, assignment history, notifications, and settings.

---

## Requirements

- PHP 8.0 or newer.
- MySQL/MariaDB.
- Apache/XAMPP or another PHP-capable web server.
- PHP extensions commonly enabled in XAMPP: `mysqli`, `openssl`, `curl`, `fileinfo`, and session support.
- Writable `src/uploads/` directory for inventory item images.
- SMTP account/app password if receipt email is enabled.
- httpSMS API key and sender number if SMS delivery is enabled.

---

## Installation

1. Place the project in the web server document root, for example:

   ```bash
   /opt/lampp/htdocs/MACPROTECH/
   ```

2. Create a MySQL/MariaDB database named:

   ```text
   crms_macprotech
   ```

3. Import:

   ```text
   crms_macprotech.sql
   ```

4. Configure the database connection in:

   ```text
   src/db/connection.php
   ```

5. Make sure image uploads can be written:

   ```text
   src/uploads/
   ```

6. Configure `MACPROTECH_APP_KEY` for encrypted settings, either through the environment or `src/handlers/config.php`.

7. Open the system in the browser:

   ```text
   http://localhost/MACPROTECH/
   ```

The SQL dump includes an initial administrator record named `Admin`. Use the project-provided password or reset it in the database if needed.

---

## Active Navigation

The current shared sidebar uses role-based navigation for:

- Dashboard
- Customers
- Work Orders
- Inventory
- Payments
- Notifications
- Reports
- Users

Settings is available from the header profile dropdown.

---

## Legacy Or Placeholder Pages

The repository still contains older/static pages that appear to be retained from an earlier template and are not part of the main role-based sidebar flow:

- `customer.php`
- `customer-work-order.php`
- `work-order-status.php`
- `services.php`
- `user-group.php`
- `bar.php`
- `pie.php`
- `technician.php`

These pages should be reviewed before treating them as active production workflows.

---

## Project Structure

```text
.
+-- *.php                         Root page controllers
+-- auth_check.php                Session guard
+-- crms_macprotech.sql           Database schema dump
+-- src/
|   +-- db/connection.php         Database/session bootstrap
|   +-- handlers/                 Form/AJAX handlers and schema helpers
|   +-- images/                   UI assets and branding
|   +-- partials/                 Dashboard and work order partials
|   +-- scripts/                  Frontend JavaScript
|   +-- styles/                   Application CSS
|   +-- uploads/                  Inventory item images
+-- vendor/PHPMailer-master/      Email library
```

---

## Security Notes

- Passwords are hashed with `password_hash()` and verified with `password_verify()`.
- Login regenerates the session ID.
- Session cookies are hardened in `src/db/connection.php`.
- `auth_check.php` protects pages and handlers.
- Administrator-only areas include reports, exports, user management, customer deletion, and settings updates.
- Many handlers use prepared statements and transactions for critical writes.
- Some list/search/filter pages still build SQL strings manually with escaping. Keep validation strict if extending those endpoints.
- SMTP password and httpSMS API key are encrypted with `MACPROTECH_APP_KEY` before storage in `app_settings`.

---

## Version

Version: 1.2
Last Updated: 2026-06-09
