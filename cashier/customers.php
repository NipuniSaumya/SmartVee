<?php
include('../includes/auth.php');
include('../includes/db.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Cashier') {
    header("Location: ../auth/login.php");
    exit();
}

$page_title = "Customer Profile Registry";
$msg = "";
$error = "";

// Handle ADD Customer
if (isset($_POST['add_customer'])) {
    $customer_name = mysqli_real_escape_string($conn, $_POST['customer_name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $vehicle_no = mysqli_real_escape_string($conn, $_POST['vehicle_no']);
    
    $stmt = $conn->prepare("INSERT INTO customers (customer_name, phone, email, vehicle_no) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $customer_name, $phone, $email, $vehicle_no);
    if ($stmt->execute()) {
        $msg = "Customer added successfully.";
    } else {
        $error = "Error adding customer: " . $stmt->error;
    }
    $stmt->close();
}

// Handle EDIT Customer
if (isset($_POST['edit_customer'])) {
    $customer_id = (int)$_POST['customer_id'];
    $customer_name = mysqli_real_escape_string($conn, $_POST['customer_name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $vehicle_no = mysqli_real_escape_string($conn, $_POST['vehicle_no']);
    
    $stmt = $conn->prepare("UPDATE customers SET customer_name=?, phone=?, email=?, vehicle_no=? WHERE customer_id=?");
    $stmt->bind_param("ssssi", $customer_name, $phone, $email, $vehicle_no, $customer_id);
    if ($stmt->execute()) {
        $msg = "Customer updated successfully.";
    } else {
        $error = "Error updating customer: " . $stmt->error;
    }
    $stmt->close();
}

include('../includes/header.php');
?>

<div class="row mb-3 align-items-center justify-content-between g-3">
    <!-- Left: Search Box -->
    <div class="col-md-5">
        <div class="input-group shadow-sm">
            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
            <input type="text" id="customerSearch" class="form-control border-start-0" placeholder="Search customers by name, phone, vehicle no..." data-search-table="#customersTable">
        </div>
    </div>
    
    <!-- Right: Add Action Button -->
    <div class="col-md-auto">
        <button class="btn btn-premium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
            <i class="bi bi-plus-circle"></i> Register Customer
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

<!-- Customers Table Card -->
<div class="card card-premium mt-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-modern" id="customersTable">
                <thead>
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th>Customer Name</th>
                        <th>Contact details</th>
                        <th>Vehicle Number</th>
                        <th style="width: 150px;" class="text-center">Registered Date</th>
                        <th style="width: 100px;" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $query = "SELECT * FROM customers ORDER BY customer_id DESC";
                    $result = mysqli_query($conn, $query);
                    
                    if (mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $customer_json = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                    ?>
                        <tr>
                            <td class="text-muted fw-bold">#<?php echo $row['customer_id']; ?></td>
                            <td>
                                <strong class="text-dark"><?php echo htmlspecialchars($row['customer_name']); ?></strong>
                            </td>
                            <td>
                                <div class="small text-dark"><i class="bi bi-telephone text-muted me-1"></i><?php echo htmlspecialchars($row['phone']); ?></div>
                                <div class="small text-muted"><i class="bi bi-envelope text-muted me-1"></i><?php echo htmlspecialchars($row['email'] ?? 'N/A'); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-dark text-white shadow-sm font-monospace py-2 px-3 border border-dark">
                                    <i class="bi bi-car-front me-2"></i><?php echo htmlspecialchars($row['vehicle_no']); ?>
                                </span>
                            </td>
                            <td class="text-center text-muted small">
                                <?php echo date('Y-m-d', strtotime($row['created_at'])); ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group customer-action-btns">
                                    <a href="invoice.php?customer_id=<?php echo urlencode($row['customer_id']); ?>"
                                       class="btn btn-sm btn-outline-dark"
                                       title="View Purchase History">
                                        <i class="bi bi-clock-history"></i>
                                    </a>
                                    <button 
                                        type="button" 
                                        class="btn btn-sm btn-outline-primary edit-customer-btn" 
                                        data-customer='<?php echo $customer_json; ?>'
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editCustomerModal"
                                        title="Edit Customer">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php 
                        }
                    } else {
                    ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-people fs-2 d-block mb-2 text-black-50"></i>
                                No customers registered. Click "Register Customer" to start.
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ==========================================
     ADD CUSTOMER MODAL
     ========================================== -->
<div class="modal fade" id="addCustomerModal" tabindex="-1" aria-labelledby="addCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header bg-dark text-white" style="border-top-left-radius: 16px; border-top-right-radius: 16px;">
                <h5 class="modal-title fw-bold" id="addCustomerModalLabel"><i class="bi bi-plus-circle-fill me-2"></i>Add Customer Profile</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label">Customer Name *</label>
                        <input type="text" name="customer_name" class="form-control" placeholder="e.g. Ruwan Silva" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number *</label>
                        <input type="text" name="phone" class="form-control" placeholder="e.g. 0777123456" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="e.g. ruwan@gmail.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Vehicle Number *</label>
                        <input type="text" name="vehicle_no" class="form-control text-uppercase" placeholder="e.g. WP WP-1234 or WP CAA-5678" required>
                    </div>
                </div>
                <div class="modal-footer bg-light" style="border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_customer" class="btn btn-premium">Save Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==========================================
     EDIT CUSTOMER MODAL
     ========================================== -->
<div class="modal fade" id="editCustomerModal" tabindex="-1" aria-labelledby="editCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header bg-primary text-white" style="border-top-left-radius: 16px; border-top-right-radius: 16px;">
                <h5 class="modal-title fw-bold" id="editCustomerModalLabel"><i class="bi bi-pencil-square me-2"></i>Edit Customer Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="customer_id" id="edit_customer_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label">Customer Name *</label>
                        <input type="text" name="customer_name" id="edit_customer_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number *</label>
                        <input type="text" name="phone" id="edit_phone" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" id="edit_email" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Vehicle Number *</label>
                        <input type="text" name="vehicle_no" id="edit_vehicle_no" class="form-control text-uppercase" required>
                    </div>
                </div>
                <div class="modal-footer bg-light" style="border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_customer" class="btn btn-primary">Update Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Populating the Edit Customer Modal
        const editButtons = document.querySelectorAll(".edit-customer-btn");
        
        editButtons.forEach(button => {
            button.addEventListener("click", function() {
                const customer = JSON.parse(this.getAttribute("data-customer"));
                
                document.getElementById("edit_customer_id").value = customer.customer_id;
                document.getElementById("edit_customer_name").value = customer.customer_name;
                document.getElementById("edit_phone").value = customer.phone;
                document.getElementById("edit_email").value = customer.email || "";
                document.getElementById("edit_vehicle_no").value = customer.vehicle_no;
            });
        });
        
        // Client-side search matching
        const searchInput = document.getElementById("customerSearch");
        if (searchInput) {
            searchInput.addEventListener("keyup", function() {
                const query = this.value.toLowerCase();
                const rows = document.querySelectorAll("#customersTable tbody tr");
                
                rows.forEach(row => {
                    const name = row.children[1].innerText.toLowerCase();
                    const info = row.children[2].innerText.toLowerCase();
                    const vehicle = row.children[3].innerText.toLowerCase();
                    
                    if (name.includes(query) || info.includes(query) || vehicle.includes(query)) {
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
