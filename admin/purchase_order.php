<?php

include('../includes/auth.php');
include('../includes/db.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != "Admin") {
    header("Location: ../auth/login.php");
    exit();
}

$page_title = "Create Purchase Order";

$supplier_id = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;

if ($supplier_id <= 0) {
    header("Location: supplier_invoice.php");
    exit();
}

$supplier = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM suppliers WHERE supplier_id='$supplier_id'"));

if (!$supplier) {
    header("Location: supplier_invoice.php");
    exit();
}

if (isset($_POST['save_po'])) {

    $selected = $_POST['selected'] ?? [];
    if (empty($selected)) {
        header("Location: purchase_order.php?supplier_id=$supplier_id&error=no_selection");
        exit();
    }

    $po_number = "PO-" . date("YmdHis");

    mysqli_query($conn,
        "INSERT INTO purchase_orders (supplier_id, status)
         VALUES ('$supplier_id', 'Pending')");

    $po_id = mysqli_insert_id($conn);

    mysqli_query($conn,
        "INSERT INTO purchase_order_logs (po_id, action)
         VALUES ('$po_id', 'Created: $po_number')");

    $invoice_no = "INV-" . date("YmdHis");
    $total_amount = 0;
    $has_items = false;

    foreach ($selected as $product_id => $checked) {
        $product_id = (int)$product_id;
        $qty = (int)($_POST['qty'][$product_id] ?? 0);

        if ($qty <= 0) {
            continue;
        }

        $p = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT purchase_price FROM products
             WHERE product_id='$product_id' AND supplier_id='$supplier_id'"));

        if (!$p) {
            continue;
        }

        $has_items = true;
        $unit_cost = $p['purchase_price'] ?? 0;

        mysqli_query($conn,
            "INSERT INTO purchase_order_items(po_id, product_id, order_qty, unit_cost)
             VALUES('$po_id', '$product_id', '$qty', '$unit_cost')");

        $total_amount += $unit_cost * $qty;
    }

    if (!$has_items) {
        mysqli_query($conn, "DELETE FROM purchase_orders WHERE po_id='$po_id'");
        header("Location: purchase_order.php?supplier_id=$supplier_id&error=no_items");
        exit();
    }

    $stmt = $conn->prepare(
        "INSERT INTO supplier_invoices (po_id, supplier_id, invoice_no, invoice_date, total_amount)
         VALUES (?, ?, ?, NOW(), ?)"
    );
    $stmt->bind_param("iisd", $po_id, $supplier_id, $invoice_no, $total_amount);
    $stmt->execute();
    $invoice_id = $stmt->insert_id;
    $stmt->close();

    mysqli_query($conn,
        "INSERT INTO supplier_invoice_items (invoice_id, product_id, quantity, unit_cost, subtotal)
         SELECT '$invoice_id', product_id, order_qty, unit_cost, (order_qty * unit_cost)
         FROM purchase_order_items
         WHERE po_id='$po_id'"
    );

    mysqli_query($conn,
        "UPDATE purchase_orders SET status='Sent' WHERE po_id='$po_id'");

    mysqli_query($conn,
        "INSERT INTO purchase_order_logs (po_id, action)
         VALUES ('$po_id', 'Supplier invoice generated: $invoice_no')");

    header("Location: supplier_invoice.php?created=" . $invoice_id);
    exit();
}

$result = mysqli_query($conn,
    "SELECT *
     FROM products
     WHERE supplier_id='$supplier_id'
     ORDER BY (stock_qty <= reorder_level) DESC, product_name ASC");

$product_count = mysqli_num_rows($result);
$products = [];
while ($row = mysqli_fetch_assoc($result)) {
    $products[] = $row;
}

include('../includes/header.php');
?>
<div class="card card-premium">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-box-seam"></i> Purchase Order</h5>
    </div>
    <div class="card-body">
        <?php if (isset($_GET['error']) && $_GET['error'] === 'no_items'): ?>
        <div class="alert alert-warning">Please select at least one product and enter a quantity.</div>
        <?php endif; ?>
        <?php if (isset($_GET['error']) && $_GET['error'] === 'no_selection'): ?>
        <div class="alert alert-warning">Please select at least one product to include in the invoice.</div>
        <?php endif; ?>

        <h4 class="mb-1">Supplier: <?php echo htmlspecialchars($supplier['supplier_name'] ?? 'Unknown'); ?></h4>
        <p class="text-muted small mb-4">Select which products should appear on this supplier invoice.</p>

        <?php if ($product_count == 0): ?>
        <div class="alert alert-info mb-0">No products linked to this supplier.</div>
        <a href="supplier_invoice.php" class="btn btn-secondary mt-3"><i class="bi bi-arrow-left me-1"></i>Back</a>
        <?php else: ?>
        <form method="POST" id="purchaseOrderForm">
            <div class="d-flex flex-wrap gap-2 mb-3">
                <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllBtn">
                    <i class="bi bi-check2-square me-1"></i>Select All
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="clearAllBtn">
                    <i class="bi bi-square me-1"></i>Clear All
                </button>
                <button type="button" class="btn btn-sm btn-outline-warning" id="selectLowStockBtn">
                    <i class="bi bi-exclamation-triangle me-1"></i>Select Low Stock Only
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-modern">
                    <thead>
                        <tr>
                            <th style="width:50px;" class="text-center">Invoice</th>
                            <th>Product</th>
                            <th class="text-center">Current Stock</th>
                            <th class="text-center">Reorder Level</th>
                            <th style="width:140px;">Order Qty</th>
                            <th class="text-end">Unit Cost</th>
                            <th class="text-end">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $row):
                            $pid = (int)$row['product_id'];
                            $is_low = (int)$row['stock_qty'] <= (int)$row['reorder_level'];
                            $default_qty = max(1, (int)$row['reorder_level'] * 2);
                            $unit_cost = (float)($row['purchase_price'] ?? 0);
                        ?>
                        <tr class="po-row <?php echo $is_low ? 'table-warning' : ''; ?>" data-low-stock="<?php echo $is_low ? '1' : '0'; ?>">
                            <td class="text-center">
                                <input type="checkbox"
                                       class="form-check-input product-select"
                                       name="selected[<?php echo $pid; ?>]"
                                       value="1"
                                       <?php echo $is_low ? 'checked' : ''; ?>>
                            </td>
                            <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars($row['product_name']); ?></div>
                                <div class="small text-muted">
                                    <?php echo htmlspecialchars($row['brand'] ?? ''); ?>
                                    <?php if (!empty($row['sku'])): ?> | SKU: <?php echo htmlspecialchars($row['sku']); ?><?php endif; ?>
                                </div>
                                <?php if ($is_low): ?>
                                <span class="badge bg-warning text-dark mt-1">Low Stock</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?php echo (int)$row['stock_qty']; ?></td>
                            <td class="text-center"><?php echo (int)$row['reorder_level']; ?></td>
                            <td>
                                <input class="form-control form-control-sm order-qty"
                                       type="number"
                                       name="qty[<?php echo $pid; ?>]"
                                       min="1"
                                       value="<?php echo $default_qty; ?>"
                                       data-unit-cost="<?php echo $unit_cost; ?>"
                                       <?php echo $is_low ? '' : 'disabled'; ?>>
                            </td>
                            <td class="text-end text-muted small">Rs. <?php echo number_format($unit_cost, 2); ?></td>
                            <td class="text-end fw-semibold line-total">
                                Rs. <span class="line-total-value"><?php echo number_format($unit_cost * $default_qty, 2); ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6" class="text-end fw-bold">Invoice Total (selected items)</td>
                            <td class="text-end fw-bold text-primary">Rs. <span id="invoiceTotal">0.00</span></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <button type="submit" name="save_po" class="btn btn-success">
                    <i class="bi bi-check-circle"></i>
                    Save Purchase Order & Generate Supplier Invoice
                </button>
                <a href="supplier_invoice.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('purchaseOrderForm');
    if (!form) return;

    const rows = Array.from(document.querySelectorAll('.po-row'));
    const totalEl = document.getElementById('invoiceTotal');

    function formatMoney(value) {
        return value.toLocaleString('en-LK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function updateRowState(row) {
        const checkbox = row.querySelector('.product-select');
        const qtyInput = row.querySelector('.order-qty');
        const enabled = checkbox.checked;
        qtyInput.disabled = !enabled;
        row.classList.toggle('opacity-50', !enabled);
    }

    function updateLineTotal(row) {
        const checkbox = row.querySelector('.product-select');
        const qtyInput = row.querySelector('.order-qty');
        const unitCost = parseFloat(qtyInput.dataset.unitCost || '0');
        const qty = checkbox.checked ? parseInt(qtyInput.value || '0', 10) : 0;
        const lineTotal = unitCost * (qty > 0 ? qty : 0);
        row.querySelector('.line-total-value').textContent = formatMoney(lineTotal);
        return checkbox.checked && qty > 0 ? lineTotal : 0;
    }

    function updateInvoiceTotal() {
        let total = 0;
        rows.forEach(row => {
            total += updateLineTotal(row);
        });
        totalEl.textContent = formatMoney(total);
    }

    rows.forEach(row => {
        const checkbox = row.querySelector('.product-select');
        const qtyInput = row.querySelector('.order-qty');

        checkbox.addEventListener('change', function () {
            updateRowState(row);
            updateInvoiceTotal();
        });

        qtyInput.addEventListener('input', updateInvoiceTotal);
        updateRowState(row);
    });

    document.getElementById('selectAllBtn').addEventListener('click', function () {
        rows.forEach(row => {
            row.querySelector('.product-select').checked = true;
            updateRowState(row);
        });
        updateInvoiceTotal();
    });

    document.getElementById('clearAllBtn').addEventListener('click', function () {
        rows.forEach(row => {
            row.querySelector('.product-select').checked = false;
            updateRowState(row);
        });
        updateInvoiceTotal();
    });

    document.getElementById('selectLowStockBtn').addEventListener('click', function () {
        rows.forEach(row => {
            const isLow = row.dataset.lowStock === '1';
            row.querySelector('.product-select').checked = isLow;
            updateRowState(row);
        });
        updateInvoiceTotal();
    });

    form.addEventListener('submit', function (e) {
        const hasSelected = rows.some(row => {
            const checkbox = row.querySelector('.product-select');
            const qty = parseInt(row.querySelector('.order-qty').value || '0', 10);
            return checkbox.checked && qty > 0;
        });

        if (!hasSelected) {
            e.preventDefault();
            alert('Please select at least one product with a valid quantity for the invoice.');
        }
    });

    updateInvoiceTotal();
});
</script>

<?php include('../includes/footer.php'); ?>
