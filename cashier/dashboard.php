<?php
include('../includes/auth.php');
include('../includes/db.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Cashier') {
    header("Location: ../auth/login.php");
    exit();
}

$page_title = "Cashier Portal";
$cashier_id = (int)$_SESSION['user_id'];

// 1. Today's Checkout Counts for current cashier
$today_sales_q = mysqli_query($conn, "
    SELECT COUNT(sale_id) AS tx_count, SUM(grand_total) AS total_revenue 
    FROM sales 
    WHERE cashier_id = $cashier_id AND DATE(sale_date) = CURRENT_DATE
");
$today_sales = mysqli_fetch_assoc($today_sales_q);
$tx_count = $today_sales['tx_count'] ?? 0;
$total_revenue = $today_sales['total_revenue'] ?? 0;

// 2. System-wide Low Stock Warning Count
$low_stock_q = mysqli_query($conn, "
    SELECT COUNT(product_id) as low_count 
    FROM products 
    WHERE stock_qty <= reorder_level
");
$low_stock = mysqli_fetch_assoc($low_stock_q);
$total_low_stock = $low_stock['low_count'] ?? 0;

include('../includes/header.php');
?>

<!-- Large POS Launcher Panel -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card card-premium bg-gradient-indigo text-white p-4 d-flex flex-md-row align-items-center justify-content-between g-4">
            <div class="mb-3 mb-md-0">
                <h3 class="fw-bold mb-1">Point of Sale (POS) Checkout</h3>
                <p class="text-white-50 mb-0">Launch the interactive billing and checkout screen to record customer transactions.</p>
            </div>
            <div>
                <a href="pos.php" class="btn btn-light btn-lg px-4 py-2.5 fw-bold text-primary shadow-lg d-flex align-items-center gap-2">
                    <i class="bi bi-cart-plus-fill"></i> Launch POS Console
                </a>
            </div>
        </div>
    </div>
</div>

<!-- KPI Summary Stats -->
<div class="row g-4 mb-4">
    <!-- Today's Revenue -->
    <div class="col-md-4">
        <div class="kpi-card bg-gradient-emerald">
            <div class="kpi-icon"><i class="bi bi-currency-dollar"></i></div>
            <p class="text-white-50 small mb-1">Today's Sales Revenue</p>
            <h3 class="fw-bold mb-0">Rs. <?php echo number_format($total_revenue, 2); ?></h3>
        </div>
    </div>
    
    <!-- Today's Transactions -->
    <div class="col-md-4">
        <div class="kpi-card bg-gradient-indigo">
            <div class="kpi-icon"><i class="bi bi-calculator"></i></div>
            <p class="text-white-50 small mb-1">Today's Transactions</p>
            <h3 class="fw-bold mb-0"><?php echo $tx_count; ?> Bills Issued</h3>
        </div>
    </div>
    
    <!-- Low Stock Alert -->
    <div class="col-md-4">
        <div class="kpi-card bg-gradient-rose">
            <div class="kpi-icon"><i class="bi bi-exclamation-triangle"></i></div>
            <p class="text-white-50 small mb-1">Low Stock Alerts</p>
            <h3 class="fw-bold mb-0"><?php echo $total_low_stock; ?> items warning</h3>
        </div>
    </div>
</div>

<!-- Cashier's Recent Billing Sessions -->
<div class="card card-premium">
    <div class="card-header"><i class="bi bi-clock-history me-2 text-primary"></i>Your Recent Checkout Transactions</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-modern">
                <thead>
                    <tr>
                        <th>Invoice No</th>
                        <th>Customer / Vehicle</th>
                        <th>Checkout Time</th>
                        <th>Payment Method</th>
                        <th>Grand Total</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $my_sales_q = mysqli_query($conn, "
                        SELECT s.*, c.customer_name, c.vehicle_no 
                        FROM sales s 
                        LEFT JOIN customers c ON s.customer_id = c.customer_id 
                        WHERE s.cashier_id = $cashier_id 
                        ORDER BY s.sale_id DESC 
                        LIMIT 5
                    ");
                    
                    if (mysqli_num_rows($my_sales_q) > 0) {
                        while ($row = mysqli_fetch_assoc($my_sales_q)) {
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
                                <?php echo date('M d, Y H:i A', strtotime($row['sale_date'])); ?>
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
                                    <i class="bi bi-eye me-1"></i>View Bill
                                </a>
                            </td>
                        </tr>
                    <?php 
                        }
                    } else {
                    ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-receipt fs-2 d-block mb-2 text-black-50"></i>
                                You haven't recorded any checkouts today. Click "Launch POS Console" to start.
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include('../includes/footer.php'); ?>
