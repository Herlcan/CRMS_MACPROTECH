<?php

require_once __DIR__ . '/settings_helpers.php';
require_once __DIR__ . '/notification_helpers.php';

if (!function_exists('communication_compact_text')) {
    function communication_compact_text(string $value, int $limit = 640): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value));
        return substr($value, 0, $limit);
    }
}

if (!function_exists('communication_display_status')) {
    function communication_display_status(string $status): string
    {
        return $status === 'Ready for Release' ? 'Repaired' : $status;
    }
}

if (!function_exists('should_send_work_order_status_sms')) {
    function should_send_work_order_status_sms(string $status): bool
    {
        return !in_array(communication_display_status($status), ['Released'], true);
    }
}

if (!function_exists('communication_short_business_name')) {
    function communication_short_business_name(array $settings): string
    {
        $businessName = communication_compact_text((string) ($settings['business_name'] ?? ''), 80);
        if ($businessName === '') {
            return 'MACPROTECH';
        }

        return strlen($businessName) > 42 ? 'MACPROTECH' : $businessName;
    }
}

if (!function_exists('normalize_sms_phone_number')) {
    function normalize_sms_phone_number(string $number, string $defaultCountryCode = '+63'): string
    {
        $number = trim($number);
        if ($number === '') {
            return '';
        }

        $countryDigits = preg_replace('/\D+/', '', $defaultCountryCode);
        if ($countryDigits === '') {
            $countryDigits = '63';
        }

        if (strpos($number, '+') === 0) {
            return '+' . preg_replace('/\D+/', '', substr($number, 1));
        }

        $digits = preg_replace('/\D+/', '', $number);
        if ($digits === '') {
            return '';
        }

        if (strpos($digits, '00') === 0) {
            return '+' . substr($digits, 2);
        }

        if (strpos($digits, $countryDigits) === 0) {
            return '+' . $digits;
        }

        if (strpos($digits, '0') === 0) {
            return '+' . $countryDigits . substr($digits, 1);
        }

        return '+' . $countryDigits . $digits;
    }
}

if (!function_exists('is_valid_sms_phone_number')) {
    function is_valid_sms_phone_number(string $number): bool
    {
        return (bool) preg_match('/^\+[1-9]\d{7,14}$/', $number);
    }
}

if (!function_exists('httpsms_request_id')) {
    function httpsms_request_id(string $prefix): string
    {
        try {
            return $prefix . '-' . bin2hex(random_bytes(12));
        } catch (Exception $e) {
            return $prefix . '-' . date('YmdHis') . '-' . mt_rand(1000, 9999);
        }
    }
}

if (!function_exists('ensure_sms_delivery_log_table')) {
    function ensure_sms_delivery_log_table(mysqli $conn): void
    {
        mysqli_query($conn, "
            CREATE TABLE IF NOT EXISTS sms_delivery_log (
                id INT PRIMARY KEY AUTO_INCREMENT,
                dedupe_key CHAR(64) NOT NULL,
                recipient VARCHAR(32) NOT NULL,
                content_hash CHAR(64) NOT NULL,
                request_id VARCHAR(100) NOT NULL,
                status ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                sent_at TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY uniq_sms_delivery_dedupe (dedupe_key),
                KEY idx_sms_delivery_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }
}

if (!function_exists('reserve_sms_delivery')) {
    function reserve_sms_delivery(
        mysqli $conn,
        string $scope,
        string $recipient,
        string $content,
        string $requestPrefix,
        int $ttlSeconds = 120
    ): array {
        ensure_sms_delivery_log_table($conn);

        $contentHash = hash('sha256', $content);
        $dedupeKey = hash('sha256', $scope . '|' . $recipient . '|' . $contentHash);
        $requestId = substr($requestPrefix . '-' . substr($dedupeKey, 0, 18) . '-' . date('YmdHi'), 0, 100);

        $insert = mysqli_prepare($conn, "
            INSERT INTO sms_delivery_log (dedupe_key, recipient, content_hash, request_id, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");

        if (!$insert) {
            return [
                'reserved' => true,
                'dedupe_key' => '',
                'request_id' => httpsms_request_id($requestPrefix),
            ];
        }

        mysqli_stmt_bind_param($insert, "ssss", $dedupeKey, $recipient, $contentHash, $requestId);
        $inserted = mysqli_stmt_execute($insert);
        $errno = mysqli_stmt_errno($insert);
        $error = mysqli_stmt_error($insert);
        mysqli_stmt_close($insert);

        if ($inserted) {
            return [
                'reserved' => true,
                'dedupe_key' => $dedupeKey,
                'request_id' => $requestId,
            ];
        }

        if ($errno !== 1062) {
            error_log('SMS delivery log insert failed: ' . $error);
            return [
                'reserved' => true,
                'dedupe_key' => '',
                'request_id' => httpsms_request_id($requestPrefix),
            ];
        }

        $lookup = mysqli_prepare($conn, "
            SELECT status, TIMESTAMPDIFF(SECOND, created_at, NOW()) AS age_seconds
            FROM sms_delivery_log
            WHERE dedupe_key = ?
            LIMIT 1
        ");

        if (!$lookup) {
            return [
                'reserved' => false,
                'dedupe_key' => $dedupeKey,
                'request_id' => $requestId,
            ];
        }

        mysqli_stmt_bind_param($lookup, "s", $dedupeKey);
        mysqli_stmt_execute($lookup);
        $result = mysqli_stmt_get_result($lookup);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($lookup);

        $ageSeconds = isset($row['age_seconds']) ? (int) $row['age_seconds'] : 0;
        $existingStatus = (string) ($row['status'] ?? 'pending');

        if ($ageSeconds >= 0 && $ageSeconds < $ttlSeconds && $existingStatus !== 'failed') {
            return [
                'reserved' => false,
                'dedupe_key' => $dedupeKey,
                'request_id' => $requestId,
            ];
        }

        $update = mysqli_prepare($conn, "
            UPDATE sms_delivery_log
            SET recipient = ?, content_hash = ?, request_id = ?, status = 'pending', created_at = NOW(), sent_at = NULL
            WHERE dedupe_key = ?
        ");

        if (!$update) {
            return [
                'reserved' => false,
                'dedupe_key' => $dedupeKey,
                'request_id' => $requestId,
            ];
        }

        mysqli_stmt_bind_param($update, "ssss", $recipient, $contentHash, $requestId, $dedupeKey);
        $updated = mysqli_stmt_execute($update);
        mysqli_stmt_close($update);

        return [
            'reserved' => $updated,
            'dedupe_key' => $updated ? $dedupeKey : '',
            'request_id' => $requestId,
        ];
    }
}

if (!function_exists('finish_sms_delivery')) {
    function finish_sms_delivery(mysqli $conn, string $dedupeKey, bool $success): void
    {
        if ($dedupeKey === '') {
            return;
        }

        $status = $success ? 'sent' : 'failed';
        $query = mysqli_prepare($conn, "
            UPDATE sms_delivery_log
            SET status = ?, sent_at = IF(? = 'sent', NOW(), sent_at)
            WHERE dedupe_key = ?
        ");

        if (!$query) {
            return;
        }

        mysqli_stmt_bind_param($query, "sss", $status, $status, $dedupeKey);
        mysqli_stmt_execute($query);
        mysqli_stmt_close($query);
    }
}

if (!function_exists('notify_current_user_sms_sent')) {
    function notify_current_user_sms_sent(mysqli $conn, string $recipient, string $content, string $link = ''): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        $message = communication_compact_text("To {$recipient}: {$content}", 1200);

        create_notification(
            $conn,
            $userId,
            'SMS Sent',
            $message,
            'success',
            $link
        );
    }
}

if (!function_exists('send_httpsms_message')) {
    function send_httpsms_message(
        mysqli $conn,
        string $recipient,
        string $content,
        ?array $settings = null,
        string $requestPrefix = 'macprotech',
        string $notificationLink = '',
        string $dedupeScope = ''
    ): array
    {
        $settings = $settings ?? get_app_settings($conn);
        $result = [
            'attempted' => false,
            'success' => false,
            'message' => 'SMS delivery is disabled.'
        ];

        if (!app_setting_enabled($settings, 'sms_enabled')) {
            return $result;
        }

        $apiKey = trim((string) ($settings['httpsms_api_key'] ?? ''));
        $sender = trim((string) ($settings['httpsms_sender_number'] ?? ''));
        $countryCode = trim((string) ($settings['sms_default_country_code'] ?? '+63')) ?: '+63';

        if ($apiKey === '') {
            return [
                'attempted' => false,
                'success' => false,
                'message' => 'httpSMS API key is missing.'
            ];
        }

        if ($sender === '') {
            return [
                'attempted' => false,
                'success' => false,
                'message' => 'httpSMS sender number is missing.'
            ];
        }

        $from = normalize_sms_phone_number($sender, $countryCode);
        $to = normalize_sms_phone_number($recipient, $countryCode);

        if (!is_valid_sms_phone_number($from)) {
            return [
                'attempted' => false,
                'success' => false,
                'message' => 'httpSMS sender number is invalid.'
            ];
        }

        if (!is_valid_sms_phone_number($to)) {
            return [
                'attempted' => false,
                'success' => false,
                'message' => 'Customer phone number is missing or invalid.'
            ];
        }

        $content = communication_compact_text($content);
        if ($content === '') {
            return [
                'attempted' => false,
                'success' => false,
                'message' => 'SMS content is empty.'
            ];
        }

        $dedupeKey = '';
        $requestId = httpsms_request_id($requestPrefix);

        if ($dedupeScope !== '') {
            $reservation = reserve_sms_delivery($conn, $dedupeScope, $to, $content, $requestPrefix);
            if (!($reservation['reserved'] ?? false)) {
                return [
                    'attempted' => false,
                    'success' => false,
                    'message' => 'Duplicate SMS notification skipped.',
                    'deduplicated' => true
                ];
            }

            $dedupeKey = (string) ($reservation['dedupe_key'] ?? '');
            $requestId = (string) ($reservation['request_id'] ?? $requestId);
        }

        if (!function_exists('curl_init')) {
            error_log('httpSMS send failed: cURL extension is not available.');
            finish_sms_delivery($conn, $dedupeKey, false);
            return [
                'attempted' => false,
                'success' => false,
                'message' => 'SMS gateway support is unavailable.'
            ];
        }

        $payload = json_encode([
            'from' => $from,
            'to' => $to,
            'content' => $content,
            'request_id' => $requestId,
        ]);

        if ($payload === false) {
            finish_sms_delivery($conn, $dedupeKey, false);
            return [
                'attempted' => false,
                'success' => false,
                'message' => 'SMS payload could not be prepared.'
            ];
        }

        $ch = curl_init('https://api.httpsms.com/v1/messages/send');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => $payload,
        ]);

        $rawResponse = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $result['attempted'] = true;

        if ($rawResponse === false) {
            error_log('httpSMS send failed: ' . $curlError);
            finish_sms_delivery($conn, $dedupeKey, false);
            $result['message'] = 'SMS notification failed.';
            return $result;
        }

        $decoded = json_decode((string) $rawResponse, true);
        $apiStatus = is_array($decoded) ? strtolower((string) ($decoded['status'] ?? '')) : '';
        $isSuccess = $httpCode >= 200 && $httpCode < 300 && ($apiStatus === '' || $apiStatus === 'success');

        if (!$isSuccess) {
            error_log('httpSMS send failed: HTTP ' . $httpCode . ' ' . substr((string) $rawResponse, 0, 500));
            finish_sms_delivery($conn, $dedupeKey, false);
            $result['message'] = 'SMS notification failed.';
            return $result;
        }

        $result['success'] = true;
        $result['message'] = 'SMS notification sent.';
        finish_sms_delivery($conn, $dedupeKey, true);
        notify_current_user_sms_sent($conn, $to, $content, $notificationLink);

        return $result;
    }
}

if (!function_exists('send_work_order_status_sms')) {
    function send_work_order_status_sms(mysqli $conn, string $recipient, string $customerName, string $workCode, string $status, ?array $settings = null): array
    {
        $settings = $settings ?? get_app_settings($conn);
        if (!should_send_work_order_status_sms($status)) {
            return [
                'attempted' => false,
                'success' => false,
                'message' => 'Released status SMS updates are disabled.'
            ];
        }

        if (!app_setting_enabled($settings, 'sms_status_updates_enabled')) {
            return [
                'attempted' => false,
                'success' => false,
                'message' => 'Status SMS updates are disabled.'
            ];
        }

        $nameParts = explode(' ', trim($customerName));
        $firstName = communication_compact_text($nameParts[0] ?? '', 40);
        if ($firstName === '') {
            $firstName = 'Customer';
        }
        $businessName = communication_short_business_name($settings);
        $displayStatus = communication_display_status($status);
        $message = "Hi {$firstName}, your {$businessName} repair request {$workCode} status is now {$displayStatus}.";

        if ($displayStatus === 'Repaired') {
            $message .= ' Your device is ready for pickup.';
        } elseif ($displayStatus === 'Cancelled') {
            $message .= ' Please contact us if you have questions.';
        }

        $businessPhone = communication_compact_text((string) ($settings['business_phone'] ?? ''), 40);
        if ($businessPhone !== '' && !in_array($displayStatus, ['Released'], true)) {
            $message .= " Call {$businessPhone} for questions.";
        }

        return send_httpsms_message(
            $conn,
            $recipient,
            $message,
            $settings,
            'status',
            'work-order.php?search=' . urlencode($workCode),
            'work_order_status|' . $workCode . '|' . $displayStatus
        );
    }
}

if (!function_exists('send_receipt_email_sms')) {
    function send_receipt_email_sms(mysqli $conn, string $recipient, string $customerName, string $workCode, string $paymentCode, string $email, ?array $settings = null): array
    {
        $settings = $settings ?? get_app_settings($conn);
        if (!app_setting_enabled($settings, 'sms_receipt_notifications_enabled')) {
            return [
                'attempted' => false,
                'success' => false,
                'message' => 'Receipt SMS notifications are disabled.'
            ];
        }

        $nameParts = explode(' ', trim($customerName));
        $firstName = communication_compact_text($nameParts[0] ?? '', 40);
        if ($firstName === '') {
            $firstName = 'Customer';
        }
        $businessName = communication_short_business_name($settings);
        $reference = $paymentCode !== '' ? $paymentCode : $workCode;
        $message = "Hi {$firstName}, your {$businessName} digital receipt {$reference} was sent to {$email}.";

        $linkSearch = $workCode !== '' ? $workCode : $reference;

        return send_httpsms_message(
            $conn,
            $recipient,
            $message,
            $settings,
            'receipt',
            'payment.php?search=' . urlencode($linkSearch)
        );
    }
}

if (!function_exists('configure_app_mailer')) {
    function configure_app_mailer($mail, array $settings, string $defaultFromName = ''): void
    {
        if (!app_setting_enabled($settings, 'mail_enabled')) {
            throw new Exception('Email delivery is disabled in settings.');
        }

        $host = trim((string) ($settings['mail_smtp_host'] ?? ''));
        $port = (int) ($settings['mail_smtp_port'] ?? 587);
        $username = trim((string) ($settings['mail_smtp_username'] ?? ''));
        $password = (string) ($settings['mail_smtp_password'] ?? '');
        $encryption = strtolower(trim((string) ($settings['mail_smtp_encryption'] ?? 'tls')));

        if ($host === '') {
            throw new Exception('Email SMTP host is missing.');
        }

        if ($port <= 0 || $port > 65535) {
            throw new Exception('Email SMTP port is invalid.');
        }

        if ($username !== '' && $password === '') {
            throw new Exception('Email password or app password is missing.');
        }

        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = $username !== '';
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->Port = $port;

        if ($encryption === 'ssl') {
            $mail->SMTPSecure = 'ssl';
        } elseif ($encryption === 'none') {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        } else {
            $mail->SMTPSecure = 'tls';
        }

        $fromEmail = trim((string) ($settings['mail_from_email'] ?? ''));
        if ($fromEmail === '') {
            $fromEmail = $username !== '' ? $username : trim((string) ($settings['business_email'] ?? ''));
        }

        if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Email from address is missing or invalid.');
        }

        $fromName = communication_compact_text((string) ($settings['mail_from_name'] ?? ''), 120);
        if ($fromName === '') {
            $fromName = $defaultFromName !== ''
                ? $defaultFromName
                : communication_short_business_name($settings);
        }

        $mail->setFrom($fromEmail, $fromName);
    }
}

?>
