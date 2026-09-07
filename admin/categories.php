<?php
include('../includes/auth.php');
include('../includes/db.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../auth/login.php");
    exit();
}

$page_title = "Category Management";
$msg = "";
$error = "";

// Handle ADD Category
if (isset($_POST['add_category'])) {
    $category_name = mysqli_real_escape_string($conn, $_POST['category_name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    
    $stmt = $conn->prepare("INSERT INTO categories (category_name, description) VALUES (?, ?)");
    $stmt->bind_param("ss", $category_name, $description);
    if ($stmt->execute()) {
        $msg = "Category added successfully.";
    } else {
        $error = "Error adding category: " . $stmt->error;
    }
    $stmt->close();
}

// Handle EDIT Category
if (isset($_POST['edit_category'])) {
    $category_id = (int)$_POST['category_id'];
    $category_name = mysqli_real_escape_string($conn, $_POST['category_name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    
    $stmt = $conn->prepare("UPDATE categories SET category_name=?, description=? WHERE category_id=?");
    $stmt->bind_param("ssi", $category_name, $description, $category_id);
    if ($stmt->execute()) {
        $msg = "Category updated successfully.";
    } else {
        $error = "Error updating category: " . $stmt->error;
    }
    $stmt->close();
}

// Handle DELETE Category
if (isset($_GET['delete'])) {
    $category_id = (int)$_GET['delete'];
    
    $stmt = $conn->prepare("DELETE FROM categories WHERE category_id = ?");
    $stmt->bind_param("i", $category_id);
    if ($stmt->execute()) {
        $msg = "Category deleted successfully.";
    } else {
        $error = "Error deleting category: " . $stmt->error;
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
            <input type="text" id="categorySearch" class="form-control border-start-0" placeholder="Search categories..." data-search-table="#categoriesTable">
        </div>
    </div>
    
    <!-- Right: Add Action Button -->
    <div class="col-md-auto">
        <button class="btn btn-premium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
            <i class="bi bi-plus-circle"></i> Add New Category
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

<!-- Categories Card -->
<div class="card card-premium mt-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-modern" id="categoriesTable">
                <thead>
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th>Category Name</th>
                        <th>Description</th>
                        <th style="width: 150px;" class="text-center">Products Count</th>
                        <th style="width: 150px;" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $query = "
                        SELECT c.*, COUNT(p.product_id) AS product_count 
                        FROM categories c 
                        LEFT JOIN products p ON c.category_id = p.category_id 
                        GROUP BY c.category_id 
                        ORDER BY c.category_id DESC
                    ";
                    $result = mysqli_query($conn, $query);
                    
                    if (mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $category_json = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                    ?>
                        <tr>
                            <td class="text-muted fw-bold">#<?php echo $row['category_id']; ?></td>
                            <td>
                                <strong class="text-dark"><?php echo htmlspecialchars($row['category_name']); ?></strong>
                            </td>
                            <td class="text-muted text-truncate" style="max-width: 300px;">
                                <?php echo !empty($row['description']) ? htmlspecialchars($row['description']) : '<em>No description provided</em>'; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge-modern badge-success">
                                    <?php echo $row['product_count']; ?> Parts
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <button 
                                        type="button" 
                                        class="btn btn-sm btn-outline-primary edit-category-btn" 
                                        data-category='<?php echo $category_json; ?>'
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editCategoryModal">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <a href="categories.php?delete=<?php echo urlencode($row['category_id']); ?>" 
                                       class="btn btn-sm btn-outline-danger" 
                                       onclick="return confirm('Are you sure you want to delete this category? Linked products will be uncategorized.');">
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
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="bi bi-tags fs-2 d-block mb-2 text-black-50"></i>
                                No categories found. Click "Add New Category" to start grouping products.
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ==========================================
     ADD CATEGORY MODAL
     ========================================== -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header bg-dark text-white" style="border-top-left-radius: 16px; border-top-right-radius: 16px;">
                <h5 class="modal-title fw-bold" id="addCategoryModalLabel"><i class="bi bi-plus-circle-fill me-2"></i>Add Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label">Category Name *</label>
                        <input type="text" name="category_name" class="form-control" placeholder="e.g. Suspension & Steering" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Describe parts in this category..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light" style="border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_category" class="btn btn-premium">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==========================================
     EDIT CATEGORY MODAL
     ========================================== -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header bg-primary text-white" style="border-top-left-radius: 16px; border-top-right-radius: 16px;">
                <h5 class="modal-title fw-bold" id="editCategoryModalLabel"><i class="bi bi-pencil-square me-2"></i>Edit Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="category_id" id="edit_category_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label">Category Name *</label>
                        <input type="text" name="category_name" id="edit_category_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light" style="border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_category" class="btn btn-primary">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Populating the Edit Category Modal
        const editButtons = document.querySelectorAll(".edit-category-btn");
        
        editButtons.forEach(button => {
            button.addEventListener("click", function() {
                const category = JSON.parse(this.getAttribute("data-category"));
                
                document.getElementById("edit_category_id").value = category.category_id;
                document.getElementById("edit_category_name").value = category.category_name;
                document.getElementById("edit_description").value = category.description || "";
            });
        });
        
        // Client-side search matching
        const searchInput = document.getElementById("categorySearch");
        if (searchInput) {
            searchInput.addEventListener("keyup", function() {
                const query = this.value.toLowerCase();
                const rows = document.querySelectorAll("#categoriesTable tbody tr");
                
                rows.forEach(row => {
                    const name = row.children[1].innerText.toLowerCase();
                    const desc = row.children[2].innerText.toLowerCase();
                    
                    if (name.includes(query) || desc.includes(query)) {
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
