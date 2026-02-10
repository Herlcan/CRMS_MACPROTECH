<?php
    $password = 'admin';
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    echo "Hashed password: " . $hashed;
?>