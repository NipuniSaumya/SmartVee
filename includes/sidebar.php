<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure base path is calculated
$base_path = "";
$script_name = $_SERVER['SCRIPT_NAME'];
if (strpos($script_name, '/admin/') !== false || strpos($script_name, '/cashier/') !== false) {
    $base_path = "../";
}

$role = $_SESSION['role'] ?? '';
$full_name = $_SESSION['name'] ?? 'System User';

// Initials for avatar
$name_parts = explode(' ', $full_name);
$initials = '';
foreach ($name_parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);
if(empty($initials)) $initials = "SV";

$current_page = basename($script_name);
?>
<div class="sidebar no-print">
    <div class="sidebar-brand">
        <i class="bi bi-cpu"></i>
        <span>SmartVee POS</span>
    </div>
    
    <div class="sidebar-profile">
        <div class="profile-avatar">
            <?php echo htmlspecialchars($initials); ?>
        </div>
        <div class="profile-info">
            <span class="profile-name" title="<?php echo htmlspecialchars($full_name); ?>">
                <?php echo htmlspecialchars($full_name); ?>
            </span>
            <span class="profile-role">
                <?php echo htmlspecialchars($role); ?>
            </span>
        </div>
    </div>
    
    <div class="sidebar-menu">
        <?php if ($role === 'Admin'): ?>
            <a href="<?php echo $base_path; ?>admin/dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                <i class="bi bi-grid-1x2"></i> Dashboard
            </a>
            <a href="<?php echo $base_path; ?>admin/products.php" class="<?php echo ($current_page == 'products.php') ? 'active' : ''; ?>">
                <i class="bi bi-box-seam"></i> Products
            </a>
            <a href="<?php echo $base_path; ?>admin/categories.php" class="<?php echo ($current_page == 'categories.php') ? 'active' : ''; ?>">
                <i class="bi bi-tags"></i> Categories
            </a>
            <a href="<?php echo $base_path; ?>admin/suppliers.php" class="<?php echo ($current_page == 'suppliers.php') ? 'active' : ''; ?>">
                <i class="bi bi-truck"></i> Suppliers
            </a>
            <a href="<?php echo $base_path; ?>admin/supplier_invoice.php" class="<?php echo ($current_page == 'supplier_invoice.php' || $current_page == 'purchase_order.php') ? 'active' : ''; ?>">
                <i class="bi bi-file-earmark-arrow-up"></i> Supplier Orders
            </a>
            <a href="<?php echo $base_path; ?>admin/customers.php" class="<?php echo ($current_page == 'customers.php') ? 'active' : ''; ?>">
                <i class="bi bi-people"></i> Customers
            </a>
            <a href="<?php echo $base_path; ?>admin/users.php" class="<?php echo ($current_page == 'users.php') ? 'active' : ''; ?>">
                <i class="bi bi-person-gear"></i> Employees
            </a>
            <a href="<?php echo $base_path; ?>cashier/invoice.php" class="<?php echo ($current_page == 'invoice.php') ? 'active' : ''; ?>">
                <i class="bi bi-receipt"></i> Sales Invoices
            </a>
            <a href="<?php echo $base_path; ?>admin/reports.php" class="<?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
                <i class="bi bi-bar-chart-line"></i> Reports
            </a>
        <?php elseif ($role === 'Cashier'): ?>
            <a href="<?php echo $base_path; ?>cashier/dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="<?php echo $base_path; ?>cashier/pos.php" class="<?php echo ($current_page == 'pos.php') ? 'active' : ''; ?>">
                <i class="bi bi-cart3"></i> POS Checkout
            </a>
            <a href="<?php echo $base_path; ?>cashier/invoice.php" class="<?php echo ($current_page == 'invoice.php') ? 'active' : ''; ?>">
                <i class="bi bi-receipt"></i> Invoices
            </a>
            <a href="<?php echo $base_path; ?>cashier/customers.php" class="<?php echo ($current_page == 'customers.php') ? 'active' : ''; ?>">
                <i class="bi bi-people"></i> Customers
            </a>
        <?php endif; ?>
        
        <a href="<?php echo $base_path; ?>auth/logout.php" class="mt-auto text-danger">
            <i class="bi bi-box-arrow-left"></i> Logout
        </a>
    </div>
</div>
