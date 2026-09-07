<?php
include('../includes/auth.php');
include('../includes/db.php');
include('../includes/invoice_mailer.php');

$role = $_SESSION['role'] ?? '';

// Check access role
if ($role !== 'Admin' && $role !== 'Cashier') {
    header("Location: ../auth/login.php");
    exit();
}

$page_title = "Billing & Invoices";

// ----------------------------------------------------
// MODE 1: DETAIL VIEW & RECEIPT PRINTER
// ----------------------------------------------------
if (isset($_GET['id'])) {
    $sale_id = (int)$_GET['id'];
    
    // Fetch Sale Header details
    $sale_query = mysqli_query($conn, "
        SELECT s.*, c.customer_name, c.phone as customer_phone, c.email as customer_email,
               c.vehicle_no, u.full_name as cashier_name 
        FROM sales s 
        LEFT JOIN customers c ON s.customer_id = c.customer_id 
        INNER JOIN users u ON s.cashier_id = u.user_id 
        WHERE s.sale_id = $sale_id
    ");
    
    if (mysqli_num_rows($sale_query) == 0) {
        die("Error: Invoice transaction record not found.");
    }
    
    $sale = mysqli_fetch_assoc($sale_query);
    $invoice_no = htmlspecialchars($sale['invoice_no']);
    $default_email = trim($sale['customer_email'] ?? '');
    
    include('../includes/header.php');
?>

<?php if (isset($_GET['email_sent'])): ?>
<div class="alert alert-success alert-dismissible fade show no-print" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i> Invoice emailed successfully.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if (!empty($_GET['email_error'])): ?>
<div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($_GET['email_error']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Action controls (Hidden when printing) -->
<div class="row mb-4 no-print">
    <div class="col-12 d-flex justify-content-between">
        <a href="<?php
            if ($role === 'Admin' && !empty($sale['customer_id'])) {
                echo '../admin/customers.php';
            } elseif ($role === 'Admin') {
                echo '../cashier/invoice.php';
            } else {
                echo 'pos.php';
            }
        ?>" class="btn btn-premium-outline">
            <i class="bi bi-arrow-left-short me-1"></i> Back
        </a>
        <div>
            <button class="btn btn-premium-outline me-2" onclick="window.location='invoice.php';">
                <i class="bi bi-list-ul me-1"></i> All Invoices
            </button>
            <button class="btn btn-premium-outline me-2" data-bs-toggle="modal" data-bs-target="#emailInvoiceModal">
                <i class="bi bi-envelope me-1"></i> Email Invoice
            </button>
            <button class="btn btn-premium" onclick="window.print();">
                <i class="bi bi-printer me-1"></i> Print Thermal Receipt
            </button>
        </div>
    </div>
</div>

<!-- Premium Thermal Receipt Invoice Layout -->
<div class="card card-premium shadow-lg mx-auto" style="max-width: 600px;">
    <div class="card-body p-5 receipt-print">
        <!-- Shop Header Details -->
        <div class="text-center mb-4">
            <div class="fs-3 fw-bold text-dark mb-1"><i class="bi bi-cpu text-primary me-2"></i>SmartVee Auto Parts</div>
            <p class="text-muted small mb-0">No. 120, Colombo Road, Kandy, Sri Lanka</p>
            <p class="text-muted small mb-0">Tel: +94 81 234 5678 | Email: billing@smartvee.com</p>
        </div>
        
        <hr style="border-top: 1px dashed #cbd5e1;">
        
        <!-- Metadata Rows -->
        <div class="row g-3 small mb-4">
            <div class="col-6">
                <div class="text-muted">Invoice No:</div>
                <div class="fw-bold text-dark"><?php echo $invoice_no; ?></div>
                <div class="text-muted mt-2">Cashier Agent:</div>
                <div class="fw-semibold text-dark"><?php echo htmlspecialchars($sale['cashier_name']); ?></div>
            </div>
            <div class="col-6 text-end">
                <div class="text-muted">Billing Date:</div>
                <div class="fw-bold text-dark"><?php echo date('M d, Y h:i A', strtotime($sale['sale_date'])); ?></div>
                <div class="text-muted mt-2">Payment Method:</div>
                <span class="badge bg-secondary py-1.5 px-2.5 mt-1"><?php echo htmlspecialchars($sale['payment_method']); ?></span>
            </div>
        </div>
        
        <!-- Customer Details (if linked) -->
        <?php if ($sale['customer_id']): ?>
            <div class="alert alert-light border p-3 mb-4 rounded-3">
                <div class="fw-bold text-dark mb-2" style="font-size: 0.85rem;"><i class="bi bi-person-circle me-2 text-primary"></i>Customer Information</div>
                <div class="row g-2 small">
                    <div class="col-6">
                        <div class="text-muted">Client Name:</div>
                        <div class="fw-semibold text-dark"><?php echo htmlspecialchars($sale['customer_name']); ?></div>
                        <div class="text-muted mt-1">Contact No:</div>
                        <div class="text-dark"><?php echo htmlspecialchars($sale['customer_phone']); ?></div>
                    </div>
                    <div class="col-6 text-end">
                        <div class="text-muted">Registered Vehicle No:</div>
                        <span class="badge bg-dark text-white font-monospace py-1.5 px-3 border border-dark mt-1">
                            <i class="bi bi-car-front me-2"></i><?php echo htmlspecialchars($sale['vehicle_no']); ?>
                        </span>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-light border text-center p-2 mb-4 rounded-3 small text-muted">
                <i class="bi bi-info-circle me-1"></i> Walk-in Retail Customer Checkout
            </div>
        <?php endif; ?>
        
        <!-- Invoice Items list -->
        <table class="table table-sm" style="border-color: #f1f5f9;">
            <thead>
                <tr class="text-muted small" style="border-bottom: 2px solid #cbd5e1;">
                    <th>Product / Part Details</th>
                    <th class="text-center" style="width: 80px;">Qty</th>
                    <th class="text-end" style="width: 100px;">Price</th>
                    <th class="text-end" style="width: 120px;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $items_query = mysqli_query($conn, "
                    SELECT si.*, p.product_name, p.brand, p.sku 
                    FROM sale_items si 
                    INNER JOIN products p ON si.product_id = p.product_id 
                    WHERE si.sale_id = $sale_id
                ");
                
                while ($item = mysqli_fetch_assoc($items_query)) {
                ?>
                    <tr class="small">
                        <td class="py-2.5">
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($item['product_name']); ?></div>
                            <div class="text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($item['brand']); ?> | SKU: <?php echo htmlspecialchars($item['sku']); ?></div>
                        </td>
                        <td class="text-center py-2.5 text-dark fw-semibold"><?php echo $item['quantity']; ?></td>
                        <td class="text-end py-2.5">Rs. <?php echo number_format($item['unit_price'], 2); ?></td>
                        <td class="text-end py-2.5 fw-bold text-dark">Rs. <?php echo number_format($item['subtotal'], 2); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        
        <hr style="border-top: 1px dashed #cbd5e1;">
        
        <!-- Financial calculations -->
        <div class="row g-2 mt-2" style="font-size: 0.9rem;">
            <div class="col-8 text-end text-muted">Subtotal:</div>
            <div class="col-4 text-end fw-semibold text-dark">Rs. <?php echo number_format($sale['subtotal'], 2); ?></div>
            
            <div class="col-8 text-end text-muted">Discount Applied:</div>
            <div class="col-4 text-end text-danger fw-semibold">- Rs. <?php echo number_format($sale['discount'], 2); ?></div>
            
            <div class="col-8 text-end fw-bold text-dark" style="font-size: 1.05rem;">Grand Total:</div>
            <div class="col-4 text-end fw-bold text-primary" style="font-size: 1.05rem;">Rs. <?php echo number_format($sale['grand_total'], 2); ?></div>
            
            <div class="col-8 text-end text-muted">Amount Received:</div>
            <div class="col-4 text-end fw-semibold text-success">Rs. <?php echo number_format($sale['amount_paid'], 2); ?></div>
            
            <div class="col-8 text-end text-muted">Change Balance:</div>
            <div class="col-4 text-end fw-bold text-dark">Rs. <?php echo number_format($sale['balance'], 2); ?></div>
        </div>
        
        <hr class="mt-4" style="border-top: 1px dashed #cbd5e1;">
        
        <!-- Footer messages -->
        <div class="text-center mt-3 text-muted small">
            <p class="mb-1 fw-bold">Thank you for your business!</p>
            <p class="mb-0">Genuine vehicle parts warranty is subject to standard manufacturer conditions.</p>
        </div>
    </div>
</div>

<div class="modal fade no-print" id="emailInvoiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-envelope me-2"></i>Email Invoice</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="send_invoice_email.php">
                <input type="hidden" name="sale_id" value="<?php echo (int)$sale_id; ?>">
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">Send invoice <strong><?php echo $invoice_no; ?></strong> as an email attachment-style HTML message.</p>
                    <label class="form-label">Recipient Email *</label>
                    <input type="email" name="to_email" class="form-control" required
                           placeholder="customer@email.com"
                           value="<?php echo htmlspecialchars($default_email); ?>">
                    <?php if (empty($default_email)): ?>
                    <div class="form-text text-warning">Customer has no email on file. Enter an address manually.</div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-premium"><i class="bi bi-send me-1"></i>Send Email</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
    include('../includes/footer.php');
} 

// ----------------------------------------------------
// MODE 2: INVOICES INDEX LIST
// ----------------------------------------------------
else {
    include('../includes/header.php');

    $filter_customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
    $filter_customer = null;
    if ($filter_customer_id) {
        $fc_res = mysqli_query($conn, "SELECT * FROM customers WHERE customer_id=$filter_customer_id");
        $filter_customer = mysqli_fetch_assoc($fc_res);
    }
?>

<?php if ($filter_customer): ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">
        <i class="bi bi-clock-history me-2"></i>Purchase History — <?php echo htmlspecialchars($filter_customer['customer_name']); ?>
        <span class="badge bg-light text-dark border font-monospace ms-2"><?php echo htmlspecialchars($filter_customer['vehicle_no']); ?></span>
    </h5>
    <div class="d-flex gap-2">
        <a href="<?php echo ($role === 'Admin') ? '../admin/customers.php' : 'customers.php'; ?>" class="btn btn-sm btn-premium-outline">
            <i class="bi bi-arrow-left me-1"></i>Back to Customers
        </a>
        <a href="invoice.php" class="btn btn-sm btn-premium-outline"><i class="bi bi-x-circle me-1"></i>Clear Filter</a>
    </div>
</div>
<?php endif; ?>

<div class="row mb-3 align-items-center g-3">
    <!-- Left: Search Box -->
    <div class="col-md-6">
        <div class="input-group shadow-sm">
            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
            <input type="text" id="invoiceSearch" class="form-control border-start-0" placeholder="Search invoices by invoice no, customer, vehicle..." data-search-table="#invoicesTable">
        </div>
    </div>
</div>

<!-- Invoices List Card -->
<div class="card card-premium mt-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-modern" id="invoicesTable">
                <thead>
                    <tr>
                        <th>Invoice No</th>
                        <th>Customer / Vehicle</th>
                        <th>Billing Date</th>
                        <th>Cashier</th>
                        <th>Payment Method</th>
                        <th>Grand Total</th>
                        <th style="width: 120px;" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Fetch list of invoices
                    $list_query = "
                        SELECT s.*, c.customer_name, c.vehicle_no, u.full_name as cashier_name 
                        FROM sales s 
                        LEFT JOIN customers c ON s.customer_id = c.customer_id 
                        INNER JOIN users u ON s.cashier_id = u.user_id 
                        " . ($filter_customer_id ? "WHERE s.customer_id = $filter_customer_id " : "") . "
                        ORDER BY s.sale_id DESC
                    ";
                    $result = mysqli_query($conn, $list_query);
                    
                    if (mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $payment_badge = 'bg-secondary';
                            if ($row['payment_method'] === 'Cash') $payment_badge = 'bg-success';
                            elseif ($row['payment_method'] === 'Card') $payment_badge = 'bg-primary';
                            elseif ($row['payment_method'] === 'QR') $payment_badge = 'bg-info text-dark';
                    ?>
                        <tr>
                            <td class="fw-bold text-dark">
                                <i class="bi bi-file-earmark-text text-muted me-1"></i><?php echo htmlspecialchars($row['invoice_no']); ?>
                            </td>
                            <td>
                                <?php if ($row['customer_id']): ?>
                                    <div class="fw-semibold text-dark"><?php echo htmlspecialchars($row['customer_name']); ?></div>
                                    <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.75rem;"><i class="bi bi-car-front me-1"></i><?php echo htmlspecialchars($row['vehicle_no']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">Walk-in Customer</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted">
                                <?php echo date('M d, Y h:i A', strtotime($row['sale_date'])); ?>
                            </td>
                            <td class="small text-dark">
                                <?php echo htmlspecialchars($row['cashier_name']); ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $payment_badge; ?> py-1.5 px-2.5">
                                    <?php echo htmlspecialchars($row['payment_method']); ?>
                                </span>
                            </td>
                            <td class="fw-bold text-dark">
                                Rs. <?php echo number_format($row['grand_total'], 2); ?>
                            </td>
                            <td class="text-center">
                                <a href="invoice.php?id=<?php echo urlencode($row['sale_id']); ?>" class="btn btn-sm btn-outline-primary py-1 px-2.5">
                                    <i class="bi bi-eye-fill me-1"></i> View Bill
                                </a>
                            </td>
                        </tr>
                    <?php 
                        }
                    } else {
                    ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-receipt fs-2 d-block mb-2 text-black-50"></i>
                                No invoice history logs found.
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const searchInput = document.getElementById("invoiceSearch");
        if (searchInput) {
            searchInput.addEventListener("keyup", function() {
                const query = this.value.toLowerCase();
                const rows = document.querySelectorAll("#invoicesTable tbody tr");
                
                rows.forEach(row => {
                    const invoice = row.children[0].innerText.toLowerCase();
                    const customer = row.children[1].innerText.toLowerCase();
                    const cashier = row.children[3].innerText.toLowerCase();
                    
                    if (invoice.includes(query) || customer.includes(query) || cashier.includes(query)) {
                        row.style.display = "";
                    } else {
                        row.style.display = "none";
                    }
                });
            });
        }
    });
</script>

<?php 
    include('../includes/footer.php');
} 
?>
