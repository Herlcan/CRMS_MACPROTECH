<?php

    include '../db/connection.php';
    include '../../auth_check.php';

    function clientDeleteTableExists(mysqli $conn, string $table): bool {
        $table = mysqli_real_escape_string($conn, $table);
        $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
        return $result && mysqli_num_rows($result) > 0;
    }

    function executeClientDeleteStatement(mysqli $conn, string $sql, int $client_id, string $error_message): void {
        $statement = mysqli_prepare($conn, $sql);

        if (!$statement) {
            throw new Exception('Database error: ' . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($statement, "i", $client_id);

        if (!mysqli_stmt_execute($statement)) {
            $statement_error = mysqli_stmt_error($statement);
            mysqli_stmt_close($statement);
            throw new Exception($error_message . ': ' . $statement_error);
        }

        mysqli_stmt_close($statement);
    }

    function clientDeletePaidPaymentCondition(string $paymentAlias = 'p'): string {
        return "(
            COALESCE($paymentAlias.amount_paid, 0) > 0
            OR COALESCE(
                NULLIF($paymentAlias.payment_status, ''),
                CASE
                    WHEN $paymentAlias.status IS NULL OR $paymentAlias.status = 'Pending' OR $paymentAlias.status = '' THEN 'Unpaid'
                    ELSE $paymentAlias.status
                END
            ) IN ('Paid', 'Partial', 'Partially Refunded', 'Refunded')
        )";
    }

    if (!function_exists('redirectClientWithDialog')) {
        function redirectClientWithDialog($type, $title, $message) {
            $_SESSION['dialog_flash'] = [
                'type' => $type,
                'title' => $title,
                'message' => $message
            ];
            header("Location: ../../clients.php");
            exit();
        }
    }

    if($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
        $client_id = intval($_GET['id']);
        $transactionStarted = false;

        if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Administrator') {
            redirectClientWithDialog('error', 'Unauthorized', 'Only administrators can delete customers.');
        }

        if ($client_id <= 0) {
            redirectClientWithDialog('error', 'Customer Not Deleted', 'Invalid customer ID.');
        }

        try {
            $verify_query = mysqli_prepare($conn, "SELECT id FROM client WHERE id = ? LIMIT 1");
            if (!$verify_query) {
                throw new Exception('Database error: ' . mysqli_error($conn));
            }

            mysqli_stmt_bind_param($verify_query, "i", $client_id);
            mysqli_stmt_execute($verify_query);
            $verify_result = mysqli_stmt_get_result($verify_query);

            if (mysqli_num_rows($verify_result) === 0) {
                mysqli_stmt_close($verify_query);
                throw new Exception('Customer record not found.');
            }

            mysqli_stmt_close($verify_query);

            $blocking_query = mysqli_prepare($conn, "
                SELECT wo.code
                FROM work_order wo
                LEFT JOIN payments p ON p.work_order_id = wo.id
                WHERE wo.client_id = ?
                AND wo.status IN ('Repaired', 'Ready for Release')
                AND COALESCE(
                    NULLIF(p.payment_status, ''),
                    CASE
                        WHEN p.status IS NULL OR p.status = 'Pending' OR p.status = '' THEN 'Unpaid'
                        ELSE p.status
                    END
                ) IN ('Unpaid', 'Partial')
                LIMIT 3
            ");

            if (!$blocking_query) {
                throw new Exception('Database error: ' . mysqli_error($conn));
            }

            mysqli_stmt_bind_param($blocking_query, "i", $client_id);
            mysqli_stmt_execute($blocking_query);
            $blocking_result = mysqli_stmt_get_result($blocking_query);
            $blocking_codes = [];

            while ($blocking_row = mysqli_fetch_assoc($blocking_result)) {
                $blocking_codes[] = $blocking_row['code'];
            }

            mysqli_stmt_close($blocking_query);

            if (!empty($blocking_codes)) {
                throw new Exception('Customer cannot be deleted because repaired work order(s) still have unpaid or partial payment: ' . implode(', ', $blocking_codes) . '. Please settle the payment first.');
            }

            if (!mysqli_begin_transaction($conn)) {
                throw new Exception('Failed to start delete transaction.');
            }
            $transactionStarted = true;

            $paidPaymentCondition = clientDeletePaidPaymentCondition('p');

            if (clientDeleteTableExists($conn, 'notifications')) {
                executeClientDeleteStatement(
                    $conn,
                    "DELETE n
                    FROM notifications n
                    INNER JOIN work_order wo ON n.link = CONCAT('work-order.php?search=', wo.code)
                    WHERE wo.client_id = ?",
                    $client_id,
                    'Failed to delete work order notifications'
                );

                executeClientDeleteStatement(
                    $conn,
                    "DELETE n
                    FROM notifications n
                    INNER JOIN payments p ON n.link = CONCAT('payment.php?search=', p.payment_code)
                    INNER JOIN work_order wo ON p.work_order_id = wo.id
                    WHERE wo.client_id = ?",
                    $client_id,
                    'Failed to delete payment notifications'
                );

                executeClientDeleteStatement(
                    $conn,
                    "DELETE n
                    FROM notifications n
                    INNER JOIN work_order wo ON n.link = CONCAT('payment.php?search=', wo.code)
                    WHERE wo.client_id = ?",
                    $client_id,
                    'Failed to delete payment status notifications'
                );
            }

            executeClientDeleteStatement(
                $conn,
                "DELETE r
                FROM refunds r
                INNER JOIN payments p ON r.payment_id = p.id
                INNER JOIN work_order wo ON p.work_order_id = wo.id
                WHERE wo.client_id = ?
                AND NOT $paidPaymentCondition",
                $client_id,
                'Failed to delete refund records'
            );

            executeClientDeleteStatement(
                $conn,
                "DELETE al
                FROM activity_logs al
                INNER JOIN work_order wo ON al.work_order_id = wo.id
                WHERE wo.client_id = ?",
                $client_id,
                'Failed to delete activity logs'
            );

            executeClientDeleteStatement(
                $conn,
                "DELETE p
                FROM payments p
                INNER JOIN work_order wo ON p.work_order_id = wo.id
                WHERE wo.client_id = ?
                AND NOT $paidPaymentCondition",
                $client_id,
                'Failed to delete payment records'
            );

            executeClientDeleteStatement(
                $conn,
                "UPDATE items i
                INNER JOIN (
                    SELECT pi.product_id, SUM(pi.quantity) AS restore_quantity
                    FROM purchased_item pi
                    INNER JOIN work_order wo ON pi.work_order_id = wo.id
                    WHERE wo.client_id = ?
                    AND wo.status IN ('Pending', 'Diagnosing', 'Waiting for Parts', 'In Progress', 'Cancelled')
                    GROUP BY pi.product_id
                ) stock ON i.id = stock.product_id
                SET i.quantity = i.quantity + stock.restore_quantity",
                $client_id,
                'Failed to restore purchased item stock'
            );

            executeClientDeleteStatement(
                $conn,
                "DELETE pi
                FROM purchased_item pi
                INNER JOIN work_order wo ON pi.work_order_id = wo.id
                WHERE wo.client_id = ?",
                $client_id,
                'Failed to delete purchased item records'
            );

            executeClientDeleteStatement(
                $conn,
                "DELETE cpc
                FROM customer_provided_component cpc
                INNER JOIN work_order wo ON cpc.work_order_id = wo.id
                WHERE wo.client_id = ?",
                $client_id,
                'Failed to delete client provided component records'
            );

            if (clientDeleteTableExists($conn, 'client_provided_parts')) {
                executeClientDeleteStatement(
                    $conn,
                    "DELETE cpp
                    FROM client_provided_parts cpp
                    INNER JOIN work_order wo ON cpp.work_order_id = wo.id
                    WHERE wo.client_id = ?",
                    $client_id,
                    'Failed to delete legacy client provided part records'
                );
            }

            executeClientDeleteStatement(
                $conn,
                "DELETE FROM work_order WHERE client_id = ?",
                $client_id,
                'Failed to delete work order records'
            );

            $delete_query = mysqli_prepare($conn, "DELETE FROM client WHERE id = ?");
            if (!$delete_query) {
                throw new Exception('Database error: ' . mysqli_error($conn));
            }

            mysqli_stmt_bind_param($delete_query, "i", $client_id);

            if (!mysqli_stmt_execute($delete_query)) {
                throw new Exception('Failed to delete customer: ' . mysqli_stmt_error($delete_query));
            }

            if (mysqli_stmt_affected_rows($delete_query) === 0) {
                throw new Exception('Customer could not be deleted.');
            }

            mysqli_stmt_close($delete_query);

            if (!mysqli_commit($conn)) {
                throw new Exception('Failed to complete customer deletion.');
            }
            $transactionStarted = false;

            redirectClientWithDialog('success', 'Customer Deleted', 'Customer deleted successfully. Paid payment records were kept for revenue reports.');
        } catch (Exception $e) {
            if ($transactionStarted) {
                mysqli_rollback($conn);
            }
            redirectClientWithDialog('error', 'Customer Not Deleted', $e->getMessage());
        }
    } else {
        header("Location: ../../clients.php");
        exit();
    }
?>
