<?php
include('../includes/auth.php');
include('../includes/db.php');
include('../includes/invoice_mailer.php');

$role = $_SESSION['role'] ?? '';
if ($role !== 'Admin' && $role !== 'Cashier') {
    header('Location: ../auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: invoice.php');
    exit();
}

$sale_id = (int)($_POST['sale_id'] ?? 0);
$to_email = trim($_POST['to_email'] ?? '');
$redirect = 'invoice.php?id=' . $sale_id;

if ($sale_id <= 0) {
    header('Location: invoice.php?email_error=' . urlencode('Invalid invoice.'));
    exit();
}

$result = send_sale_invoice_email($conn, $sale_id, $to_email);

if ($result['success']) {
    header('Location: ' . $redirect . '&email_sent=1');
} else {
    header('Location: ' . $redirect . '&email_error=' . urlencode($result['message']));
}
exit();
