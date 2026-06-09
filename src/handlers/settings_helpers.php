<?php

if (!function_exists('app_settings_defaults')) {
    function app_settings_defaults(): array
    {
        return [
            'business_name' => 'MACPROTECH Computer Repair Services',
            'owner_name' => '',
            'business_email' => '',
            'business_phone' => '',
            'business_address' => '',
            'facebook_page' => '',
            'business_hours' => 'Monday - Saturday, 9:00 AM - 6:00 PM',
            'timezone' => 'Asia/Manila',
            'receipt_footer' => 'Thank you for trusting Macprotech Computer Repair Services.',
            'service_policy' => 'Please bring your claim stub and settle any remaining balance before releasing the unit.',
            'auto_release_paid_work_orders' => '1',
        ];
    }
}

if (!function_exists('ensure_app_settings_table')) {
    function ensure_app_settings_table(mysqli $conn): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS app_settings (
                setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
                setting_value TEXT NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ";

        if (!mysqli_query($conn, $sql)) {
            throw new Exception('Failed to prepare settings table: ' . mysqli_error($conn));
        }
    }
}

if (!function_exists('get_app_settings')) {
    function get_app_settings(mysqli $conn): array
    {
        ensure_app_settings_table($conn);

        $settings = app_settings_defaults();
        $result = mysqli_query($conn, "SELECT setting_key, setting_value FROM app_settings");

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $key = (string) $row['setting_key'];
                if (array_key_exists($key, $settings)) {
                    $settings[$key] = (string) $row['setting_value'];
                }
            }
        }

        return $settings;
    }
}

if (!function_exists('save_app_settings')) {
    function save_app_settings(mysqli $conn, array $settings): void
    {
        ensure_app_settings_table($conn);

        $defaults = app_settings_defaults();
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO app_settings (setting_key, setting_value)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );

        if (!$stmt) {
            throw new Exception('Failed to prepare settings update: ' . mysqli_error($conn));
        }

        foreach ($defaults as $key => $defaultValue) {
            $settingKey = $key;
            $settingValue = (string) ($settings[$key] ?? $defaultValue);
            mysqli_stmt_bind_param($stmt, "ss", $settingKey, $settingValue);

            if (!mysqli_stmt_execute($stmt)) {
                $error = mysqli_stmt_error($stmt);
                mysqli_stmt_close($stmt);
                throw new Exception('Failed to save settings: ' . $error);
            }
        }

        mysqli_stmt_close($stmt);
    }
}

if (!function_exists('app_setting_enabled')) {
    function app_setting_enabled(array $settings, string $key): bool
    {
        return in_array((string) ($settings[$key] ?? ''), ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('app_settings_public_payload')) {
    function app_settings_public_payload(array $settings): array
    {
        return [
            'businessName' => $settings['business_name'] ?? '',
            'businessEmail' => $settings['business_email'] ?? '',
            'businessPhone' => $settings['business_phone'] ?? '',
            'businessAddress' => $settings['business_address'] ?? '',
            'businessHours' => $settings['business_hours'] ?? '',
            'receiptFooter' => $settings['receipt_footer'] ?? '',
            'servicePolicy' => $settings['service_policy'] ?? '',
        ];
    }
}

?>
