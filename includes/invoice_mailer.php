<?php

require_once __DIR__ . '/mail_helper.php';

function build_sale_invoice_html(mysqli $conn, int $sale_id): ?array
{
    $sale_id = (int)$sale_id;
    $sale_query = mysqli_query($conn, "
        SELECT s.*, c.customer_name, c.phone AS customer_phone, c.email AS customer_email,
               c.vehicle_no, u.full_name AS cashier_name
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.customer_id
        INNER JOIN users u ON s.cashier_id = u.user_id
        WHERE s.sale_id = $sale_id
    ");

    $sale = mysqli_fetch_assoc($sale_query);
    if (!$sale) {
        return null;
    }

    $items_query = mysqli_query($conn, "
        SELECT si.*, p.product_name, p.brand, p.sku
        FROM sale_items si
        INNER JOIN products p ON si.product_id = p.product_id
        WHERE si.sale_id = $sale_id
    ");

    $rows = '';
    while ($item = mysqli_fetch_assoc($items_query)) {
        $rows .= '<tr>
            <td style="padding:8px;border:1px solid #ddd;">' . htmlspecialchars($item['product_name']) . '<br>
                <small>' . htmlspecialchars($item['brand'] ?? '') . ' | SKU: ' . htmlspecialchars($item['sku'] ?? '') . '</small></td>
            <td style="padding:8px;border:1px solid #ddd;text-align:center;">' . (int)$item['quantity'] . '</td>
            <td style="padding:8px;border:1px solid #ddd;text-align:right;">Rs. ' . number_format($item['unit_price'], 2) . '</td>
            <td style="padding:8px;border:1px solid #ddd;text-align:right;">Rs. ' . number_format($item['subtotal'], 2) . '</td>
        </tr>';
    }

    $customer_block = '';
    if (!empty($sale['customer_id'])) {
        $customer_block = '
            <p><strong>Customer:</strong> ' . htmlspecialchars($sale['customer_name']) . '</p>
            <p><strong>Phone:</strong> ' . htmlspecialchars($sale['customer_phone']) . '</p>
            <p><strong>Vehicle No:</strong> ' . htmlspecialchars($sale['vehicle_no']) . '</p>';
    } else {
        $customer_block = '<p><strong>Customer:</strong> Walk-in Customer</p>';
    }

    $html = '
    <div style="font-family:Arial,sans-serif;color:#111;max-width:700px;margin:0 auto;">
        <h2 style="text-align:center;">SmartVee Auto Parts</h2>
        <p style="text-align:center;color:#555;">No. 120, Colombo Road, Kandy, Sri Lanka</p>
        <hr>
        <p><strong>Invoice No:</strong> ' . htmlspecialchars($sale['invoice_no']) . '</p>
        <p><strong>Date:</strong> ' . date('M d, Y h:i A', strtotime($sale['sale_date'])) . '</p>
        <p><strong>Cashier:</strong> ' . htmlspecialchars($sale['cashier_name']) . '</p>
        <p><strong>Payment:</strong> ' . htmlspecialchars($sale['payment_method']) . '</p>
        ' . $customer_block . '
        <table style="width:100%;border-collapse:collapse;margin-top:16px;">
            <thead>
                <tr style="background:#f3f4f6;">
                    <th style="padding:8px;border:1px solid #ddd;text-align:left;">Product</th>
                    <th style="padding:8px;border:1px solid #ddd;">Qty</th>
                    <th style="padding:8px;border:1px solid #ddd;text-align:right;">Price</th>
                    <th style="padding:8px;border:1px solid #ddd;text-align:right;">Subtotal</th>
                </tr>
            </thead>
            <tbody>' . $rows . '</tbody>
        </table>
        <table style="width:100%;margin-top:16px;">
            <tr><td style="text-align:right;padding:4px;">Subtotal:</td><td style="text-align:right;padding:4px;width:140px;">Rs. ' . number_format($sale['subtotal'], 2) . '</td></tr>
            <tr><td style="text-align:right;padding:4px;">Discount:</td><td style="text-align:right;padding:4px;">- Rs. ' . number_format($sale['discount'], 2) . '</td></tr>
            <tr><td style="text-align:right;padding:4px;font-weight:bold;">Grand Total:</td><td style="text-align:right;padding:4px;font-weight:bold;">Rs. ' . number_format($sale['grand_total'], 2) . '</td></tr>
            <tr><td style="text-align:right;padding:4px;">Amount Paid:</td><td style="text-align:right;padding:4px;">Rs. ' . number_format($sale['amount_paid'], 2) . '</td></tr>
            <tr><td style="text-align:right;padding:4px;">Balance:</td><td style="text-align:right;padding:4px;">Rs. ' . number_format($sale['balance'], 2) . '</td></tr>
        </table>
        <p style="text-align:center;margin-top:24px;color:#555;">Thank you for your business!</p>
    </div>';

    return [
        'html' => $html,
        'subject' => 'Invoice ' . $sale['invoice_no'] . ' - SmartVee Auto Parts',
        'default_email' => $sale['customer_email'] ?? '',
        'invoice_no' => $sale['invoice_no'],
    ];
}

function build_supplier_invoice_html(mysqli $conn, int $invoice_id): ?array
{
    $invoice_id = (int)$invoice_id;
    $result = mysqli_query($conn, "
        SELECT supplier_invoices.*, suppliers.supplier_name, suppliers.contact_no,
               suppliers.address, suppliers.email AS supplier_email
        FROM supplier_invoices
        JOIN suppliers ON supplier_invoices.supplier_id = suppliers.supplier_id
        WHERE invoice_id = '$invoice_id'
    ");

    $row = mysqli_fetch_assoc($result);
    if (!$row) {
        return null;
    }

    $items = mysqli_query($conn, "
        SELECT sii.*, p.product_name, p.sku, p.brand
        FROM supplier_invoice_items sii
        INNER JOIN products p ON sii.product_id = p.product_id
        WHERE sii.invoice_id = '$invoice_id'
    ");

    $rows = '';
    $n = 1;
    while ($item = mysqli_fetch_assoc($items)) {
        $rows .= '<tr>
            <td style="padding:8px;border:1px solid #ddd;">' . $n++ . '</td>
            <td style="padding:8px;border:1px solid #ddd;">' . htmlspecialchars($item['product_name']) . '</td>
            <td style="padding:8px;border:1px solid #ddd;">' . htmlspecialchars($item['sku'] ?? '-') . '</td>
            <td style="padding:8px;border:1px solid #ddd;text-align:right;">' . (int)$item['quantity'] . '</td>
            <td style="padding:8px;border:1px solid #ddd;text-align:right;">Rs. ' . number_format($item['unit_cost'], 2) . '</td>
            <td style="padding:8px;border:1px solid #ddd;text-align:right;">Rs. ' . number_format($item['subtotal'], 2) . '</td>
        </tr>';
    }

    $items_table = '';
    if ($rows !== '') {
        $items_table = '
        <table style="width:100%;border-collapse:collapse;margin-top:16px;">
            <thead>
                <tr style="background:#f3f4f6;">
                    <th style="padding:8px;border:1px solid #ddd;">#</th>
                    <th style="padding:8px;border:1px solid #ddd;">Product</th>
                    <th style="padding:8px;border:1px solid #ddd;">SKU</th>
                    <th style="padding:8px;border:1px solid #ddd;">Qty</th>
                    <th style="padding:8px;border:1px solid #ddd;">Unit Cost</th>
                    <th style="padding:8px;border:1px solid #ddd;">Subtotal</th>
                </tr>
            </thead>
            <tbody>' . $rows . '
                <tr>
                    <td colspan="5" style="padding:8px;border:1px solid #ddd;text-align:right;font-weight:bold;">Grand Total</td>
                    <td style="padding:8px;border:1px solid #ddd;text-align:right;font-weight:bold;">Rs. ' . number_format($row['total_amount'], 2) . '</td>
                </tr>
            </tbody>
        </table>';
    } else {
        $items_table = '<p><strong>Total Amount:</strong> Rs. ' . number_format($row['total_amount'], 2) . '</p>';
    }

    $html = '
    <div style="font-family:Arial,sans-serif;color:#111;max-width:700px;margin:0 auto;">
        <h2 style="text-align:center;">SmartVee Auto Parts</h2>
        <h3 style="text-align:center;color:#555;">Supplier Purchase Invoice</h3>
        <p><strong>Supplier:</strong> ' . htmlspecialchars($row['supplier_name']) . '</p>
        <p><strong>Phone:</strong> ' . htmlspecialchars($row['contact_no']) . '</p>
        <p><strong>Address:</strong> ' . htmlspecialchars($row['address']) . '</p>
        <p><strong>Invoice No:</strong> ' . htmlspecialchars($row['invoice_no']) . '</p>
        <p><strong>Date:</strong> ' . htmlspecialchars($row['invoice_date']) . '</p>
        ' . $items_table . '
        <p style="text-align:center;margin-top:24px;color:#555;">This invoice is generated for supplier restocking purposes.</p>
    </div>';

    return [
        'html' => $html,
        'subject' => 'Supplier Invoice ' . $row['invoice_no'] . ' - SmartVee Auto Parts',
        'default_email' => $row['supplier_email'] ?? '',
        'invoice_no' => $row['invoice_no'],
    ];
}

function send_sale_invoice_email(mysqli $conn, int $sale_id, string $to_email): array
{
    $data = build_sale_invoice_html($conn, $sale_id);
    if (!$data) {
        return ['success' => false, 'message' => 'Sale invoice not found.'];
    }

    return send_email($to_email, $data['subject'], $data['html']);
}

function send_supplier_invoice_email(mysqli $conn, int $invoice_id, string $to_email): array
{
    $data = build_supplier_invoice_html($conn, $invoice_id);
    if (!$data) {
        return ['success' => false, 'message' => 'Supplier invoice not found.'];
    }

    return send_email($to_email, $data['subject'], $data['html']);
}
