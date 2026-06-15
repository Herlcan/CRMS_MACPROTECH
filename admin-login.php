<?php
	error_reporting(E_ALL);
	ini_set('display_errors', '1');
	ini_set('display_startup_errors', '1'); 

	include 'src/db/connection.php';
	require_once __DIR__ . '/src/handlers/security_helpers.php';
	require_once __DIR__ . '/src/handlers/activity_log_helper.php';
	require_once __DIR__ . '/src/handlers/asset_helpers.php';

// Ensure a session is started before reading/writing $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

	if (isset($_POST['login'])) {
		$username = trim($_POST['username'] ?? '');
		$password = $_POST['password'] ?? '';
		$retryAfterSeconds = 0;

		if (!verify_csrf_token()) {
			$error = "Your login session expired. Please try again.";
		} elseif (login_is_rate_limited($conn, $username, $retryAfterSeconds)) {
			$minutes = max(1, (int) ceil($retryAfterSeconds / 60));
			$error = "Too many failed attempts. Try again in {$minutes} minute(s).";
		} else {
			$stmt = mysqli_prepare($conn, "SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1");
			mysqli_stmt_bind_param($stmt, "s", $username);
			mysqli_stmt_execute($stmt);
			$result = mysqli_stmt_get_result($stmt);

			if (mysqli_num_rows($result) > 0) {

				$row = mysqli_fetch_assoc($result);

				if (password_verify($password, $row['password'])) {

					if ($row['role'] !== 'Administrator') {
						record_login_attempt($conn, $username, false);
						log_activity($conn, "Failed administrator login attempt for non-admin account {$username}", null, (int) $row['id']);
						$error = "This login is for administrators only.";
					} else {
						record_login_attempt($conn, $username, true);
						// Prevent session fixation: generate new session id on successful login
						session_regenerate_id(true);

						$_SESSION['user_id'] = $row['id'];
						$_SESSION['username'] = $row['username'];
						$_SESSION['role'] = $row['role'];
						csrf_token();
						security_refresh_session_activity();
						log_activity($conn, "Administrator login success");
						header("Location: index.php");
						exit();
					}

				} else {
					record_login_attempt($conn, $username, false);
					log_activity($conn, "Failed administrator login attempt for username {$username}", null, (int) $row['id']);
					$error = "Invalid login password";
				}

			} else {
				record_login_attempt($conn, $username, false);
				$error = "Invalid login credentials";
			}

			if (isset($stmt) && is_object($stmt)) {
				mysqli_stmt_close($stmt);
			}

		}
	}

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>Admin Login</title>
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
	<link rel="apple-touch-icon" sizes="180x180" href="<?= asset_attr('src/images/apple-touch-icon.png'); ?>">
	<link rel="icon" type="image/png" sizes="192x192" href="<?= asset_attr('src/images/favicon-192x192.png'); ?>">
	<link rel="icon" type="image/png" sizes="32x32" href="<?= asset_attr('src/images/favicon-32x32.png'); ?>">
	<link rel="icon" type="image/png" sizes="16x16" href="<?= asset_attr('src/images/favicon-16x16.png'); ?>">
	<link rel="shortcut icon" href="<?= asset_attr('src/images/favicon.ico'); ?>">
	<link rel="stylesheet" type="text/css" href="<?= asset_attr('src/styles/style-improved.css'); ?>">
</head>

<body class="login-page">
	<?php if (isset($error)): ?>
		<div class="login-error-alert">
			<div class="login-error-message">
				<i class="dw dw-info"></i> <?= htmlspecialchars($error) ?>
			</div>
		</div>
	<?php endif; ?>
	<main class="login-wrap">
		<section class="login-card" aria-label="MACPROTECH admin login">
			<div class="login-brand-panel">
				<div class="login-brand-content">
					<img class="login-brand-logo" src="<?= asset_attr('src/images/MACPROTECH_LOGO_SQUARE.png'); ?>" alt="MACPROTECH logo" decoding="async">
					<p class="login-eyebrow">Administrator Workspace</p>
					<h1>Welcome back, Admin</h1>
					<p class="login-brand-copy">Sign in to oversee service operations, manage staff access, and keep MACPROTECH workflows moving securely.</p>
					<div class="login-brand-highlights" aria-label="Admin portal highlights">
						<span>Staff management</span>
						<span>System oversight</span>
						<span>Secure controls</span>
					</div>
				</div>
			</div>

			<div class="login-form-panel">
				<form class="login-form" method="POST">
					<?= csrf_input() ?>
					<div class="login-form-header">
						<p class="login-form-kicker">Admin Login</p>
						<h2>Access admin tools</h2>
						<p>Use your administrator account to continue.</p>
					</div>

					<div class="login-field">
						<label for="admin-username">Username</label>
						<div class="login-input-wrap">
							<img src="<?= asset_attr('src/images/user-dark.png'); ?>" alt="" aria-hidden="true" decoding="async">
							<input id="admin-username" type="text" placeholder="Enter username" name="username" required autocomplete="username">
						</div>
					</div>

					<div class="login-field">
						<label for="admin-password">Password</label>
						<div class="login-input-wrap">
							<img src="<?= asset_attr('src/images/lock.png'); ?>" alt="" aria-hidden="true" decoding="async">
							<input id="admin-password" type="password" placeholder="Enter password" name="password" required autocomplete="current-password">
						</div>
					</div>

					<div class="login-form-links">
						<span>Administrator access</span>
						<a href="forgot-password.php">Forgot Password?</a>
					</div>

					<button class="login-submit" type="submit" name="login" value="1">
						<img src="<?= asset_attr('src/images/sign-in-alt.png'); ?>" alt="" aria-hidden="true" decoding="async">
						<span>Login</span>
					</button>

					<a class="login-admin-link" href="login.php">User Login</a>
				</form>
			</div>
		</section>
	</main>
</body>
</html>
