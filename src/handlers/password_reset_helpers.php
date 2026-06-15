<?php

require_once __DIR__ . '/db_helpers.php';
require_once __DIR__ . '/settings_helpers.php';
require_once __DIR__ . '/communication_helpers.php';
require_once __DIR__ . '/activity_log_helper.php';
require_once __DIR__ . '/../../vendor/PHPMailer-master/src/Exception.php';
require_once __DIR__ . '/../../vendor/PHPMailer-master/src/PHPMailer.php';
require_once __DIR__ . '/../../vendor/PHPMailer-master/src/SMTP.php';

if (!function_exists('ensure_password_reset_tables')) {
    function ensure_password_reset_tables(mysqli $conn): void {
        db_execute_statement($conn, "
            CREATE TABLE IF NOT EXISTS password_resets (
                id int(11) NOT NULL AUTO_INCREMENT,
                user_id int(11) NOT NULL,
                selector char(16) NOT NULL,
                token_hash char(64) NOT NULL,
                expires_at datetime NOT NULL,
                used_at datetime DEFAULT NULL,
                verify_attempts int(11) NOT NULL DEFAULT 0,
                locked_at datetime DEFAULT NULL,
                created_at datetime NOT NULL DEFAULT current_timestamp(),
                request_ip varchar(45) NOT NULL,
                user_agent varchar(255) DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_password_resets_selector (selector),
                KEY idx_password_resets_user_active (user_id, used_at, expires_at),
                CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        if (!db_table_column_exists($conn, 'password_resets', 'verify_attempts')) {
            db_execute_statement($conn, "ALTER TABLE password_resets ADD COLUMN verify_attempts int(11) NOT NULL DEFAULT 0 AFTER used_at");
        }

        if (!db_table_column_exists($conn, 'password_resets', 'locked_at')) {
            db_execute_statement($conn, "ALTER TABLE password_resets ADD COLUMN locked_at datetime DEFAULT NULL AFTER verify_attempts");
        }

        db_execute_statement($conn, "
            CREATE TABLE IF NOT EXISTS password_reset_attempts (
                id int(11) NOT NULL AUTO_INCREMENT,
                identifier varchar(190) NOT NULL,
                ip_address varchar(45) NOT NULL,
                success tinyint(1) NOT NULL DEFAULT 0,
                requested_at datetime NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (id),
                KEY idx_password_reset_attempts_lookup (identifier, ip_address, requested_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }
}

if (!function_exists('password_reset_normalize_identifier')) {
    function password_reset_normalize_identifier(string $identifier): string {
        return substr(strtolower(trim($identifier)), 0, 190);
    }
}

if (!function_exists('password_reset_public_message')) {
    function password_reset_public_message(): string {
        return 'If an account exists for that username or email, a verification code has been sent.';
    }
}

if (!function_exists('password_reset_client_ip')) {
    function password_reset_client_ip(): string {
        if (function_exists('security_client_ip')) {
            return security_client_ip();
        }

        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);
    }
}

if (!function_exists('password_reset_is_rate_limited')) {
    function password_reset_is_rate_limited(mysqli $conn, string $identifier, int &$retryAfterSeconds = 0): bool {
        ensure_password_reset_tables($conn);

        $identifier = password_reset_normalize_identifier($identifier);
        $ipAddress = password_reset_client_ip();
        $retryAfterSeconds = 0;

        $cleanup = mysqli_prepare($conn, "DELETE FROM password_reset_attempts WHERE requested_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
        if ($cleanup) {
            mysqli_stmt_execute($cleanup);
            mysqli_stmt_close($cleanup);
        }

        $query = mysqli_prepare(
            $conn,
            "SELECT COUNT(*) AS total, TIMESTAMPDIFF(SECOND, MIN(requested_at), NOW()) AS oldest_age
             FROM password_reset_attempts
             WHERE identifier = ? AND ip_address = ?
             AND requested_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
        );

        if (!$query) {
            return false;
        }

        mysqli_stmt_bind_param($query, "ss", $identifier, $ipAddress);
        mysqli_stmt_execute($query);
        $result = mysqli_stmt_get_result($query);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($query);

        $total = (int) ($row['total'] ?? 0);
        if ($total < 5) {
            return false;
        }

        $oldestAge = max(0, (int) ($row['oldest_age'] ?? 0));
        $retryAfterSeconds = max(60, 900 - $oldestAge);

        return true;
    }
}

if (!function_exists('password_reset_record_attempt')) {
    function password_reset_record_attempt(mysqli $conn, string $identifier, bool $success): void {
        ensure_password_reset_tables($conn);

        $identifier = password_reset_normalize_identifier($identifier);
        $ipAddress = password_reset_client_ip();
        $successInt = $success ? 1 : 0;

        $insert = mysqli_prepare(
            $conn,
            "INSERT INTO password_reset_attempts (identifier, ip_address, success) VALUES (?, ?, ?)"
        );

        if (!$insert) {
            return;
        }

        mysqli_stmt_bind_param($insert, "ssi", $identifier, $ipAddress, $successInt);
        mysqli_stmt_execute($insert);
        mysqli_stmt_close($insert);
    }
}

if (!function_exists('password_reset_find_user')) {
    function password_reset_find_user(mysqli $conn, string $identifier): ?array {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        $query = mysqli_prepare(
            $conn,
            "SELECT id, username, first_name, last_name, email, role
             FROM users
             WHERE username = ? OR email = ?
             LIMIT 1"
        );

        if (!$query) {
            return null;
        }

        mysqli_stmt_bind_param($query, "ss", $identifier, $identifier);
        mysqli_stmt_execute($query);
        $result = mysqli_stmt_get_result($query);
        $user = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($query);

        return $user ?: null;
    }
}

if (!function_exists('password_reset_user_name')) {
    function password_reset_user_name(array $user): string {
        $name = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        return trim((string) ($user['username'] ?? 'User')) ?: 'User';
    }
}

if (!function_exists('password_reset_html')) {
    function password_reset_html(?string $value): string {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('password_reset_generate_otp')) {
    function password_reset_generate_otp(): string {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('password_reset_normalize_otp')) {
    function password_reset_normalize_otp(string $otp): string {
        return substr(preg_replace('/\D+/', '', $otp), 0, 6);
    }
}

if (!function_exists('password_reset_token_hash')) {
    function password_reset_token_hash(string $selector, string $secret): string {
        return hash_hmac('sha256', strtolower($selector) . '|' . $secret, app_settings_crypto_key());
    }
}

if (!function_exists('password_reset_create_otp')) {
    function password_reset_create_otp(mysqli $conn, int $userId, int $expiresMinutes = 10): array {
        ensure_password_reset_tables($conn);

        $invalidate = mysqli_prepare(
            $conn,
            "UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL"
        );
        if ($invalidate) {
            mysqli_stmt_bind_param($invalidate, "i", $userId);
            mysqli_stmt_execute($invalidate);
            mysqli_stmt_close($invalidate);
        }

        $requestIp = password_reset_client_ip();
        $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $selector = bin2hex(random_bytes(8));
            $otp = password_reset_generate_otp();
            $tokenHash = password_reset_token_hash($selector, $otp);

            $insert = mysqli_prepare(
                $conn,
                "INSERT INTO password_resets (user_id, selector, token_hash, expires_at, request_ip, user_agent)
                 VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL {$expiresMinutes} MINUTE), ?, ?)"
            );

            if (!$insert) {
                throw new Exception('Unable to create password reset token.');
            }

            mysqli_stmt_bind_param($insert, "issss", $userId, $selector, $tokenHash, $requestIp, $userAgent);
            $inserted = mysqli_stmt_execute($insert);
            $errno = mysqli_stmt_errno($insert);
            $error = mysqli_stmt_error($insert);
            mysqli_stmt_close($insert);

            if ($inserted) {
                return [
                    'selector' => $selector,
                    'otp' => $otp,
                    'expires_minutes' => $expiresMinutes
                ];
            }

            if ($errno !== 1062) {
                throw new Exception('Unable to save password reset token: ' . $error);
            }
        }

        throw new Exception('Unable to create a unique password reset token.');
    }
}

if (!function_exists('password_reset_send_otp_email')) {
    function password_reset_send_otp_email(mysqli $conn, array $user, array $token): void {
        $settings = get_app_settings($conn);
        $businessName = trim((string) ($settings['business_name'] ?? '')) ?: 'MACPROTECH Computer Repair Services';
        $recipientName = password_reset_user_name($user);
        $otp = (string) ($token['otp'] ?? '');
        $expiresMinutes = (int) ($token['expires_minutes'] ?? 60);
        $safeBusinessName = password_reset_html($businessName);
        $safeRecipientName = password_reset_html($recipientName);
        $safeOtp = password_reset_html($otp);

        $body = '
            <!doctype html>
            <html lang="en">
            <head>
                <meta charset="utf-8">
                <style>
                    body { font-family: Arial, sans-serif; color: #111827; line-height: 1.5; }
                    .wrap { max-width: 640px; margin: 0 auto; }
                    .header { border-bottom: 2px solid #111827; padding-bottom: 14px; margin-bottom: 18px; }
                    .code { display: inline-block; padding: 14px 20px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; color: #123f8c; font-size: 28px; font-weight: 700; }
                    .muted { color: #6b7280; font-size: 13px; }
                </style>
            </head>
            <body>
                <div class="wrap">
                    <div class="header">
                        <h2>' . $safeBusinessName . ' Password Reset</h2>
                    </div>
                    <p>Hello <strong>' . $safeRecipientName . '</strong>,</p>
                    <p>We received a request to reset your MACPROTECH account password.</p>
                    <p>Enter this verification code in MACPROTECH:</p>
                    <p><span class="code">' . $safeOtp . '</span></p>
                    <p>This code expires in ' . $expiresMinutes . ' minutes and can only be used once.</p>
                    <p class="muted">If you did not request this reset, you can ignore this email and your password will stay unchanged.</p>
                </div>
            </body>
            </html>
        ';

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        configure_app_mailer($mail, $settings, $businessName);
        $mail->addAddress((string) $user['email'], $recipientName);
        $mail->isHTML(true);
        $mail->Subject = $businessName . ' Password Reset';
        $mail->Body = $body;
        $mail->AltBody = $businessName . " Password Reset\n\n"
            . "Hello {$recipientName},\n\n"
            . "Enter this verification code in MACPROTECH: {$otp}\n\n"
            . "This code expires in {$expiresMinutes} minutes and can only be used once.\n"
            . "If you did not request this reset, ignore this email.";

        $mail->send();
    }
}

if (!function_exists('password_reset_valid_otp_format')) {
    function password_reset_valid_otp_format(string $selector, string $otp): bool {
        return (bool) preg_match('/^[a-f0-9]{16}$/', $selector)
            && (bool) preg_match('/^\d{6}$/', $otp);
    }
}

if (!function_exists('password_reset_record_invalid_otp')) {
    function password_reset_record_invalid_otp(mysqli $conn, int $resetId, int $currentAttempts): void {
        $nextAttempts = $currentAttempts + 1;
        $query = mysqli_prepare(
            $conn,
            "UPDATE password_resets
             SET verify_attempts = ?,
                 locked_at = IF(? >= 5, NOW(), locked_at),
                 used_at = IF(? >= 5, NOW(), used_at)
             WHERE id = ?"
        );

        if (!$query) {
            return;
        }

        mysqli_stmt_bind_param($query, "iiii", $nextAttempts, $nextAttempts, $nextAttempts, $resetId);
        mysqli_stmt_execute($query);
        mysqli_stmt_close($query);
    }
}

if (!function_exists('password_reset_find_valid_otp')) {
    function password_reset_find_valid_otp(mysqli $conn, string $selector, string $otp): ?array {
        ensure_password_reset_tables($conn);

        $selector = strtolower(trim($selector));
        $otp = password_reset_normalize_otp($otp);
        if (!password_reset_valid_otp_format($selector, $otp)) {
            return null;
        }

        $query = mysqli_prepare(
            $conn,
            "SELECT
                pr.id AS reset_id,
                pr.user_id,
                pr.token_hash,
                pr.expires_at,
                pr.verify_attempts,
                u.username,
                u.first_name,
                u.last_name,
                u.email,
                u.role
             FROM password_resets pr
             INNER JOIN users u ON u.id = pr.user_id
             WHERE pr.selector = ?
             AND pr.used_at IS NULL
             AND pr.expires_at > NOW()
             AND pr.locked_at IS NULL
             AND pr.verify_attempts < 5
             LIMIT 1"
        );

        if (!$query) {
            return null;
        }

        mysqli_stmt_bind_param($query, "s", $selector);
        mysqli_stmt_execute($query);
        $result = mysqli_stmt_get_result($query);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($query);

        if (!$row) {
            return null;
        }

        $submittedHash = password_reset_token_hash($selector, $otp);
        if (!hash_equals((string) $row['token_hash'], $submittedHash)) {
            password_reset_record_invalid_otp($conn, (int) $row['reset_id'], (int) ($row['verify_attempts'] ?? 0));
            return null;
        }

        return $row;
    }
}

if (!function_exists('password_reset_find_active_reset')) {
    function password_reset_find_active_reset(mysqli $conn, int $resetId): ?array {
        ensure_password_reset_tables($conn);

        if ($resetId <= 0) {
            return null;
        }

        $query = mysqli_prepare(
            $conn,
            "SELECT
                pr.id AS reset_id,
                pr.user_id,
                pr.selector,
                pr.expires_at,
                pr.verify_attempts,
                u.username,
                u.first_name,
                u.last_name,
                u.email,
                u.role
             FROM password_resets pr
             INNER JOIN users u ON u.id = pr.user_id
             WHERE pr.id = ?
             AND pr.used_at IS NULL
             AND pr.expires_at > NOW()
             AND pr.locked_at IS NULL
             AND pr.verify_attempts < 5
             LIMIT 1"
        );

        if (!$query) {
            return null;
        }

        mysqli_stmt_bind_param($query, "i", $resetId);
        mysqli_stmt_execute($query);
        $result = mysqli_stmt_get_result($query);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($query);

        return $row ?: null;
    }
}

if (!function_exists('password_reset_complete')) {
    function password_reset_complete(mysqli $conn, array $resetRecord, string $newPassword): void {
        $userId = (int) $resetRecord['user_id'];
        $resetId = (int) $resetRecord['reset_id'];
        $username = (string) $resetRecord['username'];
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        mysqli_begin_transaction($conn);

        try {
            $updateUser = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
            if (!$updateUser) {
                throw new Exception('Unable to update password.');
            }
            mysqli_stmt_bind_param($updateUser, "si", $hashedPassword, $userId);
            if (!mysqli_stmt_execute($updateUser)) {
                $error = mysqli_stmt_error($updateUser);
                mysqli_stmt_close($updateUser);
                throw new Exception('Unable to update password: ' . $error);
            }
            mysqli_stmt_close($updateUser);

            $markUsed = mysqli_prepare(
                $conn,
                "UPDATE password_resets SET used_at = NOW() WHERE id = ? AND used_at IS NULL"
            );
            if (!$markUsed) {
                throw new Exception('Unable to finalize reset token.');
            }
            mysqli_stmt_bind_param($markUsed, "i", $resetId);
            mysqli_stmt_execute($markUsed);
            $affected = mysqli_stmt_affected_rows($markUsed);
            mysqli_stmt_close($markUsed);

            if ($affected < 1) {
                throw new Exception('This password reset code has already been used.');
            }

            $invalidate = mysqli_prepare(
                $conn,
                "UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL"
            );
            if ($invalidate) {
                mysqli_stmt_bind_param($invalidate, "i", $userId);
                mysqli_stmt_execute($invalidate);
                mysqli_stmt_close($invalidate);
            }

            if (function_exists('ensure_login_attempts_table')) {
                ensure_login_attempts_table($conn);
            }
            $clearAttempts = mysqli_prepare($conn, "DELETE FROM login_attempts WHERE username = ? AND success = 0");
            if ($clearAttempts) {
                mysqli_stmt_bind_param($clearAttempts, "s", $username);
                mysqli_stmt_execute($clearAttempts);
                mysqli_stmt_close($clearAttempts);
            }

            log_security_event($conn, "Password reset completed for username {$username}", null, $userId);
            mysqli_commit($conn);
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            throw $e;
        }
    }
}

?>
