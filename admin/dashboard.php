<?php
include('../includes/auth.php');
include('../includes/db.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../auth/login.php");
    exit();
}

$page_title = "Admin Dashboard";

// Stats queries
$total_products = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM products"));
$total_customers = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM customers"));
$total_suppliers = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM suppliers"));

// Low stock items count
$low_stock_query = mysqli_query($conn, "SELECT COUNT(*) as low_qty FROM products WHERE stock_qty <= reorder_level");
$low_stock_row = mysqli_fetch_assoc($low_stock_query);
$total_low_stock = $low_stock_row['low_qty'] ?? 0;

// Revenue
$total_sales = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(grand_total) AS total FROM sales"));
$total_revenue = $total_sales['total'] ?? 0;

// Chart Data (Grouped by Month)
$chart = mysqli_query($conn, "
    SELECT MONTH(sale_date) as month_num, SUM(grand_total) as total 
    FROM sales 
    WHERE YEAR(sale_date) = YEAR(CURRENT_DATE)
    GROUP BY MONTH(sale_date)
    ORDER BY MONTH(sale_date)
");

$months = [];
$values = [];
$month_map = ["", "Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

while ($row = mysqli_fetch_assoc($chart)) {
    $months[] = $month_map[(int)$row['month_num']];
    $values[] = (float)$row['total'];
}

// Fallback if no sales recorded yet
if (empty($months)) {
    $months = [date('M')];
    $values = [0];
}

include('../includes/header.php');
?>

<!-- KPI Row -->
<div class="row g-4 mb-4">
    <!-- Revenue Card -->
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card bg-gradient-indigo">
            <div class="kpi-icon">
                <i class="bi bi-wallet2"></i>
            </div>
            <p class="text-white-50 small mb-1">Total Revenue</p>
            <h3 class="fw-bold mb-0">Rs. <?php echo number_format($total_revenue, 2); ?></h3>
        </div>
    </div>
    
    <!-- Products Card -->
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card bg-gradient-emerald">
            <div class="kpi-icon">
                <i class="bi bi-box-seam"></i>
            </div>
            <p class="text-white-50 small mb-1">Total Products</p>
            <h3 class="fw-bold mb-0"><?php echo $total_products; ?></h3>
        </div>
    </div>
    
    <!-- Customers Card -->
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card bg-gradient-amber">
            <div class="kpi-icon">
                <i class="bi bi-people"></i>
            </div>
            <p class="text-white-50 small mb-1">Total Customers</p>
            <h3 class="fw-bold mb-0"><?php echo $total_customers; ?></h3>
        </div>
    </div>
    
    <!-- Low Stock Card -->
    <div class="col-sm-6 col-xl-3">
        <a href="supplier_invoice.php" class="text-decoration-none">
        <div class="kpi-card bg-gradient-rose">
            <div class="kpi-icon">
                <i class="bi bi-exclamation-octagon"></i>
            </div>
            <p class="text-white-50 small mb-1">Low Stock Alerts</p>
            <h3 class="fw-bold mb-0"><?php echo $total_low_stock; ?></h3>
            <?php if ($total_low_stock > 0): ?>
            <p class="text-white-50 small mb-0 mt-2"><i class="bi bi-cart-plus me-1"></i>Click to order from suppliers</p>
            <?php endif; ?>
        </div>
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Chart Column -->
    <div class="col-xl-8">
        <div class="card card-premium h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-graph-up me-2 text-primary"></i>Sales Analytics (This Year)</span>
            </div>
            <div class="card-body">
                <div style="height: 300px; position: relative;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Sales Table Column -->
    <div class="col-xl-4">
        <div class="card card-premium h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history me-2 text-primary"></i>Recent Transactions</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-modern">
                        <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>Total</th>
                                <th>Method</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $recent_sales_query = mysqli_query($conn, "
                                SELECT * FROM sales 
                                ORDER BY sale_id DESC 
                                LIMIT 5
                            ");
                            
                            if (mysqli_num_rows($recent_sales_query) > 0) {
                                while ($sale = mysqli_fetch_assoc($recent_sales_query)) {
                                    $payment_badge = 'bg-secondary';
                                    if ($sale['payment_method'] === 'Cash') $payment_badge = 'bg-success';
                                    elseif ($sale['payment_method'] === 'Card') $payment_badge = 'bg-primary';
                                    elseif ($sale['payment_method'] === 'QR') $payment_badge = 'bg-info text-dark';
                            ?>
                                <tr>
                                    <td class="fw-bold">
                                        <i class="bi bi-file-earmark-text text-muted me-1"></i>
                                        <?php echo htmlspecialchars($sale['invoice_no']); ?>
                                        <div class="text-muted" style="font-size: 0.75rem;">
                                            <?php echo date('M d, H:i', strtotime($sale['sale_date'])); ?>
                                        </div>
                                    </td>
                                    <td class="fw-bold text-dark">
                                        Rs.<?php echo number_format($sale['grand_total'], 2); ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $payment_badge; ?>" style="font-size: 0.75rem;">
                                            <?php echo htmlspecialchars($sale['payment_method']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php 
                                }
                            } else {
                            ?>
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-muted">
                                        <i class="bi bi-info-circle me-1"></i> No transactions found
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ChartJS Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const ctx = document.getElementById('salesChart').getContext('2d');
        
        // Premium gradient configuration for Chart line
        let gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(99, 102, 241, 0.4)');
        gradient.addColorStop(1, 'rgba(99, 102, 241, 0.0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($months); ?>,
                datasets: [{
                    label: 'Total Revenue (Rs.)',
                    data: <?php echo json_encode($values); ?>,
                    borderColor: '#6366f1',
                    borderWidth: 3,
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#6366f1',
                    pointHoverRadius: 7,
                    pointHoverBackgroundColor: '#ffffff',
                    pointHoverBorderColor: '#6366f1',
                    pointHoverBorderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        grid: {
                            color: '#f1f5f9'
                        },
                        ticks: {
                            font: {
                                family: 'Plus Jakarta Sans'
                            },
                            callback: function(value) {
                                return 'Rs.' + value.toLocaleString();
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                family: 'Plus Jakarta Sans',
                                weight: '500'
                            }
                        }
                    }
                }
            }
        });
    });
</script>

<?php include('../includes/footer.php'); ?>