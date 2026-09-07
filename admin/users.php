<?php
include('../includes/auth.php');
include('../includes/db.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../auth/login.php");
    exit();
}

$page_title = "Employee Management";
$msg = "";
$error = "";

// Handle ADD User
if (isset($_POST['add_user'])) {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $status = $_POST['status'];
    
    // Check duplicate username
    $check = mysqli_query($conn, "SELECT * FROM users WHERE username = '$username'");
    if (mysqli_num_rows($check) > 0) {
        $error = "Username already exists.";
    } else {
        $hashed_pw = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO users (full_name, username, password, role, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $full_name, $username, $hashed_pw, $role, $status);
        if ($stmt->execute()) {
            $msg = "User account created successfully.";
        } else {
            $error = "Error creating user: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle EDIT User
if (isset($_POST['edit_user'])) {
    $user_id = (int)$_POST['user_id'];
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $role = $_POST['role'];
    $status = $_POST['status'];
    $new_password = $_POST['password'];
    
    // Check duplicate username for other users
    $check = mysqli_query($conn, "SELECT * FROM users WHERE username = '$username' AND user_id != $user_id");
    if (mysqli_num_rows($check) > 0) {
        $error = "Username already in use by another account.";
    } else {
        if (!empty($new_password)) {
            $hashed_pw = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE users SET full_name=?, username=?, password=?, role=?, status=? WHERE user_id=?");
            $stmt->bind_param("sssssi", $full_name, $username, $hashed_pw, $role, $status, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET full_name=?, username=?, role=?, status=? WHERE user_id=?");
            $stmt->bind_param("ssssi", $full_name, $username, $role, $status, $user_id);
        }
        
        if ($stmt->execute()) {
            $msg = "User account details updated.";
            // If updating current logged in user details, sync session
            if ($user_id === (int)$_SESSION['user_id']) {
                $_SESSION['name'] = $full_name;
                $_SESSION['role'] = $role;
            }
        } else {
            $error = "Error updating user details: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle DELETE User
if (isset($_GET['delete'])) {
    $user_id = (int)$_GET['delete'];
    
    // Prevent self deletion
    if ($user_id === (int)$_SESSION['user_id']) {
        $error = "You cannot delete your own active administrative account.";
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $msg = "User account deleted successfully.";
        } else {
            $error = "Error deleting user account: " . $stmt->error;
        }
        $stmt->close();
    }
}

include('../includes/header.php');
?>

<div class="row mb-3 align-items-center justify-content-between g-3">
    <!-- Left: Search Box -->
    <div class="col-md-5">
        <div class="input-group shadow-sm">
            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
            <input type="text" id="userSearch" class="form-control border-start-0" placeholder="Search employees..." data-search-table="#usersTable">
        </div>
    </div>
    
    <!-- Right: Add Action Button -->
    <div class="col-md-auto">
        <button class="btn btn-premium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="bi bi-person-plus"></i> Add New Employee
        </button>
    </div>
</div>

<?php if (!empty($msg)): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mt-3" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> <?php echo htmlspecialchars($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mt-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Users Card -->
<div class="card card-premium mt-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-modern" id="usersTable">
                <thead>
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>System Role</th>
                        <th>Status</th>
                        <th style="width: 150px;" class="text-center">Registered Date</th>
                        <th style="width: 150px;" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $query = "SELECT * FROM users ORDER BY user_id DESC";
                    $result = mysqli_query($conn, $query);
                    
                    if (mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            // Status formatting
                            $status = $row['status'];
                            $status_badge = ($status === 'Active') 
                                ? '<span class="badge-modern badge-success"><i class="bi bi-check-circle me-1"></i>Active</span>'
                                : '<span class="badge-modern badge-danger"><i class="bi bi-slash-circle me-1"></i>Inactive</span>';
                            
                            $user_json = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                    ?>
                        <tr>
                            <td class="text-muted fw-bold">#<?php echo $row['user_id']; ?></td>
                            <td>
                                <strong class="text-dark"><?php echo htmlspecialchars($row['full_name']); ?></strong>
                            </td>
                            <td class="font-monospace text-muted small">
                                @<?php echo htmlspecialchars($row['username']); ?>
                            </td>
                            <td>
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($row['role']); ?></span>
                            </td>
                            <td>
                                <?php echo $status_badge; ?>
                            </td>
                            <td class="text-center text-muted small">
                                <?php echo date('Y-m-d', strtotime($row['created_at'])); ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <button 
                                        type="button" 
                                        class="btn btn-sm btn-outline-primary edit-user-btn" 
                                        data-user='<?php echo $user_json; ?>'
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editUserModal">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php if ($row['user_id'] !== $_SESSION['user_id']): ?>
                                        <a href="users.php?delete=<?php echo urlencode($row['user_id']); ?>" 
                                           class="btn btn-sm btn-outline-danger" 
                                           onclick="return confirm('Are you sure you want to delete this employee account?');">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php 
                        }
                    } else {
                    ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-person-gear fs-2 d-block mb-2 text-black-50"></i>
                                No user accounts found.
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ==========================================
     ADD USER MODAL
     ========================================== -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header bg-dark text-white" style="border-top-left-radius: 16px; border-top-right-radius: 16px;">
                <h5 class="modal-title fw-bold" id="addUserModalLabel"><i class="bi bi-person-plus-fill me-2"></i>Add Employee</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="full_name" class="form-control" placeholder="e.g. Ruwan Silva" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username *</label>
                        <input type="text" name="username" class="form-control" placeholder="e.g. ruwans" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-control" placeholder="Choose a secure password" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">System Role *</label>
                            <select name="role" class="form-select" required>
                                <option value="Cashier">Cashier</option>
                                <option value="Admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Account Status *</label>
                            <select name="status" class="form-select" required>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light" style="border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_user" class="btn btn-premium">Save Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==========================================
     EDIT USER MODAL
     ========================================== -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header bg-primary text-white" style="border-top-left-radius: 16px; border-top-right-radius: 16px;">
                <h5 class="modal-title fw-bold" id="editUserModalLabel"><i class="bi bi-pencil-square me-2"></i>Modify Account Settings</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username *</label>
                        <input type="text" name="username" id="edit_username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password (leave blank to keep current)</label>
                        <input type="password" name="password" id="edit_password" class="form-control" placeholder="Update password">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">System Role *</label>
                            <select name="role" id="edit_role" class="form-select" required>
                                <option value="Cashier">Cashier</option>
                                <option value="Admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Account Status *</label>
                            <select name="status" id="edit_status" class="form-select" required>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light" style="border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_user" class="btn btn-primary">Update Details</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Populating the Edit User Modal
        const editButtons = document.querySelectorAll(".edit-user-btn");
        
        editButtons.forEach(button => {
            button.addEventListener("click", function() {
                const user = JSON.parse(this.getAttribute("data-user"));
                
                document.getElementById("edit_user_id").value = user.user_id;
                document.getElementById("edit_full_name").value = user.full_name;
                document.getElementById("edit_username").value = user.username;
                document.getElementById("edit_role").value = user.role;
                document.getElementById("edit_status").value = user.status;
                document.getElementById("edit_password").value = ""; // Clear password input
            });
        });
        
        // Client-side search matching
        const searchInput = document.getElementById("userSearch");
        if (searchInput) {
            searchInput.addEventListener("keyup", function() {
                const query = this.value.toLowerCase();
                const rows = document.querySelectorAll("#usersTable tbody tr");
                
                rows.forEach(row => {
                    const name = row.children[1].innerText.toLowerCase();
                    const username = row.children[2].innerText.toLowerCase();
                    const role = row.children[3].innerText.toLowerCase();
                    
                    if (name.includes(query) || username.includes(query) || role.includes(query)) {
                        row.style.display = "";
                    } else {
                        row.style.display = "none";
                    }
                });
            });
        }
    });
</script>

<?php include('../includes/footer.php'); ?>
