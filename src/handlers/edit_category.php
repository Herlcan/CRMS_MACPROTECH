<?php

	error_reporting(E_ALL);
	ini_set('display_errors', 1);

    include '../db/connection.php';
    include '../../auth_check.php';
    require_once __DIR__ . '/category_schema.php';

    $edit_category_message = '';
    $edit_category_error = '';

    if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_category'])) {

        $category_id  = $_POST['id'];
        $category_name = trim($_POST['category']);

        try {
            ensure_item_category_name_column($conn);
        } catch (Exception $e) {
            $edit_category_error = $e->getMessage();
        }

        if ($edit_category_error === '' && $category_name === '') {
            $edit_category_error = 'Category name is required.';
        }

        if ($edit_category_error === '' && strlen($category_name) > 50) {
            $edit_category_error = 'Category name must be 50 characters or fewer.';
        }

        if ($edit_category_error !== '') {
            return;
        }

        // Check duplicate username or email
        $check_query = mysqli_prepare($conn,
            "SELECT id FROM item_category WHERE category_name = ?"
        );

        mysqli_stmt_bind_param($check_query, "s", $category_name);
        mysqli_stmt_execute($check_query);
        mysqli_stmt_store_result($check_query);
        
        if (mysqli_stmt_num_rows($check_query) > 0) {

            $edit_category_error = 'Category already exists.';

            mysqli_stmt_close($check_query);

        } else {

            mysqli_stmt_close($check_query);

            $update_query = mysqli_prepare($conn,
				"UPDATE item_category 
                SET category_name = ? 
                WHERE id = ?"
            );

            mysqli_stmt_bind_param(
                $update_query,
                "si",
                $category_name,
                $category_id
            );

            if (mysqli_stmt_execute($update_query)) {

                mysqli_stmt_close($update_query);
                $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : 'item-category.php';
                header("Location: ../../" . basename($redirect));
                exit();

            } else {

                $edit_category_error = 'Failed to update category. Please try again.';
            }
        }
    }

?>
