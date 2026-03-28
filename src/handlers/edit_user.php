<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../db/connection.php';

$update_message = '';
$update_error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $user_id = intval($_POST['user_id']);
	$username = mysqli_real_escape_string($conn, $_POST['username']);
	$first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
	$last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
	$contact_num = mysqli_real_escape_string($conn, $_POST['contact_num']);
	$email = mysqli_real_escape_string($conn, $_POST['email']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
	$new_password = ($_POST['new_password']);

	// Validation
	if (empty($username) || empty($first_name) || empty($last_name) || empty($email)) {
		$update_error = 'All fields are required.';
	} elseif (strlen($username) < 3) {
		$update_error = 'Username must be at least 3 characters long.';
	} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$update_error = 'Invalid email address.';
	} elseif (!empty($new_password) && strlen($new_password) < 8) {
		$update_error = 'Password must be at least 8 characters long.';
	} else {
		// Check if username already exists (for other users)
		$check_query = mysqli_prepare($conn,
			"SELECT id FROM users WHERE username = ? AND id != ?"
		);
		mysqli_stmt_bind_param($check_query, "si", $username, $user_id);
		mysqli_stmt_execute($check_query);
		$check_result = mysqli_stmt_get_result($check_query);
		
		if (mysqli_num_rows($check_result) > 0) {
			$update_error = 'Username already taken. Please choose a different username.';
		} else {
			// Prepare password update if provided
			if (!empty($new_password)) {
				$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
				$update_query = mysqli_prepare($conn,
					"UPDATE users SET username = ?, first_name = ?, last_name = ?, contact_num = ?, email = ?, role = ?, password = ? WHERE id = ?"
				);
				mysqli_stmt_bind_param($update_query, "ssssssi", $username, $first_name, $last_name, $contact_num, $email, $role, $hashed_password, $user_id);
			} else {
				// Update without password
				$update_query = mysqli_prepare($conn,
					"UPDATE users SET username = ?, first_name = ?, last_name = ?, contact_num = ?, email = ?, role = ? WHERE id = ?"
				);
				mysqli_stmt_bind_param($update_query, "ssssssi", $username, $first_name, $last_name, $contact_num, $email, $role, $user_id);
			}
			
			if (mysqli_stmt_execute($update_query)) {
				$update_message = 'User updated successfully!';

                header("Location: ../../user.php");
                exit();
			} else {
				$update_error = 'Failed to update profile. Please try again.';
			}
		}
	}
}
?>
