<?php

	include 'src/db/connection.php';
	include 'auth_check.php';

	$user_id = $_SESSION['user_id'];

	// Include profile update handler
	include 'src/handlers/update_profile.php';

	$query = mysqli_prepare($conn,
		"SELECT username, email, first_name, last_name, contact_num, role FROM users WHERE id = ?"
	);
	mysqli_stmt_bind_param($query, "i", $user_id);
	mysqli_stmt_execute($query);

	$result = mysqli_stmt_get_result($query);
	$user = mysqli_fetch_assoc($result);

	$first_name = $user['first_name'];
?>

<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>MACPROTECH</title>
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
	<!--<meta http-equiv="Content-Security-Policy" content="script-src 'self' 'unsafe-eval';">-->
	<link rel="stylesheet" type="text/css" href="src/styles/style-improved.css">
</head>

<body>

	<div class="header">
		<div class="header-left">
			<div class="menu-icon dw dw-menu"></div>
		</div>
		<div class="header-right">
			<div class="user-info-dropdown">
				<div class="dropdown">
					<a class="dropdown-toggle">
						<span class="user-icon">
							<div class="icon-letter-container">
								<label class="icon-letter"><?= " ".  htmlspecialchars($first_name[0]) . htmlspecialchars($user['last_name'][0]) ." "; ?></label>
							</div>
						</span>
						<span class="user-name">
							<?= htmlspecialchars($_SESSION['username']); ?>
							<small class="form-text text-muted" style="margin-top: 0;"><?= htmlspecialchars($user['role']); ?></small>
						</span>
					</a>
					<div class="dropdown-menu dropdown-menu-right dropdown-menu-icon-list">
						<label class="dropdown-item profile-toggle-label" for="profileToggle"><img src="src/images/user-dark.png" width="20px" height="20px"> Profile</label>
						<a class="dropdown-item" href="settings.php"><img src="src/images/settings-sliders-dark.png" width="20px" height="20px"> Setting</a>
						<hr>
						<a class="dropdown-item" href="logout.php"><img src="src/images/user-logout.png" width="20px" height="20px"> Log Out</a>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Hidden checkbox for modal toggle (Pure CSS) -->
	<input type="checkbox" id="profileToggle" class="profile-toggle">
	<input type="checkbox" id="profileEditMode" class="profile-edit-mode">

	<!-- Pure CSS Modal Overlay -->
	<label for="profileToggle" class="css-modal-overlay"></label>

	<!-- Pure CSS Modal -->
	<div class="css-modal">
		<div class="css-modal-content">
			<!-- Modal Header -->
			<div class="css-modal-header">
				<h5 class="css-modal-title">User Profile</h5>
				<label for="profileToggle" class="css-modal-close">&times;</label>
			</div>

			<!-- Modal Body -->
			<div class="css-modal-body">
				<!-- Status Messages -->
				<?php if ($update_message): ?>
					<div class="alert alert-success">
						<i class="dw dw-checked"></i> <?= htmlspecialchars($update_message) ?>
					</div>
				<?php endif; ?>
				<?php if ($update_error): ?>
					<div class="alert alert-danger">
						<i class="dw dw-close"></i> <?= htmlspecialchars($update_error) ?>
					</div>
				<?php endif; ?>

				<!-- Profile Picture Section -->
				<div class="profile-pic-container icon-letter-container profile-pic" style="margin-left: auto; margin-right: auto; margin-bottom: 20px;">
					<label class="icon-letter text-center" style="font-size: 30px;"><?= " ".  htmlspecialchars($first_name[0]) . htmlspecialchars($user['last_name'][0]) ." "; ?></label>
				</div>

				<!-- Profile Form -->
				<form method="POST" class="profile-form">
					<div class="form-group">
						<label class="form-label">Username</label>
						<input type="text" class="form-control profile-input" name="username" value="<?= htmlspecialchars($user['username']); ?>" autocomplete="off">
						<small class="form-text text-muted">At least 3 characters, must be unique</small>
					</div>

					<div class="form-group">
						<label class="form-label">First Name</label>
						<input type="text" class="form-control profile-input" name="first_name" value="<?= htmlspecialchars($user['first_name']); ?>" autocomplete="off">
					</div>

					<div class="form-group">
						<label class="form-label">Last Name</label>
						<input type="text" class="form-control profile-input" name="last_name" value="<?= htmlspecialchars($user['last_name']); ?>" autocomplete="off">
					</div>

					<div class="form-group">
						<label class="form-label">Contact Number</label>
						<input type="text" class="form-control profile-input" name="contact_num" value="<?= htmlspecialchars($user['contact_num']); ?>" autocomplete="off">
					</div>

					<div class="form-group">
						<label class="form-label">Email</label>
						<input type="email" class="form-control profile-input" name="email" value="<?= htmlspecialchars($user['email']); ?>" autocomplete="off">
					</div>

					<div class="form-group">
						<label class="form-label">New Password (optional)</label>
						<input type="password" class="form-control profile-input" name="new_password" placeholder="Enter new password" autocomplete="off">
					</div>

					<div class="form-group">
						<label class="form-label">Position/Role</label>
						<input type="text" class="form-control" value="<?= htmlspecialchars($user['role']); ?>" readonly>
						<small class="form-text text-muted">Role is managed by administrator</small>
					</div>

					<!-- Modal Footer -->
					<div class="css-modal-footer">
						<!-- View Mode: Show Edit Button and Close -->
						<label for="profileToggle" class="btn btn-secondary edit-mode-hide">Close</label>
						<label for="profileEditMode" class="btn btn-primary edit-mode-hide">Edit Profile</label>

						<!-- Edit Mode: Show Save and Cancel -->
						<label for="profileEditMode" class="btn btn-secondary edit-mode-show">Cancel</label>
						<button type="submit" name="update_profile" class="btn btn-primary edit-mode-show">Save Changes</button>
					</div>
				</form>
			</div>
		</div>
	</div>
