<?php
// Calculate the relative path prefix dynamically
$base_path = "";
$script_name = $_SERVER['SCRIPT_NAME'];
if (strpos($script_name, '/admin/') !== false || strpos($script_name, '/cashier/') !== false) {
    $base_path = "../";
} elseif (strpos($script_name, '/auth/') !== false) {
    $base_path = "../";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : "SmartVee POS"; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <!-- Custom CSS Styles -->
    <link href="<?php echo $base_path; ?>assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Sidebar backdrop for mobile view overlay click-aways -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- Mobile Top Navigation Header -->
    <header class="mobile-top-header no-print">
        <button class="btn btn-light px-2.5 py-1.5" type="button" id="mobileSidebarToggle">
            <i class="bi bi-list fs-4"></i>
        </button>
        <span class="fw-bold text-dark text-uppercase tracking-wider" style="font-size: 0.95rem;">SmartVee POS</span>
        <span class="badge bg-light text-dark border px-2.5 py-1.5">
            <i class="bi bi-shield-check text-success"></i>
        </span>
    </header>

    <div class="app-container">
        <!-- Sidebar layout wrapper -->
        <?php include(__DIR__ . '/sidebar.php'); ?>
        
        <!-- Main Panel Container -->
        <main class="main-content">
            <!-- Desktop header panel (Hidden on mobile) -->
            <div class="d-flex justify-content-between align-items-center mb-4 no-print d-none d-lg-flex">
                <h4 class="mb-0 fw-bold text-uppercase tracking-wider">
                    <?php echo isset($page_title) ? htmlspecialchars($page_title) : "SmartVee POS"; ?>
                </h4>
                
                <!-- Quick User Dropdown / Status info -->
                <div class="d-flex align-items-center gap-3">
                    <span class="badge bg-white text-dark shadow-sm py-2 px-3 border border-light">
                        <i class="bi bi-calendar-event me-2 text-primary"></i>
                        <?php echo date("F d, Y"); ?>
                    </span>
                    <span class="badge bg-white text-dark shadow-sm py-2 px-3 border border-light">
                        <i class="bi bi-shield-check me-2 text-success"></i>
                        <?php echo htmlspecialchars($_SESSION['role'] ?? 'Guest'); ?>
                    </span>
                </div>
            </div>
