<?php
include('../includes/auth.php');
include('../includes/db.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../auth/login.php");
    exit();
}

$page_title = "Product Management";
$msg = "";
$error = "";

// Handle ADD Product
if (isset($_POST['add_product'])) {
    $product_name = mysqli_real_escape_string($conn, $_POST['product_name']);
    $category_id = (int)$_POST['category_id'];
    $supplier_id = (int)$_POST['supplier_id'];
    $brand = mysqli_real_escape_string($conn, $_POST['brand']);
    $sku = mysqli_real_escape_string($conn, $_POST['sku']);
    $barcode = mysqli_real_escape_string($conn, $_POST['barcode']);
    $purchase_price = (float)$_POST['purchase_price'];
    $selling_price = (float)$_POST['selling_price'];
    $stock_qty = (int)$_POST['stock_qty'];
    $reorder_level = (int)$_POST['reorder_level'];

    $image = "";
    if (!empty($_FILES['product_image']['name'])) {
        $image = time() . '_' . $_FILES['product_image']['name'];
        $temp = $_FILES['product_image']['tmp_name'];
        move_uploaded_file($temp, "../assets/images/" . $image);
    }

    $stmt = $conn->prepare("INSERT INTO products 
        (product_name, category_id, supplier_id, brand, sku, barcode, purchase_price, selling_price, stock_qty, reorder_level, product_image) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("siisssddiis", $product_name, $category_id, $supplier_id, $brand, $sku, $barcode, $purchase_price, $selling_price, $stock_qty, $reorder_level, $image);
    
    if ($stmt->execute()) {
        $msg = "Product added successfully.";
    } else {
        $error = "Error adding product: " . $stmt->error;
    }
    $stmt->close();
}

// Handle EDIT Product
if (isset($_POST['edit_product'])) {
    $product_id = (int)$_POST['product_id'];
    $product_name = mysqli_real_escape_string($conn, $_POST['product_name']);
    $category_id = (int)$_POST['category_id'];
    $supplier_id = (int)$_POST['supplier_id'];
    $brand = mysqli_real_escape_string($conn, $_POST['brand']);
    $sku = mysqli_real_escape_string($conn, $_POST['sku']);
    $barcode = mysqli_real_escape_string($conn, $_POST['barcode']);
    $purchase_price = (float)$_POST['purchase_price'];
    $selling_price = (float)$_POST['selling_price'];
    $stock_qty = (int)$_POST['stock_qty'];
    $reorder_level = (int)$_POST['reorder_level'];

    if (!empty($_FILES['product_image']['name'])) {
        $image = time() . '_' . $_FILES['product_image']['name'];
        $temp = $_FILES['product_image']['tmp_name'];
        move_uploaded_file($temp, "../assets/images/" . $image);
        
        $stmt = $conn->prepare("UPDATE products SET 
            product_name=?, category_id=?, supplier_id=?, brand=?, sku=?, barcode=?, purchase_price=?, selling_price=?, stock_qty=?, reorder_level=?, product_image=? 
            WHERE product_id=?");
        $stmt->bind_param("siisssddiisi", $product_name, $category_id, $supplier_id, $brand, $sku, $barcode, $purchase_price, $selling_price, $stock_qty, $reorder_level, $image, $product_id);
    } else {
        $stmt = $conn->prepare("UPDATE products SET 
            product_name=?, category_id=?, supplier_id=?, brand=?, sku=?, barcode=?, purchase_price=?, selling_price=?, stock_qty=?, reorder_level=? 
            WHERE product_id=?");
        $stmt->bind_param("siisssddiii", $product_name, $category_id, $supplier_id, $brand, $sku, $barcode, $purchase_price, $selling_price, $stock_qty, $reorder_level, $product_id);
    }
    
    if ($stmt->execute()) {
        $msg = "Product updated successfully.";
    } else {
        $error = "Error updating product: " . $stmt->error;
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
            <input type="text" id="productSearch" class="form-control border-start-0" placeholder="Search by name, brand, SKU..." data-search-table="#productsTable">
        </div>
    </div>
    
    <!-- Right: Add Action Button -->
    <div class="col-md-auto">
        <button class="btn btn-premium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addProductModal">
            <i class="bi bi-plus-circle"></i> Add New Product
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

<!-- Products List Card -->
<div class="card card-premium mt-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-modern" id="productsTable">
                <thead>
                    <tr>
                        <th style="width: 70px;">Image</th>
                        <th>Name & Brand</th>
                        <th>SKU / Barcode</th>
                        <th>Category</th>
                        <th>Pricing (Rs.)</th>
                        <th>Stock Status</th>
                        <th>Supplier</th>
                        <th style="width: 150px;" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $query = "
                        SELECT p.*, c.category_name, s.supplier_name 
                        FROM products p 
                        LEFT JOIN categories c ON p.category_id = c.category_id 
                        LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id 
                        ORDER BY p.product_id DESC
                    ";
                    $result = mysqli_query($conn, $query);
                    
                    if (mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            // Determine stock status and badges
                            $qty = (int)$row['stock_qty'];
                            $reorder = (int)$row['reorder_level'];
                            
                            if ($qty <= 0) {
                                $stock_badge = '<span class="badge-modern badge-danger d-inline-block"><i class="bi bi-x-circle me-1"></i>Out of Stock</span>';
                            } elseif ($qty <= $reorder) {
                                $stock_badge = '<span class="badge-modern badge-warning d-inline-block"><i class="bi bi-exclamation-circle me-1"></i>Low Stock (' . $qty . ')</span>';
                            } else {
                                $stock_badge = '<span class="badge-modern badge-success d-inline-block"><i class="bi bi-check-circle me-1"></i>In Stock (' . $qty . ')</span>';
                            }
                            
                            // Image path
                            $img_file = htmlspecialchars($row['product_image']);
                            $img_src = !empty($img_file) && file_exists("../assets/images/" . $img_file) 
                                ? "../assets/images/" . $img_file 
                                : "../assets/images/default-parts.png";
                            
                            // Safe JSON for edit button data attributes
                            $product_json = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                    ?>
                        <tr>
                            <td>
                                <img src="<?php echo $img_src; ?>" class="rounded shadow-sm" style="width: 50px; height: 50px; object-fit: cover;" onerror="this.src='../assets/images/default-parts.png';">
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['product_name']); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($row['brand'] ?? 'No Brand'); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border font-monospace"><?php echo htmlspecialchars($row['sku'] ?? 'N/A'); ?></span>
                                <div class="text-muted" style="font-size: 0.75rem;"><i class="bi bi-upc-scan me-1"></i><?php echo htmlspecialchars($row['barcode'] ?? 'N/A'); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($row['category_name'] ?? 'Uncategorized'); ?></span>
                            </td>
                            <td>
                                <div class="small">Selling: <strong class="text-dark">Rs.<?php echo number_format($row['selling_price'], 2); ?></strong></div>
                                <div class="text-muted" style="font-size: 0.8rem;">Cost: Rs.<?php echo number_format($row['purchase_price'], 2); ?></div>
                            </td>
                            <td>
                                <?php echo $stock_badge; ?>
                                <?php if ($qty <= $reorder && !empty($row['supplier_id'])): ?>
                                    <a href="purchase_order.php?supplier_id=<?php echo (int)$row['supplier_id']; ?>" class="d-block small mt-1 text-decoration-none">
                                        <i class="bi bi-cart-plus me-1"></i>Order from Supplier
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="text-dark small"><i class="bi bi-truck text-muted me-1"></i><?php echo htmlspecialchars($row['supplier_name'] ?? 'N/A'); ?></span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <button 
                                        type="button" 
                                        class="btn btn-sm btn-outline-primary edit-product-btn" 
                                        data-product='<?php echo $product_json; ?>'
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editProductModal">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <a href="delete_product.php?id=<?php echo urlencode($row['product_id']); ?>" 
                                       class="btn btn-sm btn-outline-danger" 
                                       onclick="return confirm('Are you sure you want to delete this product?');">
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
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-box-seam fs-2 d-block mb-2 text-black-50"></i>
                                No products found. Click "Add New Product" to populate stock items.
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ==========================================
     ADD PRODUCT MODAL
     ========================================== -->
<div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header bg-dark text-white" style="border-top-left-radius: 16px; border-top-right-radius: 16px;">
                <h5 class="modal-title fw-bold" id="addProductModalLabel"><i class="bi bi-plus-circle-fill me-2"></i>Add New Product</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Product Name *</label>
                            <input type="text" name="product_name" class="form-control" placeholder="e.g. Brake Pads Front" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Brand</label>
                            <input type="text" name="brand" class="form-control" placeholder="e.g. Bosch">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">SKU (Unique Code) *</label>
                            <input type="text" name="sku" class="form-control" placeholder="e.g. BP-BOS-001" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Barcode</label>
                            <input type="text" name="barcode" class="form-control" placeholder="e.g. 123456789">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Purchase Price (Rs.) *</label>
                            <input type="number" step="0.01" name="purchase_price" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Selling Price (Rs.) *</label>
                            <input type="number" step="0.01" name="selling_price" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Stock Qty *</label>
                            <input type="number" name="stock_qty" class="form-control" value="0" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Reorder Lvl</label>
                            <input type="number" name="reorder_level" class="form-control" value="5" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Category *</label>
                            <select name="category_id" class="form-select" required>
                                <option value="">Select Category</option>
                                <?php
                                $cat_query = mysqli_query($conn, "SELECT * FROM categories ORDER BY category_name ASC");
                                while ($c = mysqli_fetch_assoc($cat_query)) {
                                    echo "<option value='{$c['category_id']}'>{$c['category_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Supplier *</label>
                            <select name="supplier_id" class="form-select" required>
                                <option value="">Select Supplier</option>
                                <?php
                                $sup_query = mysqli_query($conn, "SELECT * FROM suppliers ORDER BY supplier_name ASC");
                                while ($s = mysqli_fetch_assoc($sup_query)) {
                                    echo "<option value='{$s['supplier_id']}'>{$s['supplier_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Product Image</label>
                            <input type="file" name="product_image" class="form-control" accept="image/*">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light" style="border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_product" class="btn btn-premium">Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==========================================
     EDIT PRODUCT MODAL
     ========================================== -->
<div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header bg-primary text-white" style="border-top-left-radius: 16px; border-top-right-radius: 16px;">
                <h5 class="modal-title fw-bold" id="editProductModalLabel"><i class="bi bi-pencil-square me-2"></i>Modify Product Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="product_id" id="edit_product_id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Product Name *</label>
                            <input type="text" name="product_name" id="edit_product_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Brand</label>
                            <input type="text" name="brand" id="edit_brand" class="form-control">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">SKU (Unique Code) *</label>
                            <input type="text" name="sku" id="edit_sku" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Barcode</label>
                            <input type="text" name="barcode" id="edit_barcode" class="form-control">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Purchase Price (Rs.) *</label>
                            <input type="number" step="0.01" name="purchase_price" id="edit_purchase_price" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Selling Price (Rs.) *</label>
                            <input type="number" step="0.01" name="selling_price" id="edit_selling_price" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Stock Qty *</label>
                            <input type="number" name="stock_qty" id="edit_stock_qty" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Reorder Lvl</label>
                            <input type="number" name="reorder_level" id="edit_reorder_level" class="form-control" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Category *</label>
                            <select name="category_id" id="edit_category_id" class="form-select" required>
                                <?php
                                $cat_query = mysqli_query($conn, "SELECT * FROM categories ORDER BY category_name ASC");
                                while ($c = mysqli_fetch_assoc($cat_query)) {
                                    echo "<option value='{$c['category_id']}'>{$c['category_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Supplier *</label>
                            <select name="supplier_id" id="edit_supplier_id" class="form-select" required>
                                <?php
                                $sup_query = mysqli_query($conn, "SELECT * FROM suppliers ORDER BY supplier_name ASC");
                                while ($s = mysqli_fetch_assoc($sup_query)) {
                                    echo "<option value='{$s['supplier_id']}'>{$s['supplier_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Update Product Image (Optional)</label>
                            <input type="file" name="product_image" class="form-control" accept="image/*">
                            <div class="form-text">Leave blank to retain current image.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light" style="border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_product" class="btn btn-primary">Update Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Populating the Edit Product Modal dynamically
        const editButtons = document.querySelectorAll(".edit-product-btn");
        
        editButtons.forEach(button => {
            button.addEventListener("click", function() {
                const product = JSON.parse(this.getAttribute("data-product"));
                
                document.getElementById("edit_product_id").value = product.product_id;
                document.getElementById("edit_product_name").value = product.product_name;
                document.getElementById("edit_brand").value = product.brand || "";
                document.getElementById("edit_sku").value = product.sku || "";
                document.getElementById("edit_barcode").value = product.barcode || "";
                document.getElementById("edit_purchase_price").value = product.purchase_price;
                document.getElementById("edit_selling_price").value = product.selling_price;
                document.getElementById("edit_stock_qty").value = product.stock_qty;
                document.getElementById("edit_reorder_level").value = product.reorder_level;
                document.getElementById("edit_category_id").value = product.category_id;
                document.getElementById("edit_supplier_id").value = product.supplier_id;
            });
        });
        
        // Search filter matching
        const searchInput = document.getElementById("productSearch");
        if (searchInput) {
            searchInput.addEventListener("keyup", function() {
                const query = this.value.toLowerCase();
                const rows = document.querySelectorAll("#productsTable tbody tr");
                
                rows.forEach(row => {
                    const name = row.children[1].innerText.toLowerCase();
                    const sku = row.children[2].innerText.toLowerCase();
                    const category = row.children[3].innerText.toLowerCase();
                    const supplier = row.children[6].innerText.toLowerCase();
                    
                    if (name.includes(query) || sku.includes(query) || category.includes(query) || supplier.includes(query)) {
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