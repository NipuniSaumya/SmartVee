<?php
include('../includes/auth.php');
include('../includes/db.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../auth/login.php");
    exit();
}

$page_title = "Business Reports & Analytics";

// Date Filters (defaulting to current month)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

$start_date_esc = mysqli_real_escape_string($conn, $start_date);
$end_date_esc = mysqli_real_escape_string($conn, $end_date);

// 1. Sales summary metrics
$sales_query = mysqli_query($conn, "
    SELECT SUM(grand_total) AS total_revenue, COUNT(sale_id) AS total_tx 
    FROM sales 
    WHERE DATE(sale_date) BETWEEN '$start_date_esc' AND '$end_date_esc'
");
$sales_row = mysqli_fetch_assoc($sales_query);
$total_revenue = $sales_row['total_revenue'] ?? 0;
$total_tx = $sales_row['total_tx'] ?? 0;

// 2. Cost of Goods Sold (COGS)
$cogs_query = mysqli_query($conn, "
    SELECT SUM(si.quantity * p.purchase_price) AS total_cogs 
    FROM sale_items si 
    INNER JOIN sales s ON si.sale_id = s.sale_id 
    INNER JOIN products p ON si.product_id = p.product_id 
    WHERE DATE(s.sale_date) BETWEEN '$start_date_esc' AND '$end_date_esc'
");
$cogs_row = mysqli_fetch_assoc($cogs_query);
$total_cogs = $cogs_row['total_cogs'] ?? 0;

// Gross Profit
$gross_profit = $total_revenue - $total_cogs;

include('../includes/header.php');
?>

<!-- Filter Form (Hidden when printing) -->
<div class="card card-premium mb-4 no-print">
    <div class="card-body">
        <form method="GET" class="row align-items-end g-3">
            <div class="col-md-4">
                <label class="form-label">Start Date</label>
                <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">End Date</label>
                <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>" required>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-premium w-100"><i class="bi bi-filter-left me-1"></i> Filter</button>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-premium-outline w-100" onclick="window.print();"><i class="bi bi-printer me-1"></i> Print</button>
            </div>
        </form>
    </div>
</div>

<!-- Report Summary Header (Only shown when printing) -->
<div class="d-none d-print-block mb-4">
    <h2 class="text-center fw-bold">SmartVee POS Business Report</h2>
    <p class="text-center text-muted">Reporting Period: <?php echo date('M d, Y', strtotime($start_date)); ?> to <?php echo date('M d, Y', strtotime($end_date)); ?></p>
    <hr>
</div>

<!-- KPI Cards -->
<div class="row g-4 mb-4">
    <!-- Revenue -->
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card bg-gradient-indigo">
            <div class="kpi-icon"><i class="bi bi-cash-stack"></i></div>
            <p class="text-white-50 small mb-1">Total Sales Revenue</p>
            <h3 class="fw-bold mb-0">Rs. <?php echo number_format($total_revenue, 2); ?></h3>
        </div>
    </div>
    
    <!-- COGS -->
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card bg-gradient-rose">
            <div class="kpi-icon"><i class="bi bi-calculator"></i></div>
            <p class="text-white-50 small mb-1">Cost of Goods Sold (COGS)</p>
            <h3 class="fw-bold mb-0">Rs. <?php echo number_format($total_cogs, 2); ?></h3>
        </div>
    </div>
    
    <!-- Gross Profit -->
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card bg-gradient-emerald">
            <div class="kpi-icon"><i class="bi bi-trophy"></i></div>
            <p class="text-white-50 small mb-1">Gross Profit Margin</p>
            <h3 class="fw-bold mb-0">Rs. <?php echo number_format($gross_profit, 2); ?></h3>
        </div>
    </div>
    
    <!-- Transactions Count -->
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card bg-gradient-amber">
            <div class="kpi-icon"><i class="bi bi-cart-check"></i></div>
            <p class="text-white-50 small mb-1">Invoices Issued</p>
            <h3 class="fw-bold mb-0"><?php echo $total_tx; ?></h3>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Top Selling & Cashier Performance -->
    <div class="col-lg-7">
        <!-- Top Selling Products -->
        <div class="card card-premium mb-4">
            <div class="card-header"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Top Selling Products</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-modern">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th class="text-center">Qty Sold</th>
                                <th class="text-end">Revenue Generated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $top_products = mysqli_query($conn, "
                                SELECT p.product_name, p.brand, SUM(si.quantity) AS total_qty, SUM(si.subtotal) AS total_sales
                                FROM sale_items si
                                INNER JOIN sales s ON si.sale_id = s.sale_id
                                INNER JOIN products p ON si.product_id = p.product_id
                                WHERE DATE(s.sale_date) BETWEEN '$start_date_esc' AND '$end_date_esc'
                                GROUP BY si.product_id
                                ORDER BY total_qty DESC
                                LIMIT 5
                            ");
                            
                            if (mysqli_num_rows($top_products) > 0) {
                                while ($p = mysqli_fetch_assoc($top_products)) {
                            ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($p['product_name']); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($p['brand']); ?></div>
                                    </td>
                                    <td class="text-center fw-semibold text-dark">
                                        <?php echo $p['total_qty']; ?> Units
                                    </td>
                                    <td class="text-end fw-bold text-primary">
                                        Rs. <?php echo number_format($p['total_sales'], 2); ?>
                                    </td>
                                </tr>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='3' class='text-center py-4 text-muted'>No sales recorded in date range</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Cashier Performance -->
        <div class="card card-premium">
            <div class="card-header"><i class="bi bi-person-badge me-2 text-primary"></i>Sales by Employee / Cashier</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-modern">
                        <thead>
                            <tr>
                                <th>Employee Name</th>
                                <th class="text-center">Sales Invoiced</th>
                                <th class="text-end">Total Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $cashier_perf = mysqli_query($conn, "
                                SELECT u.full_name, u.role, COUNT(s.sale_id) as tx_count, SUM(s.grand_total) as total_collected
                                FROM sales s
                                INNER JOIN users u ON s.cashier_id = u.user_id
                                WHERE DATE(s.sale_date) BETWEEN '$start_date_esc' AND '$end_date_esc'
                                GROUP BY s.cashier_id
                                ORDER BY total_collected DESC
                            ");
                            
                            if (mysqli_num_rows($cashier_perf) > 0) {
                                while ($c = mysqli_fetch_assoc($cashier_perf)) {
                            ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($c['full_name']); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($c['role']); ?></div>
                                    </td>
                                    <td class="text-center fw-semibold text-dark">
                                        <?php echo $c['tx_count']; ?> Checkouts
                                    </td>
                                    <td class="text-end fw-bold text-success">
                                        Rs. <?php echo number_format($c['total_collected'], 2); ?>
                                    </td>
                                </tr>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='3' class='text-center py-4 text-muted'>No sales logs found for this period</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Right Column: Low Stock Alerts -->
    <div class="col-lg-5">
        <div class="card card-premium">
            <div class="card-header"><i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>Low Stock Inventory Warning</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-modern">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th class="text-center">Stock</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $low_stock = mysqli_query($conn, "
                                SELECT p.*, c.category_name 
                                FROM products p
                                LEFT JOIN categories c ON p.category_id = c.category_id
                                WHERE p.stock_qty <= p.reorder_level
                                ORDER BY p.stock_qty ASC
                                LIMIT 6
                            ");
                            
                            if (mysqli_num_rows($low_stock) > 0) {
                                while ($ls = mysqli_fetch_assoc($low_stock)) {
                                    $qty = $ls['stock_qty'];
                                    $badge_class = ($qty == 0) ? 'bg-danger' : 'bg-warning text-dark';
                            ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($ls['product_name']); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($ls['brand']); ?></div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?php echo $badge_class; ?> py-1.5 px-2.5 rounded">
                                            <?php echo $qty; ?> left
                                        </span>
                                    </td>
                                    <td class="text-center no-print">
                                        <a href="products.php" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.8rem;">Restock</a>
                                    </td>
                                </tr>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='3' class='text-center py-4 text-success'><i class='bi bi-check-circle me-1'></i> All product stock levels are stable</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('../includes/footer.php'); ?>
