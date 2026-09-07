<?php

session_start();
include('../includes/db.php');

if (isset($_POST['username']) && isset($_POST['password'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE username='$username'";
    $result = mysqli_query($conn, $sql);

    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);

        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['full_name'];

            if ($user['role'] == 'Admin') {
                header("Location: ../admin/dashboard.php");
                exit();
            } elseif ($user['role'] == 'Cashier') {
                header("Location: ../cashier/dashboard.php");
                exit();
            } else {
                header("Location: login.php");
                exit();
            }
        } else {
            header("Location: login.php?error=Invalid Password");
            exit();
        }
    } else {
        header("Location: login.php?error=User Not Found");
        exit();
    }
} else {
    header("Location: login.php");
    exit();
}
?>