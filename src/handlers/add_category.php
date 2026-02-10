<?php

include '../db/connection.php';
include '../../auth_check.php';

$add_category_message = '';
$add_category_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {

    $category_name = trim($_POST['category_name']);

    // Validation
    if (empty($category_name)) {

        $add_category_error = 'Category name is required.';

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
                header("Location: ../../item-category.php");
                exit();

            } else {

                $add_category_error = 'Failed to add category. Please try again.';
                mysqli_stmt_close($add_query);
                mysqli_stmt_close($check_query);
            }
        }
    }
}
?>
