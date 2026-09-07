<?php
include('../includes/auth.php');
include('../includes/db.php');
include('../includes/invoice_mailer.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header('Location: ../auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: supplier_invoice.php');
    exit();
}

$invoice_id = (int)($_POST['invoice_id'] ?? 0);
$to_email = trim($_POST['to_email'] ?? '');

if ($invoice_id <= 0) {
    header('Location: supplier_invoice.php?email_error=' . urlencode('Invalid invoice.'));
    exit();
}

$result = send_supplier_invoice_email($conn, $invoice_id, $to_email);

if ($result['success']) {
    header('Location: supplier_invoice.php?email_sent=1&invoice_id=' . $invoice_id);
} else {
    header('Location: supplier_invoice.php?email_error=' . urlencode($result['message']) . '&invoice_id=' . $invoice_id);
}
exit();
