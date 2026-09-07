<?php
include('../includes/auth.php');
include('../includes/db.php');
include('../includes/invoice_mailer.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../auth/login.php");
    exit();
}

$page_title = "Supplier Orders & Invoices";
$msg = "";
$error = "";
$created_invoice_id = isset($_GET['created']) ? (int)$_GET['created'] : 0;
$highlight_invoice_id = isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : 0;

if(isset($_POST['add_invoice'])){

    $supplier_id = $_POST['supplier_id'];
    $invoice_no = $_POST['invoice_no'];
    $date = $_POST['invoice_date'];
    $amount = $_POST['total_amount'];

    $stmt=$conn->prepare(
    "INSERT INTO supplier_invoices
    (supplier_id,invoice_no,invoice_date,total_amount)
    VALUES(?,?,?,?)"
    );

    $stmt->bind_param("isss",
    $supplier_id,
    $invoice_no,
    $date,
    $amount
    );

    if($stmt->execute()){
        $msg="Invoice Added Successfully";
    }
}

if(isset($_GET['created'])){
    $msg="Supplier invoice generated successfully from the purchase order.";
}

if (isset($_GET['email_sent'])) {
    $msg = "Supplier invoice emailed successfully.";
}

if (!empty($_GET['email_error'])) {
    $error = $_GET['email_error'];
}

$email_modal_invoice = null;
$email_prefill_id = $created_invoice_id ?: $highlight_invoice_id;
if ($email_prefill_id) {
    $email_modal_invoice = build_supplier_invoice_html($conn, $email_prefill_id);
}

include('../includes/header.php');
?>


<h3><i class="bi bi-file-earmark-arrow-up me-2"></i>Supplier Orders & Invoices</h3>
<p class="text-muted">Order stock from suppliers when inventory is low and generate supplier invoices. You can choose which products to include on each invoice.</p>

<?php if($msg!=""){ ?>
<div class="alert alert-success">
<?php echo htmlspecialchars($msg); ?>
<?php
$print_id = $created_invoice_id ?: $highlight_invoice_id;
if ($print_id):
?>
    <a href="print_supplier_invoice.php?id=<?php echo $print_id; ?>" class="btn btn-sm btn-success ms-2" target="_blank">
        <i class="bi bi-printer"></i> Print Invoice
    </a>
    <button type="button" class="btn btn-sm btn-primary ms-1" data-bs-toggle="modal" data-bs-target="#emailSupplierModal"
        data-invoice-id="<?php echo $print_id; ?>"
        data-invoice-no="<?php echo htmlspecialchars($email_modal_invoice['invoice_no'] ?? '', ENT_QUOTES); ?>"
        data-default-email="<?php echo htmlspecialchars($email_modal_invoice['default_email'] ?? '', ENT_QUOTES); ?>">
        <i class="bi bi-envelope"></i> Email Invoice
    </button>
<?php endif; ?>
</div>
<?php } ?>

<?php if($error!=""){ ?>
<div class="alert alert-danger">
<?php echo htmlspecialchars($error); ?>
</div>
<?php } ?>

<h4>Low Stock Suppliers (need restocking)</h4>

<div class="row g-3 mb-3">
<?php
$low_suppliers = mysqli_query($conn,
"SELECT s.supplier_id, s.supplier_name, COUNT(p.product_id) AS low_count
 FROM suppliers s
 JOIN products p ON p.supplier_id = s.supplier_id
 WHERE p.stock_qty <= p.reorder_level
 GROUP BY s.supplier_id, s.supplier_name
 ORDER BY low_count DESC");

if(mysqli_num_rows($low_suppliers) == 0){
?>
<div class="col-12"><div class="alert alert-info mb-0">No suppliers currently have low-stock products. Stock levels are healthy.</div></div>
<?php
} else {
while($s = mysqli_fetch_assoc($low_suppliers)){
?>
<div class="col-md-4">
  <div class="card card-premium h-100">
    <div class="card-body d-flex justify-content-between align-items-center">
      <div>
        <div class="fw-bold"><?php echo htmlspecialchars($s['supplier_name']); ?></div>
        <div class="text-muted small"><?php echo (int)$s['low_count']; ?> product(s) below reorder level</div>
      </div>
      <a class="btn btn-warning btn-sm"
         href="purchase_order.php?supplier_id=<?php echo (int)$s['supplier_id']; ?>">
        <i class="bi bi-cart-plus"></i> Order Stock
      </a>
    </div>
  </div>
</div>
<?php } } ?>
</div>

<hr>

<h4>Create Purchase Order (Select Products)</h4>
<p class="text-muted small">Choose a supplier and select only the products you want on the invoice.</p>
<form method="GET" action="purchase_order.php" class="row g-2 align-items-end mb-4">
    <div class="col-md-6">
        <label class="form-label">Supplier</label>
        <select name="supplier_id" class="form-control" required>
            <option value="">Select Supplier</option>
            <?php
            $all_suppliers = mysqli_query($conn, "SELECT supplier_id, supplier_name FROM suppliers ORDER BY supplier_name");
            while ($sup = mysqli_fetch_assoc($all_suppliers)):
            ?>
            <option value="<?php echo (int)$sup['supplier_id']; ?>">
                <?php echo htmlspecialchars($sup['supplier_name']); ?>
            </option>
            <?php endwhile; ?>
        </select>
    </div>
    <div class="col-md-auto">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-cart-plus me-1"></i>Create Order
        </button>
    </div>
</form>

<hr>

<h4>Add Invoice Manually</h4>
<form method="POST">

<select name="supplier_id" class="form-control" required>

<option value="">Select Supplier</option>

<?php

$result=mysqli_query($conn,
"SELECT * FROM suppliers");

while($row=mysqli_fetch_assoc($result)){
?>

<option value="<?php echo $row['supplier_id']; ?>">

<?php echo $row['supplier_name']; ?>

</option>

<?php } ?>

</select><br>


<input class="form-control"
name="invoice_no"
placeholder="Invoice Number"
required><br>


<input type="date"
class="form-control"
name="invoice_date"
required><br>


<input class="form-control"
name="total_amount"
placeholder="Total Amount"
required><br>


<button class="btn btn-primary"
name="add_invoice">

Save Invoice

</button>


</form>

<hr>

<h4>Supplier Invoice List</h4>

<div class="table-responsive">
<table class="table table-bordered table-hover">

    <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>Supplier</th>
            <th>Invoice No</th>
            <th>Date</th>
            <th>Total</th>
            <th>Action</th>
        </tr>
    </thead>

    <tbody>

<?php

$sql = "
SELECT supplier_invoices.*, suppliers.supplier_name, suppliers.email AS supplier_email
FROM supplier_invoices
JOIN suppliers
ON supplier_invoices.supplier_id = suppliers.supplier_id
ORDER BY invoice_id DESC
";

$result = mysqli_query($conn,$sql);

while($row=mysqli_fetch_assoc($result)){
?>

<tr>

<td><?php echo $row['invoice_id']; ?></td>

<td><?php echo $row['supplier_name']; ?></td>

<td><?php echo $row['invoice_no']; ?></td>

<td><?php echo $row['invoice_date']; ?></td>

<td>Rs. <?php echo number_format($row['total_amount'],2); ?></td>

<td>

<a href="print_supplier_invoice.php?id=<?php echo $row['invoice_id']; ?>"
class="btn btn-success btn-sm"
target="_blank">
Print
</a>

<button type="button"
class="btn btn-primary btn-sm email-supplier-btn"
data-bs-toggle="modal"
data-bs-target="#emailSupplierModal"
data-invoice-id="<?php echo (int)$row['invoice_id']; ?>"
data-invoice-no="<?php echo htmlspecialchars($row['invoice_no'], ENT_QUOTES); ?>"
data-default-email="<?php echo htmlspecialchars($row['supplier_email'] ?? '', ENT_QUOTES); ?>">
Email
</button>

</td>

</tr>

<?php } ?>

</tbody>

</table>

</div>

<div class="modal fade" id="emailSupplierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-envelope me-2"></i>Email Supplier Invoice</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="send_supplier_invoice_email.php">
                <input type="hidden" name="invoice_id" id="email_invoice_id" value="">
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">Send invoice <strong id="email_invoice_no"></strong> to the supplier.</p>
                    <label class="form-label">Supplier Email *</label>
                    <input type="email" name="to_email" id="email_to_address" class="form-control" required placeholder="supplier@email.com">
                    <div class="form-text" id="email_no_address_hint" style="display:none;">Supplier has no email on file. Enter an address manually.</div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-premium"><i class="bi bi-send me-1"></i>Send Email</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('emailSupplierModal');
    if (!modal) return;

    modal.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        if (!trigger) return;

        const invoiceId = trigger.getAttribute('data-invoice-id') || '';
        const invoiceNo = trigger.getAttribute('data-invoice-no') || '';
        const defaultEmail = trigger.getAttribute('data-default-email') || '';

        document.getElementById('email_invoice_id').value = invoiceId;
        document.getElementById('email_invoice_no').textContent = invoiceNo;
        document.getElementById('email_to_address').value = defaultEmail;
        document.getElementById('email_no_address_hint').style.display = defaultEmail ? 'none' : 'block';
    });
});
</script>

<?php include('../includes/footer.php'); ?>
