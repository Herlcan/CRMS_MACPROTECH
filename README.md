# MACPROTECH

MACPROTECH is a PHP + MySQL web application for managing technical service operations: **clients**, **work orders**, **inventory items**, **purchased/client-provided parts**, **work-order status workflow**, and **payments**.

This README reflects how the system currently works based on the code in this repo.

---

## How the system works (end-to-end)

### 1) Authentication (login + session protection)
- **`login.php`** authenticates a user from the `users` table and stores:
  - `$_SESSION['user_id']`
  - `$_SESSION['username']`
  - `$_SESSION['role']`
- After login, users are redirected to **`index.php`**.
- **`auth_check.php`** is included by handlers/pages to protect access:
  - Verifies `$_SESSION['user_id']` exists in `users`.
  - If the user was deleted, it clears session cookies and redirects to `login.php`.

> Roles used in code: `Administrator`, `Technician`.

---

### 2) Dashboard overview + charts
- **`index.php`** loads aggregated counts directly from the database:
  - total clients (`client`)
  - total work orders (`work_order`)
  - pending/completed work orders (filtered by `work_order.status`)
  - total technicians (`users` where `role='Technician'`)
  - low stock items (`items.quantity < 10`)
  - work order status distribution (`SELECT status, COUNT(*) ... GROUP BY status`)
  - recent work orders (last 5)
  - last-6-month trends for work orders and clients
- **`src/scripts/dashboard-charts.js`** + **`src/scripts/chart.js`** render charts using values embedded by `index.php`.

---

### 3) Work orders list + viewing details
- **`work-order.php`** displays work orders in a paginated, searchable table.
  - Search uses `GET search`, filter uses `GET filter`.
  - Technicians are restricted server-side:
    - If `$_SESSION['role'] == 'Technician'`, the query includes `technician_id = $_SESSION['user_id']`.

- Each row renders the HTML template **`src/partials/workorder_row_template.php`**:
  - Shows status badge.
  - If the user is allowed to edit status, a `<select>` is shown.
  - A “View” button opens a modal/drawer.

#### Fetching the “View” modal content
- Clicking “View” calls the JS function which fetches:
  - **`src/handlers/get_work_order.php?id=WORK_ORDER_ID`**

**`src/handlers/get_work_order.php`** returns JSON:
- The selected work order (`work_order`), including technician name (join to `users`).
- Purchased parts from `purchased_item` joined to `items`.
- Client-provided parts from `customer_provided_component`.
- Payments from `payments` (only if the table exists in the DB).

The modal computes a cost summary on the client side using:
- `wo.diagnostic_fee`
- `wo.work_order_cost`
- purchased parts (`quantity * product_price`)

---

### 4) Updating work order status (workflow + audit + email)
- Status updates are performed via AJAX to:
  - **`src/handlers/update_status.php`**

#### Role-based permissions (what each role can do)
Roles in the codebase: `Administrator` and `Technician` (from `users.role`).

**Administrator** (full operational access in the UI + handlers)
- Can update **work order status** via `src/handlers/update_status.php`.
- Can view and edit work order details (where the UI shows editable controls).
- Can update **payment statuses** via `src/handlers/update_payment_status.php` (handler validates via `auth_check.php`; the form is shown in the payment UI).

**Technician** (restricted access to their assigned work)
- Can update **work order status** via `src/handlers/update_status.php`.
- Can only see/update work orders where:
  - `work-order.php` adds `AND technician_id = $_SESSION['user_id']`.
- In `src/partials/workorder_row_template.php`, status edit controls are rendered only when `$canEdit` is true (role-based).

> If a user is not in `Administrator`/`Technician`, status updates are rejected by `update_status.php`.

**`src/handlers/update_status.php`**:
- Requires POST + `update_status=1`
- Checks authorization via role (`Administrator`, `Technician`).
- Validates requested status against:
  - `Pending`, `In Progress`, `Repaired`, `Completed`, `Cancelled`
- Updates `work_order.status` and sets/clears `completion_date`:
  - If `Completed`: sets `completion_date = NOW()`
  - Otherwise: sets `completion_date = NULL`
- Writes an audit record into `activity_logs`:
  - `action = "Changed status from {previous} to {status}"`
- Sends an email when status changes to `Completed` using PHPMailer:
  - `sendCompletionEmail($clientEmail, $clientName, $workCode)`

---

### 5) Creating work orders (work order + parts + payment)
- Work order creation happens in **`src/handlers/add_work_order.php`**.

**`add_work_order.php`** implements a transaction that:
1. Inserts a new row into `work_order`.
2. Generates the work order code:
   - `code = "WO-" + 4-digit id padding`
   - then updates the inserted row.
3. Handles parts:
   - **Purchased parts**:
     - Inserts into `purchased_item (work_order_id, product_id, quantity, date)`
     - Deducts inventory quantity from `items.quantity`.
   - **Client provided parts**:
     - Inserts into `customer_provided_component (work_order_id, product_name, description, quantity)`
4. Creates a payment record in `payments`:
   - `payment_code = "PMT-" + sequence based on last payment id`
   - `total_amount = diagnostic_fee + work_order_cost + purchased_parts_total`
   - `status = 'Pending'`
   - `date = current date`

Finally it redirects back to:
- `client-view.php?client_id=...`

---

### 6) Editing a work order (details + replacing parts)
- Work order edit updates and replaces associated parts through:
  - **`src/handlers/update_work_order.php`**

Key behavior:
- Updates the main `work_order` fields (unit type, brand/model, specs, fees, status, technician, notes, etc.).
- Deletes existing parts for that work order:
  - `DELETE FROM purchased_item WHERE work_order_id = ?`
  - `DELETE FROM customer_provided_component WHERE work_order_id = ?`
- Inserts the newly provided parts (purchased + client-provided).

---

### 7) Payments management
- Payments list is rendered by **`payment.php`**.
- Updating payment status is handled by:
  - **`src/handlers/update_payment_status.php`**

**`update_payment_status.php`**:
- POST required + `update_payment_status` flag.
- Valid statuses: `Pending`, `Paid`, `Overdue`.
- Updates `payments.status` and `payments.date`.
- Uses session flash messages:
  - `$_SESSION['payment_success']` / `$_SESSION['payment_error']`
- Redirects back to `payment.php`.

---

### 8) Inventory items
- Items are created/edited via handlers:
  - **`src/handlers/add_item.php`**
  - **`src/handlers/edit_item.php`**

Item creation behavior (`add_item.php`):
- Inserts into `items` including image upload (stored in `src/uploads/`).
- Generates `items.product_code` after insert based on:
  - item category name prefix
  - brand name prefix
  - `PI-0000` based on inserted item id.

---

## Database schema reference
The repository includes a schema dump:
- **`crms_macprotech.sql`**

Tables used directly by the code paths above:
- `users`
- `client`
- `work_order`
- `items`
- `item_category`
- `purchased_item`
- `customer_provided_component`
- `payments`
- `activity_logs`

---

## Requirements
- PHP 7.0+
- MySQL/MariaDB 5.7+
- Apache/XAMPP (or compatible PHP server)
- A writable `src/uploads/` directory (for item images)

---

## Installation (typical)
1. Deploy the project to your document root (example):
   ```bash
   /opt/lampp/htdocs/MACPROTECH/
   ```
2. Update DB credentials in:
   - **`src/db/connection.php`**
3. Import schema:
   - **`crms_macprotech.sql`**
4. Open:
   - `http://localhost/MACPROTECH/`

---

## Security notes (as implemented)
- Central session guard in **`auth_check.php`**.
- Work order status updates are role-protected and validated in **`src/handlers/update_status.php`**.
- Inputs are partially sanitized via prepared statements; some UI-driven filtering/search uses manual SQL string building—treat those endpoints carefully if you extend the system.

---

## License
Proprietary software for MACPROTECH.

---

**Version:** 1.1
**Last Updated:** 2026-05-20

