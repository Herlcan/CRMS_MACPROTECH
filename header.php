<?php

	if (!defined('MACPROTECH_PAGE_BUFFER_STARTED')) {
		define('MACPROTECH_PAGE_BUFFER_STARTED', true);
		ob_start();
	}

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
	<link rel="apple-touch-icon" sizes="180x180" href="src/images/apple-touch-icon.png?v=<?= filemtime(__DIR__ . '/src/images/apple-touch-icon.png'); ?>">
	<link rel="icon" type="image/png" sizes="192x192" href="src/images/favicon-192x192.png?v=<?= filemtime(__DIR__ . '/src/images/favicon-192x192.png'); ?>">
	<link rel="icon" type="image/png" sizes="32x32" href="src/images/favicon-32x32.png?v=<?= filemtime(__DIR__ . '/src/images/favicon-32x32.png'); ?>">
	<link rel="icon" type="image/png" sizes="16x16" href="src/images/favicon-16x16.png?v=<?= filemtime(__DIR__ . '/src/images/favicon-16x16.png'); ?>">
	<link rel="shortcut icon" href="src/images/favicon.ico?v=<?= filemtime(__DIR__ . '/src/images/favicon.ico'); ?>">
	<!--<meta http-equiv="Content-Security-Policy" content="script-src 'self' 'unsafe-eval';">-->
	<script>
		(function () {
			try {
				document.documentElement.classList.toggle(
					'sidebar-collapsed',
					localStorage.getItem('macprotechSidebarCollapsed') === 'true'
				);
				if (sessionStorage.getItem('macproPageNavigating') === 'true') {
					const skeletonDelay = 360;
					const startedAt = Number(sessionStorage.getItem('macproPageNavigationStartedAt')) || Date.now();
					const remainingDelay = Math.max(0, skeletonDelay - (Date.now() - startedAt));
					document.documentElement.dataset.macproSkeleton = sessionStorage.getItem('macproPageSkeletonLayout') || 'table';
					if (remainingDelay === 0) {
						document.documentElement.classList.add('macpro-page-boot-loading');
					} else {
						window.setTimeout(function () {
							if (sessionStorage.getItem('macproPageNavigating') === 'true') {
								document.documentElement.classList.add('macpro-page-boot-loading');
							}
						}, remainingDelay);
					}
				}
			} catch (error) {}
		})();
	</script>
	<link rel="stylesheet" type="text/css" href="src/styles/style-improved.css?v=<?= filemtime(__DIR__ . '/src/styles/style-improved.css'); ?>">
	<script defer src="src/scripts/dialogs.js"></script>
	<script defer src="src/scripts/page-skeleton.js?v=<?= filemtime(__DIR__ . '/src/scripts/page-skeleton.js'); ?>"></script>
	<script defer src="src/scripts/notifications.js"></script>
	<script defer src="src/scripts/transition-tabs.js?v=<?= filemtime(__DIR__ . '/src/scripts/transition-tabs.js'); ?>"></script>
</head>

<body>
	<div class="macpro-page-skeleton" id="macproPageSkeleton" role="status" aria-live="polite" aria-label="Loading page" aria-hidden="true">
		<div class="macpro-page-skeleton-inner">
			<div class="macpro-page-skeleton-head">
				<span class="macpro-page-skeleton-title"></span>
				<span class="macpro-page-skeleton-action"></span>
			</div>
			<div class="macpro-page-skeleton-metrics">
				<span></span>
				<span></span>
				<span></span>
				<span></span>
			</div>
			<div class="macpro-page-skeleton-panel">
				<span class="macpro-page-skeleton-line is-wide"></span>
				<span class="macpro-page-skeleton-line"></span>
				<span class="macpro-page-skeleton-line is-short"></span>
				<div class="macpro-page-skeleton-table">
					<span></span><span></span><span></span><span></span>
					<span></span><span></span><span></span><span></span>
					<span></span><span></span><span></span><span></span>
					<span></span><span></span><span></span><span></span>
				</div>
			</div>
		</div>
	</div>
	<?php
	$dialog_flash = $_SESSION['dialog_flash'] ?? null;
	unset($_SESSION['dialog_flash']);

	if (!$dialog_flash && isset($_GET['message'])) {
		$dialog_flash = [
			'type' => 'success',
			'title' => 'Success',
			'message' => (string) $_GET['message']
		];
	}

	if (!$dialog_flash && isset($_GET['error'])) {
		$dialog_flash = [
			'type' => 'error',
			'title' => 'Action Failed',
			'message' => (string) $_GET['error']
		];
	}
	?>
	<?php if ($dialog_flash): ?>
		<script>
			window.MACPRO_DIALOG_FLASH = <?= json_encode($dialog_flash, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
		</script>
	<?php endif; ?>
	<script>
		window.MACPRO_CSRF_TOKEN = <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
	</script>

	<div class="header">
		<div class="header-left">
			<button type="button" class="sidebar-collapse-toggle" id="sidebarCollapseToggle" aria-label="Toggle sidebar" aria-expanded="true">
				<img src="src/images/menu-bar.png" width="30" height="30" alt="Menu">
			</button>
		</div>
		<div class="header-right">
			<div class="notification-dropdown">
				<button type="button" class="notification-bell" id="notificationBellToggle" aria-label="Notifications">
					<img src="src/images/bell.png" width="22" height="22" alt="">
					<span class="notification-badge" id="notificationUnreadBadge" hidden>0</span>
				</button>
				<div class="notification-menu" id="notificationDropdownMenu">
					<div class="notification-menu-header">
						<strong>Notifications</strong>
						<button type="button" id="notificationMarkAll">Mark all read</button>
					</div>
					<div class="notification-preview-list" id="notificationPreviewList">
						<div class="notification-empty">Loading notifications...</div>
					</div>
					<a class="notification-view-all" href="notifications.php">View All Notifications</a>
				</div>
			</div>
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
					<?= csrf_input() ?>
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
