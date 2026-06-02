<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../db/connection.php';
include '../../auth_check.php';
require_once __DIR__ . '/notification_helpers.php';
require_once __DIR__ . '/item_schema.php';
require_once __DIR__ . '/category_schema.php';

$add_item_error = '';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {

    $brand_name = trim($_POST['brand_name']);
    $model = trim($_POST['model']);
    $description  = trim($_POST['description']);
    $category     = resolveItemCategoryId($conn, $_POST['category'] ?? '', $_POST['other_category'] ?? '', $add_item_error);
    $date         = $_POST['date'] ?? date('Y-m-d');

    if (empty($add_item_error) && (empty($brand_name) || empty($model) || $category <= 0)) {
        $add_item_error = "Please fill all required fields.";
    }

    $image_name_to_save = "none";

    if (!empty($_FILES['image']['name'])) {

        if ($_FILES['image']['error'] !== 0) {
            redirectItemWithDialog('error', 'Product Item Not Created', 'Image upload error.');
        }

        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 10 * 1024 * 1024; // 5MB

        $file_tmp  = $_FILES['image']['tmp_name'];
        $file_size = $_FILES['image']['size'];

        // 🔐 Use finfo (recommended)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $file_type = finfo_file($finfo, $file_tmp);
        finfo_close($finfo);

        // Validate type
        if (!in_array($file_type, $allowed_types)) {
            redirectItemWithDialog('error', 'Product Item Not Created', 'Invalid image type. Only JPG, PNG, GIF, WEBP allowed.');
        }

        // Validate size
        if ($file_size > $max_size) {
            redirectItemWithDialog('error', 'Product Item Not Created', 'Image is too large. Maximum size is 10MB.');
        }

        // Generate safe unique filename
        $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $new_name = "IMG_" . time() . "_" . bin2hex(random_bytes(5)) . "." . $extension;

        $upload_dir = "../uploads/";
        $upload_path = $upload_dir . $new_name;

        // Ensure upload folder exists
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        if (!move_uploaded_file($file_tmp, $upload_path)) {
            redirectItemWithDialog('error', 'Product Item Not Created', 'Failed to upload image.');
        }

        $image_name_to_save = $new_name;
    }

    if (empty($add_item_error)) {
        try {
            ensure_items_inventory_columns($conn);
        } catch (Exception $e) {
            redirectItemWithDialog('error', 'Product Item Not Created', $e->getMessage());
        }

        mysqli_begin_transaction($conn);

        $add_query = mysqli_prepare($conn,
            "INSERT INTO items
            (brand_name, model, description, category_id, quantity, markup_percentage, average_price, status, date, image)
            VALUES (?, ?, ?, ?, 0, 0.00, 0.00, '', ?, ?)"
        );

        if (!$add_query) {
            mysqli_rollback($conn);
            redirectItemWithDialog('error', 'Product Item Not Created', 'Database error. Please try again.');
        }

        mysqli_stmt_bind_param(
            $add_query,
            "sssiss",
            $brand_name,
            $model,
            $description,
            $category,
            $date,
            $image_name_to_save
        );

        if (mysqli_stmt_execute($add_query)) {

            $id = mysqli_insert_id($conn);

            // Get category name
            $cat_query = mysqli_prepare($conn, "SELECT category_name FROM item_category WHERE id = ?");
            mysqli_stmt_bind_param($cat_query, "i", $category);
            mysqli_stmt_execute($cat_query);
            $cat_result = mysqli_stmt_get_result($cat_query);
            $cat_row = mysqli_fetch_assoc($cat_result);
            $category_name = $cat_row['category_name'];
            mysqli_stmt_close($cat_query);

            // Generate SKU: 3letter category - 3letter brand - PI - 0001
            $cat_3 = strtoupper(substr($category_name, 0, 3));
            $brand_3 = strtoupper(substr($brand_name, 0, 3));
            $code = $cat_3 . '-' . $brand_3 . '-PI-' . sprintf("%04d", $id);

            $update_query = mysqli_prepare(
                $conn,
                "UPDATE items SET product_code = ? WHERE id = ?"
            );

            if (!$update_query) {
                mysqli_stmt_close($add_query);
                mysqli_rollback($conn);
                redirectItemWithDialog('error', 'Product Item Not Created', 'Failed to generate product code.');
            }

            mysqli_stmt_bind_param($update_query, "si", $code, $id);
            if (!mysqli_stmt_execute($update_query)) {
                mysqli_stmt_close($add_query);
                mysqli_stmt_close($update_query);
                mysqli_rollback($conn);
                redirectItemWithDialog('error', 'Product Item Not Created', 'Failed to generate product code.');
            }

            mysqli_commit($conn);

            mysqli_stmt_close($add_query);
            mysqli_stmt_close($update_query);

            notify_low_stock_for_item($conn, (int) $id);
            redirectItemWithDialog('success', 'Product Item Created', 'New product item created successfully.');

        } else {
            mysqli_rollback($conn);
            $add_item_error = "Database error. Please try again.";
        }
    }

    if (!empty($add_item_error)) {
        redirectItemWithDialog('error', 'Product Item Not Created', $add_item_error);
    }
}
?>
