<?php
	error_reporting(E_ALL);
	ini_set('display_errors', '1');
	ini_set('display_startup_errors', '1'); 

	include 'src/db/connection.php';

// Ensure a session is started before reading/writing $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

	if (isset($_POST['login'])) {
		$username = trim($_POST['username']);
		$password = $_POST['password'];

		
		$stmt = mysqli_prepare($conn, "SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1");
		mysqli_stmt_bind_param($stmt, "s", $username);
		mysqli_stmt_execute($stmt);
		$result = mysqli_stmt_get_result($stmt);

		if (mysqli_num_rows($result) > 0) {

			$row = mysqli_fetch_assoc($result);

			if (password_verify($password, $row['password'])) {

				if ($row['role'] !== 'Administrator') {
					$error = "This login is for administrators only.";
				} else {
					// Prevent session fixation: generate new session id on successful login
					session_regenerate_id(true);

					$_SESSION['user_id'] = $row['id'];
					$_SESSION['username'] = $row['username'];
					$_SESSION['role'] = $row['role'];
					header("Location: index.php");
					exit();
				}

			} else {
				$error = "Invalid login password";
			}

		} else {
			$error = "Invalid login credentials";
		}

		if (isset($stmt) && is_object($stmt)) {
			mysqli_stmt_close($stmt);
		}
	}

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>Admin Login</title>
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
	<link rel="stylesheet" type="text/css" href="src/styles/style-improved.css">
</head>

<body class="login-page">
	<?php if (isset($error)): ?>
		<div class="login-error-alert">
			<div class="login-error-message">
				<i class="dw dw-info"></i> <?= htmlspecialchars($error) ?>
			</div>
		</div>
	<?php endif; ?>
	<div class="login-wrap d-flex align-items-center flex-wrap justify-content-center">
		<div class="container">
			<div class="row-login align-items-center">
				<div class="col-md-12">
					<div class="login-box bg-white box-shadow border-radius-10">
						<form method="POST">
							<div class="text-center mb-30">
								<h2 class="h2 text-primary">MACPROTECH</h2>
								<p class="h5 text-primary">Admin</p>
							</div>
							<div class="form-group">
								<label class="form-label">Username</label>
								<div class="input-group custom">
									<input type="text" class="form-control form-control-lg" placeholder="Enter username" name="username" required autocomplete="off">
									<div class="input-group-append custom">
										<span class="input-group-text"><img src="src/images/user-dark.png" style="width: 20px;"></span>
									</div>
								</div>
							</div>
							<div class="form-group">
								<label class="form-label">Password</label>
								<div class="input-group custom">
									<input type="password" class="form-control form-control-lg" placeholder="Enter password" name="password" required autocomplete="off">
									<div class="input-group-append custom">
										<span class="input-group-text"><img src="src/images/lock.png" style="width: 20px;"></span>
									</div>
								</div>
							</div>
							<div class="row-div-right pb-30">
								<div class="col-6" style="display: grid; place-items: rigth;">
									<div class="forgot-password"><a href="forgot-password.html">Forgot Password?</a></div>
								</div>
							</div>
							<div class="form-group">
								<input class="btn btn-primary btn-lg btn-block" type="submit" value="Login" name="login">
							</div>
							<div class="text-center">
								<a href="login.php">User Login</a>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
</body>
</html>