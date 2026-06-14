<?php

if (!function_exists('macprotech_security_headers_enabled')) {
    function macprotech_security_headers_enabled(): bool {
        return PHP_SAPI !== 'cli' && !headers_sent();
    }
}

if (!function_exists('macprotech_content_security_policy')) {
    function macprotech_content_security_policy(): string {
        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "frame-ancestors 'self'",
            "form-action 'self'",
            "img-src 'self' data:",
            "script-src 'self' 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline'",
            "connect-src 'self'",
            "font-src 'self' data:",
            "object-src 'none'"
        ]);
    }
}

if (!function_exists('macprotech_send_security_headers')) {
    function macprotech_send_security_headers(): void {
        if (!macprotech_security_headers_enabled()) {
            return;
        }

        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Content-Security-Policy: ' . macprotech_content_security_policy());
    }
}

?>
