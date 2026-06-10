<?php

if (!function_exists('db_bind_params')) {
    function db_bind_params(mysqli_stmt $stmt, string $types, array &$params): bool {
        if ($types === '') {
            return true;
        }

        $references = [];
        foreach ($params as $key => &$value) {
            $references[$key] = &$value;
        }

        return mysqli_stmt_bind_param($stmt, $types, ...$references);
    }
}

?>
