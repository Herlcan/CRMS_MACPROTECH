<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../db/connection.php';
include '../../auth_check.php';

$edit_item_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_item'])) {

    $item_id      = (int) $_POST['id'];
    $brand_name   = trim($_POST['brand_name']);
    $model        = trim($_POST['model']);
    $description  = trim($_POST['description']);
    $category     = (int) $_POST['category'];
    $date         = $_POST['date'] ?? date('Y-m-d');
    $capital      = (float) $_POST['capital'];
    $quantity     = (int) $_POST['quantity'];
    $price        = (float) $_POST['price'];

    if (empty($brand_name) || empty($model) || $category <= 0) {
        $edit_item_error = "Please fill all required fields.";
    }

    if (empty($edit_item_error)) {
        
        $image_name_to_save = null;

        // Handle image upload if provided
        if (!empty($_FILES['image']['name'])) {

            if ($_FILES['image']['error'] !== 0) {
                die("Image upload error.");
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
                die("Invalid image type. Only JPG, PNG, GIF, WEBP allowed.");
            }

            // Validate size
            if ($file_size > $max_size) {
                die("Image file too large. Maximum size is 10MB.");
            }

            // Generate unique filename
            $image_name_to_save = "item_" . time() . "_" . rand(1000, 9999) . "." . pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $upload_path = __DIR__ . "/../../src/uploads/" . $image_name_to_save;

            if (!move_uploaded_file($file_tmp, $upload_path)) {
                die("Failed to upload image.");
            }
        }

        // Update query based on whether image was uploaded
        if ($image_name_to_save) {
            $update_query = mysqli_prepare($conn,
                "UPDATE items SET brand_name = ?, model = ?, description = ?, category_id = ?, date = ?, capital = ?, quantity = ?, price = ?, image = ? WHERE id = ?"
            );
            mysqli_stmt_bind_param($update_query, "sssisddi", 
                $brand_name, $model, $description, $category, $date, $capital, $quantity, $price, $image_name_to_save, $item_id
            );
        } else {
            $update_query = mysqli_prepare($conn,
                "UPDATE items SET brand_name = ?, model = ?, description = ?, category_id = ?, date = ?, capital = ?, quantity = ?, price = ? WHERE id = ?"
            );
            mysqli_stmt_bind_param($update_query, "sssisddii", 
                $brand_name, $model, $description, $category, $date, $capital, $quantity, $price, $item_id
            );
        }

        if (mysqli_stmt_execute($update_query)) {
            mysqli_stmt_close($update_query);
            header("Location: ../../items.php");
            exit();
        } else {
            $edit_item_error = "Failed to update item. Please try again.";
        }
    }

    if (!empty($edit_item_error)) {
        $_SESSION['edit_item_error'] = $edit_item_error;
        header("Location: ../../items.php");
        exit();
    }
} else {
    header("Location: ../../items.php");
    exit();
}
?>
