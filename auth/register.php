<?php
session_start();
include('../includes/db.php');

$error_msg = "";
$success_msg = "";

if (isset($_POST['register'])) {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    
    // Validate role
    if ($role !== 'Admin' && $role !== 'Cashier') {
        $error_msg = "Invalid role selected.";
    } else {
        // Check if username already exists
        $check_query = "SELECT * FROM users WHERE username='$username'";
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) > 0) {
            $error_msg = "Username already taken.";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            
            // Insert
            $insert_query = "INSERT INTO users (full_name, username, password, role, status) VALUES ('$full_name', '$username', '$hashed_password', '$role', 'Active')";
            if (mysqli_query($conn, $insert_query)) {
                header("Location: login.php?registered=1");
                exit();
            } else {
                $error_msg = "Registration failed: " . mysqli_error($conn);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartVee POS - Employee Registration</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-logo">
                <i class="bi bi-person-plus text-primary fs-1"></i>
            </div>
            <h3 class="fw-bold mb-1">Create Account</h3>
            <p class="text-white-50 small mb-0">Register a new Admin or Cashier profile</p>
        </div>
        
        <div class="auth-body">
            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php echo htmlspecialchars($error_msg); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0 text-muted">
                            <i class="bi bi-card-text"></i>
                        </span>
                        <input 
                            type="text" 
                            name="full_name" 
                            class="form-control border-start-0" 
                            placeholder="Enter full name" 
                            required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0 text-muted">
                            <i class="bi bi-person"></i>
                        </span>
                        <input 
                            type="text" 
                            name="username" 
                            class="form-control border-start-0" 
                            placeholder="Create username" 
                            required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0 text-muted">
                            <i class="bi bi-lock"></i>
                        </span>
                        <input 
                            type="password" 
                            name="password" 
                            class="form-control border-start-0" 
                            placeholder="Create password" 
                            required>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">System Role</label>
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0 text-muted">
                            <i class="bi bi-shield-lock"></i>
                        </span>
                        <select name="role" class="form-select border-start-0" required>
                            <option value="Cashier">Cashier</option>
                            <option value="Admin">Admin</option>
                        </select>
                    </div>
                </div>
                
                <button type="submit" name="register" class="btn btn-premium w-100 py-2.5">
                    Register User <i class="bi bi-check-circle-fill ms-1"></i>
                </button>
                
                <div class="text-center mt-3">
                    <span class="text-muted small">Already have an account? </span>
                    <a href="login.php" class="text-primary fw-semibold small text-decoration-none">Sign In</a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>