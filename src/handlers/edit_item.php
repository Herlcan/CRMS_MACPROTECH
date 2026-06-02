<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../db/connection.php';
include '../../auth_check.php';
require_once __DIR__ . '/notification_helpers.php';
require_once __DIR__ . '/item_schema.php';
require_once __DIR__ . '/category_schema.php';

$edit_item_error = '';

if (!function_exists('redirectItemWithDialog')) {
    function redirectItemWithDialog($type, $title, $message) {
        $_SESSION['dialog_flash'] = [
            'type' => $type,
            'title' => $title,
            'message' => $message
        ];
        header("Location: ../../items.php");
        exit();
    }
}

function resolveItemCategoryId($conn, $category, $other_category, &$error) {
    if ($category !== '__other__') {
        return (int) $category;
    }

    $category_name = trim($other_category);

    if ($category_name === '') {
        $error = "Please enter a category.";
        return 0;
    }

    if (strlen($category_name) > 50) {
        $error = "Category must be 50 characters or fewer.";
        return 0;
    }

    ensure_item_category_name_column($conn);

    $check_query = mysqli_prepare($conn, "SELECT id FROM item_category WHERE LOWER(category_name) = LOWER(?) LIMIT 1");
    if (!$check_query) {
        $error = "Database error. Please try again.";
        return 0;
    }

    mysqli_stmt_bind_param($check_query, "s", $category_name);
    mysqli_stmt_execute($check_query);
    $check_result = mysqli_stmt_get_result($check_query);
    $existing_category = mysqli_fetch_assoc($check_result);
    mysqli_stmt_close($check_query);

    if ($existing_category) {
        return (int) $existing_category['id'];
    }

    $insert_query = mysqli_prepare($conn, "INSERT INTO item_category (category_name) VALUES (?)");
    if (!$insert_query) {
        $error = "Database error. Please try again.";
        return 0;
    }

    mysqli_stmt_bind_param($insert_query, "s", $category_name);
    if (!mysqli_stmt_execute($insert_query)) {
        $error = "Failed to add category. Please try again.";
        mysqli_stmt_close($insert_query);
        return 0;
    }

    $category_id = mysqli_insert_id($conn);
    mysqli_stmt_close($insert_query);

    return (int) $category_id;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_item'])) {

    $item_id      = (int) $_POST['id'];
    $brand_name   = trim($_POST['brand_name']);
    $model        = trim($_POST['model']);
    $description  = trim($_POST['description']);
    $category     = resolveItemCategoryId($conn, $_POST['category'] ?? '', $_POST['other_category'] ?? '', $edit_item_error);
    $date         = $_POST['date'] ?? date('Y-m-d');

    if (empty($edit_item_error) && (empty($brand_name) || empty($model) || $category <= 0)) {
        $edit_item_error = "Please fill all required fields.";
    }

    if (empty($edit_item_error)) {
        try {
            ensure_items_inventory_columns($conn);
        } catch (Exception $e) {
            redirectItemWithDialog('error', 'Product Item Not Updated', $e->getMessage());
        }
        
        $image_name_to_save = null;

        // Handle image upload if provided
        if (!empty($_FILES['image']['name'])) {

            if ($_FILES['image']['error'] !== 0) {
                redirectItemWithDialog('error', 'Product Item Not Updated', 'Image upload error.');
            }

            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 10 * 1024 * 1024; // 10MB

            $file_tmp  = $_FILES['image']['tmp_name'];
            $file_size = $_FILES['image']['size'];

            // Validate type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $file_type = finfo_file($finfo, $file_tmp);
            finfo_close($finfo);

            if (!in_array($file_type, $allowed_types)) {
                redirectItemWithDialog('error', 'Product Item Not Updated', 'Invalid image type. Only JPG, PNG, GIF, WEBP allowed.');
            }

            // Validate size
            if ($file_size > $max_size) {
                redirectItemWithDialog('error', 'Product Item Not Updated', 'Image file too large. Maximum size is 10MB.');
            }

            // Generate unique filename
            $image_name_to_save = "item_" . time() . "_" . rand(1000, 9999) . "." . pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $upload_path = __DIR__ . "/../../src/uploads/" . $image_name_to_save;

            if (!move_uploaded_file($file_tmp, $upload_path)) {
                redirectItemWithDialog('error', 'Product Item Not Updated', 'Failed to upload image.');
            }
        }

        // Update query based on whether image was uploaded
        if ($image_name_to_save) {
            $update_query = mysqli_prepare($conn,
                "UPDATE items SET brand_name = ?, model = ?, description = ?, category_id = ?, date = ?, image = ? WHERE id = ?"
            );
            mysqli_stmt_bind_param($update_query, "sssissi",
                $brand_name, $model, $description, $category, $date, $image_name_to_save, $item_id
            );
        } else {
            $update_query = mysqli_prepare($conn,
                "UPDATE items SET brand_name = ?, model = ?, description = ?, category_id = ?, date = ? WHERE id = ?"
            );
            mysqli_stmt_bind_param($update_query, "sssisi",
                $brand_name, $model, $description, $category, $date, $item_id
            );
        }

        if (mysqli_stmt_execute($update_query)) {
            mysqli_stmt_close($update_query);
            notify_low_stock_for_item($conn, $item_id);
            redirectItemWithDialog('success', 'Product Item Updated', 'Product item updated successfully.');
        } else {
            $edit_item_error = "Failed to update item. Please try again.";
        }
    }

    if (!empty($edit_item_error)) {
        $_SESSION['edit_item_error'] = $edit_item_error;
        redirectItemWithDialog('error', 'Product Item Not Updated', $edit_item_error);
    }
} else {
    header("Location: ../../items.php");
    exit();
}
?>
