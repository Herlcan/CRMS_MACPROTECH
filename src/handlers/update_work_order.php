<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

include '../db/connection.php';
include '../../auth_check.php';
require_once __DIR__ . '/notification_helpers.php';
require_once __DIR__ . '/payment_schema.php';
require_once __DIR__ . '/work_order_assignment_schema.php';
require_once __DIR__ . '/work_order_schema.php';
require_once __DIR__ . '/ordered_part_schema.php';
require_once __DIR__ . '/inventory_transaction_schema.php';
require_once __DIR__ . '/activity_log_helper.php';
require_once __DIR__ . '/communication_helpers.php';
require_once __DIR__ . '/security_helpers.php';

$update_work_order_message = '';
$update_work_order_error = '';

if (!function_exists('redirectWorkOrderWithDialog')) {
    function redirectWorkOrderWithDialog($client_id, $type, $title, $message) {
        $_SESSION['dialog_flash'] = [
            'type' => $type,
            'title' => $title,
            'message' => $message
        ];
        header("Location: ../../client-view.php?client_id=" . urlencode((string) $client_id));
        exit();
    }
}

function resolveWorkOrderUnitType($conn, $unit_type, $other_unit_type) {
    if ($unit_type !== '__other__') {
        return $unit_type;
    }

    $unit_type = trim($other_unit_type);

    if ($unit_type === '') {
        throw new Exception('Please enter a unit type.');
    }

    if (strlen($unit_type) > 50) {
        throw new Exception('Unit type must be 50 characters or fewer.');
    }

    $check_query = mysqli_prepare($conn, "SELECT id FROM unit_type WHERE LOWER(unit_type) = LOWER(?) LIMIT 1");
    if (!$check_query) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($check_query, "s", $unit_type);
    if (!mysqli_stmt_execute($check_query)) {
        throw new Exception('Failed to check unit type: ' . mysqli_stmt_error($check_query));
    }

    $check_result = mysqli_stmt_get_result($check_query);
    $existing_unit_type = mysqli_fetch_assoc($check_result);
    mysqli_stmt_close($check_query);

    if (!$existing_unit_type) {
        $insert_query = mysqli_prepare($conn, "INSERT INTO unit_type (unit_type) VALUES (?)");
        if (!$insert_query) {
            throw new Exception('Database error: ' . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($insert_query, "s", $unit_type);
        if (!mysqli_stmt_execute($insert_query)) {
            throw new Exception('Failed to add unit type: ' . mysqli_stmt_error($insert_query));
        }
        log_activity($conn, "Added unit type {$unit_type}");
        mysqli_stmt_close($insert_query);
    }

    return $unit_type;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_work_order'])) {
    if (!verify_csrf_token_or_audit($conn, 'update work order', isset($_POST['work_order_id']) ? (int) $_POST['work_order_id'] : null)) {
        redirectWorkOrderWithDialog((int) ($_POST['client_id'] ?? 0), 'error', 'Security Check Failed', 'Your form session expired. Please try again.');
    }

    require_role(['Administrator', 'Cashier/Front Desk', 'Cashier/Front Desk Staff'], function () {
        redirectWorkOrderWithDialog((int) ($_POST['client_id'] ?? 0), 'error', 'Permission Required', 'Only authorized staff can update work orders.');
    }, $conn, 'update work order', isset($_POST['work_order_id']) ? (int) $_POST['work_order_id'] : null);

    $work_order_id = intval($_POST['work_order_id']);
    $client_id = (int) ($_POST['client_id'] ?? 0);
    $unit_type = trim($_POST['unit_type']);
    $other_unit_type = isset($_POST['other_unit_type']) ? trim($_POST['other_unit_type']) : '';
    $brand = trim($_POST['brand']);
    $model = trim($_POST['model']);
    $specs_acce = trim($_POST['specs_acce']);
    $request_date = trim($_POST['request_date']);
    $prob_find = trim($_POST['prob_find']);
    $diagnostic_fee = trim($_POST['diagnostic_fee']);
    $work_order_cost = trim($_POST['work_order_cost']);
    $priority = normalize_work_order_priority($_POST['priority'] ?? 'In Que');
    $warranty_days = normalize_work_order_warranty_days($_POST['warranty_days'] ?? 0);
    $status = trim($_POST['status']);
    $technician_id = !empty($_POST['technician_id']) ? intval($_POST['technician_id']) : null;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    if ($work_order_id <= 0) {
        $update_work_order_error = 'Invalid work order ID';
    } else {
        ensure_notifications_table($conn);
        ensure_payment_detail_columns($conn);
        ensure_work_order_assignments_table($conn);
        ensure_work_order_priority_column($conn);
        ensure_work_order_warranty_columns($conn);
        ensure_ordered_parts_table($conn);
        ensure_items_inventory_columns($conn);
        ensure_inventory_transaction_table($conn);
        backfill_inventory_transactions_from_items($conn);

        // Start transaction
        mysqli_begin_transaction($conn);

        try {
            $existingTechnicianId = null;
            $workOrderCode = '';
            $existingStatus = '';
            $customerName = '';
            $customerContact = '';
            $existingQuery = mysqli_prepare($conn, "
                SELECT
                    w.technician_id,
                    w.code,
                    w.status,
                    CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
                    c.contact_num
                FROM work_order w
                LEFT JOIN client c ON w.client_id = c.id
                WHERE w.id = ?
                LIMIT 1
                FOR UPDATE
            ");
            if (!$existingQuery) {
                throw new Exception('Database error: ' . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($existingQuery, "i", $work_order_id);
            mysqli_stmt_execute($existingQuery);
            $existingResult = mysqli_stmt_get_result($existingQuery);
            $existingRow = mysqli_fetch_assoc($existingResult);
            mysqli_stmt_close($existingQuery);

            if (!$existingRow) {
                throw new Exception('Work order not found.');
            }

            $existingTechnicianId = !empty($existingRow['technician_id']) ? (int) $existingRow['technician_id'] : null;
            $workOrderCode = $existingRow['code'] ?: ('WO-' . sprintf('%04d', $work_order_id));
            $existingStatus = (string) ($existingRow['status'] ?? '');
            $customerName = (string) ($existingRow['customer_name'] ?? 'Customer');
            $customerContact = (string) ($existingRow['contact_num'] ?? '');

            $unit_type = resolveWorkOrderUnitType($conn, $unit_type, $other_unit_type);

            // Update work order
            $update_query = mysqli_prepare($conn,
                "UPDATE work_order SET
                unit_type = ?, brand = ?, model = ?, specs_acce = ?, 
                request_date = ?, prob_find = ?, diagnostic_fee = ?, 
                work_order_cost = ?, priority = ?, warranty_days = ?, status = ?, technician_id = ?, notes = ?
                WHERE id = ?"
            );

            if (!$update_query) {
                throw new Exception('Database error: ' . mysqli_error($conn));
            }

            mysqli_stmt_bind_param(
                $update_query,
                "sssssssssisisi",
                $unit_type,
                $brand,
                $model,
                $specs_acce,
                $request_date,
                $prob_find,
                $diagnostic_fee,
                $work_order_cost,
                $priority,
                $warranty_days,
                $status,
                $technician_id,
                $notes,
                $work_order_id
            );

            if (!mysqli_stmt_execute($update_query)) {
                throw new Exception('Failed to update work order: ' . mysqli_stmt_error($update_query));
            }
            mysqli_stmt_close($update_query);

            $existing_purchased = mysqli_prepare(
                $conn,
                "SELECT product_id, quantity FROM purchased_item WHERE work_order_id = ?"
            );
            if (!$existing_purchased) {
                throw new Exception('Database error: ' . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($existing_purchased, "i", $work_order_id);
            if (!mysqli_stmt_execute($existing_purchased)) {
                throw new Exception('Failed to fetch existing purchased items: ' . mysqli_stmt_error($existing_purchased));
            }
            $existing_purchased_result = mysqli_stmt_get_result($existing_purchased);

            while ($existing_part = mysqli_fetch_assoc($existing_purchased_result)) {
                $product_id = (int) $existing_part['product_id'];
                $quantity = (int) $existing_part['quantity'];

                if ($product_id <= 0 || $quantity <= 0) {
                    continue;
                }

                $restore_query = mysqli_prepare($conn, "UPDATE items SET quantity = quantity + ? WHERE id = ?");
                if (!$restore_query) {
                    throw new Exception('Database error: ' . mysqli_error($conn));
                }
                mysqli_stmt_bind_param($restore_query, "ii", $quantity, $product_id);
                if (!mysqli_stmt_execute($restore_query)) {
                    throw new Exception('Failed to restore existing purchased item stock: ' . mysqli_stmt_error($restore_query));
                }
                mysqli_stmt_close($restore_query);

            }
            mysqli_stmt_close($existing_purchased);

            // Delete existing purchased items
            $delete_purchased = mysqli_prepare($conn, "DELETE FROM purchased_item WHERE work_order_id = ?");
            if (!$delete_purchased) {
                throw new Exception('Database error: ' . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($delete_purchased, "i", $work_order_id);
            if (!mysqli_stmt_execute($delete_purchased)) {
                throw new Exception('Failed to delete existing purchased items');
            }
            mysqli_stmt_close($delete_purchased);
            sync_stock_out_transactions_for_work_order($conn, $work_order_id);

            // Delete existing client provided parts
            $delete_client = mysqli_prepare($conn, "DELETE FROM customer_provided_component WHERE work_order_id = ?");
            if (!$delete_client) {
                throw new Exception('Database error: ' . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($delete_client, "i", $work_order_id);
            if (!mysqli_stmt_execute($delete_client)) {
                throw new Exception('Failed to delete existing client provided parts');
            }
            mysqli_stmt_close($delete_client);

            // Delete existing ordered parts
            $delete_ordered = mysqli_prepare($conn, "DELETE FROM ordered_parts WHERE work_order_id = ?");
            if (!$delete_ordered) {
                throw new Exception('Database error: ' . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($delete_ordered, "i", $work_order_id);
            if (!mysqli_stmt_execute($delete_ordered)) {
                throw new Exception('Failed to delete existing ordered parts');
            }
            mysqli_stmt_close($delete_ordered);

            // Handle Purchased Parts (may be empty)
            if (isset($_POST['purchased_part_item_id']) && is_array($_POST['purchased_part_item_id'])) {
                $item_ids = $_POST['purchased_part_item_id'];
                $quantities = isset($_POST['purchased_part_quantity']) ? $_POST['purchased_part_quantity'] : [];
                $current_date = date('Y-m-d');

                for ($i = 0; $i < count($item_ids); $i++) {
                    $product_id = trim($item_ids[$i]);
                    $quantity = isset($quantities[$i]) ? intval($quantities[$i]) : 1;

                    if (!empty($product_id) && $quantity > 0) {
                        $product_id = (int) $product_id;

                        $price_query = mysqli_prepare($conn, "SELECT average_price FROM items WHERE id = ?");
                        if (!$price_query) {
                            throw new Exception('Database error: ' . mysqli_error($conn));
                        }
                        mysqli_stmt_bind_param($price_query, "i", $product_id);
                        mysqli_stmt_execute($price_query);
                        $price_result = mysqli_stmt_get_result($price_query);
                        $price_row = mysqli_fetch_assoc($price_result);
                        $item_price = $price_row ? (float) $price_row['average_price'] : 0.0;
                        mysqli_stmt_close($price_query);

                        $purchased_query = mysqli_prepare(
                            $conn,
                            "INSERT INTO purchased_item (work_order_id, product_id, quantity, unit_price, date) VALUES (?, ?, ?, ?, ?)"
                        );

                        if (!$purchased_query) {
                            throw new Exception('Database error: ' . mysqli_error($conn));
                        }

                        mysqli_stmt_bind_param($purchased_query, "iiids", $work_order_id, $product_id, $quantity, $item_price, $current_date);
                        if (!mysqli_stmt_execute($purchased_query)) {
                            throw new Exception('Failed to add purchased item');
                        }
                        mysqli_stmt_close($purchased_query);

                        $deduct_query = mysqli_prepare($conn, "UPDATE items SET quantity = quantity - ? WHERE id = ?");
                        if (!$deduct_query) {
                            throw new Exception('Database error: ' . mysqli_error($conn));
                        }
                        mysqli_stmt_bind_param($deduct_query, "ii", $quantity, $product_id);
                        if (!mysqli_stmt_execute($deduct_query)) {
                            throw new Exception('Failed to deduct purchased item stock: ' . mysqli_stmt_error($deduct_query));
                        }
                        mysqli_stmt_close($deduct_query);

                        notify_low_stock_for_item($conn, $product_id);
                    }
                }
                sync_stock_out_transactions_for_work_order($conn, $work_order_id);
            }

            save_ordered_parts_from_post($conn, $work_order_id);

            // Handle Client Provided Parts (may be empty)
            if (isset($_POST['client_part_product_name']) && is_array($_POST['client_part_product_name'])) {
                $product_names = $_POST['client_part_product_name'];
                $descriptions = isset($_POST['client_part_description']) ? $_POST['client_part_description'] : [];
                $quantities = isset($_POST['client_part_quantity']) ? $_POST['client_part_quantity'] : [];

                for ($i = 0; $i < count($product_names); $i++) {
                    $product_name = trim($product_names[$i]);
                    $description = isset($descriptions[$i]) ? trim($descriptions[$i]) : '';
                    $quantity = isset($quantities[$i]) ? intval($quantities[$i]) : 1;

                    if (!empty($product_name) && $quantity > 0) {
                        $client_part_query = mysqli_prepare(
                            $conn,
                            "INSERT INTO customer_provided_component (work_order_id, product_name, description, quantity) VALUES (?, ?, ?, ?)"
                        );

                        if (!$client_part_query) {
                            throw new Exception('Database error: ' . mysqli_error($conn));
                        }

                        mysqli_stmt_bind_param($client_part_query, "issi", $work_order_id, $product_name, $description, $quantity);
                        if (!mysqli_stmt_execute($client_part_query)) {
                            throw new Exception('Failed to add client provided part');
                        }
                        mysqli_stmt_close($client_part_query);
                    }
                }
            }

            if ($technician_id && $technician_id !== $existingTechnicianId) {
                record_work_order_assignment(
                    $conn,
                    $work_order_id,
                    $existingTechnicianId,
                    $technician_id,
                    (int) $_SESSION['user_id'],
                    'Changed from work order edit'
                );
                notify_work_order_assigned($conn, $technician_id, $workOrderCode, $work_order_id);
            } elseif (!$technician_id && $existingTechnicianId) {
                record_work_order_assignment(
                    $conn,
                    $work_order_id,
                    $existingTechnicianId,
                    null,
                    (int) $_SESSION['user_id'],
                    'Technician removed from work order edit'
                );
            } elseif ($technician_id) {
                notify_work_order_updated($conn, $technician_id, $workOrderCode, 'Work order details were updated.');
            }

            refresh_payment_summaries($conn, $work_order_id);
            if ($status === 'Released') {
                activate_work_order_warranty($conn, $work_order_id);
            } else {
                clear_work_order_warranty_dates($conn, $work_order_id);
            }
            log_activity($conn, "Updated work order {$workOrderCode}", $work_order_id);

            // Commit transaction
            mysqli_commit($conn);

            if (communication_display_status($status) !== communication_display_status($existingStatus) && should_send_work_order_status_sms($status)) {
                try {
                    send_work_order_status_sms($conn, $customerContact, $customerName, $workOrderCode, $status);
                } catch (Throwable $smsError) {
                    error_log('Work order edit status SMS failed: ' . $smsError->getMessage());
                }
            }

            redirectWorkOrderWithDialog($client_id, 'success', 'Work Order Updated', 'Work order updated successfully.');

        } catch (Exception $e) {
            // Rollback transaction
            mysqli_rollback($conn);
            $update_work_order_error = $e->getMessage();
            redirectWorkOrderWithDialog($client_id, 'error', 'Work Order Not Updated', $update_work_order_error);
        }
    }
}
?>
