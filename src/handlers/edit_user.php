<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../db/connection.php';
include '../../auth_check.php';
require_once __DIR__ . '/activity_log_helper.php';
require_once __DIR__ . '/security_helpers.php';

$update_message = '';
$update_error = '';

if (!function_exists('redirectUserWithDialog')) {
    function redirectUserWithDialog($type, $title, $message) {
        $_SESSION['dialog_flash'] = [
            'type' => $type,
            'title' => $title,
            'message' => $message
        ];
        header("Location: ../../user.php");
        exit();
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    if (!verify_csrf_token()) {
        redirectUserWithDialog('error', 'Security Check Failed', 'Your form session expired. Please try again.');
    }

    require_role('Administrator', function () {
        redirectUserWithDialog('error', 'Permission Required', 'Only administrators can update users.');
    });

    $user_id = intval($_POST['user_id']);
	$username = trim($_POST['username'] ?? '');
	$first_name = trim($_POST['first_name'] ?? '');
	$last_name = trim($_POST['last_name'] ?? '');
	$contact_num = trim($_POST['contact_num'] ?? '');
	$email = trim($_POST['email'] ?? '');
    $role = trim($_POST['role'] ?? '');
	$new_password = ($_POST['new_password'] ?? '');
    $allowed_roles = ['Administrator', 'Technician', 'Cashier/Front Desk', 'Cashier/Front Desk Staff'];

	// Validation
	if (empty($username) || empty($first_name) || empty($last_name) || empty($email)) {
		$update_error = 'All fields are required.';
	} elseif (strlen($username) < 3) {
		$update_error = 'Username must be at least 3 characters long.';
	} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$update_error = 'Invalid email address.';
	} elseif (!in_array($role, $allowed_roles, true)) {
		$update_error = 'Invalid role selected.';
	} elseif (!empty($new_password) && password_policy_message($new_password) !== '') {
		$update_error = password_policy_message($new_password);
	} else {
		// Check if username already exists (for other users)
		$check_query = mysqli_prepare($conn,
			"SELECT username, email FROM users WHERE (username = ? OR email = ?) AND id != ? LIMIT 1"
		);
		mysqli_stmt_bind_param($check_query, "ssi", $username, $email, $user_id);
		mysqli_stmt_execute($check_query);
		$check_result = mysqli_stmt_get_result($check_query);
		
		if (mysqli_num_rows($check_result) > 0) {
			$existing_user = mysqli_fetch_assoc($check_result);
			$update_error = strcasecmp((string) $existing_user['username'], $username) === 0
				? 'Username already taken. Please choose a different username.'
				: 'Email already used by another account.';
		} else {
			if ($role !== 'Administrator') {
				$admin_count_query = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'Administrator' AND id != ?");
				mysqli_stmt_bind_param($admin_count_query, "i", $user_id);
				mysqli_stmt_execute($admin_count_query);
				$admin_count_result = mysqli_stmt_get_result($admin_count_query);
				$admin_count = (int) (mysqli_fetch_assoc($admin_count_result)['total'] ?? 0);
				mysqli_stmt_close($admin_count_query);

				if ($admin_count === 0) {
					$update_error = 'Cannot remove the last administrator role.';
				}
			}

			if ($update_error !== '') {
				redirectUserWithDialog('error', 'User Not Updated', $update_error);
			}

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
                log_activity($conn, "Updated user {$first_name} {$last_name} ({$username}, {$role})");

                redirectUserWithDialog('success', 'User Updated', 'User updated successfully.');
			} else {
				$update_error = 'Failed to update profile. Please try again.';
			}
		}
	}

    if (!empty($update_error)) {
        redirectUserWithDialog('error', 'User Not Updated', $update_error);
    }
}
?>
