<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

include '../db/connection.php';
include '../../auth_check.php';

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
        mysqli_stmt_close($insert_query);
    }

    return $unit_type;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_work_order'])) {

    $work_order_id = intval($_POST['work_order_id']);
    $client_id = trim($_POST['client_id']);
    $unit_type = trim($_POST['unit_type']);
    $other_unit_type = isset($_POST['other_unit_type']) ? trim($_POST['other_unit_type']) : '';
    $brand = trim($_POST['brand']);
    $model = trim($_POST['model']);
    $specs_acce = trim($_POST['specs_acce']);
    $request_date = trim($_POST['request_date']);
    $prob_find = trim($_POST['prob_find']);
    $diagnostic_fee = trim($_POST['diagnostic_fee']);
    $work_order_cost = trim($_POST['work_order_cost']);
    $status = trim($_POST['status']);
    $technician_id = !empty($_POST['technician_id']) ? intval($_POST['technician_id']) : null;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    if ($work_order_id <= 0) {
        $update_work_order_error = 'Invalid work order ID';
    } else {
        // Start transaction
        mysqli_begin_transaction($conn);

        try {
            $unit_type = resolveWorkOrderUnitType($conn, $unit_type, $other_unit_type);

            // Update work order
            $update_query = mysqli_prepare($conn,
                "UPDATE work_order SET
                unit_type = ?, brand = ?, model = ?, specs_acce = ?, 
                request_date = ?, prob_find = ?, diagnostic_fee = ?, 
                work_order_cost = ?, status = ?, technician_id = ?, notes = ?
                WHERE id = ?"
            );

            if (!$update_query) {
                throw new Exception('Database error: ' . mysqli_error($conn));
            }

            mysqli_stmt_bind_param(
                $update_query,
                "sssssssssisi",
                $unit_type,
                $brand,
                $model,
                $specs_acce,
                $request_date,
                $prob_find,
                $diagnostic_fee,
                $work_order_cost,
                $status,
                $technician_id,
                $notes,
                $work_order_id
            );

            if (!mysqli_stmt_execute($update_query)) {
                throw new Exception('Failed to update work order: ' . mysqli_stmt_error($update_query));
            }
            mysqli_stmt_close($update_query);

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

            // Handle Purchased Parts (may be empty)
            if (isset($_POST['purchased_part_item_id']) && is_array($_POST['purchased_part_item_id'])) {
                $item_ids = $_POST['purchased_part_item_id'];
                $quantities = isset($_POST['purchased_part_quantity']) ? $_POST['purchased_part_quantity'] : [];
                $current_date = date('Y-m-d');

                for ($i = 0; $i < count($item_ids); $i++) {
                    $product_id = trim($item_ids[$i]);
                    $quantity = isset($quantities[$i]) ? intval($quantities[$i]) : 1;

                    if (!empty($product_id) && $quantity > 0) {
                        $purchased_query = mysqli_prepare(
                            $conn,
                            "INSERT INTO purchased_item (work_order_id, product_id, quantity, date) VALUES (?, ?, ?, ?)"
                        );

                        if (!$purchased_query) {
                            throw new Exception('Database error: ' . mysqli_error($conn));
                        }

                        mysqli_stmt_bind_param($purchased_query, "iiis", $work_order_id, $product_id, $quantity, $current_date);
                        if (!mysqli_stmt_execute($purchased_query)) {
                            throw new Exception('Failed to add purchased item');
                        }
                        mysqli_stmt_close($purchased_query);
                    }
                }
            }

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

            // Commit transaction
            mysqli_commit($conn);

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
