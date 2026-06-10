<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$password = $argv[1] ?? '';

if ($password === '') {
    fwrite(STDERR, "Usage: php tryhash.php <password>\n");
    exit(1);
}

$hashed = password_hash($password, PASSWORD_DEFAULT);
echo "Hashed password: " . $hashed . PHP_EOL;
?>
