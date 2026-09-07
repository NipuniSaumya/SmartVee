<?php
include('../includes/auth.php');
include('../includes/db.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../auth/login.php");
    exit();
}

$page_title = "Supplier Management";
$msg = "";
$error = "";

// Handle ADD Supplier
if (isset($_POST['add_supplier'])) {
    $supplier_name = mysqli_real_escape_string($conn, $_POST['supplier_name']);
    $contact_no = mysqli_real_escape_string($conn, $_POST['contact_no']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    
    $stmt = $conn->prepare("INSERT INTO suppliers (supplier_name, contact_no, email, address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $supplier_name, $contact_no, $email, $address);
    if ($stmt->execute()) {
        $msg = "Supplier added successfully.";
    } else {
        $error = "Error adding supplier: " . $stmt->error;
    }
    $stmt->close();
}

// Handle EDIT Supplier
if (isset($_POST['edit_supplier'])) {
    $supplier_id = (int)$_POST['supplier_id'];
    $supplier_name = mysqli_real_escape_string($conn, $_POST['supplier_name']);
    $contact_no = mysqli_real_escape_string($conn, $_POST['contact_no']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    
    $stmt = $conn->prepare("UPDATE suppliers SET supplier_name=?, contact_no=?, email=?, address=? WHERE supplier_id=?");
    $stmt->bind_param("ssssi", $supplier_name, $contact_no, $email, $address, $supplier_id);
    if ($stmt->execute()) {
        $msg = "Supplier updated successfully.";
    } else {
        $error = "Error updating supplier: " . $stmt->error;
    }
    $stmt->close();
}

// Handle DELETE Supplier
if (isset($_GET['delete'])) {
    $supplier_id = (int)$_GET['delete'];
    
    $stmt = $conn->prepare("DELETE FROM suppliers WHERE supplier_id = ?");
    $stmt->bind_param("i", $supplier_id);
    if ($stmt->execute()) {
        $msg = "Supplier deleted successfully.";
    } else {
        $error = "Error deleting supplier: " . $stmt->error;
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
            <input type="text" id="supplierSearch" class="form-control border-start-0" placeholder="Search suppliers..." data-search-table="#suppliersTable">
        </div>
    </div>
    
    <!-- Right: Add Action Button -->
    <div class="col-md-auto">
        <button class="btn btn-premium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
            <i class="bi bi-plus-circle"></i> Add New Supplier
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

<!-- Suppliers Card -->
<div class="card card-premium mt-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-modern" id="suppliersTable">
                <thead>
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th>Supplier Name</th>
                        <th>Contact info</th>
                        <th>Address</th>
                        <th style="width: 150px;" class="text-center">Products</th>
                        <th style="width: 180px;" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $query = "
                        SELECT s.*, 
                               COUNT(p.product_id) AS product_count,
                               SUM(CASE WHEN p.stock_qty <= p.reorder_level THEN 1 ELSE 0 END) AS low_stock_count
                        FROM suppliers s 
                        LEFT JOIN products p ON s.supplier_id = p.supplier_id 
                        GROUP BY s.supplier_id 
                        ORDER BY s.supplier_id DESC
                    ";
                    $result = mysqli_query($conn, $query);
                    
                    if (mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $supplier_json = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                    ?>
                        <tr>
                            <td class="text-muted fw-bold">#<?php echo $row['supplier_id']; ?></td>
                            <td>
                                <strong class="text-dark"><?php echo htmlspecialchars($row['supplier_name']); ?></strong>
                            </td>
                            <td>
                                <div class="small text-dark"><i class="bi bi-telephone text-muted me-1"></i><?php echo htmlspecialchars($row['contact_no']); ?></div>
                                <div class="small text-muted"><i class="bi bi-envelope text-muted me-1"></i><?php echo htmlspecialchars($row['email'] ?? 'N/A'); ?></div>
                            </td>
                            <td class="text-muted small">
                                <?php echo !empty($row['address']) ? htmlspecialchars($row['address']) : '<em>No address listed</em>'; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge-modern badge-success">
                                    <?php echo $row['product_count']; ?> Products
                                </span>
                                <?php if ((int)$row['low_stock_count'] > 0): ?>
                                <div class="mt-1">
                                    <span class="badge bg-warning text-dark"><?php echo (int)$row['low_stock_count']; ?> low stock</span>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <?php if ((int)$row['product_count'] > 0): ?>
                                    <a href="purchase_order.php?supplier_id=<?php echo (int)$row['supplier_id']; ?>"
                                       class="btn btn-sm btn-outline-success"
                                       title="Create purchase order">
                                        <i class="bi bi-cart-plus"></i>
                                    </a>
                                    <?php endif; ?>
                                    <button 
                                        type="button" 
                                        class="btn btn-sm btn-outline-primary edit-supplier-btn" 
                                        data-supplier='<?php echo $supplier_json; ?>'
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editSupplierModal">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <a href="suppliers.php?delete=<?php echo urlencode($row['supplier_id']); ?>" 
                                       class="btn btn-sm btn-outline-danger" 
                                       onclick="return confirm('Are you sure you want to delete this supplier? Linked products will lose their supplier link.');">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php 
                        }
                    } else {
                    ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-truck fs-2 d-block mb-2 text-black-50"></i>
                                No suppliers found. Click "Add New Supplier" to create supplier records.
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ==========================================
     ADD SUPPLIER MODAL
     ========================================== -->
<div class="modal fade" id="addSupplierModal" tabindex="-1" aria-labelledby="addSupplierModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header bg-dark text-white" style="border-top-left-radius: 16px; border-top-right-radius: 16px;">
                <h5 class="modal-title fw-bold" id="addSupplierModalLabel"><i class="bi bi-plus-circle-fill me-2"></i>Add Supplier</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label">Supplier Name *</label>
                        <input type="text" name="supplier_name" class="form-control" placeholder="e.g. ABC Auto Spares" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Number *</label>
                        <input type="text" name="contact_no" class="form-control" placeholder="e.g. 0771234567" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="e.g. sales@abc.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Office Address</label>
                        <textarea name="address" class="form-control" rows="3" placeholder="Enter physical address..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light" style="border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_supplier" class="btn btn-premium">Save Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==========================================
     EDIT SUPPLIER MODAL
     ========================================== -->
<div class="modal fade" id="editSupplierModal" tabindex="-1" aria-labelledby="editSupplierModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header bg-primary text-white" style="border-top-left-radius: 16px; border-top-right-radius: 16px;">
                <h5 class="modal-title fw-bold" id="editSupplierModalLabel"><i class="bi bi-pencil-square me-2"></i>Edit Supplier Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="supplier_id" id="edit_supplier_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label">Supplier Name *</label>
                        <input type="text" name="supplier_name" id="edit_supplier_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Number *</label>
                        <input type="text" name="contact_no" id="edit_contact_no" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" id="edit_email" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Office Address</label>
                        <textarea name="address" id="edit_address" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light" style="border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_supplier" class="btn btn-primary">Update Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Populating the Edit Supplier Modal
        const editButtons = document.querySelectorAll(".edit-supplier-btn");
        
        editButtons.forEach(button => {
            button.addEventListener("click", function() {
                const supplier = JSON.parse(this.getAttribute("data-supplier"));
                
                document.getElementById("edit_supplier_id").value = supplier.supplier_id;
                document.getElementById("edit_supplier_name").value = supplier.supplier_name;
                document.getElementById("edit_contact_no").value = supplier.contact_no;
                document.getElementById("edit_email").value = supplier.email || "";
                document.getElementById("edit_address").value = supplier.address || "";
            });
        });
        
        // Client-side search matching
        const searchInput = document.getElementById("supplierSearch");
        if (searchInput) {
            searchInput.addEventListener("keyup", function() {
                const query = this.value.toLowerCase();
                const rows = document.querySelectorAll("#suppliersTable tbody tr");
                
                rows.forEach(row => {
                    const name = row.children[1].innerText.toLowerCase();
                    const info = row.children[2].innerText.toLowerCase();
                    const addr = row.children[3].innerText.toLowerCase();
                    
                    if (name.includes(query) || info.includes(query) || addr.includes(query)) {
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
