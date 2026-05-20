<?php
	error_reporting(E_ALL);
	ini_set('display_errors', '1');
	ini_set('display_startup_errors', '1'); 

include '../db/connection.php';
include '../../auth_check.php';

$add_work_order_message = '';
$add_work_order_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_work_order'])) {

    $client_id = trim($_POST['client_id']);
    $unit_type = trim($_POST['unit_type']);
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

    // Start transaction
    mysqli_begin_transaction($conn);

    try {
        $add_query = mysqli_prepare($conn,
            "INSERT INTO work_order
            (client_id, request_date, unit_type, brand, model, specs_acce, prob_find, diagnostic_fee, work_order_cost, status, technician_id, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        if (!$add_query) {
            throw new Exception('Database error: ' . mysqli_error($conn));
        }

        mysqli_stmt_bind_param(
            $add_query,
            "isssssssssis",
            $client_id,
            $request_date,
            $unit_type,
            $brand,
            $model,
            $specs_acce,
            $prob_find,
            $diagnostic_fee,
            $work_order_cost,
            $status,
            $technician_id,
            $notes
        );

        if (!mysqli_stmt_execute($add_query)) {
            throw new Exception('Failed to add work order: ' . mysqli_stmt_error($add_query));
        }

        $id = mysqli_insert_id($conn);
        $code = "WO-" . sprintf("%04d", $id);

        $update_query = mysqli_prepare(
            $conn,
            "UPDATE work_order SET code = ? WHERE id = ?"
        );

        if (!$update_query) {
            throw new Exception('Database error: ' . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($update_query, "si", $code, $id);
        if (!mysqli_stmt_execute($update_query)) {
            throw new Exception('Failed to generate work order code');
        }
        
        mysqli_stmt_close($add_query);
        mysqli_stmt_close($update_query);

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

                    mysqli_stmt_bind_param($purchased_query, "iiis", $id, $product_id, $quantity, $current_date);
                    if (!mysqli_stmt_execute($purchased_query)) {
                        throw new Exception('Failed to add purchased item');
                    } else {
                        $deduct_query = mysqli_prepare (
                            $conn, "UPDATE items SET quantity = quantity - ? WHERE id = ?"
                        );

                        mysqli_stmt_bind_param($deduct_query, "ii", $quantity, $product_id);

                        if(!mysqli_stmt_execute($deduct_query)) {
                            throw new Exception('Failed to add purchased item');
                        }
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

                    mysqli_stmt_bind_param($client_part_query, "issi", $id, $product_name, $description, $quantity);
                    if (!mysqli_stmt_execute($client_part_query)) {
                        throw new Exception('Failed to add client provided part');
                    }
                    mysqli_stmt_close($client_part_query);
                }
            }
        }

        // Commit transaction
        mysqli_commit($conn);

        header("Location: ../../client-view.php?client_id=$client_id");
        exit();

    } catch (Exception $e) {
        // Rollback transaction
        mysqli_rollback($conn);
        $add_work_order_error = $e->getMessage();
    }
}
?>
