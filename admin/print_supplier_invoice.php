<?php

include('../includes/db.php');

$id = (int)($_GET['id'] ?? 0);

$sql = "
SELECT supplier_invoices.*, suppliers.supplier_name,
suppliers.contact_no,
suppliers.address
FROM supplier_invoices
JOIN suppliers
ON supplier_invoices.supplier_id=suppliers.supplier_id
WHERE invoice_id='$id'
";

$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);

if (!$row) {
    die("Invoice not found.");
}

$items = mysqli_query($conn, "
    SELECT sii.*, p.product_name, p.sku, p.brand
    FROM supplier_invoice_items sii
    INNER JOIN products p ON sii.product_id = p.product_id
    WHERE sii.invoice_id = '$id'
");

$po_ref = '';
if (!empty($row['po_id'])) {
    $log = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT action FROM purchase_order_logs WHERE po_id='{$row['po_id']}' AND action LIKE 'Created:%' LIMIT 1"));
    if ($log) {
        $po_ref = trim(str_replace('Created:', '', $log['action']));
    }
}

?>

<!DOCTYPE html>

<html>

<head>

<title>Supplier Invoice - <?php echo htmlspecialchars($row['invoice_no']); ?></title>

<style>

body{
font-family:Arial, sans-serif;
margin:40px;
color:#111;
}

.header{
text-align:center;
margin-bottom:24px;
}

.meta{
margin-bottom:20px;
line-height:1.6;
}

table{
width:100%;
border-collapse:collapse;
margin-top:16px;
}

td,th{
border:1px solid #333;
padding:10px;
text-align:left;
}

th{
background:#f3f4f6;
}

.text-end{
text-align:right;
}

.total-row td{
font-weight:bold;
font-size:1.05rem;
}

.footer{
margin-top:32px;
text-align:center;
color:#555;
font-size:0.9rem;
}

</style>

</head>

<body onload="window.print()">

<div class="header">
<h2>SmartVee Auto Parts</h2>
<p>Supplier Purchase Invoice</p>
</div>

<div class="meta">
<p><b>Supplier :</b> <?php echo htmlspecialchars($row['supplier_name']); ?></p>
<p><b>Phone :</b> <?php echo htmlspecialchars($row['contact_no']); ?></p>
<p><b>Address :</b> <?php echo htmlspecialchars($row['address']); ?></p>
<p><b>Invoice No :</b> <?php echo htmlspecialchars($row['invoice_no']); ?></p>
<p><b>Date :</b> <?php echo htmlspecialchars($row['invoice_date']); ?></p>
<?php if ($po_ref): ?>
<p><b>Purchase Order :</b> <?php echo htmlspecialchars($po_ref); ?></p>
<?php endif; ?>
</div>

<?php if (mysqli_num_rows($items) > 0): ?>
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Product</th>
            <th>SKU</th>
            <th class="text-end">Qty</th>
            <th class="text-end">Unit Cost</th>
            <th class="text-end">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $n = 1;
        while ($item = mysqli_fetch_assoc($items)):
        ?>
        <tr>
            <td><?php echo $n++; ?></td>
            <td>
                <?php echo htmlspecialchars($item['product_name']); ?>
                <?php if (!empty($item['brand'])): ?>
                <br><small><?php echo htmlspecialchars($item['brand']); ?></small>
                <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($item['sku'] ?? '-'); ?></td>
            <td class="text-end"><?php echo (int)$item['quantity']; ?></td>
            <td class="text-end">Rs. <?php echo number_format($item['unit_cost'], 2); ?></td>
            <td class="text-end">Rs. <?php echo number_format($item['subtotal'], 2); ?></td>
        </tr>
        <?php endwhile; ?>
        <tr class="total-row">
            <td colspan="5" class="text-end">Grand Total</td>
            <td class="text-end">Rs. <?php echo number_format($row['total_amount'], 2); ?></td>
        </tr>
    </tbody>
</table>
<?php else: ?>
<table>
    <tr>
        <th>Total Amount</th>
    </tr>
    <tr>
        <td class="text-end">Rs. <?php echo number_format($row['total_amount'], 2); ?></td>
    </tr>
</table>
<?php endif; ?>

<div class="footer">
<p>This invoice is generated for supplier restocking purposes.</p>
<p>SmartVee POS — Vehicle Parts Management System</p>
</div>

</body>

</html>
