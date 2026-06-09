<?php
	include 'src/db/connection.php';
	include 'auth_check.php';
	require_once 'src/handlers/settings_helpers.php';
	require_once 'src/handlers/activity_log_helper.php';

	function settings_page_text($value, int $limit): string {
		$value = trim(preg_replace('/\s+/', ' ', (string) $value));
		return substr($value, 0, $limit);
	}

	function settings_page_multiline($value, int $limit): string {
		$value = trim((string) $value);
		return substr($value, 0, $limit);
	}

	function settings_page_e($value): string {
		return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
	}

	$can_manage_settings = ($_SESSION['role'] ?? '') === 'Administrator';
	$settings_error = '';

	try {
		$app_settings = get_app_settings($conn);
	} catch (Exception $e) {
		$app_settings = app_settings_defaults();
		$settings_error = $e->getMessage();
	}

	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_app_settings'])) {
		if (!$can_manage_settings) {
			$_SESSION['dialog_flash'] = [
				'type' => 'error',
				'title' => 'Permission Required',
				'message' => 'Only administrators can update system settings.'
			];
			header('Location: settings.php');
			exit();
		}

		$facebook_page = settings_page_text($_POST['facebook_page'] ?? '', 255);
		if ($facebook_page !== '' && !preg_match('/^https?:\/\//i', $facebook_page)) {
			$facebook_page = 'https://' . $facebook_page;
		}

		$incoming_settings = [
			'business_name' => settings_page_text($_POST['business_name'] ?? '', 120),
			'owner_name' => settings_page_text($_POST['owner_name'] ?? '', 120),
			'business_email' => settings_page_text($_POST['business_email'] ?? '', 120),
			'business_phone' => settings_page_text($_POST['business_phone'] ?? '', 50),
			'business_address' => settings_page_multiline($_POST['business_address'] ?? '', 500),
			'facebook_page' => $facebook_page,
			'business_hours' => settings_page_multiline($_POST['business_hours'] ?? '', 300),
			'timezone' => settings_page_text($_POST['timezone'] ?? 'Asia/Manila', 80),
			'receipt_footer' => settings_page_multiline($_POST['receipt_footer'] ?? '', 500),
			'service_policy' => settings_page_multiline($_POST['service_policy'] ?? '', 700),
			'auto_release_paid_work_orders' => isset($_POST['auto_release_paid_work_orders']) ? '1' : '0',
		];

		if ($incoming_settings['business_name'] === '') {
			$settings_error = 'Business name is required.';
		} elseif ($incoming_settings['business_email'] !== '' && !filter_var($incoming_settings['business_email'], FILTER_VALIDATE_EMAIL)) {
			$settings_error = 'Business email is invalid.';
		} elseif ($incoming_settings['facebook_page'] !== '' && !filter_var($incoming_settings['facebook_page'], FILTER_VALIDATE_URL)) {
			$settings_error = 'Facebook page must be a valid URL.';
		} else {
			try {
				save_app_settings($conn, $incoming_settings);
				log_activity($conn, 'Updated system settings');
				$_SESSION['dialog_flash'] = [
					'type' => 'success',
					'title' => 'Settings Saved',
					'message' => 'System settings updated successfully.'
				];
				header('Location: settings.php');
				exit();
			} catch (Exception $e) {
				$settings_error = $e->getMessage();
			}
		}

		$app_settings = array_merge($app_settings, $incoming_settings);
	}

	include 'header.php';
	include 'sidebar.php';
?>

	<div class="mobile-menu-overlay"></div>

	<div class="main-container">
		<div class="pd-ltr-20 xs-pd-20-10">
			<div class="min-height-200px">
				<div class="page-header settings-page-header">
					<div class="row">
						<div class="col-md-6 col-sm-12">
							<div class="title">
								<h4><i class="micon dw dw-settings2 mtext"></i> Settings</h4>
							</div>
							<nav aria-label="breadcrumb" role="navigation">
								<ol class="breadcrumb">
									<li class="breadcrumb-item"><a href="index.php">Home</a></li>
									<li class="breadcrumb-item active" aria-current="page">Settings</li>
								</ol>
							</nav>
						</div>
						<div class="col-md-6 col-sm-12 settings-header-actions">
							<a href="notifications.php" class="btn btn-secondary btn-sm">
								<i class="dw dw-bell"></i> Notifications
							</a>
							<?php if ($can_manage_settings): ?>
								<a href="user.php" class="btn btn-secondary btn-sm">
									<i class="dw dw-user1"></i> Users
								</a>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<?php if ($settings_error): ?>
					<div class="alert alert-danger">
						<i class="dw dw-close"></i> <?= settings_page_e($settings_error) ?>
					</div>
				<?php endif; ?>

				<div class="settings-grid">
					<form method="POST" class="card-box settings-panel">
						<input type="hidden" name="save_app_settings" value="1">
						<div class="settings-panel-heading">
							<div>
								<span class="settings-kicker">Shop</span>
								<h5>Business Profile</h5>
							</div>
							<span class="settings-status-pill"><?= $can_manage_settings ? 'Administrator' : 'Read Only' ?></span>
						</div>

						<?php if (!$can_manage_settings): ?>
							<div class="alert alert-danger settings-compact-alert">
								<i class="dw dw-lock"></i> Only administrators can edit shop settings.
							</div>
						<?php endif; ?>

						<fieldset <?= $can_manage_settings ? '' : 'disabled' ?>>
							<div class="settings-form-grid">
								<div class="form-group">
									<label class="form-label" for="businessName">Business Name</label>
									<input id="businessName" class="form-control" type="text" name="business_name" value="<?= settings_page_e($app_settings['business_name']) ?>" required autocomplete="organization">
								</div>
								<div class="form-group">
									<label class="form-label" for="ownerName">Owner Name</label>
									<input id="ownerName" class="form-control" type="text" name="owner_name" value="<?= settings_page_e($app_settings['owner_name']) ?>" autocomplete="name">
								</div>
								<div class="form-group">
									<label class="form-label" for="businessEmail">Email</label>
									<input id="businessEmail" class="form-control" type="email" name="business_email" value="<?= settings_page_e($app_settings['business_email']) ?>" autocomplete="email">
								</div>
								<div class="form-group">
									<label class="form-label" for="businessPhone">Contact Number</label>
									<input id="businessPhone" class="form-control" type="text" name="business_phone" value="<?= settings_page_e($app_settings['business_phone']) ?>" autocomplete="tel">
								</div>
								<div class="form-group settings-span-2">
									<label class="form-label" for="businessAddress">Address</label>
									<textarea id="businessAddress" class="form-control" name="business_address" rows="3"><?= settings_page_e($app_settings['business_address']) ?></textarea>
								</div>
								<div class="form-group settings-span-2">
									<label class="form-label" for="facebookPage">Facebook Page</label>
									<input id="facebookPage" class="form-control" type="url" name="facebook_page" value="<?= settings_page_e($app_settings['facebook_page']) ?>" placeholder="https://facebook.com/your-page" autocomplete="url">
								</div>
							</div>

							<div class="settings-section-divider"></div>

							<div class="settings-form-grid">
								<div class="form-group">
									<label class="form-label" for="businessHours">Business Hours</label>
									<textarea id="businessHours" class="form-control" name="business_hours" rows="3"><?= settings_page_e($app_settings['business_hours']) ?></textarea>
								</div>
								<div class="form-group">
									<label class="form-label" for="timezone">Timezone</label>
									<select id="timezone" class="form-control" name="timezone">
										<option value="Asia/Manila" <?= $app_settings['timezone'] === 'Asia/Manila' ? 'selected' : '' ?>>Asia/Manila</option>
										<option value="UTC" <?= $app_settings['timezone'] === 'UTC' ? 'selected' : '' ?>>UTC</option>
									</select>
								</div>
								<div class="form-group settings-span-2">
									<label class="form-label" for="receiptFooter">Receipt Footer</label>
									<textarea id="receiptFooter" class="form-control" name="receipt_footer" rows="3"><?= settings_page_e($app_settings['receipt_footer']) ?></textarea>
								</div>
								<div class="form-group settings-span-2">
									<label class="form-label" for="servicePolicy">Service Policy</label>
									<textarea id="servicePolicy" class="form-control" name="service_policy" rows="4"><?= settings_page_e($app_settings['service_policy']) ?></textarea>
								</div>
								<label class="settings-toggle settings-span-2" for="autoReleasePaidWorkOrders">
									<input id="autoReleasePaidWorkOrders" type="checkbox" name="auto_release_paid_work_orders" value="1" <?= app_setting_enabled($app_settings, 'auto_release_paid_work_orders') ? 'checked' : '' ?>>
									<span>Auto-release repaired work orders after full payment</span>
								</label>
							</div>
						</fieldset>

						<?php if ($can_manage_settings): ?>
							<div class="settings-actions">
								<button class="btn btn-primary" type="submit">
									<i class="dw dw-diskette1"></i> Save Settings
								</button>
							</div>
						<?php endif; ?>
					</form>

					<div class="settings-side-column">
						<form method="POST" class="card-box settings-panel">
							<input type="hidden" name="redirect_to" value="settings.php">
							<div class="settings-panel-heading">
								<div>
									<span class="settings-kicker">Account</span>
									<h5>Profile & Security</h5>
								</div>
								<span class="settings-status-pill"><?= settings_page_e($user['role'] ?? '') ?></span>
							</div>

							<?php if (!empty($update_message)): ?>
								<div class="alert alert-success">
									<i class="dw dw-checked"></i> <?= settings_page_e($update_message) ?>
								</div>
							<?php endif; ?>
							<?php if (!empty($update_error)): ?>
								<div class="alert alert-danger">
									<i class="dw dw-close"></i> <?= settings_page_e($update_error) ?>
								</div>
							<?php endif; ?>

							<div class="form-group">
								<label class="form-label" for="settingsUsername">Username</label>
								<input id="settingsUsername" type="text" class="form-control" name="username" value="<?= settings_page_e($user['username'] ?? '') ?>" autocomplete="username" required>
							</div>
							<div class="settings-form-grid settings-account-grid">
								<div class="form-group">
									<label class="form-label" for="settingsFirstName">First Name</label>
									<input id="settingsFirstName" type="text" class="form-control" name="first_name" value="<?= settings_page_e($user['first_name'] ?? '') ?>" autocomplete="given-name" required>
								</div>
								<div class="form-group">
									<label class="form-label" for="settingsLastName">Last Name</label>
									<input id="settingsLastName" type="text" class="form-control" name="last_name" value="<?= settings_page_e($user['last_name'] ?? '') ?>" autocomplete="family-name" required>
								</div>
							</div>
							<div class="form-group">
								<label class="form-label" for="settingsContact">Contact Number</label>
								<input id="settingsContact" type="text" class="form-control" name="contact_num" value="<?= settings_page_e($user['contact_num'] ?? '') ?>" autocomplete="tel">
							</div>
							<div class="form-group">
								<label class="form-label" for="settingsEmail">Email</label>
								<input id="settingsEmail" type="email" class="form-control" name="email" value="<?= settings_page_e($user['email'] ?? '') ?>" autocomplete="email" required>
							</div>
							<div class="form-group">
								<label class="form-label" for="settingsNewPassword">New Password</label>
								<input id="settingsNewPassword" type="password" class="form-control" name="new_password" placeholder="Leave blank to keep current password" autocomplete="new-password">
							</div>
							<div class="form-group">
								<label class="form-label" for="settingsRole">Role</label>
								<input id="settingsRole" type="text" class="form-control" value="<?= settings_page_e($user['role'] ?? '') ?>" readonly>
							</div>
							<div class="settings-actions">
								<button type="submit" name="update_profile" class="btn btn-primary">
									<i class="dw dw-diskette1"></i> Save Profile
								</button>
							</div>
						</form>

						<div class="card-box settings-panel">
							<div class="settings-panel-heading">
								<div>
									<span class="settings-kicker">System</span>
									<h5>Quick Actions</h5>
								</div>
							</div>
							<div class="settings-link-list">
								<a href="notifications.php">
									<i class="dw dw-bell"></i>
									<span>Notifications</span>
								</a>
								<?php if ($can_manage_settings): ?>
									<a href="user.php">
										<i class="dw dw-user1"></i>
										<span>User Accounts</span>
									</a>
									<a href="reports.php">
										<i class="dw dw-analytics-21"></i>
										<span>Reports</span>
									</a>
								<?php endif; ?>
								<a href="logout.php" class="settings-danger-link">
									<i class="dw dw-logout"></i>
									<span>Log Out</span>
								</a>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<!-- js -->
	<script src="vendors/scripts/core.js"></script>
	<script src="vendors/scripts/script.min.js"></script>
	<script src="vendors/scripts/process.js"></script>
	<script src="vendors/scripts/layout-settings.js"></script>
</body>
</html>
