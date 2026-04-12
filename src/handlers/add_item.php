<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../db/connection.php';
include '../../auth_check.php';

$add_item_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {

    $brand_name = trim($_POST['brand_name']);
    $model = trim($_POST['model']);
    $description  = trim($_POST['description']);
    $category     = (int) ($_POST['category']);
    $date         = $_POST['date'] ?? date('Y-m-d');
    $capital      = (float) ($_POST['capital'] ?? 0);
    $quantity     = (int) ($_POST['quantity'] ?? 0);
    $price        = (float) ($_POST['price'] ?? 0);

    if (empty($brand_name) || empty($model) || $category <= 0) {
        $add_item_error = "Please fill all required fields.";
    }

    $image_name_to_save = "none";

    if (!empty($_FILES['image']['name'])) {

        if ($_FILES['image']['error'] !== 0) {
            die("Image upload error.");
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
            die("Invalid image type. Only JPG, PNG, GIF, WEBP allowed.");
        }

        // Validate size
        if ($file_size > $max_size) {
            die("Image is too large. Maximum size is 10MB.");
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
            die("Failed to upload image.");
        }

        $image_name_to_save = $new_name;
    }

    if (empty($add_item_error)) {

        $add_query = mysqli_prepare($conn,
            "INSERT INTO items
            (brand_name, model, description, category_id, capital, quantity, price, date, image)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        mysqli_stmt_bind_param(
            $add_query,
            "sssididss",
            $brand_name,
            $model,
            $description,
            $category,
            $capital,
            $quantity,
            $price,
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

            mysqli_stmt_bind_param($update_query, "si", $code, $id);
            mysqli_stmt_execute($update_query);

            mysqli_stmt_close($add_query);
            mysqli_stmt_close($update_query);

            header("Location: ../../items.php");
            exit();

        } else {
            $add_item_error = "Database error. Please try again.";
        }
    }
}
?>
