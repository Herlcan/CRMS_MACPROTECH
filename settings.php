<?php
	include 'src/db/connection.php';
	include 'auth_check.php';
	require_once 'src/handlers/settings_helpers.php';
	require_once 'src/handlers/communication_helpers.php';
	require_once 'src/handlers/activity_log_helper.php';

	function settings_page_text($value, int $limit): string {
		$value = trim(preg_replace('/\s+/', ' ', (string) $value));
		return substr($value, 0, $limit);
	}

	function settings_page_multiline($value, int $limit): string {
		$value = trim((string) $value);
		return substr($value, 0, $limit);
	}

	function settings_page_secret($value, int $limit): string {
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
		if (!verify_csrf_token_or_audit($conn, 'update system settings')) {
			$_SESSION['dialog_flash'] = [
				'type' => 'error',
				'title' => 'Security Check Failed',
				'message' => 'Your form session expired. Please try again.'
			];
			header('Location: settings.php');
			exit();
		}

		if (!$can_manage_settings) {
			audit_authorization_failure($conn, 'update system settings');
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

		$mail_smtp_password = settings_page_secret($_POST['mail_smtp_password'] ?? '', 512);
		if (isset($_POST['clear_mail_smtp_password'])) {
			$mail_smtp_password = '';
		} elseif ($mail_smtp_password === '') {
			$mail_smtp_password = (string) ($app_settings['mail_smtp_password'] ?? '');
		}

		$httpsms_api_key = settings_page_secret($_POST['httpsms_api_key'] ?? '', 512);
		if (isset($_POST['clear_httpsms_api_key'])) {
			$httpsms_api_key = '';
		} elseif ($httpsms_api_key === '') {
			$httpsms_api_key = (string) ($app_settings['httpsms_api_key'] ?? '');
		}

		$mail_smtp_encryption = strtolower(settings_page_text($_POST['mail_smtp_encryption'] ?? 'tls', 20));
		if (!in_array($mail_smtp_encryption, ['tls', 'ssl', 'none'], true)) {
			$mail_smtp_encryption = 'tls';
		}

		$sms_default_country_code = settings_page_text($_POST['sms_default_country_code'] ?? '+63', 8);
		$sms_default_country_code = '+' . preg_replace('/\D+/', '', $sms_default_country_code);
		if ($sms_default_country_code === '+') {
			$sms_default_country_code = '+63';
		}

		$httpsms_sender_number = settings_page_text($_POST['httpsms_sender_number'] ?? '', 30);
		if ($httpsms_sender_number !== '') {
			$httpsms_sender_number = normalize_sms_phone_number($httpsms_sender_number, $sms_default_country_code);
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
			'mail_enabled' => isset($_POST['mail_enabled']) ? '1' : '0',
			'mail_smtp_host' => settings_page_text($_POST['mail_smtp_host'] ?? '', 120),
			'mail_smtp_port' => settings_page_text($_POST['mail_smtp_port'] ?? '587', 6),
			'mail_smtp_username' => settings_page_text($_POST['mail_smtp_username'] ?? '', 190),
			'mail_smtp_password' => $mail_smtp_password,
			'mail_smtp_encryption' => $mail_smtp_encryption,
			'mail_from_email' => settings_page_text($_POST['mail_from_email'] ?? '', 190),
			'mail_from_name' => settings_page_text($_POST['mail_from_name'] ?? '', 120),
			'sms_enabled' => isset($_POST['sms_enabled']) ? '1' : '0',
			'sms_status_updates_enabled' => isset($_POST['sms_status_updates_enabled']) ? '1' : '0',
			'sms_receipt_notifications_enabled' => isset($_POST['sms_receipt_notifications_enabled']) ? '1' : '0',
			'httpsms_api_key' => $httpsms_api_key,
			'httpsms_sender_number' => $httpsms_sender_number,
			'sms_default_country_code' => $sms_default_country_code,
		];

		if ($incoming_settings['business_name'] === '') {
			$settings_error = 'Business name is required.';
		} elseif ($incoming_settings['business_email'] !== '' && !filter_var($incoming_settings['business_email'], FILTER_VALIDATE_EMAIL)) {
			$settings_error = 'Business email is invalid.';
		} elseif ($incoming_settings['facebook_page'] !== '' && !filter_var($incoming_settings['facebook_page'], FILTER_VALIDATE_URL)) {
			$settings_error = 'Facebook page must be a valid URL.';
		} elseif (!preg_match('/^\+\d{1,4}$/', $incoming_settings['sms_default_country_code'])) {
			$settings_error = 'Default SMS country code is invalid.';
		} elseif (app_setting_enabled($incoming_settings, 'mail_enabled') && $incoming_settings['mail_smtp_host'] === '') {
			$settings_error = 'SMTP host is required when email delivery is enabled.';
		} elseif (app_setting_enabled($incoming_settings, 'mail_enabled') && ((int) $incoming_settings['mail_smtp_port'] <= 0 || (int) $incoming_settings['mail_smtp_port'] > 65535)) {
			$settings_error = 'SMTP port is invalid.';
		} elseif (app_setting_enabled($incoming_settings, 'mail_enabled') && $incoming_settings['mail_smtp_username'] !== '' && $incoming_settings['mail_smtp_password'] === '') {
			$settings_error = 'SMTP password or app password is required when SMTP username is set.';
		} elseif ($incoming_settings['mail_from_email'] !== '' && !filter_var($incoming_settings['mail_from_email'], FILTER_VALIDATE_EMAIL)) {
			$settings_error = 'Email from address is invalid.';
		} elseif (app_setting_enabled($incoming_settings, 'sms_enabled') && $incoming_settings['httpsms_api_key'] === '') {
			$settings_error = 'httpSMS API key is required when SMS delivery is enabled.';
		} elseif (app_setting_enabled($incoming_settings, 'sms_enabled') && !is_valid_sms_phone_number($incoming_settings['httpsms_sender_number'])) {
			$settings_error = 'httpSMS sender number is required and must include a valid country code.';
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
								<a href="backup-restore.php" class="btn btn-secondary btn-sm">
									<i class="dw dw-database"></i> Backup
								</a>
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
						<?= csrf_input() ?>
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

							<div class="settings-section-divider"></div>

							<div class="settings-form-grid">
								<div class="settings-section-label settings-span-2">Email Delivery</div>
								<label class="settings-toggle settings-span-2" for="mailEnabled">
									<input id="mailEnabled" type="checkbox" name="mail_enabled" value="1" <?= app_setting_enabled($app_settings, 'mail_enabled') ? 'checked' : '' ?>>
									<span>Enable receipt email</span>
								</label>
								<div class="form-group">
									<label class="form-label" for="mailSmtpHost">SMTP Host</label>
									<input id="mailSmtpHost" class="form-control" type="text" name="mail_smtp_host" value="<?= settings_page_e($app_settings['mail_smtp_host']) ?>" autocomplete="off">
								</div>
								<div class="form-group">
									<label class="form-label" for="mailSmtpPort">SMTP Port</label>
									<input id="mailSmtpPort" class="form-control" type="number" min="1" max="65535" name="mail_smtp_port" value="<?= settings_page_e($app_settings['mail_smtp_port']) ?>" autocomplete="off">
								</div>
								<div class="form-group">
									<label class="form-label" for="mailSmtpEncryption">Encryption</label>
									<select id="mailSmtpEncryption" class="form-control" name="mail_smtp_encryption">
										<option value="tls" <?= $app_settings['mail_smtp_encryption'] === 'tls' ? 'selected' : '' ?>>TLS</option>
										<option value="ssl" <?= $app_settings['mail_smtp_encryption'] === 'ssl' ? 'selected' : '' ?>>SSL</option>
										<option value="none" <?= $app_settings['mail_smtp_encryption'] === 'none' ? 'selected' : '' ?>>None</option>
									</select>
								</div>
								<div class="form-group">
									<label class="form-label" for="mailSmtpUsername">SMTP Username</label>
									<input id="mailSmtpUsername" class="form-control" type="text" name="mail_smtp_username" value="<?= settings_page_e($app_settings['mail_smtp_username']) ?>" autocomplete="username">
								</div>
								<div class="form-group">
									<label class="form-label" for="mailSmtpPassword">SMTP Password</label>
									<input id="mailSmtpPassword" class="form-control" type="password" name="mail_smtp_password" value="" placeholder="<?= app_setting_has_secret($app_settings, 'mail_smtp_password') ? 'Saved - leave blank to keep' : 'SMTP password or app password' ?>" autocomplete="new-password">
								</div>
								<label class="settings-toggle" for="clearMailSmtpPassword">
									<input id="clearMailSmtpPassword" type="checkbox" name="clear_mail_smtp_password" value="1">
									<span>Clear saved password</span>
								</label>
								<div class="form-group">
									<label class="form-label" for="mailFromEmail">From Email</label>
									<input id="mailFromEmail" class="form-control" type="email" name="mail_from_email" value="<?= settings_page_e($app_settings['mail_from_email']) ?>" autocomplete="email">
								</div>
								<div class="form-group">
									<label class="form-label" for="mailFromName">From Name</label>
									<input id="mailFromName" class="form-control" type="text" name="mail_from_name" value="<?= settings_page_e($app_settings['mail_from_name']) ?>" autocomplete="organization">
								</div>
							</div>

							<div class="settings-section-divider"></div>

							<div class="settings-form-grid">
								<div class="settings-section-label settings-span-2">SMS Delivery</div>
								<label class="settings-toggle settings-span-2" for="smsEnabled">
									<input id="smsEnabled" type="checkbox" name="sms_enabled" value="1" <?= app_setting_enabled($app_settings, 'sms_enabled') ? 'checked' : '' ?>>
									<span>Enable httpSMS</span>
								</label>
								<div class="form-group">
									<label class="form-label" for="httpsmsSenderNumber">httpSMS Sender Number</label>
									<input id="httpsmsSenderNumber" class="form-control" type="text" name="httpsms_sender_number" value="<?= settings_page_e($app_settings['httpsms_sender_number']) ?>" placeholder="+639171234567" autocomplete="tel">
								</div>
								<div class="form-group">
									<label class="form-label" for="smsDefaultCountryCode">Default Country Code</label>
									<input id="smsDefaultCountryCode" class="form-control" type="text" name="sms_default_country_code" value="<?= settings_page_e($app_settings['sms_default_country_code']) ?>" placeholder="+63" autocomplete="off">
								</div>
								<div class="form-group">
									<label class="form-label" for="httpsmsApiKey">httpSMS API Key</label>
									<input id="httpsmsApiKey" class="form-control" type="password" name="httpsms_api_key" value="" placeholder="<?= app_setting_has_secret($app_settings, 'httpsms_api_key') ? 'Saved - leave blank to keep' : 'API key' ?>" autocomplete="new-password">
								</div>
								<label class="settings-toggle" for="clearHttpsmsApiKey">
									<input id="clearHttpsmsApiKey" type="checkbox" name="clear_httpsms_api_key" value="1">
									<span>Clear saved API key</span>
								</label>
								<label class="settings-toggle" for="smsStatusUpdatesEnabled">
									<input id="smsStatusUpdatesEnabled" type="checkbox" name="sms_status_updates_enabled" value="1" <?= app_setting_enabled($app_settings, 'sms_status_updates_enabled') ? 'checked' : '' ?>>
									<span>Repair status updates</span>
								</label>
								<label class="settings-toggle" for="smsReceiptNotificationsEnabled">
									<input id="smsReceiptNotificationsEnabled" type="checkbox" name="sms_receipt_notifications_enabled" value="1" <?= app_setting_enabled($app_settings, 'sms_receipt_notifications_enabled') ? 'checked' : '' ?>>
									<span>Receipt email alerts</span>
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
							<?= csrf_input() ?>
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
</body>
</html>
