<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    $redirect_path = "auth/login.php";
    if (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false || strpos($_SERVER['SCRIPT_NAME'], '/cashier/') !== false) {
        $redirect_path = "../auth/login.php";
    }
    header("Location: " . $redirect_path);
    exit();
}
?>