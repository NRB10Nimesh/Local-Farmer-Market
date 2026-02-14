<?php
// Updated farmer/dashboard.php - Farmers see only their prices
session_start();

if (!isset($_SESSION['farmer_id'])) {
    header("Location: ../login_farmer.php");
    exit();
}

require_once '../db.php';

$farmer_id = intval($_SESSION['farmer_id']);
$page_title = 'Products - Farmer Dashboard';
$page_css = ['../assets/css/farmer.css'];
$message = '';
$errors = [];

// Fetch farmer profile
$stmt = $conn->prepare("SELECT * FROM Farmer WHERE farmer_id = ?");
$stmt->bind_param("i", $farmer_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Add product - with total_stock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_product') {
    $product_name = trim($_POST['product_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 0);
    $unit = trim($_POST['unit'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    // for inline errors and preserving values
    $add_form_values = ['product_name'=>$product_name, 'category'=>$category, 'price'=>$price, 'quantity'=>$quantity, 'unit'=>$unit, 'description'=>$description];
    $add_errors = [];

    $image_name = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed) && $_FILES['image']['size'] <= 2 * 1024 * 1024) {
            $image_name = uniqid() . '.' . $ext;
            if (!move_uploaded_file($_FILES['image']['tmp_name'], '../uploads/' . $image_name)) {
                $add_errors['image'] = 'Failed to save uploaded image.';
            }
        } else {
            $add_errors['image'] = 'Invalid image file or file too large (max 2MB).';
        }
    }

    // if an image was uploaded, keep it in form values so we can preview it if validation fails
    if (!empty($image_name)) {
        $add_form_values['image'] = $image_name;
    }
    
    // server-side validation with inline errors
    if (!$product_name) $add_errors['product_name'] = 'Product name is required.';
    if (!$category) $add_errors['category'] = 'Category is required.';
    if ($price <= 0) $add_errors['price'] = 'Price must be greater than 0.';
    if ($quantity <= 0) $add_errors['quantity'] = 'Quantity must be at least 1.';
    if (!$unit) $add_errors['unit'] = 'Unit is required.';

    if (empty($add_errors)) {
        // Insert with pending status and set total_stock = quantity initially
        $stmt = $conn->prepare("INSERT INTO products (farmer_id, product_name, category, price, quantity, total_stock, unit, description, image, approval_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        // correct types: i (farmer_id), s (name), s (category), d (price), i (quantity), i (total_stock), s (unit), s (description), s (image)
        $stmt->bind_param("issdiisss", $farmer_id, $product_name, $category, $price, $quantity, $quantity, $unit, $description, $image_name);
        
        if ($stmt->execute()) {
            $message = "Product submitted for admin approval!";
            // clear previous add form values on success
            $add_form_values = [];
            // optionally redirect to avoid resubmission on refresh
            header('Location: dashboard.php?msg=product_submitted');
            exit();
        } else {
            // include DB error for debugging and show modal with previous values
            $add_errors['form'] = "Failed to add product: " . htmlspecialchars($stmt->error);
            $open_add_modal = true;
            // cleanup uploaded image to avoid orphaned file on DB failure
            if (!empty($image_name)) {
                $path = __DIR__ . '/../uploads/' . $image_name;
                if (file_exists($path)) @unlink($path);
            }
        }
        $stmt->close();
    } else {
        // if there are errors, tell frontend to re-open add modal and show inline errors
        $open_add_modal = true;
    }
}

// Edit product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_product') {
    $product_id = intval($_POST['product_id']);
    $product_name = trim($_POST['product_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 0);
    $unit = trim($_POST['unit'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // Start with existing image (may be null/empty)
    $image_name = $_POST['current_image'] ?? null;

    // Handle file upload (user may or may not upload a new file)
    $new_uploaded_image = null; // track new uploaded filename
    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (in_array($ext, $allowed) && $_FILES['image']['size'] <= 2 * 1024 * 1024) {
                $newName = uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], '../uploads/' . $newName)) {
                    $image_name = $newName; // only overwrite when move succeeds
                    $new_uploaded_image = $newName;
                } else {
                    $errors[] = "Failed to save uploaded image. Please check server permissions.";
                }
            } else {
                $errors[] = "Invalid image file or file too large (max 2MB).";
            }
        } else {
            $errors[] = "Image upload error (code: " . intval($_FILES['image']['error']) . ")";
        }
    }

    if (empty($errors)) {
        if ($product_name && $category && $price > 0 && $quantity >= 0 && $unit) {
            // Update total_stock when quantity changes; only change image when $image_name is not null
            $stmt = $conn->prepare("UPDATE products SET product_name=?, category=?, price=?, quantity=?, total_stock=?, unit=?, description=?, image=COALESCE(?, image), approval_status='pending' WHERE product_id=? AND farmer_id=?");
            $stmt->bind_param("ssdiisssii", $product_name, $category, $price, $quantity, $quantity, $unit, $description, $image_name, $product_id, $farmer_id);

            if ($stmt->execute()) {
                $message = "Product updated and resubmitted for approval!";

                // If a new image was uploaded and there was an old image, remove the old file
                $old_image = $_POST['current_image'] ?? '';
                if (!empty($new_uploaded_image) && $old_image && $old_image !== $new_uploaded_image) {
                    $old_path = __DIR__ . '/../uploads/' . $old_image;
                    if (file_exists($old_path)) {
                        @unlink($old_path);
                    }
                }
            } else {
                $errors[] = "Failed to update product.";
            }
            $stmt->close();
        } else {
            $errors[] = "Invalid input.";
        }
    }
}

// Delete product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_product') {
    $product_id = intval($_POST['product_id']);
    
    $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ? AND farmer_id = ?");
    $stmt->bind_param("ii", $product_id, $farmer_id);
    
    if ($stmt->execute()) {
        $message = "Product deleted!";
    } else {
        $errors[] = "Failed to delete product.";
    }
    $stmt->close();
}

// Fetch products - ONLY show farmer's price, hide admin price
$search = trim($_GET['search'] ?? '');
$category_filter = trim($_GET['category'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

// Modified query to only show farmer's data
$sql = "SELECT p.product_id, p.product_name, p.description, p.price, p.quantity, p.total_stock, 
               p.sold_quantity, p.category, p.image, p.unit, p.approval_status, p.rejection_reason, p.created_at
        FROM products p 
        WHERE p.farmer_id = ?";

$params = [$farmer_id];
$types = "i";

if ($search !== '') {
    $sql .= " AND (p.product_name LIKE ? OR p.description LIKE ?)";
    $pattern = "%{$search}%";
    $params[] = $pattern;
    $params[] = $pattern;
    $types .= "ss";
}

if ($category_filter !== '') {
    $sql .= " AND p.category = ?";
    $params[] = $category_filter;
    $types .= "s";
}

if ($status_filter !== '') {
    $sql .= " AND p.approval_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$sql .= " ORDER BY FIELD(p.approval_status, 'pending', 'approved', 'rejected'), p.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$products = $stmt->get_result();
$stmt->close();

// Get categories
$cats_stmt = $conn->prepare("SELECT DISTINCT category FROM products WHERE farmer_id = ?");
$cats_stmt->bind_param("i", $farmer_id);
$cats_stmt->execute();
$cats = $cats_stmt->get_result();
$cats_stmt->close();

// Get dashboard statistics - only farmer's revenue
$stats_query = "SELECT 
    COUNT(*) as total_products,
    COUNT(CASE WHEN approval_status = 'pending' THEN 1 END) as pending,
    COUNT(CASE WHEN approval_status = 'approved' THEN 1 END) as approved,
    COUNT(CASE WHEN approval_status = 'rejected' THEN 1 END) as rejected,
    SUM(CASE WHEN approval_status = 'approved' THEN quantity ELSE 0 END) as approved_stock,
    SUM(CASE WHEN approval_status = 'approved' THEN sold_quantity ELSE 0 END) as total_sold
    FROM products 
    WHERE farmer_id = ?";

$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $farmer_id);
$stmt->execute();
$product_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Calculate farmer's actual revenue (based on farmer's price only)
$revenue_query = "SELECT SUM(od.quantity * od.farmer_price) as farmer_revenue
                  FROM order_details od
                  INNER JOIN products p ON od.product_id = p.product_id
                  INNER JOIN orders o ON od.order_id = o.order_id
                  WHERE p.farmer_id = ? AND o.status = 'Completed'";
$stmt = $conn->prepare($revenue_query);
$stmt->bind_param("i", $farmer_id);
$stmt->execute();
$revenue_result = $stmt->get_result()->fetch_assoc();
$farmer_revenue = $revenue_result['farmer_revenue'] ?? 0;
$stmt->close();

include 'includes/header.php';
?>

<div class="main-wrapper">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main-content">
    <div class="wrap">
        <div class="container">
    <!-- Alert for pending products -->
    <?php if ($product_stats['pending'] > 0): ?>
      <div class="card alert-card alert-warning">
        <div class="alert-card-inner">
          <div class="alert-card-title">⏳ <?php echo $product_stats['pending']; ?> product(s) awaiting admin approval</div>
          <div class="alert-card-desc">Once approved by admin, your products will be visible to buyers</div>
        </div>
      </div>
    <?php endif; ?>


    <!-- Statistics Cards -->
    <div class="stats-grid">
      <div class="card stat-card stat-revenue">
        <div class="small"><span class="material-icons" aria-hidden="true">attach_money</span> Your Revenue</div>
        <div class="stat-value">Rs<?php echo number_format($farmer_revenue, 0); ?></div>
        <div class="small">From completed orders</div>
      </div>
      <div class="card stat-card stat-total">
        <div class="small-muted">Total Products</div>
        <div class="stat-value"><?php echo $product_stats['total_products'] ?? 0; ?></div>
      </div>
      <div class="card stat-card stat-pending">
        <div class="small-muted"><span class="material-icons" aria-hidden="true" style="font-size:16px;vertical-align:middle">hourglass_top</span> Pending</div>
        <div class="stat-value"><?php echo $product_stats['pending'] ?? 0; ?></div>
      </div>
      <div class="card stat-card stat-approved">
        <div class="small-muted"><span class="material-icons" aria-hidden="true">check_circle</span> Approved</div>
        <div class="stat-value"><?php echo $product_stats['approved'] ?? 0; ?></div>
      </div>
      <div class="card stat-card stat-sold">
        <div class="small-muted"><span class="material-icons" aria-hidden="true">inventory_2</span> Total Sold</div>
        <div class="stat-value"><?php echo $product_stats['total_sold'] ?? 0; ?></div>
      </div>
    </div> 

    <!-- Main Content -->
    <div class="card">
      <div class="card-header">
        <h2 class="card-title"><span class="material-icons" aria-hidden="true">local_farm</span> My Products</h2>
        <div class="card-actions">
          <button class="btn btn-primary" onclick="openModal('addModal')">Add Product</button>
        </div>
      </div>

      <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
      <?php endif; ?>
      <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div>
      <?php endforeach; ?>

      <!-- Filters -->
      <div class="filters">
        <input type="text" id="searchInput" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>" class="filters-input">
        <select id="categoryFilter" class="select filters-select">
          <option value="">All Categories</option>
          <?php while ($c = $cats->fetch_assoc()): ?>
            <option value="<?php echo htmlspecialchars($c['category']); ?>" <?php echo ($category_filter === $c['category']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['category']); ?></option>
          <?php endwhile; ?>
        </select>
        <select id="statusFilter" class="select filters-select">
          <option value="">All Status</option>
          <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
          <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
          <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
        </select>
        <button class="btn btn-primary" onclick="applyFilters()">Apply</button>
        <?php if ($search || $category_filter || $status_filter): ?>
          <a href="dashboard.php" class="btn btn-ghost">Clear</a>
        <?php endif; ?>
      </div> 

      <!-- Products Grid -->
      <div class="products-grid">
        <?php if ($products->num_rows > 0): ?>
          <?php while ($p = $products->fetch_assoc()): ?>
            <div class="product">
              <!-- Approval Status Badge -->
              <div class="product-badge">
                <?php if ($p['approval_status'] === 'pending'): ?>
                  <span class="badge badge-warning">⏳ Pending</span>
                <?php elseif ($p['approval_status'] === 'approved'): ?>
                  <span class="badge badge-success"><span class="material-icons">check_circle</span> Approved</span>
                <?php elseif ($p['approval_status'] === 'rejected'): ?>
                  <span class="badge badge-danger"><span class="material-icons">cancel</span> Rejected</span>
                <?php endif; ?>
              </div>
              
              <?php if ($p['image'] && file_exists(__DIR__ . '/../uploads/' . $p['image'])): ?>
                <img src="../uploads/<?php echo htmlspecialchars($p['image']); ?>" alt="" class="product-img">
              <?php else: ?>
                <div class="product-placeholder">No image</div>
              <?php endif; ?>
              
              <h4 class="product-name"><?php echo htmlspecialchars($p['product_name']); ?></h4>
              <div class="meta">Category: <?php echo htmlspecialchars($p['category']); ?></div>
              
              <!-- Show only farmer's price -->
              <div class="meta">Your Price: Rs<?php echo number_format($p['price'], 2); ?> / <?php echo htmlspecialchars($p['unit']); ?></div>
              
              <!-- Show stock information -->
              <div class="stock-box">
                <div class="stock-title">
                  <span class="material-icons" aria-hidden="true">inventory_2</span> Stock Status:
                </div>
                <div class="stock-current <?php echo $p['quantity'] == 0 ? 'out' : 'in'; ?>">
                  Current: <?php echo $p['quantity']; ?> <?php echo htmlspecialchars($p['unit']); ?>
                  <?php echo $p['quantity'] == 0 ? '(Out of Stock)' : ''; ?>
                </div>
                <div class="meta">
                  Total Added: <?php echo $p['total_stock']; ?> <?php echo htmlspecialchars($p['unit']); ?>
                </div>
                <?php if ($p['sold_quantity'] > 0): ?>
                  <div class="meta sold">
                    Sold: <?php echo $p['sold_quantity']; ?> <?php echo htmlspecialchars($p['unit']); ?>
                  </div>
                <?php endif; ?>
              </div>
              
              <?php if ($p['approval_status'] === 'rejected' && $p['rejection_reason']): ?>
                <div class="rejection-box">
                  <strong>Reason:</strong> <?php echo htmlspecialchars($p['rejection_reason']); ?>
                </div>
              <?php endif; ?>
              
              <div class="product-actions">
                <button class="btn btn-ghost small-btn" onclick='openEdit(<?php echo json_encode($p); ?>)'>Edit</button>
                <form method="POST" class="delete-form" onsubmit="return confirm('Delete this product?')">
                  <input type="hidden" name="action" value="delete_product">
                  <input type="hidden" name="product_id" value="<?php echo $p['product_id']; ?>">
                  <button class="btn btn-danger small-btn btn-block">Delete</button>
                </form>
              </div>
            </div>
          <?php endwhile; ?>
        <?php else: ?>
          <div class="no-products">
            <p>No products found. Add your first product!</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
      </div>
    </div>
  </div>
</div>

<!-- Add Product Modal -->
<div id="addModal" class="modal">
  <div class="modal-body">
    <div class="modal-header">
      <button onclick="closeModal('addModal')" class="modal-close modal-close-left">&times;</button>
      <h3 class="modal-title">Add New Product</h3>
    </div>
    
    <div class="alert alert-info modal-alert">
      <!-- icon is added via CSS ::before -->
      Your product will be submitted for admin approval. It will be visible to buyers once approved.
    </div>
    
    <form id="addForm" method="POST" enctype="multipart/form-data" onsubmit="return handleAddSubmit(event)">
      <input type="hidden" name="action" value="add_product">
      
      <label>Product Name *</label>
      <input id="add_product_name" type="text" name="product_name" value="<?php echo htmlspecialchars($add_form_values['product_name'] ?? ''); ?>">
      <div class="field-error" id="err_add_product_name"><?php echo htmlspecialchars($add_errors['product_name'] ?? ''); ?></div>

      <div class="form-grid">
        <div>
          <label>Category *</label>
          <select id="add_category" name="category" class="select">
            <option value="">Select...</option>
            <option value="Vegetables" <?php echo (isset($add_form_values['category']) && $add_form_values['category'] === 'Vegetables') ? 'selected' : ''; ?>>Vegetables</option>
            <option value="Fruits" <?php echo (isset($add_form_values['category']) && $add_form_values['category'] === 'Fruits') ? 'selected' : ''; ?>>Fruits</option>
            <option value="Grains" <?php echo (isset($add_form_values['category']) && $add_form_values['category'] === 'Grains') ? 'selected' : ''; ?>>Grains</option>
            <option value="Dairy" <?php echo (isset($add_form_values['category']) && $add_form_values['category'] === 'Dairy') ? 'selected' : ''; ?>>Dairy</option>
            <option value="Meat" <?php echo (isset($add_form_values['category']) && $add_form_values['category'] === 'Meat') ? 'selected' : ''; ?>>Meat</option>
            <option value="Other" <?php echo (isset($add_form_values['category']) && $add_form_values['category'] === 'Other') ? 'selected' : ''; ?>>Other</option>
          </select>
          <div class="field-error" id="err_add_category"><?php echo htmlspecialchars($add_errors['category'] ?? ''); ?></div>
        </div>
        
        <div>
          <label>Unit *</label>
          <select id="add_unit" name="unit" class="select">
            <option value="">Select...</option>
            <option value="kg" <?php echo (isset($add_form_values['unit']) && $add_form_values['unit'] === 'kg') ? 'selected' : ''; ?>>kg</option>
            <option value="liter" <?php echo (isset($add_form_values['unit']) && $add_form_values['unit'] === 'liter') ? 'selected' : ''; ?>>liter</option>
            <option value="piece" <?php echo (isset($add_form_values['unit']) && $add_form_values['unit'] === 'piece') ? 'selected' : ''; ?>>piece</option>
            <option value="dozen" <?php echo (isset($add_form_values['unit']) && $add_form_values['unit'] === 'dozen') ? 'selected' : ''; ?>>dozen</option>
          </select>
          <div class="field-error" id="err_add_unit"><?php echo htmlspecialchars($add_errors['unit'] ?? ''); ?></div>
        </div>
      </div>
      
      <div class="form-grid">
        <div>
          <label>Your Price (Rs) *</label>
          <input id="add_price" type="number" name="price" step="0.01" min="0" value="<?php echo htmlspecialchars($add_form_values['price'] ?? ''); ?>">
          <div class="small-muted">Admin may adjust final selling price</div>
          <div class="field-error" id="err_add_price"><?php echo htmlspecialchars($add_errors['price'] ?? ''); ?></div>
        </div>
        
        <div>
          <label>Initial Quantity *</label>
          <input id="add_quantity" type="number" name="quantity" min="1" value="<?php echo htmlspecialchars($add_form_values['quantity'] ?? ''); ?>">
          <div class="small-muted">You can update this later</div>
          <div class="field-error" id="err_add_quantity"><?php echo htmlspecialchars($add_errors['quantity'] ?? ''); ?></div>
        </div>
      </div>
      
      <label style="margin-top:12px">Description</label>
      <textarea id="add_description" name="description" rows="3"><?php echo htmlspecialchars($add_form_values['description'] ?? ''); ?></textarea>
      <div class="field-error" id="err_add_description"><?php echo htmlspecialchars($add_errors['description'] ?? ''); ?></div>
      
      <label style="margin-top:12px">Product Image (Max 2MB)</label>
      <input id="add_image" type="file" name="image" accept="image/*" onchange="previewAddImage(this)">
      <div id="addPreview" class="preview"></div>
      <div class="field-error" id="err_add_image"><?php echo htmlspecialchars($add_errors['image'] ?? ''); ?></div>
      
      <div class="modal-actions">
        <button type="button" class="btn btn-primary" onclick="handleAddSubmit(event)">Submit for Approval</button>
        <button type="button" class="btn btn-ghost" onclick="closeModal('addModal')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Product Modal -->
<div id="editModal" class="modal">
  <div class="modal-body" id="editBody"></div>
</div>

<!-- Confirm Modal -->
<div id="confirmModal" class="modal">
  <div class="modal-body">
    <div class="modal-header">
      <button onclick="closeModal('confirmModal')" class="modal-close modal-close-left">&times;</button>
      <h3 class="modal-title">Confirm Submission</h3>
    </div>
    <div id="confirmBody">Are you sure you want to submit?</div>
    <div style="margin-top:18px;display:flex;gap:12px">
      <button id="confirmYes" class="btn btn-primary">Yes, submit</button>
      <button class="btn btn-ghost" onclick="closeModal('confirmModal')">Cancel</button>
    </div>
  </div>
</div>

<script src="../assets/js/script.js"></script>
<script>
function applyFilters() {
  const search = document.getElementById('searchInput').value;
  const category = document.getElementById('categoryFilter').value;
  const status = document.getElementById('statusFilter').value;
  window.location.href = 'dashboard.php?search=' + encodeURIComponent(search) + 
                         '&category=' + encodeURIComponent(category) + 
                         '&status=' + encodeURIComponent(status);
}


</script>

<?php if (!empty($open_add_modal)): ?>
<script>
  document.addEventListener('DOMContentLoaded', function(){
    openModal('addModal');
    <?php if (!empty($add_form_values['image'])): ?>
      // show preview of the previously uploaded image
      createPreviewWithRemove(document.getElementById('addPreview'), '<?php echo addslashes("../uploads/" . $add_form_values['image']); ?>');
    <?php endif; ?>
  });
</script>
<?php endif; ?>

</body>
</html>
