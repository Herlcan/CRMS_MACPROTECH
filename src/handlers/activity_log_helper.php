<?php

if (!function_exists('activity_log_clean_action')) {
    function activity_log_clean_action(string $action): string {
        $action = trim(preg_replace('/\s+/', ' ', $action));

        if ($action === '') {
            return '';
        }

        if (strlen($action) > 255) {
            return substr($action, 0, 252) . '...';
        }

        return $action;
    }
}

if (!function_exists('log_activity')) {
    function log_activity(mysqli $conn, string $action, ?int $work_order_id = null, ?int $user_id = null): bool {
        $action = activity_log_clean_action($action);

        if ($action === '') {
            return false;
        }

        $user_id = $user_id ?? (int) ($_SESSION['user_id'] ?? 0);
        if ($user_id <= 0) {
            return false;
        }

        $work_order_id = ($work_order_id !== null && $work_order_id > 0) ? $work_order_id : 0;

        try {
            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO activity_logs (user_id, work_order_id, action) VALUES (?, ?, ?)"
            );

            if (!$stmt) {
                error_log('Activity log prepare failed: ' . mysqli_error($conn));
                return false;
            }

            mysqli_stmt_bind_param($stmt, "iis", $user_id, $work_order_id, $action);
            $logged = mysqli_stmt_execute($stmt);

            if (!$logged) {
                error_log('Activity log insert failed: ' . mysqli_stmt_error($stmt));
            }

            mysqli_stmt_close($stmt);
            return $logged;
        } catch (Throwable $e) {
            error_log('Activity log error: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('log_security_event')) {
    function log_security_event(mysqli $conn, string $action, ?int $work_order_id = null, ?int $user_id = null): bool {
        $action = activity_log_clean_action($action);

        if ($action === '') {
            return false;
        }

        $user_id = $user_id ?? (int) ($_SESSION['user_id'] ?? 0);
        $user_id = max(0, (int) $user_id);
        $work_order_id = ($work_order_id !== null && $work_order_id > 0) ? $work_order_id : 0;

        try {
            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO activity_logs (user_id, work_order_id, action) VALUES (?, ?, ?)"
            );

            if (!$stmt) {
                error_log('Security event log prepare failed: ' . mysqli_error($conn));
                return false;
            }

            mysqli_stmt_bind_param($stmt, "iis", $user_id, $work_order_id, $action);
            $logged = mysqli_stmt_execute($stmt);

            if (!$logged) {
                error_log('Security event log insert failed: ' . mysqli_stmt_error($stmt));
            }

            mysqli_stmt_close($stmt);
            return $logged;
        } catch (Throwable $e) {
            error_log('Security event log error: ' . $e->getMessage());
            return false;
        }
    }
}

?>
