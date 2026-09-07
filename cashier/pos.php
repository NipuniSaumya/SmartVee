<?php
include('../includes/auth.php');
include('../includes/db.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Cashier') {
    header("Location: ../auth/login.php");
    exit();
}

// Handle AJAX Customer Registration inline
if (isset($_POST['ajax_add_customer'])) {
    header('Content-Type: application/json');
    $customer_name = mysqli_real_escape_string($conn, $_POST['customer_name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $vehicle_no = mysqli_real_escape_string($conn, $_POST['vehicle_no']);
    
    $stmt = $conn->prepare("INSERT INTO customers (customer_name, phone, email, vehicle_no) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $customer_name, $phone, $email, $vehicle_no);
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'customer_id' => $stmt->insert_id,
            'customer_name' => $customer_name,
            'vehicle_no' => $vehicle_no
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $stmt->error
        ]);
    }
    $stmt->close();
    exit();
}

$page_title = "Point Of Sale (POS)";
$error = "";

// Handle POS Checkout submission
if (isset($_POST['checkout'])) {
    $customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
    $cashier_id = (int)$_SESSION['user_id'];
    $subtotal = (float)$_POST['subtotal'];
    $discount = (float)$_POST['discount'];
    $grand_total = (float)$_POST['grand_total'];
    $payment_method = $_POST['payment_method'];
    $amount_paid = (float)$_POST['amount_paid'];
    $balance = (float)$_POST['balance'];
    
    // Generate Invoice ID (SV-20260613-0001)
    $today = date('Ymd');
    $invoice_prefix = "SV-" . $today . "-";
    
    $last_inv_q = mysqli_query($conn, "SELECT invoice_no FROM sales WHERE invoice_no LIKE '$invoice_prefix%' ORDER BY sale_id DESC LIMIT 1");
    if (mysqli_num_rows($last_inv_q) > 0) {
        $last_inv_row = mysqli_fetch_assoc($last_inv_q);
        $last_inv = $last_inv_row['invoice_no'];
        $last_num = (int)substr($last_inv, -4);
        $next_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $next_num = "0001";
    }
    $invoice_no = $invoice_prefix . $next_num;
    
    $cart_data = json_decode($_POST['cart_data'], true);
    
    if (empty($cart_data)) {
        $error = "Billing failed: Shopping cart is empty.";
    } else {
        // Start SQL transaction
        mysqli_begin_transaction($conn);
        try {
            // Save Sale header
            $stmt = $conn->prepare("INSERT INTO sales (invoice_no, customer_id, cashier_id, subtotal, discount, grand_total, payment_method, amount_paid, balance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("siidddsdd", $invoice_no, $customer_id, $cashier_id, $subtotal, $discount, $grand_total, $payment_method, $amount_paid, $balance);
            $stmt->execute();
            $sale_id = $stmt->insert_id;
            $stmt->close();
            
            // Loop items and log entries
            foreach ($cart_data as $item) {
                $pid = (int)$item['id'];
                $qty = (int)$item['qty'];
                $price = (float)$item['price'];
                $item_subtotal = $qty * $price;
                
                // Verify current stock
                $stock_check = mysqli_query($conn, "SELECT stock_qty, product_name FROM products WHERE product_id = $pid");
                $stock_row = mysqli_fetch_assoc($stock_check);
                if ($stock_row['stock_qty'] < $qty) {
                    throw new Exception("Insufficient stock for product: " . $stock_row['product_name']);
                }
                
                // Add sale item
                $stmt_item = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)");
                $stmt_item->bind_param("iiidd", $sale_id, $pid, $qty, $price, $item_subtotal);
                $stmt_item->execute();
                $stmt_item->close();
                
                // Decrement product inventory
                $stmt_stock = $conn->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE product_id = ?");
                $stmt_stock->bind_param("ii", $qty, $pid);
                $stmt_stock->execute();
                $stmt_stock->close();
                
                // Log inventory out-transaction
                $stmt_tx = $conn->prepare("INSERT INTO stock_transactions (product_id, transaction_type, quantity, reference_no) VALUES (?, 'OUT', ?, ?)");
                $stmt_tx->bind_param("iis", $pid, $qty, $invoice_no);
                $stmt_tx->execute();
                $stmt_tx->close();
            }
            
            mysqli_commit($conn);
            header("Location: invoice.php?id=" . $sale_id);
            exit();
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = $e->getMessage();
        }
    }
}

include('../includes/header.php');
?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-3" role="alert">
        <i class="bi bi-exclamation-octagon-fill me-2"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="pos-wrapper">
    <!-- Catalog Section -->
    <div class="pos-catalog">
        <!-- Search bar and category chips -->
        <div class="row g-2">
            <div class="col-12">
                <div class="input-group shadow-sm">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" id="catalogSearch" class="form-control border-start-0" placeholder="Search vehicle parts by name, SKU, brand...">
                </div>
            </div>
            <div class="col-12">
                <div class="category-chips py-1">
                    <div class="category-chip active" data-cat-id="all">
                        <i class="bi bi-grid-fill me-1"></i> All Categories
                    </div>
                    <?php
                    $cats = mysqli_query($conn, "SELECT * FROM categories ORDER BY category_name ASC");
                    while($c = mysqli_fetch_assoc($cats)) {
                        echo "<div class='category-chip' data-cat-id='{$c['category_id']}'><i class='bi bi-tag-fill me-1'></i>" . htmlspecialchars($c['category_name']) . "</div>";
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <!-- Products Scroll Grid -->
        <div class="products-scroll-grid mt-2">
            <div class="row row-cols-2 row-cols-md-3 g-3" id="productsCatalogContainer">
                <?php
                $prods = mysqli_query($conn, "
                    SELECT p.*, c.category_name 
                    FROM products p 
                    LEFT JOIN categories c ON p.category_id = c.category_id 
                    ORDER BY p.product_name ASC
                ");
                while ($p = mysqli_fetch_assoc($prods)) {
                    $qty = (int)$p['stock_qty'];
                    $price = (float)$p['selling_price'];
                    $name_js = addslashes($p['product_name']);
                    $brand_js = addslashes($p['brand']);
                    
                    // Stock badge logic
                    if ($qty <= 0) {
                        $stock_badge = '<span class="badge bg-danger position-absolute top-0 end-0 m-2">Out of Stock</span>';
                        $disabled = 'disabled';
                    } elseif ($qty <= $p['reorder_level']) {
                        $stock_badge = '<span class="badge bg-warning text-dark position-absolute top-0 end-0 m-2">Low Stock (' . $qty . ')</span>';
                        $disabled = '';
                    } else {
                        $stock_badge = '<span class="badge bg-success position-absolute top-0 end-0 m-2">Stock: ' . $qty . '</span>';
                        $disabled = '';
                    }
                    
                    // Image thumbnail
                    $img = htmlspecialchars($p['product_image']);
                    $img_src = !empty($img) && file_exists("../assets/images/" . $img) 
                        ? "../assets/images/" . $img 
                        : "../assets/images/default-parts.png";
                ?>
                    <div class="col product-card-col" data-category="<?php echo $p['category_id']; ?>" data-search-keys="<?php echo htmlspecialchars(strtolower($p['product_name'] . ' ' . $p['brand'] . ' ' . $p['sku'] . ' ' . $p['barcode'])); ?>">
                        <div class="product-item-card h-100 <?php echo ($qty <= 0) ? 'opacity-75' : ''; ?>">
                            <?php echo $stock_badge; ?>
                            <img src="<?php echo $img_src; ?>" class="product-item-img" onerror="this.src='../assets/images/default-parts.png';">
                            <div class="product-item-details">
                                <div>
                                    <div class="product-item-name text-truncate" title="<?php echo htmlspecialchars($p['product_name']); ?>">
                                        <?php echo htmlspecialchars($p['product_name']); ?>
                                    </div>
                                    <div class="text-muted small mb-2"><?php echo htmlspecialchars($p['brand'] ?? 'No Brand'); ?></div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="product-item-price">Rs. <?php echo number_format($price, 2); ?></div>
                                    <button 
                                        type="button" 
                                        class="btn btn-sm btn-premium py-1 px-2.5 add-to-cart-btn" 
                                        data-id="<?php echo $p['product_id']; ?>" 
                                        data-name="<?php echo $name_js; ?>" 
                                        data-price="<?php echo $price; ?>" 
                                        data-max-stock="<?php echo $qty; ?>"
                                        <?php echo $disabled; ?>>
                                        <i class="bi bi-cart-plus"></i> Add
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
    
    <!-- Billing Checkout Sidebar -->
    <div class="pos-cart-panel">
        <div class="cart-header-pos d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <i class="pos-cart-close-btn bi bi-chevron-down me-2" id="closeCartDrawerBtn"></i>
                <span><i class="bi bi-cart3 me-2 text-primary d-none d-lg-inline"></i>Checkout Cart</span>
            </div>
            <span class="badge bg-primary rounded-pill" id="cartBadgeCount">0 items</span>
        </div>
        
        <!-- Cart Items Scroll Section -->
        <div class="cart-items-list" id="cartContainer">
            <!-- Populated via Javascript -->
            <div class="text-center py-5 text-muted" id="cartEmptyPlaceholder">
                <i class="bi bi-cart3 fs-1 d-block mb-2 text-black-50"></i>
                Cart is empty. Click "Add" on products to fill.
            </div>
        </div>
        
        <!-- Cart Operations and Pay Forms -->
        <form method="POST" id="checkoutForm">
            <!-- Hidden inputs to submit cart json -->
            <input type="hidden" name="cart_data" id="cartDataInput">
            <input type="hidden" name="subtotal" id="subtotalInput">
            <input type="hidden" name="discount" id="discountInput">
            <input type="hidden" name="grand_total" id="grandTotalInput">
            <input type="hidden" name="balance" id="balanceInput">
            
            <div class="cart-checkout-summary">
                <!-- Customer Selection -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label mb-0">Select Customer (Vehicle No)</label>
                        <button type="button" class="btn btn-sm btn-link py-0 text-decoration-none" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                            <i class="bi bi-person-plus-fill me-1"></i>New Client
                        </button>
                    </div>
                    <select name="customer_id" id="customerSelect" class="form-select">
                        <option value="">Walk-in Customer</option>
                        <?php
                        $cust = mysqli_query($conn, "SELECT * FROM customers ORDER BY customer_name ASC");
                        while ($c = mysqli_fetch_assoc($cust)) {
                            echo "<option value='{$c['customer_id']}'>" . htmlspecialchars($c['customer_name']) . " (" . htmlspecialchars($c['vehicle_no']) . ")</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <!-- Financial details -->
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span id="subtotalVal">Rs. 0.00</span>
                </div>
                <div class="summary-row align-items-center">
                    <span>Discount (Rs.)</span>
                    <input type="number" id="discountField" class="form-control text-end py-1 px-2" style="width: 100px; height: 30px;" value="0" min="0">
                </div>
                
                <div class="summary-row total-row">
                    <span>Grand Total</span>
                    <span id="grandTotalVal">Rs. 0.00</span>
                </div>
                
                <!-- Payment methods -->
                <label class="form-label mb-2">Payment Method</label>
                <div class="payment-selector">
                    <div class="payment-method-btn active" data-value="Cash"><i class="bi bi-cash me-1"></i>Cash</div>
                    <div class="payment-method-btn" data-value="Card"><i class="bi bi-credit-card me-1"></i>Card</div>
                    <div class="payment-method-btn" data-value="QR"><i class="bi bi-qr-code-scan me-1"></i>QR</div>
                </div>
                <input type="hidden" name="payment_method" id="paymentMethodInput" value="Cash">
                
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small mb-1">Amount Paid *</label>
                        <input type="number" step="0.01" name="amount_paid" id="amountPaidField" class="form-control" value="0.00" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small mb-1">Balance Due</label>
                        <div class="form-control bg-light text-end fw-bold" id="balanceVal">Rs. 0.00</div>
                    </div>
                </div>
                
                <button type="submit" name="checkout" class="btn btn-premium w-100 py-2.5 fw-bold" id="checkoutSubmitBtn" disabled>
                    <i class="bi bi-check2-circle me-1"></i> Complete Checkout
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Mobile Sticky Bottom Floating Cart Bar -->
<div class="floating-cart-bar" id="floatingCartBarTrigger">
    <div class="floating-cart-info">
        <span class="small text-muted fw-bold"><i class="bi bi-cart-fill me-1"></i> Cart Items</span>
        <span class="floating-cart-total" id="mobileCartTotalVal">Rs. 0.00</span>
    </div>
    <button class="btn btn-premium px-4 fw-bold shadow-sm d-flex align-items-center gap-2">
        Checkout <i class="bi bi-chevron-up"></i>
    </button>
</div>

<!-- ==========================================
     INLINE CLIENT REGISTRATION MODAL (AJAX)
     ========================================== -->
<div class="modal fade" id="addCustomerModal" tabindex="-1" aria-labelledby="addCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header bg-dark text-white" style="border-top-left-radius: 16px; border-top-right-radius: 16px;">
                <h5 class="modal-title fw-bold" id="addCustomerModalLabel"><i class="bi bi-person-plus-fill me-2"></i>Quick Register Customer</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="ajaxCustomerForm">
                <input type="hidden" name="ajax_add_customer" value="1">
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
                        <input type="text" name="vehicle_no" class="form-control text-uppercase" placeholder="e.g. WP-1234 or CAA-5678" required>
                    </div>
                </div>
                <div class="modal-footer bg-light" style="border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-premium">Register & Select</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- POS Client Controller Script -->
<script>
let cart = [];

document.addEventListener("DOMContentLoaded", function() {
    // 1. Catalog filtering (Category Chips)
    const categoryChips = document.querySelectorAll(".category-chip");
    categoryChips.forEach(chip => {
        chip.addEventListener("click", function() {
            // Toggle active state styling
            categoryChips.forEach(c => c.classList.remove("active"));
            this.classList.add("active");
            
            const catId = this.getAttribute("data-cat-id");
            const productCards = document.querySelectorAll(".product-card-col");
            
            productCards.forEach(card => {
                if (catId === "all" || card.getAttribute("data-category") === catId) {
                    card.style.display = "";
                } else {
                    card.style.display = "none";
                }
            });
        });
    });

    // 2. Catalog live search
    const catalogSearch = document.getElementById("catalogSearch");
    if (catalogSearch) {
        catalogSearch.addEventListener("keyup", function() {
            const query = this.value.toLowerCase();
            const productCards = document.querySelectorAll(".product-card-col");
            
            productCards.forEach(card => {
                const searchKeys = card.getAttribute("data-search-keys");
                if (searchKeys.includes(query)) {
                    card.style.display = "";
                } else {
                    card.style.display = "none";
                }
            });
            
            // De-select active categories on search
            if (query !== "") {
                categoryChips.forEach(c => c.classList.remove("active"));
                document.querySelector('[data-cat-id="all"]').classList.add("active");
            }
        });
    }

    // 3. Add to Cart Actions
    const addButtons = document.querySelectorAll(".add-to-cart-btn");
    addButtons.forEach(btn => {
        btn.addEventListener("click", function() {
            const id = parseInt(this.getAttribute("data-id"));
            const name = this.getAttribute("data-name");
            const price = parseFloat(this.getAttribute("data-price"));
            const maxStock = parseInt(this.getAttribute("data-max-stock"));
            
            addToCart(id, name, price, maxStock);
        });
    });

    // 4. Payment Method buttons toggler
    const payBtns = document.querySelectorAll(".payment-method-btn");
    const payMethodInput = document.getElementById("paymentMethodInput");
    payBtns.forEach(btn => {
        btn.addEventListener("click", function() {
            payBtns.forEach(b => b.classList.remove("active"));
            this.classList.add("active");
            payMethodInput.value = this.getAttribute("data-value");
        });
    });

    // 5. Discount field dynamic re-eval
    document.getElementById("discountField").addEventListener("input", function() {
        calculateCart();
    });

    // 6. Amount Paid field dynamic re-eval
    document.getElementById("amountPaidField").addEventListener("input", function() {
        calculateCart();
    });

    // 7. Complete Checkout safety checks
    document.getElementById("checkoutForm").addEventListener("submit", function(e) {
        const grandTotal = parseFloat(document.getElementById("grandTotalInput").value);
        const amountPaid = parseFloat(document.getElementById("amountPaidField").value);
        
        if (amountPaid < grandTotal) {
            e.preventDefault();
            alert("Insufficient payment! Amount paid must meet or exceed the Grand Total.");
        }
    });

    // 8. Inline AJAX customer registration
    document.getElementById("ajaxCustomerForm").addEventListener("submit", function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch('pos.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Add option to select element and set active
                const selectEl = document.getElementById("customerSelect");
                const newOption = new Option(`${data.customer_name} (${data.vehicle_no})`, data.customer_id, true, true);
                selectEl.add(newOption);
                
                // Hide modal
                const modalEl = document.getElementById("addCustomerModal");
                const bootstrapModal = bootstrap.Modal.getInstance(modalEl);
                bootstrapModal.hide();
                
                // Reset form
                document.getElementById("ajaxCustomerForm").reset();
            } else {
                alert("Error registering customer: " + data.error);
            }
        })
        .catch(err => {
            console.error("AJAX Error:", err);
            alert("Connection error during registration.");
        });
    });
});

// Add Item Core Logic
// - Different product  → new line in cart
// - Same product again → qty +1 on existing line
function addToCart(id, name, price, maxStock) {
    const productId = Number(id);
    const stockLimit = Number(maxStock) || 0;

    if (stockLimit <= 0) {
        alert("This product is out of stock.");
        return;
    }

    const existing = cart.find(item => Number(item.id) === productId);

    if (existing) {
        existing.maxStock = stockLimit;
        if (existing.qty >= stockLimit) {
            alert(`Stock limit reached. Only ${stockLimit} unit(s) available (${existing.qty} already in cart).`);
            return;
        }
        existing.qty++;
    } else {
        cart.push({
            id: productId,
            name: name,
            price: price,
            qty: 1,
            maxStock: stockLimit
        });
    }

    renderCart(productId);
}

function getCartTotalQty() {
    return cart.reduce((sum, item) => sum + item.qty, 0);
}

// Render Cart HTML
function renderCart(highlightProductId = null) {
    const cartContainer = document.getElementById("cartContainer");
    const placeholder = document.getElementById("cartEmptyPlaceholder");
    const countBadge = document.getElementById("cartBadgeCount");
    const checkoutSubmitBtn = document.getElementById("checkoutSubmitBtn");
    const totalQty = getCartTotalQty();
    
    if (cart.length === 0) {
        cartContainer.innerHTML = '';
        cartContainer.appendChild(placeholder);
        placeholder.style.display = "block";
        countBadge.innerText = "0 items";
        checkoutSubmitBtn.disabled = true;
        
        document.getElementById("subtotalVal").innerText = "Rs. 0.00";
        document.getElementById("grandTotalVal").innerText = "Rs. 0.00";
        document.getElementById("balanceVal").innerText = "Rs. 0.00";
        document.getElementById("amountPaidField").value = "0.00";
        
        const mobTotalEl = document.getElementById("mobileCartTotalVal");
        if (mobTotalEl) {
            mobTotalEl.innerText = "Rs. 0.00";
        }
        return;
    }
    
    placeholder.style.display = "none";
    checkoutSubmitBtn.disabled = false;
    const productCount = cart.length;
    countBadge.innerText = productCount === totalQty
        ? `${productCount} product${productCount === 1 ? '' : 's'}`
        : `${productCount} products · ${totalQty} items`;
    
    let cartHtml = '';
    cart.forEach(item => {
        const highlightClass = highlightProductId !== null && Number(item.id) === Number(highlightProductId)
            ? ' cart-item-highlight'
            : '';
        cartHtml += `
            <div class="cart-item${highlightClass}" data-product-id="${item.id}">
                <div class="cart-item-info">
                    <div class="fw-bold text-dark text-truncate" style="max-width: 150px;">${item.name}</div>
                    <div class="text-muted small">Rs. ${item.price.toFixed(2)} each</div>
                </div>
                <div class="cart-item-qty">
                    <button type="button" class="qty-btn" onclick="updateQty(${item.id}, -1)">-</button>
                    <span class="fw-semibold text-dark cart-qty-value">${item.qty}</span>
                    <button type="button" class="qty-btn" onclick="updateQty(${item.id}, 1)">+</button>
                </div>
                <div class="cart-item-subtotal">
                    Rs. ${(item.price * item.qty).toFixed(2)}
                </div>
                <div class="cart-item-remove" onclick="removeFromCart(${item.id})">
                    <i class="bi bi-trash-fill"></i>
                </div>
            </div>
        `;
    });
    
    cartContainer.innerHTML = cartHtml;
    calculateCart();

    if (highlightProductId !== null) {
        const highlightedRow = cartContainer.querySelector(`[data-product-id="${highlightProductId}"]`);
        if (highlightedRow) {
            highlightedRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            setTimeout(() => highlightedRow.classList.remove('cart-item-highlight'), 700);
        }
    }
}

// Update Cart Quantity
function updateQty(id, change) {
    const productId = Number(id);
    const item = cart.find(i => Number(i.id) === productId);
    if (item) {
        const newQty = item.qty + change;
        if (newQty <= 0) {
            removeFromCart(productId);
        } else if (newQty > item.maxStock) {
            alert(`Stock limit reached. Only ${item.maxStock} unit(s) available in store.`);
        } else {
            item.qty = newQty;
            renderCart(productId);
        }
    }
}

// Remove from Cart
function removeFromCart(id) {
    const productId = Number(id);
    cart = cart.filter(i => Number(i.id) !== productId);
    renderCart();
}

// Calculate totals and populate hidden forms
function calculateCart() {
    let subtotal = 0;
    cart.forEach(item => {
        subtotal += item.price * item.qty;
    });
    
    const discountInput = parseFloat(document.getElementById("discountField").value) || 0;
    const grandTotal = Math.max(0, subtotal - discountInput);
    
    // Amount paid field validation/syncing
    let amountPaidField = document.getElementById("amountPaidField");
    let amountPaid = parseFloat(amountPaidField.value) || 0;
    
    // Auto populate amount paid if it is 0 or less
    if (amountPaid <= 0 && subtotal > 0) {
        amountPaid = grandTotal;
        amountPaidField.value = amountPaid.toFixed(2);
    }
    
    const balance = Math.max(0, amountPaid - grandTotal);
    
    // Populate display
    document.getElementById("subtotalVal").innerText = `Rs. ${subtotal.toFixed(2)}`;
    document.getElementById("grandTotalVal").innerText = `Rs. ${grandTotal.toFixed(2)}`;
    document.getElementById("balanceVal").innerText = `Rs. ${balance.toFixed(2)}`;
    
    // Update mobile total
    const mobTotalEl = document.getElementById("mobileCartTotalVal");
    if (mobTotalEl) {
        mobTotalEl.innerText = `Rs. ${grandTotal.toFixed(2)}`;
    }
    
    // Populate hidden inputs
    document.getElementById("cartDataInput").value = JSON.stringify(cart);
    document.getElementById("subtotalInput").value = subtotal.toFixed(2);
    document.getElementById("discountInput").value = discountInput.toFixed(2);
    document.getElementById("grandTotalInput").value = grandTotal.toFixed(2);
    document.getElementById("balanceInput").value = balance.toFixed(2);
}
</script>

<?php include('../includes/footer.php'); ?>
