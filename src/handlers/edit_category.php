<?php

	error_reporting(E_ALL);
	ini_set('display_errors', getenv('APP_ENV') === 'production' ? '0' : '1');

    include '../db/connection.php';
    include '../../auth_check.php';
    require_once __DIR__ . '/category_schema.php';
    require_once __DIR__ . '/activity_log_helper.php';
    require_once __DIR__ . '/security_helpers.php';

    $edit_category_message = '';
    $edit_category_error = '';

    if (!function_exists('redirectCategoryWithDialog')) {
        function redirectCategoryWithDialog($type, $title, $message) {
            $_SESSION['dialog_flash'] = [
                'type' => $type,
                'title' => $title,
                'message' => $message
            ];
            $redirect = isset($_POST['redirect']) ? basename($_POST['redirect']) : 'item-category.php';
            header("Location: ../../" . $redirect);
            exit();
        }
    }

    if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_category'])) {
        if (!verify_csrf_token_or_audit($conn, 'update item category')) {
            redirectCategoryWithDialog('error', 'Security Check Failed', 'Your form session expired. Please try again.');
        }

        require_role(['Administrator', 'Cashier/Front Desk', 'Cashier/Front Desk Staff'], function () {
            redirectCategoryWithDialog('error', 'Permission Required', 'Only authorized staff can update categories.');
        }, $conn, 'update item category');

        $category_id  = (int) ($_POST['id'] ?? 0);
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
            redirectCategoryWithDialog('error', 'Category Not Updated', $edit_category_error);
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
            redirectCategoryWithDialog('error', 'Category Not Updated', $edit_category_error);

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
                log_activity($conn, "Updated item category {$category_name} (#{$category_id})");
                $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : 'item-category.php';
                header("Location: ../../" . basename($redirect));
                exit();

            } else {

                $edit_category_error = 'Failed to update category. Please try again.';
                redirectCategoryWithDialog('error', 'Category Not Updated', $edit_category_error);
            }
        }
    }

?>
