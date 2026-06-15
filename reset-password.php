<?php
error_reporting(E_ALL);
ini_set('display_errors', getenv('APP_ENV') === 'production' ? '0' : '1');
ini_set('display_startup_errors', getenv('APP_ENV') === 'production' ? '0' : '1');

include 'src/db/connection.php';
require_once __DIR__ . '/src/handlers/security_helpers.php';
require_once __DIR__ . '/src/handlers/password_reset_helpers.php';
require_once __DIR__ . '/src/handlers/asset_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$sessionSelector = trim((string) ($_SESSION['password_reset_selector'] ?? ''));
$selector = trim((string) ($_POST['selector'] ?? $sessionSelector));
$error = '';
$notice = (string) ($_SESSION['password_reset_notice'] ?? '');
$resetComplete = false;
$verifiedResetId = (int) ($_SESSION['password_reset_verified_id'] ?? 0);
$verifiedSelector = trim((string) ($_SESSION['password_reset_verified_selector'] ?? ''));
$verifiedAt = (int) ($_SESSION['password_reset_verified_at'] ?? 0);
$otpVerified = $verifiedResetId > 0
    && $selector !== ''
    && $verifiedSelector !== ''
    && hash_equals($verifiedSelector, $selector)
    && $verifiedAt > 0
    && (time() - $verifiedAt) <= 600;

ensure_password_reset_tables($conn);
unset($_SESSION['password_reset_notice']);

$verifiedResetRecord = $otpVerified ? password_reset_find_active_reset($conn, $verifiedResetId) : null;
if (!$verifiedResetRecord) {
    $otpVerified = false;
    unset($_SESSION['password_reset_verified_id'], $_SESSION['password_reset_verified_selector'], $_SESSION['password_reset_verified_at']);
}

$canVerifyOtp = $selector !== '' && $sessionSelector !== '' && !$otpVerified;
$canSetPassword = $otpVerified && $verifiedResetRecord !== null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $otpCode = password_reset_normalize_otp((string) ($_POST['otp_code'] ?? ''));
    $selectorMatchesSession = $sessionSelector !== '' && hash_equals($sessionSelector, $selector);

    if (!verify_csrf_token()) {
        $error = 'Your form session expired. Please try again.';
    } elseif (!$selectorMatchesSession) {
        $error = 'This reset session is invalid or expired. Request a new password reset code.';
        $canVerifyOtp = false;
    } elseif ($otpCode === '') {
        $error = 'Enter the verification code sent to your email.';
    } else {
        $resetRecord = password_reset_find_valid_otp($conn, $selector, $otpCode);
        if (!$resetRecord) {
            $error = 'The verification code is invalid, expired, or already used. Request a new code if needed.';
        } else {
            $_SESSION['password_reset_verified_id'] = (int) $resetRecord['reset_id'];
            $_SESSION['password_reset_verified_selector'] = $selector;
            $_SESSION['password_reset_verified_at'] = time();

            $verifiedResetRecord = $resetRecord;
            $otpVerified = true;
            $canVerifyOtp = false;
            $canSetPassword = true;
            $notice = 'Code verified. Create your new password.';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (!verify_csrf_token()) {
        $error = 'Your form session expired. Please try again.';
    } elseif (!$canSetPassword) {
        $error = 'Verify your email code before setting a new password.';
    } elseif ($newPassword === '' || $confirmPassword === '') {
        $error = 'Enter and confirm your new password.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New password and confirmation do not match.';
    } elseif (password_policy_message($newPassword) !== '') {
        $error = password_policy_message($newPassword);
    } else {
        try {
            password_reset_complete($conn, $verifiedResetRecord, $newPassword);
            $resetComplete = true;
            $notice = 'Your password has been reset. You can now sign in with your new password.';
            $selector = '';
            $canVerifyOtp = false;
            $canSetPassword = false;
            unset(
                $_SESSION['password_reset_selector'],
                $_SESSION['password_reset_verified_id'],
                $_SESSION['password_reset_verified_selector'],
                $_SESSION['password_reset_verified_at']
            );
        } catch (Throwable $e) {
            error_log('Password reset completion failed: ' . $e->getMessage());
            $error = 'Unable to reset your password. Request a new reset code and try again.';
        }
    }
}

if (!$resetComplete && !$canVerifyOtp && !$canSetPassword && $error === '') {
    $error = 'This reset session is invalid or expired. Request a new password reset code.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Reset Password</title>
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
        <section class="login-card" aria-label="MACPROTECH password reset">
            <div class="login-brand-panel">
                <div class="login-brand-content">
                    <img class="login-brand-logo" src="<?= asset_attr('src/images/MACPROTECH_LOGO_SQUARE.png'); ?>" alt="MACPROTECH logo" decoding="async">
                    <p class="login-eyebrow">Secure Reset</p>
                    <h1>Create a new account password</h1>
                    <p class="login-brand-copy">Verify the email code first, then choose a strong password that only you know.</p>
                    <div class="login-brand-highlights" aria-label="Password reset safeguards">
                        <span>Verify code</span>
                        <span>Set password</span>
                        <span>One-time use</span>
                    </div>
                </div>
            </div>

            <div class="login-form-panel">
                <form class="login-form" method="POST">
                    <?= csrf_input() ?>
                    <input type="hidden" name="selector" value="<?= htmlspecialchars($selector, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="login-form-header">
                        <p class="login-form-kicker">Reset Password</p>
                        <h2><?= $resetComplete ? 'Password updated' : ($canSetPassword ? 'Set new password' : 'Verify code') ?></h2>
                        <p><?= $resetComplete ? 'Return to the correct portal and sign in again.' : ($canSetPassword ? 'Enter and confirm your new password.' : 'Enter the code from your email to continue.') ?></p>
                    </div>

                    <?php if ($notice !== ''): ?>
                        <div class="login-alert login-alert-success"><?= htmlspecialchars($notice) ?></div>
                    <?php endif; ?>

                    <?php if ($error !== ''): ?>
                        <div class="login-alert login-alert-error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <?php if ($canVerifyOtp && !$resetComplete): ?>
                        <div class="login-field">
                            <label for="otp-code">Verification code</label>
                            <div class="login-input-wrap">
                                <img src="<?= asset_attr('src/images/lock.png'); ?>" alt="" aria-hidden="true" decoding="async">
                                <input id="otp-code" type="text" placeholder="Enter 6-digit code" name="otp_code" required inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="one-time-code">
                            </div>
                        </div>

                        <button class="login-submit" type="submit" name="verify_otp" value="1">
                            <img src="<?= asset_attr('src/images/sign-in-alt.png'); ?>" alt="" aria-hidden="true" decoding="async">
                            <span>Verify code</span>
                        </button>
                    <?php endif; ?>

                    <?php if ($canSetPassword && !$resetComplete): ?>
                        <div class="login-field">
                            <label for="new-password">New password</label>
                            <div class="login-input-wrap">
                                <img src="<?= asset_attr('src/images/lock.png'); ?>" alt="" aria-hidden="true" decoding="async">
                                <input id="new-password" type="password" placeholder="Enter new password" name="new_password" required autocomplete="new-password">
                            </div>
                        </div>

                        <div class="login-field">
                            <label for="confirm-password">Confirm password</label>
                            <div class="login-input-wrap">
                                <img src="<?= asset_attr('src/images/lock.png'); ?>" alt="" aria-hidden="true" decoding="async">
                                <input id="confirm-password" type="password" placeholder="Confirm new password" name="confirm_password" required autocomplete="new-password">
                            </div>
                        </div>

                        <button class="login-submit" type="submit" name="reset_password" value="1">
                            <img src="<?= asset_attr('src/images/sign-in-alt.png'); ?>" alt="" aria-hidden="true" decoding="async">
                            <span>Update password</span>
                        </button>
                    <?php endif; ?>

                    <div class="login-reset-actions">
                        <?php if (!$resetComplete): ?>
                            <a class="login-admin-link" href="forgot-password.php">Request new code</a>
                        <?php endif; ?>
                        <a class="login-admin-link" href="login.php">User Login</a>
                        <a class="login-admin-link" href="admin-login.php">Admin Login</a>
                    </div>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
