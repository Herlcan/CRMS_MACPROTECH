<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

include 'src/db/connection.php';
require_once __DIR__ . '/src/handlers/security_helpers.php';
require_once __DIR__ . '/src/handlers/password_reset_helpers.php';
require_once __DIR__ . '/src/handlers/asset_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$notice = '';
$error = '';
$identifier = '';

ensure_password_reset_tables($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {
    $identifier = trim($_POST['identifier'] ?? '');
    $retryAfterSeconds = 0;

    if (!verify_csrf_token()) {
        $error = 'Your form session expired. Please try again.';
    } elseif ($identifier === '') {
        $error = 'Enter your username or email address.';
    } elseif (password_reset_is_rate_limited($conn, $identifier, $retryAfterSeconds)) {
        $minutes = max(1, (int) ceil($retryAfterSeconds / 60));
        $error = "Too many reset requests. Try again in {$minutes} minute(s).";
    } else {
        $emailSent = false;
        $selector = bin2hex(random_bytes(8));
        $user = password_reset_find_user($conn, $identifier);

        if ($user && filter_var((string) ($user['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
            $token = null;
            try {
                $token = password_reset_create_otp($conn, (int) $user['id']);
                password_reset_send_otp_email($conn, $user, $token);
                $selector = (string) $token['selector'];
                $emailSent = true;
                log_security_event($conn, 'Password reset verification code sent for username ' . (string) $user['username'], null, (int) $user['id']);
            } catch (Throwable $e) {
                error_log('Password reset email failed: ' . $e->getMessage());
                if (is_array($token ?? null) && !empty($token['selector'])) {
                    $invalidate = mysqli_prepare($conn, "UPDATE password_resets SET used_at = NOW() WHERE selector = ?");
                    if ($invalidate) {
                        $failedSelector = (string) $token['selector'];
                        mysqli_stmt_bind_param($invalidate, "s", $failedSelector);
                        mysqli_stmt_execute($invalidate);
                        mysqli_stmt_close($invalidate);
                    }
                }
                log_security_event(
                    $conn,
                    'Password reset verification code email failed for username ' . (string) ($user['username'] ?? 'unknown'),
                    null,
                    isset($user['id']) ? (int) $user['id'] : null
                );
            }
        }

        password_reset_record_attempt($conn, $identifier, $emailSent);
        unset(
            $_SESSION['password_reset_verified_id'],
            $_SESSION['password_reset_verified_selector'],
            $_SESSION['password_reset_verified_at']
        );
        $_SESSION['password_reset_selector'] = $selector;
        $_SESSION['password_reset_notice'] = password_reset_public_message();

        header('Location: reset-password.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Forgot Password</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= asset_attr('src/images/apple-touch-icon.png'); ?>">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= asset_attr('src/images/favicon-192x192.png'); ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= asset_attr('src/images/favicon-32x32.png'); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= asset_attr('src/images/favicon-16x16.png'); ?>">
    <link rel="shortcut icon" href="<?= asset_attr('src/images/favicon.ico'); ?>">
    <link rel="stylesheet" type="text/css" href="<?= asset_attr('src/styles/style-improved.css'); ?>">
</head>

<body class="login-page">
    <main class="login-wrap">
        <section class="login-card" aria-label="MACPROTECH password reset request">
            <div class="login-brand-panel">
                <div class="login-brand-content">
                    <img class="login-brand-logo" src="<?= asset_attr('src/images/MACPROTECH_LOGO_SQUARE.png'); ?>" alt="MACPROTECH logo" decoding="async">
                    <p class="login-eyebrow">Account Recovery</p>
                    <h1>Reset your MACPROTECH password</h1>
                    <p class="login-brand-copy">Request a secure one-time code to set a new password for your staff, technician, or administrator account.</p>
                    <div class="login-brand-highlights" aria-label="Password reset safeguards">
                        <span>One-time code</span>
                        <span>Timed expiry</span>
                        <span>Secure email</span>
                    </div>
                </div>
            </div>

            <div class="login-form-panel">
                <form class="login-form" method="POST">
                    <?= csrf_input() ?>
                    <div class="login-form-header">
                        <p class="login-form-kicker">Forgot Password</p>
                        <h2>Request reset code</h2>
                        <p>Enter your username or email address to continue.</p>
                    </div>

                    <?php if ($notice !== ''): ?>
                        <div class="login-alert login-alert-success"><?= htmlspecialchars($notice) ?></div>
                    <?php endif; ?>

                    <?php if ($error !== ''): ?>
                        <div class="login-alert login-alert-error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <div class="login-field">
                        <label for="identifier">Username or email</label>
                        <div class="login-input-wrap">
                            <img src="<?= asset_attr('src/images/user-dark.png'); ?>" alt="" aria-hidden="true" decoding="async">
                            <input id="identifier" type="text" placeholder="Enter username or email" name="identifier" value="<?= htmlspecialchars($identifier, ENT_QUOTES, 'UTF-8') ?>" required autocomplete="username">
                        </div>
                    </div>

                    <button class="login-submit" type="submit" name="request_reset" value="1">
                        <img src="<?= asset_attr('src/images/sign-in-alt.png'); ?>" alt="" aria-hidden="true" decoding="async">
                        <span>Send reset code</span>
                    </button>

                    <div class="login-reset-actions">
                        <a class="login-admin-link" href="login.php">User Login</a>
                        <a class="login-admin-link" href="admin-login.php">Admin Login</a>
                    </div>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
