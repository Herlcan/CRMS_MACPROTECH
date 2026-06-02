<?php

include '../db/connection.php';
include '../../auth_check.php';
require_once __DIR__ . '/category_schema.php';

$add_category_message = '';
$add_category_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {

    $category_name = trim($_POST['category_name']);

    try {
        ensure_item_category_name_column($conn);
    } catch (Exception $e) {
        $add_category_error = $e->getMessage();
    }

    // Validation
    if (!empty($add_category_error)) {

    } elseif (empty($category_name)) {

        $add_category_error = 'Category name is required.';

    } elseif (strlen($category_name) > 50) {

        $add_category_error = 'Category name must be 50 characters or fewer.';

    } else {

        // Check duplicate username or email
        $check_query = mysqli_prepare($conn,
            "SELECT id FROM item_category WHERE category_name = ?"
        );

        mysqli_stmt_bind_param($check_query, "s", $category_name);
        mysqli_stmt_execute($check_query);
        $check_result = mysqli_stmt_get_result($check_query);

        if (mysqli_num_rows($check_result) > 0) {

            $add_category_error = 'Category name already exists.';

        } else {

            $add_query = mysqli_prepare($conn,
                "INSERT INTO item_category 
                (category_name) 
                VALUES (?)"
            );

            mysqli_stmt_bind_param(
                $add_query,
                "s",
                $category_name
            );

            if (mysqli_stmt_execute($add_query)) {

                mysqli_stmt_close($add_query);
                mysqli_stmt_close($check_query);
                    $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : 'item-category.php';
                    header("Location: ../../" . basename($redirect));
                $add_category_error = 'Failed to add category. Please try again.';
                mysqli_stmt_close($add_query);
                mysqli_stmt_close($check_query);
            }
        }
    }
}
?>
