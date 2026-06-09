<?php

if (!defined('MACPROTECH_APP_KEY') && is_file(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

if (!function_exists('app_settings_config_value')) {
    function app_settings_config_value(string $constantName, string $default = ''): string
    {
        return defined($constantName) ? (string) constant($constantName) : $default;
    }
}

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
            'mail_enabled' => app_settings_config_value('SMTP_PASS') !== '' ? '1' : '0',
            'mail_smtp_host' => app_settings_config_value('SMTP_HOST', 'smtp.gmail.com'),
            'mail_smtp_port' => app_settings_config_value('SMTP_PORT', '587'),
            'mail_smtp_username' => app_settings_config_value('SMTP_USER'),
            'mail_smtp_password' => app_settings_config_value('SMTP_PASS'),
            'mail_smtp_encryption' => 'tls',
            'mail_from_email' => '',
            'mail_from_name' => '',
            'sms_enabled' => '0',
            'sms_status_updates_enabled' => '1',
            'sms_receipt_notifications_enabled' => '1',
            'httpsms_api_key' => '',
            'httpsms_sender_number' => '',
            'sms_default_country_code' => '+63',
        ];
    }
}

if (!function_exists('app_settings_secret_keys')) {
    function app_settings_secret_keys(): array
    {
        return ['mail_smtp_password', 'httpsms_api_key'];
    }
}

if (!function_exists('app_settings_is_secret_key')) {
    function app_settings_is_secret_key(string $key): bool
    {
        return in_array($key, app_settings_secret_keys(), true);
    }
}

if (!function_exists('app_settings_key_material')) {
    function app_settings_key_material(): string
    {
        $envKey = getenv('MACPROTECH_APP_KEY');
        if ($envKey !== false && trim((string) $envKey) !== '') {
            return trim((string) $envKey);
        }

        return defined('MACPROTECH_APP_KEY') ? trim((string) MACPROTECH_APP_KEY) : '';
    }
}

if (!function_exists('app_settings_crypto_key')) {
    function app_settings_crypto_key(): string
    {
        $keyMaterial = app_settings_key_material();

        if ($keyMaterial === '') {
            throw new Exception('Application encryption key is not configured.');
        }

        if (strpos($keyMaterial, 'base64:') === 0) {
            $decoded = base64_decode(substr($keyMaterial, 7), true);
            if ($decoded !== false && strlen($decoded) >= 32) {
                return substr($decoded, 0, 32);
            }
        }

        return hash('sha256', $keyMaterial, true);
    }
}

if (!function_exists('app_settings_encrypt_secret')) {
    function app_settings_encrypt_secret(string $value): string
    {
        if ($value === '' || strpos($value, 'enc:v1:') === 0) {
            return $value;
        }

        if (!function_exists('openssl_encrypt')) {
            throw new Exception('OpenSSL is required to store secure settings.');
        }

        $iv = random_bytes(12);
        $tag = '';
        $encrypted = openssl_encrypt($value, 'aes-256-gcm', app_settings_crypto_key(), OPENSSL_RAW_DATA, $iv, $tag);

        if ($encrypted === false || $tag === '') {
            throw new Exception('Failed to encrypt secure setting.');
        }

        return 'enc:v1:' . base64_encode($iv . $tag . $encrypted);
    }
}

if (!function_exists('app_settings_decrypt_secret')) {
    function app_settings_decrypt_secret(string $value): string
    {
        if ($value === '' || strpos($value, 'enc:v1:') !== 0) {
            return $value;
        }

        if (!function_exists('openssl_decrypt')) {
            return '';
        }

        $payload = base64_decode(substr($value, 7), true);
        if ($payload === false || strlen($payload) <= 28) {
            return '';
        }

        $iv = substr($payload, 0, 12);
        $tag = substr($payload, 12, 16);
        $cipherText = substr($payload, 28);
        $decrypted = openssl_decrypt($cipherText, 'aes-256-gcm', app_settings_crypto_key(), OPENSSL_RAW_DATA, $iv, $tag);

        return $decrypted === false ? '' : $decrypted;
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
                    $value = (string) $row['setting_value'];
                    $settings[$key] = app_settings_is_secret_key($key)
                        ? app_settings_decrypt_secret($value)
                        : $value;
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
            if (app_settings_is_secret_key($settingKey)) {
                $settingValue = app_settings_encrypt_secret($settingValue);
            }

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

if (!function_exists('app_setting_has_secret')) {
    function app_setting_has_secret(array $settings, string $key): bool
    {
        return trim((string) ($settings[$key] ?? '')) !== '';
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
