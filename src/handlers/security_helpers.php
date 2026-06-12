<?php

require_once __DIR__ . '/db_helpers.php';

if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_input')) {
    function csrf_input(): string {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token(?string $token = null): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $sessionToken = $_SESSION['csrf_token'] ?? '';
        $submittedToken = $token ?? ($_POST['csrf_token'] ?? '');

        return is_string($sessionToken)
            && is_string($submittedToken)
            && $sessionToken !== ''
            && hash_equals($sessionToken, $submittedToken);
    }
}

if (!function_exists('current_user_role')) {
    function current_user_role(): string {
        return (string) ($_SESSION['role'] ?? '');
    }
}

if (!function_exists('user_has_role')) {
    function user_has_role($roles): bool {
        $allowedRoles = is_array($roles) ? $roles : [$roles];

        return in_array(current_user_role(), $allowedRoles, true);
    }
}

if (!function_exists('require_role')) {
    function require_role($roles, ?callable $onFailure = null): void {
        if (user_has_role($roles)) {
            return;
        }

        if ($onFailure) {
            $onFailure();
        }

        http_response_code(403);
        exit('Forbidden');
    }
}

if (!function_exists('json_response')) {
    function json_response(array $payload, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }
}

if (!function_exists('require_authenticated_json')) {
    function require_authenticated_json(mysqli $conn): array {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            json_response(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $statement = mysqli_prepare(
            $conn,
            "SELECT id, username, role, first_name, last_name FROM users WHERE id = ? LIMIT 1"
        );

        if (!$statement) {
            json_response(['success' => false, 'message' => 'Unable to verify your session.'], 500);
        }

        mysqli_stmt_bind_param($statement, "i", $userId);
        mysqli_stmt_execute($statement);
        $result = mysqli_stmt_get_result($statement);
        $user = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($statement);

        if (!$user) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }
            session_destroy();
            json_response(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['first_name'] = $user['first_name'] ?? '';
        $_SESSION['last_name'] = $user['last_name'] ?? '';
        csrf_token();

        return $user;
    }
}

if (!function_exists('require_json_role')) {
    function require_json_role($roles, string $message = 'Forbidden'): void {
        if (user_has_role($roles)) {
            return;
        }

        json_response(['success' => false, 'message' => $message], 403);
    }
}

if (!function_exists('require_authenticated_fragment')) {
    function require_authenticated_fragment(mysqli $conn): array {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            http_response_code(401);
            exit('Unauthorized');
        }

        $statement = mysqli_prepare(
            $conn,
            "SELECT id, username, role, first_name, last_name FROM users WHERE id = ? LIMIT 1"
        );

        if (!$statement) {
            http_response_code(500);
            exit('Unable to verify session.');
        }

        mysqli_stmt_bind_param($statement, "i", $userId);
        mysqli_stmt_execute($statement);
        $result = mysqli_stmt_get_result($statement);
        $user = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($statement);

        if (!$user) {
            http_response_code(401);
            exit('Unauthorized');
        }

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['first_name'] = $user['first_name'] ?? '';
        $_SESSION['last_name'] = $user['last_name'] ?? '';
        csrf_token();

        return $user;
    }
}

if (!function_exists('password_policy_errors')) {
    function password_policy_errors(string $password): array {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'at least 8 characters';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'one uppercase letter';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'one lowercase letter';
        }

        if (!preg_match('/\d/', $password)) {
            $errors[] = 'one number';
        }

        return $errors;
    }
}

if (!function_exists('password_policy_message')) {
    function password_policy_message(string $password): string {
        $errors = password_policy_errors($password);

        if (empty($errors)) {
            return '';
        }

        return 'Password must include ' . implode(', ', $errors) . '.';
    }
}

if (!function_exists('security_client_ip')) {
    function security_client_ip(): string {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);
    }
}

if (!function_exists('ensure_login_attempts_table')) {
    function ensure_login_attempts_table(mysqli $conn): void {
        $sql = "
            CREATE TABLE IF NOT EXISTS login_attempts (
                id int(11) NOT NULL AUTO_INCREMENT,
                username varchar(100) NOT NULL,
                ip_address varchar(45) NOT NULL,
                success tinyint(1) NOT NULL DEFAULT 0,
                attempted_at datetime NOT NULL DEFAULT current_timestamp(),
                lock_until datetime DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_login_attempts_lookup (username, ip_address, success, attempted_at),
                KEY idx_login_attempts_lock (username, ip_address, lock_until)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ";

        db_execute_statement($conn, $sql);
    }
}

if (!function_exists('login_is_rate_limited')) {
    function login_is_rate_limited(mysqli $conn, string $username, int &$retryAfterSeconds = 0): bool {
        ensure_login_attempts_table($conn);

        $normalizedUsername = strtolower(trim($username));
        $ipAddress = security_client_ip();
        $retryAfterSeconds = 0;

        $cleanup = mysqli_prepare($conn, "DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
        if ($cleanup) {
            mysqli_stmt_execute($cleanup);
            mysqli_stmt_close($cleanup);
        }

        $query = mysqli_prepare(
            $conn,
            "SELECT TIMESTAMPDIFF(SECOND, NOW(), MAX(lock_until)) AS retry_after
             FROM login_attempts
             WHERE username = ? AND ip_address = ? AND lock_until > NOW()"
        );

        if (!$query) {
            return false;
        }

        mysqli_stmt_bind_param($query, "ss", $normalizedUsername, $ipAddress);
        mysqli_stmt_execute($query);
        $result = mysqli_stmt_get_result($query);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($query);

        $retryAfterSeconds = max(0, (int) ($row['retry_after'] ?? 0));

        return $retryAfterSeconds > 0;
    }
}

if (!function_exists('record_login_attempt')) {
    function record_login_attempt(mysqli $conn, string $username, bool $success): void {
        ensure_login_attempts_table($conn);

        $normalizedUsername = strtolower(trim($username));
        $ipAddress = security_client_ip();
        $successInt = $success ? 1 : 0;

        if ($success) {
            $delete = mysqli_prepare($conn, "DELETE FROM login_attempts WHERE username = ? AND ip_address = ? AND success = 0");
            if ($delete) {
                mysqli_stmt_bind_param($delete, "ss", $normalizedUsername, $ipAddress);
                mysqli_stmt_execute($delete);
                mysqli_stmt_close($delete);
            }
        }

        $insert = mysqli_prepare(
            $conn,
            "INSERT INTO login_attempts (username, ip_address, success) VALUES (?, ?, ?)"
        );

        if ($insert) {
            mysqli_stmt_bind_param($insert, "ssi", $normalizedUsername, $ipAddress, $successInt);
            mysqli_stmt_execute($insert);
            mysqli_stmt_close($insert);
        }

        if ($success) {
            return;
        }

        $countQuery = mysqli_prepare(
            $conn,
            "SELECT COUNT(*) AS total
             FROM login_attempts
             WHERE username = ? AND ip_address = ? AND success = 0
             AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
        );

        if (!$countQuery) {
            return;
        }

        mysqli_stmt_bind_param($countQuery, "ss", $normalizedUsername, $ipAddress);
        mysqli_stmt_execute($countQuery);
        $result = mysqli_stmt_get_result($countQuery);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($countQuery);

        if ((int) ($row['total'] ?? 0) < 5) {
            return;
        }

        $lock = mysqli_prepare(
            $conn,
            "UPDATE login_attempts
             SET lock_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE)
             WHERE username = ? AND ip_address = ? AND success = 0
             AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
        );

        if ($lock) {
            mysqli_stmt_bind_param($lock, "ss", $normalizedUsername, $ipAddress);
            mysqli_stmt_execute($lock);
            mysqli_stmt_close($lock);
        }
    }
}

?>
