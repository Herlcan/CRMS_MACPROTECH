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
    $work_order_cost = trim($_POST['work_order_cost']);
    $status = trim($_POST['status']);


    $add_query = mysqli_prepare($conn,
        "INSERT INTO work_order
        (client_id, request_date, unit_type, brand, model, specs_acce, prob_find, work_order_cost, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    mysqli_stmt_bind_param(
        $add_query,
        "issssssss",
        $client_id,
        $request_date,
        $unit_type,
        $brand,
        $model,
        $specs_acce,
        $prob_find,
        $work_order_cost,
        $status
    );

    if (mysqli_stmt_execute($add_query)) {

        $id = mysqli_insert_id($conn);
        $code = "WO-" . sprintf("%04d", $id);

        $update_query = mysqli_prepare(
            $conn,
            "UPDATE work_order SET code = ? WHERE id = ?"
        );

        mysqli_stmt_bind_param($update_query, "si", $code, $id);
        mysqli_stmt_execute($update_query);
	
		mysqli_stmt_close($add_query);
        mysqli_stmt_close($update_query);

        header("Location: ../../client-view.php?client_id=$client_id");
        exit();

    } else {
        $add_work_order_error = 'Failed to add work order. Please try again.';
    }
}
?>
