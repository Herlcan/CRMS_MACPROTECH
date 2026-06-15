# MACPROTECH

MACPROTECH is a PHP and MySQL repair service management system for a computer repair shop. It manages the full shop workflow: customer intake, work orders, technician assignment, repair status tracking, inventory and parts, payments, refunds, receipts, notifications, reports, database backup/restore, and configurable email/SMS communication.

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
- Administrator database backup and restore for disaster recovery and business continuity.

---

## Recent System Additions

Recent system updates added or expanded these areas:

- Separate administrator and staff/technician login portals with role enforcement.
- CSRF protection for login forms and important form/AJAX actions, with audit logging for failed CSRF checks.
- Login attempt tracking and temporary lockouts after repeated failed sign-in attempts.
- Forgot-password flow with one-time email OTP codes, token expiry, request throttling, verification-attempt limits, and password policy enforcement.
- Stronger session handling, including regenerated session IDs, hardened session cookies, and deleted-user session invalidation.
- Common security headers and a compatibility Content Security Policy are sent from the database/session bootstrap.
- Idle session timeout logs users out after 30 minutes of inactivity by default.
- Reusable authentication and role guards for page, JSON, and fragment handlers.
- Stricter SQL filter helpers for dynamic search/filter pages, including placeholder validation and allow-listed SQL fragments.
- Audit logging for failed authorization attempts, report page views/exports, payment-detail reads, and receipt email/SMS sending.
- JSON/AJAX handlers now disable PHP error display so runtime details are not returned in API responses.
- Unique database constraints now cover usernames, emails, work order codes, payment codes, and product codes.
- Work order, item, and payment code creation now uses temporary unique codes before final ID-based codes are written.
- Technician status updates are validated at the server and constrained in the database update to the assigned technician.
- Administrator backup/restore page with CSRF protection, audit logging, SQL export, restore upload limits, and restore statement allow-listing.
- Password policy checks for new users, edited users, and profile password changes.
- Encrypted Settings secrets for SMTP passwords and httpSMS API keys.
- Secure inventory image uploads with size limits, extension/MIME checks, randomized filenames, and script execution blocking in `src/uploads/`.
- A security/integrity migration for existing installations, covering `login_attempts`, unique business codes, inventory cost snapshots, and core foreign keys.
- Role-specific dashboards, work order reassignment history, ordered/customer-provided parts, payment refunds, receipt email/SMS delivery, notifications, and expanded reports/exports.
- App shell and modal behavior improvements so drawers/modals lock scroll correctly and remain usable inside the shared frame layout.

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

Technicians can update work order status through the status controls. The UI limits their list to assigned work orders; the status handler validates ownership and constrains the database update to the logged-in technician before accepting status changes.

---

## Authentication And Sessions

Important files:

- `login.php`
- `admin-login.php`
- `forgot-password.php`
- `reset-password.php`
- `auth_check.php`
- `logout.php`
- `src/db/connection.php`
- `src/handlers/security_helpers.php`
- `src/handlers/password_reset_helpers.php`
- `database/security_integrity_migration.sql`

Implemented behavior:

- Passwords are hashed with `password_hash()` when users are created or updated and verified with `password_verify()` during login.
- Successful login regenerates the session ID to reduce session fixation risk.
- Session cookies are configured with strict mode, cookie-only sessions, `HttpOnly`, `SameSite=Lax`, and `secure` when HTTPS is detected.
- `auth_check.php` protects authenticated pages and handlers.
- If a logged-in user has been deleted from the `users` table, the session is cleared and the user is redirected to login.
- Idle sessions expire after 30 minutes of inactivity by default. The timeout can be changed by defining `MACPROTECH_IDLE_TIMEOUT_SECONDS`.
- Login forms include CSRF tokens and reject expired or invalid tokens.
- Failed login attempts are recorded by username and IP address. Five failed attempts inside 15 minutes lock the username/IP pair for 15 minutes.
- Forgot-password requests accept username or email, always return a generic response, throttle repeated requests, and send one-time OTP codes through the configured SMTP mailer.
- Password reset OTPs store only keyed token hashes, expire after 10 minutes, lock after repeated invalid verification attempts, must be verified before the password form is shown, can be used once, and require the same password policy as user creation/profile updates.
- Administrator accounts are accepted only through `admin-login.php`; regular staff and technician accounts use `login.php`.
- Shared helpers provide `require_role()`, `require_authenticated_json()`, `require_json_role()`, and `require_authenticated_fragment()` for protected actions.
- Logout records an activity entry when possible, clears session data, removes the session cookie, and destroys the session.

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

Encrypted settings use an application key. For normal installs, the system creates `src/handlers/config.local.php` automatically the first time an SMTP password or httpSMS API key is saved in Settings. Keep this file with the deployment backup so saved SMTP and SMS secrets remain decryptable.

---

## Backup And Restore

Important files:

- `backup-restore.php`
- `src/handlers/database_backup_helpers.php`
- `sidebar.php`
- `settings.php`

Backup and restore is administrator-only.

The backup page includes:

- Full SQL database export with table structure and data.
- Download filenames in the `backup_YYYY-mm-dd_His.sql` format.
- Restore upload for `.sql` files up to 50MB.
- CSRF protection for export and restore actions.
- Audit logging for backup downloads, restore start, restore completion, and restore failures.
- Restore SQL statement allow-listing for expected dump operations such as table drops, table creation, inserts, table alters, transactions, and session settings.

Administrators can open Backup from the sidebar or from the Settings header actions.

Important restore note: restoring a backup replaces the current database contents. Download a fresh backup before restoring, and keep `src/handlers/config.local.php` with deployment backups so encrypted SMTP/SMS settings remain decryptable.

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
- `src/handlers/security_helpers.php`
- `activity_logs` table

The system records operational activity for:

- Work order creation, update, deletion, and status changes.
- Technician assignment and reassignment.
- Payment and refund transactions.
- Product item and category changes.
- Customer and user deletion.
- Settings updates.
- Receipt email/SMS sending outcomes.
- Report page views and report exports.
- Payment-detail reads.
- Failed authorization and failed CSRF/security checks.

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
- `login_attempts`
- `password_reset_attempts`
- `password_resets`
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

Many handlers call schema helper functions before use. These helpers add missing columns/tables for newer features such as payment details, ordered parts, stock movement, work order priority, assignment history, notifications, settings, login-attempt tracking, and password reset tracking.

Database uniqueness rules:

- `users.username`
- `users.email`
- `work_order.code`
- `payments.payment_code`
- `items.product_code`

Existing installations should run `database/security_integrity_migration.sql` after backing up the database. The migration creates `login_attempts`, `password_resets`, and `password_reset_attempts`, adds `average_cost_snapshot` to stock-out history, adds the unique constraints above when existing data has no duplicates, and adds core foreign keys for work orders, payments, refunds, and payment transactions.

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

## Docker Local Setup

Use this path if you want to run the system locally without installing XAMPP, PHP, Apache, or MySQL directly on your computer.

### What Docker Runs

The included Docker setup starts three containers:

- `app`: Apache with PHP 8.2, serving this project at `http://localhost:8080/`.
- `db`: MariaDB 10.11, initialized with `crms_macprotech.sql`.
- `phpmyadmin`: phpMyAdmin at `http://localhost:8081/` for database viewing and manual edits.

The app container reads database settings from environment variables, so the same `src/db/connection.php` still works for normal XAMPP installs and for Docker.

### 1. Install Docker

Install Docker Desktop on Windows or macOS, or Docker Engine on Linux.

After installing, open a terminal and confirm Docker Compose works:

```bash
docker compose version
```

If the command prints a version, Docker is ready.

### 2. Start The System

From the project folder, run:

```bash
docker compose up -d --build
```

What this does:

- Downloads the PHP, MariaDB, and phpMyAdmin images the first time.
- Builds the MACPROTECH PHP/Apache image.
- Creates a persistent MariaDB volume.
- Creates the `crms_macprotech` database.
- Imports `crms_macprotech.sql` during the first database startup.

Check that everything is running:

```bash
docker compose ps
```

### 3. Open The App

Open these URLs in your browser:

```text
Application:       http://localhost:8080/
Administrator:     http://localhost:8080/admin-login.php
Staff/technician:  http://localhost:8080/login.php
phpMyAdmin:        http://localhost:8081/
```

phpMyAdmin login:

```text
Server: db
Username: root
Password: root
```

You can also use:

```text
Username: macprotech
Password: macprotech
Database: crms_macprotech
```

The database is exposed to your host machine on port `3307`, so a local database tool can connect to `127.0.0.1:3307`.

### 4. Stop And Restart

Stop the containers:

```bash
docker compose stop
```

Start them again:

```bash
docker compose start
```

Stop and remove the containers, while keeping the database data:

```bash
docker compose down
```

### 5. Reset The Local Database

MariaDB imports `crms_macprotech.sql` only when the database volume is created for the first time. If you want to wipe local Docker data and re-import the SQL dump:

```bash
docker compose down -v
docker compose up -d --build
```

This deletes the Docker database volume. Do not run it if you need to keep local test data.

### 6. Upload Folder Permissions

The app stores inventory item images in:

```text
src/uploads/
```

If uploads fail on Linux because Apache inside Docker cannot write to the mounted folder, run this from the project folder:

```bash
chmod -R a+rwX src/uploads
```

### 7. App Key For Settings Secrets

The Docker setup provides a local development `MACPROTECH_APP_KEY` so Settings secrets such as SMTP passwords and httpSMS API keys can be encrypted without creating `src/handlers/config.local.php`.

For real production deployment, set your own persistent `MACPROTECH_APP_KEY` instead of using the default local development value in `docker-compose.yml`.

### Useful Docker Commands

```bash
docker compose logs -f app       # Watch PHP/Apache logs
docker compose logs -f db        # Watch MariaDB logs
docker compose exec app php -v   # Check PHP inside the app container
docker compose exec db mariadb -u macprotech -pmacprotech crms_macprotech
```

---

## Shop Production Docker Setup

Use this setup when installing MACPROTECH on the repair shop computer. It is stricter than the local development setup:

- The app image includes the PHP source code instead of bind-mounting the whole project folder.
- MariaDB is not exposed to the host network.
- phpMyAdmin is disabled unless you explicitly start the `tools` profile.
- Uploaded inventory images persist in a Docker volume.
- Database data persists in a Docker volume.
- Browser PHP error display is disabled through `APP_ENV=production`.

### 1. Create The Production Environment File

Copy the template:

```bash
cp .env.example .env
```

Open `.env` and fill in:

```text
DB_PASSWORD=
DB_ROOT_PASSWORD=
MACPROTECH_APP_KEY=
```

Generate the application key with:

```bash
openssl rand -base64 32
```

Paste it into `.env` with the `base64:` prefix:

```text
MACPROTECH_APP_KEY=base64:PASTE_GENERATED_VALUE_HERE
```

Use strong unique values for `DB_PASSWORD` and `DB_ROOT_PASSWORD`. Keep `.env` private; it is ignored by Git and Docker builds.

### 2. Start The Production Stack

Run:

```bash
docker compose -f docker-compose.prod.yml up -d --build
```

Open:

```text
https://localhost/
```

The production setup includes a Caddy HTTPS reverse proxy. It listens on ports `80` and `443` and forwards traffic to the PHP/Apache app container.

The default production binding is `127.0.0.1`, which means only the shop computer itself can open the HTTPS site. If trusted computers on the shop LAN must access it, change this in `.env`:

```text
HTTPS_HOSTNAME=macprotech.local
HTTPS_BIND_ADDRESS=0.0.0.0
```

Then map `macprotech.local` to the shop computer's LAN IP address using your router DNS or each client computer's hosts file.

Only expose HTTPS on a trusted local network, and make sure the computer firewall allows the chosen `HTTPS_PORT`, usually `443`.

The direct HTTP app port remains bound to `APP_BIND_ADDRESS` and `APP_HTTP_PORT` for local fallback access. Use HTTPS for normal shop use.

### 3. Trust The Local HTTPS Certificate

The production setup uses Caddy's internal certificate authority so the shop can run HTTPS without buying a domain. The connection is encrypted, but browsers will warn until the Caddy root certificate is trusted on each computer that opens the system.

After the production stack is running, export the root certificate:

```bash
docker cp macprotech-proxy:/data/caddy/pki/authorities/local/root.crt ./macprotech-caddy-root.crt
```

On Linux, trust it on the shop computer with:

```bash
sudo cp macprotech-caddy-root.crt /usr/local/share/ca-certificates/macprotech-caddy-root.crt
sudo update-ca-certificates
```

On Windows, import `macprotech-caddy-root.crt` into:

```text
Trusted Root Certification Authorities
```

Restart the browser after trusting the certificate.

### 4. Start Automatically After Reboot

All production services use Docker restart policies, so Docker can restart them after reboot. First make sure Docker starts on boot:

```bash
sudo systemctl enable --now docker
```

Start the production stack once:

```bash
docker compose -f docker-compose.prod.yml up -d --build
```

For a stricter Linux boot setup, install the included systemd service:

```bash
sudo cp docker/systemd/macprotech.service.example /etc/systemd/system/macprotech.service
sudo systemctl daemon-reload
sudo systemctl enable --now macprotech
sudo systemctl status macprotech
```

If the project is not installed at `/opt/lampp/htdocs/MACPROTECH`, edit `WorkingDirectory` in `/etc/systemd/system/macprotech.service` before enabling it.

On Windows with Docker Desktop, enable Docker Desktop's "Start Docker Desktop when you sign in" setting, then keep the production stack created with `docker compose -f docker-compose.prod.yml up -d --build`.

### 5. phpMyAdmin When Needed

phpMyAdmin does not start by default in the production setup. Start it temporarily with:

```bash
docker compose -f docker-compose.prod.yml --profile tools up -d phpmyadmin
```

Open:

```text
http://localhost:8081/
```

Stop phpMyAdmin after use:

```bash
docker compose -f docker-compose.prod.yml stop phpmyadmin
```

### 6. Database Backups

Create a backup:

```bash
docker/backup/backup-db.sh
```

Backups are written to:

```text
backups/
```

Copy backups to an external drive or another trusted computer regularly. A database backup stored only on the same computer does not protect against drive failure.

### 7. Production Maintenance Commands

Check containers:

```bash
docker compose -f docker-compose.prod.yml ps
```

Watch logs:

```bash
docker compose -f docker-compose.prod.yml logs -f app
docker compose -f docker-compose.prod.yml logs -f db
```

Stop the system:

```bash
docker compose -f docker-compose.prod.yml down
```

Rebuild after updating code:

```bash
docker compose -f docker-compose.prod.yml up -d --build
```

Do not run `docker compose -f docker-compose.prod.yml down -v` on the shop computer unless you intentionally want to delete the production database and uploaded image volume.

### 8. Shop Install Checklist

Before relying on the system:

- Change the initial administrator password.
- Create staff/technician accounts with the correct roles.
- Configure business details in Settings.
- Configure SMTP/httpSMS only if receipt email or SMS delivery is needed.
- Create a test customer, work order, inventory item, payment, refund, report export, and backup.
- Confirm HTTPS opens without browser warnings on every computer that will use the system.
- Confirm the computer starts Docker after reboot.
- Confirm a recent backup can be found outside the computer.

---

## Installation

These steps assume XAMPP, Apache, PHP, and MySQL/MariaDB. Adjust paths if you use a different web server.

### 1. Copy The Project

Place the project inside the web server document root.

Linux/XAMPP example:

```bash
/opt/lampp/htdocs/MACPROTECH/
```

Windows/XAMPP example:

```text
C:\xampp\htdocs\MACPROTECH\
```

### 2. Start Apache And MySQL

Start both Apache and MySQL from the XAMPP control panel.

Linux/XAMPP terminal example:

```bash
sudo /opt/lampp/lampp start
```

### 3. Create The Database

Open phpMyAdmin or MySQL and create a database named:

```text
crms_macprotech
```

### 4. Import The Database Dump

Import the schema dump:

```text
crms_macprotech.sql
```

Using phpMyAdmin:

1. Select the `crms_macprotech` database.
2. Open the Import tab.
3. Choose `crms_macprotech.sql`.
4. Click Import.

Using MySQL CLI:

```bash
mysql -u root -p crms_macprotech < crms_macprotech.sql
```

For an existing database that was installed before the security update, back up the database first, then run:

```text
database/security_integrity_migration.sql
```

This migration adds login-attempt tracking, inventory cost snapshots, unique business-code constraints, and core foreign keys. If a unique constraint is skipped, check for duplicate usernames, emails, work order codes, payment codes, or product codes before rerunning the migration. If foreign keys fail, check for orphan records before rerunning the migration.

### 5. Configure The Database Connection

By default, no change is needed for standard XAMPP. If your local database uses different credentials, open:

```text
src/db/connection.php
```

Update the fallback values or provide matching environment variables:

```text
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=
DB_NAME=crms_macprotech
```

Default XAMPP usually uses:

- Host: `localhost`
- Username: `root`
- Password: empty
- Database: `crms_macprotech`

### 6. Application Key

No command-line key setup is required for normal installation.

The system automatically creates:

```text
src/handlers/config.local.php
```

This file stores the local application key used to encrypt Settings secrets such as the SMTP password and httpSMS API key. It is created the first time an administrator saves one of those secrets in Settings.

Important:

- Do not delete `src/handlers/config.local.php` after email or SMS secrets are saved.
- Include `src/handlers/config.local.php` when backing up or moving the system.
- If the file is deleted or replaced, re-enter the SMTP password and httpSMS API key in Settings.

Advanced production installs may still provide `MACPROTECH_APP_KEY` through the web server environment. If both are present, the environment value is used.

### 7. Check Writable Folders

Inventory item images are stored in:

```text
src/uploads/
```

Make sure Apache/PHP can write to that folder.

Linux/XAMPP example:

```bash
sudo chown -R daemon:daemon /opt/lampp/htdocs/MACPROTECH/src/uploads
sudo chmod 755 /opt/lampp/htdocs/MACPROTECH/src/uploads
```

The folder includes `.htaccess` protection to block uploaded scripts from executing.

Apache/PHP should also be able to create this file when Settings secrets are saved:

```text
src/handlers/config.local.php
```

### 8. Open The System

Open:

```text
http://localhost/MACPROTECH/
```

Administrator login page:

```text
http://localhost/MACPROTECH/admin-login.php
```

Staff/technician login page:

```text
http://localhost/MACPROTECH/login.php
```

The SQL dump includes an initial administrator record named `Admin`. Use the project-provided password or reset it in the database if needed.

### 9. Configure Shop Settings

After logging in as administrator, open Settings from the header profile menu.

Configure:

- Business name, contact details, address, receipt footer, and service policy.
- SMTP settings if receipt email is enabled.
- httpSMS API key and sender number if SMS delivery is enabled.
- Automatic release behavior after full payment.

### 10. Verify The Main Workflow

Before using the system in production, test these flows:

- Log in and log out.
- Create a customer.
- Create a work order.
- Add inventory and attach purchased parts.
- Update repair status.
- Record payment and print/send receipt.
- Create a refund.
- Export a report as administrator.
- Download a database backup as administrator.

### Troubleshooting

If a page is blank, check the Apache/PHP error log first.

Common issues:

- Apache or MySQL is not running.
- Database name or credentials are incorrect. XAMPP uses the defaults in `src/db/connection.php`; Docker uses the `DB_HOST`, `DB_USER`, `DB_PASSWORD`, and `DB_NAME` environment variables from `docker-compose.yml`.
- In Docker, check `docker compose logs -f app` and `docker compose logs -f db` when the page says the database connection failed.
- In Docker, `crms_macprotech.sql` imports only on the first database volume creation. Use `docker compose down -v` only when you intentionally want a fresh local database.
- `src/handlers/config.local.php` is missing after encrypted settings were saved.
- Apache/PHP cannot write to `src/handlers/` when saving SMTP or SMS secrets for the first time.
- PHP extensions such as `mysqli`, `openssl`, `curl`, or `fileinfo` are disabled.
- `src/uploads/` is not writable by Apache/PHP.

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
- Backup

Settings is available from the header profile dropdown.

---

## Project Structure

```text
.
+-- *.php                         Root page controllers
+-- auth_check.php                Session guard
+-- backup-restore.php            Administrator database backup/restore page
+-- crms_macprotech.sql           Database schema dump
+-- database/
|   +-- security_integrity_migration.sql
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

## Security Implementation

The current security layer includes:

- `src/db/connection.php` starts sessions with strict mode, cookie-only sessions, `HttpOnly`, `SameSite=Lax`, and HTTPS-only secure cookies when applicable.
- `src/handlers/security_headers.php` sends `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, and a Content Security Policy from the common bootstrap.
- The current CSP uses `default-src 'self'`, `frame-ancestors 'self'`, `object-src 'none'`, self/data images, and self-only connections/fonts. It still allows inline scripts/styles for compatibility with existing inline handlers and can be tightened further after those handlers are moved to external JavaScript.
- `src/handlers/security_helpers.php` centralizes CSRF generation/validation, audited CSRF checks, role checks, JSON/fragment authentication guards, password policy validation, and login attempt tracking.
- Login and mutation forms include CSRF tokens through `csrf_input()`. Sensitive handlers use audited CSRF verification so failed checks are written to `activity_logs`.
- Staff/technician login and administrator login are separated. Each login endpoint rejects accounts that belong to the other portal.
- Failed login attempts are stored in `login_attempts`; repeated failures create a temporary lockout and successful login clears previous failures for that username/IP pair.
- Idle authenticated sessions expire after 30 minutes by default. Expired sessions are destroyed and logged before the user is redirected or a JSON 401 response is returned.
- New and changed passwords must be at least eight characters and include uppercase, lowercase, and numeric characters.
- `auth_check.php` verifies that the active session still maps to an existing user record before allowing access.
- Role checks protect administrator-only features such as reports, exports, user management, customer deletion, and settings updates.
- Technician work order status updates are checked against `work_order.technician_id`; the update statement itself also requires the assigned technician to match the current user.
- Failed authorization attempts are logged with the attempted action context and current role when available.
- Password reset requests use generic public responses to avoid account enumeration; reset OTPs are stored as keyed hashes, expire after 10 minutes, lock after repeated invalid verification attempts, must be verified before password creation, and are invalidated after use.
- AJAX endpoints use authenticated JSON guards and return 401/403 responses instead of rendering protected content to unauthorized users.
- JSON/AJAX handlers disable `display_errors` and `display_startup_errors` to avoid leaking PHP runtime details in API responses.
- Critical database writes use prepared statements, helper binding, and transactions where multi-table updates must stay consistent.
- Runtime schema checks and migrations enforce unique usernames, emails, work order codes, payment codes, and product codes when existing data is clean.
- Dynamic search/filter pages use `src/handlers/sql_filter_helpers.php` to build strict `WHERE` clauses with checked placeholder counts, validated identifiers, and explicit parameter types.
- Dynamic SQL fragments that cannot be parameter-bound, such as payment/report status expressions, date filter columns, table sources, and priority sort expressions, are selected from allow-lists instead of request input.
- Receipt email/SMS sending, receipt send failures, report page views, report exports, and payment-detail reads are logged for audit visibility.
- Backup/restore is administrator-only, CSRF-protected, audited, limited to `.sql` uploads up to 50MB, and restore execution is limited to expected SQL dump statement types.
- Settings secrets are encrypted with AES-256-GCM before being stored in `app_settings`.
- Normal installs use an auto-generated local key in `src/handlers/config.local.php`; advanced installs may provide `MACPROTECH_APP_KEY` through the web server environment.
- Inventory uploads are limited to JPG, PNG, and WEBP files, checked by extension and MIME type, capped at 10MB, saved with random filenames, and protected by `src/uploads/.htaccess`.
- The database dump and `database/security_integrity_migration.sql` include security/integrity updates for `login_attempts`, password reset tables, stock-out cost snapshots, unique business codes, and foreign-key relationships.

Maintenance note: when adding new search, filter, report, or export behavior, prefer `sql_filter_helpers.php` and allow-listed fragments over ad hoc SQL string assembly.

---

## Version

Version: 1.5
Last Updated: 2026-06-15
