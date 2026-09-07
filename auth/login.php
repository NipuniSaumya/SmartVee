<?php
session_start();

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'Admin') {
        header("Location: ../admin/dashboard.php");
    } else {
        header("Location: ../cashier/dashboard.php");
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartVee POS - Secure Login</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Custom Style Sheet -->
    <link href="../assets/css/style.css" rel="stylesheet">
    
    <style>
        .input-group-text-custom {
            background: transparent;
            border-right: none;
            color: var(--text-muted);
        }
        .form-control-custom {
            border-left: none;
        }
        .form-control-custom:focus {
            border-left-color: transparent !important;
        }
    </style>
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-logo">
                <i class="bi bi-cpu text-primary fs-1"></i>
            </div>
            <h3 class="fw-bold mb-1">SmartVee POS</h3>
            <p class="text-white-50 small mb-0">Vehicle Parts Management System</p>
        </div>
        
        <div class="auth-body">
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php echo htmlspecialchars($_GET['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <form action="loginprocess.php" method="POST">
                <div class="mb-4">
                    <label class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text input-group-text-custom">
                            <i class="bi bi-person"></i>
                        </span>
                        <input 
                            type="text" 
                            name="username" 
                            class="form-control form-control-custom" 
                            placeholder="Enter username" 
                            required 
                            autocomplete="username">
                    </div>
                </div>
                
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label mb-0">Password</label>
                    </div>
                    <div class="input-group" id="show_hide_password">
                        <span class="input-group-text input-group-text-custom">
                            <i class="bi bi-lock"></i>
                        </span>
                        <input 
                            type="password" 
                            name="password" 
                            class="form-control form-control-custom" 
                            placeholder="Enter password" 
                            required 
                            autocomplete="current-password">
                        <span class="input-group-text" style="background: transparent; border-left: none; cursor: pointer;" id="toggle-pw-btn">
                            <i class="bi bi-eye-slash" id="toggle-pw-icon"></i>
                        </span>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-premium w-100 py-2.5 mt-2 mb-3">
                    Sign In <i class="bi bi-arrow-right-short ms-1"></i>
                </button>
                
                <?php if (isset($_GET['registered'])): ?>
                    <div class="text-center">
                        <span class="text-success small">Registration successful! Pls sign in.</span>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Handle password visibility toggle
    document.getElementById('toggle-pw-btn').addEventListener('click', function() {
        const passwordInput = document.querySelector('#show_hide_password input');
        const passwordIcon = document.querySelector('#toggle-pw-icon');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            passwordIcon.classList.remove('bi-eye-slash');
            passwordIcon.classList.add('bi-eye');
        } else {
            passwordInput.type = 'password';
            passwordIcon.classList.remove('bi-eye');
            passwordIcon.classList.add('bi-eye-slash');
        }
    });
</script>
</body>
</html>