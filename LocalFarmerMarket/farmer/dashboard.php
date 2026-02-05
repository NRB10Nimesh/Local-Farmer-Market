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
    
    $image_name = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed) && $_FILES['image']['size'] <= 2 * 1024 * 1024) {
            $image_name = uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], '../uploads/' . $image_name);
        }
    }
    
    if ($product_name && $category && $price > 0 && $quantity > 0 && $unit) {
        // Insert with pending status and set total_stock = quantity initially
        $stmt = $conn->prepare("INSERT INTO products (farmer_id, product_name, category, price, quantity, total_stock, unit, description, image, approval_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("issdissss", $farmer_id, $product_name, $category, $price, $quantity, $quantity, $unit, $description, $image_name);
        
        if ($stmt->execute()) {
            $message = "Product submitted for admin approval!";
        } else {
            $errors[] = "Failed to add product.";
        }
        $stmt->close();
    } else {
        $errors[] = "Please fill all required fields correctly.";
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
    
    $image_name = $_POST['current_image'] ?? null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed) && $_FILES['image']['size'] <= 2 * 1024 * 1024) {
            $image_name = uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], '../uploads/' . $image_name);
        }
    }
    
    if ($product_name && $category && $price > 0 && $quantity >= 0 && $unit) {
        // Update total_stock when quantity changes
        $stmt = $conn->prepare("UPDATE products SET product_name=?, category=?, price=?, quantity=?, total_stock=?, unit=?, description=?, image=?, approval_status='pending' WHERE product_id=? AND farmer_id=?");
        $stmt->bind_param("ssdiisssii", $product_name, $category, $price, $quantity, $quantity, $unit, $description, $image_name, $product_id, $farmer_id);
        
        if ($stmt->execute()) {
            $message = "Product updated and resubmitted for approval!";
        } else {
            $errors[] = "Failed to update product.";
        }
        $stmt->close();
    } else {
        $errors[] = "Invalid input.";
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
$cats = $conn->query("SELECT DISTINCT category FROM products WHERE farmer_id = $farmer_id");

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

<div class="wrap">
  <div style="max-width:1400px;margin:0 auto">
    
    <!-- Alert for pending products -->
    <?php if ($product_stats['pending'] > 0): ?>
      <div class="card" style="background:linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);color:white;margin-bottom:20px">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <div>
            <div style="font-size:1.1rem;font-weight:700;margin-bottom:4px">
              ⏳ <?php echo $product_stats['pending']; ?> product(s) awaiting admin approval
            </div>
            <div style="opacity:0.9">
              Once approved by admin, your products will be visible to buyers
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
    
    <?php if ($product_stats['rejected'] > 0): ?>
      <div class="card" style="background:linear-gradient(135deg, #ef4444 0%, #dc2626 100%);color:white;margin-bottom:20px">
        <div style="font-size:1.1rem;font-weight:700;margin-bottom:4px">
          ❌ <?php echo $product_stats['rejected']; ?> product(s) were rejected
        </div>
        <div style="opacity:0.9">
          Review rejected products below and resubmit after making changes
        </div>
      </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px">
      <div class="card" style="background:#eff6ff;border-left:4px solid #3b82f6">
        <div class="small-muted">Total Products</div>
        <div style="font-size:2rem;font-weight:700;color:#3b82f6"><?php echo $product_stats['total_products'] ?? 0; ?></div>
      </div>
      
      <div class="card" style="background:#fef3c7;border-left:4px solid #f59e0b">
        <div class="small-muted">⏳ Pending</div>
        <div style="font-size:2rem;font-weight:700;color:#f59e0b"><?php echo $product_stats['pending'] ?? 0; ?></div>
      </div>
      
      <div class="card" style="background:#f0fdf4;border-left:4px solid #16a34a">
        <div class="small-muted">✅ Approved</div>
        <div style="font-size:2rem;font-weight:700;color:#16a34a"><?php echo $product_stats['approved'] ?? 0; ?></div>
      </div>
      
      <div class="card" style="background:#fee2e2;border-left:4px solid #ef4444">
        <div class="small-muted">❌ Rejected</div>
        <div style="font-size:2rem;font-weight:700;color:#ef4444"><?php echo $product_stats['rejected'] ?? 0; ?></div>
      </div>
      
      <div class="card" style="background:#dbeafe;border-left:4px solid #3b82f6">
        <div class="small-muted">📦 Total Sold</div>
        <div style="font-size:2rem;font-weight:700;color:#3b82f6"><?php echo $product_stats['total_sold'] ?? 0; ?></div>
      </div>
      
      <div class="card" style="background:linear-gradient(135deg, #10b981 0%, #059669 100%);color:white">
        <div class="small" style="opacity:0.9">💰 Your Revenue</div>
        <div style="font-size:1.5rem;font-weight:700;margin:4px 0">Rs<?php echo number_format($farmer_revenue, 0); ?></div>
        <div class="small" style="opacity:0.9">From completed orders</div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px">
        <h2 style="margin:0">🌾 My Products</h2>
        <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Product</button>
      </div>

      <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
      <?php endif; ?>
      <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div>
      <?php endforeach; ?>

      <!-- Filters -->
      <div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap">
        <input type="text" id="searchInput" placeholder="Search products..." 
               value="<?php echo htmlspecialchars($search); ?>"
               style="flex:1;min-width:200px;padding:10px;border:2px solid #e5e7eb;border-radius:8px">
        
        <select id="categoryFilter" class="select">
          <option value="">All Categories</option>
          <?php while ($c = $cats->fetch_assoc()): ?>
            <option value="<?php echo htmlspecialchars($c['category']); ?>" 
                    <?php echo ($category_filter === $c['category']) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($c['category']); ?>
            </option>
          <?php endwhile; ?>
        </select>
        
        <select id="statusFilter" class="select">
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
            <div class="product" style="position:relative">
              <!-- Approval Status Badge -->
              <div style="position:absolute;top:8px;right:8px;z-index:1">
                <?php if ($p['approval_status'] === 'pending'): ?>
                  <span class="badge badge-warning">⏳ Pending</span>
                <?php elseif ($p['approval_status'] === 'approved'): ?>
                  <span class="badge badge-success">✅ Approved</span>
                <?php elseif ($p['approval_status'] === 'rejected'): ?>
                  <span class="badge badge-danger">❌ Rejected</span>
                <?php endif; ?>
              </div>
              
              <?php if ($p['image'] && file_exists(__DIR__ . '/../uploads/' . $p['image'])): ?>
                <img src="../uploads/<?php echo htmlspecialchars($p['image']); ?>" alt="">
              <?php else: ?>
                <div class="product-placeholder">No image</div>
              <?php endif; ?>
              
              <h4><?php echo htmlspecialchars($p['product_name']); ?></h4>
              <div class="meta">Category: <?php echo htmlspecialchars($p['category']); ?></div>
              
              <!-- Show only farmer's price -->
              <div class="meta">Your Price: Rs<?php echo number_format($p['price'], 2); ?> / <?php echo htmlspecialchars($p['unit']); ?></div>
              
              <!-- Show stock information -->
              <div style="background:#f9fafb;padding:8px;border-radius:6px;margin:8px 0">
                <div class="meta" style="font-weight:600;color:#374151;margin-bottom:4px">
                  📦 Stock Status:
                </div>
                <div class="meta" style="<?php echo $p['quantity'] == 0 ? 'color:#ef4444;font-weight:700;' : 'color:#16a34a;font-weight:700;'; ?>">
                  Current: <?php echo $p['quantity']; ?> <?php echo htmlspecialchars($p['unit']); ?>
                  <?php echo $p['quantity'] == 0 ? '(Out of Stock)' : ''; ?>
                </div>
                <div class="meta" style="color:#6b7280">
                  Total Added: <?php echo $p['total_stock']; ?> <?php echo htmlspecialchars($p['unit']); ?>
                </div>
                <?php if ($p['sold_quantity'] > 0): ?>
                  <div class="meta" style="color:#3b82f6;font-weight:600">
                    Sold: <?php echo $p['sold_quantity']; ?> <?php echo htmlspecialchars($p['unit']); ?>
                  </div>
                <?php endif; ?>
              </div>
              
              <?php if ($p['approval_status'] === 'rejected' && $p['rejection_reason']): ?>
                <div style="background:#fee2e2;color:#991b1b;padding:8px;border-radius:6px;margin:8px 0;font-size:0.85rem">
                  <strong>Reason:</strong> <?php echo htmlspecialchars($p['rejection_reason']); ?>
                </div>
              <?php endif; ?>
              
              <div class="product-actions">
                <button class="btn btn-ghost small-btn" onclick='editProduct(<?php echo json_encode($p); ?>)'>Edit</button>
                <form method="POST" style="flex:1" onsubmit="return confirm('Delete this product?')">
                  <input type="hidden" name="action" value="delete_product">
                  <input type="hidden" name="product_id" value="<?php echo $p['product_id']; ?>">
                  <button class="btn btn-danger small-btn" style="width:100%">Delete</button>
                </form>
              </div>
            </div>
          <?php endwhile; ?>
        <?php else: ?>
          <div style="grid-column:1/-1;text-align:center;padding:40px">
            <p>No products found. Add your first product!</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/sidebar.php'; ?>

<!-- Add Product Modal -->
<div id="addModal" class="modal">
  <div class="modal-body">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h3 style="margin:0">Add New Product</h3>
      <button onclick="closeModal('addModal')" style="border:none;background:none;font-size:1.5rem;cursor:pointer">&times;</button>
    </div>
    
    <div class="alert alert-info" style="margin-bottom:16px">
      ℹ️ Your product will be submitted for admin approval. It will be visible to buyers once approved.
    </div>
    
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="add_product">
      
      <label>Product Name *</label>
      <input type="text" name="product_name" required>
      
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px">
        <div>
          <label>Category *</label>
          <select name="category" required class="select">
            <option value="">Select...</option>
            <option value="Vegetables">Vegetables</option>
            <option value="Fruits">Fruits</option>
            <option value="Grains">Grains</option>
            <option value="Dairy">Dairy</option>
            <option value="Meat">Meat</option>
            <option value="Other">Other</option>
          </select>
        </div>
        
        <div>
          <label>Unit *</label>
          <select name="unit" required class="select">
            <option value="">Select...</option>
            <option value="kg">kg</option>
            <option value="liter">liter</option>
            <option value="piece">piece</option>
            <option value="dozen">dozen</option>
          </select>
        </div>
      </div>
      
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px">
        <div>
          <label>Your Price (Rs) *</label>
          <input type="number" name="price" step="0.01" min="0" required>
          <div class="small-muted">Admin may adjust final selling price</div>
        </div>
        
        <div>
          <label>Initial Quantity *</label>
          <input type="number" name="quantity" min="1" required>
          <div class="small-muted">You can update this later</div>
        </div>
      </div>
      
      <label style="margin-top:12px">Description</label>
      <textarea name="description" rows="3"></textarea>
      
      <label style="margin-top:12px">Product Image (Max 2MB)</label>
      <input type="file" name="image" accept="image/*" onchange="previewAddImage(this)">
      <div id="addPreview" style="margin-top:12px"></div>
      
      <div style="display:flex;gap:8px;margin-top:20px">
        <button type="submit" class="btn btn-primary">Submit for Approval</button>
        <button type="button" class="btn btn-ghost" onclick="closeModal('addModal')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Product Modal -->
<div id="editModal" class="modal">
  <div class="modal-body" id="editBody"></div>
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

function editProduct(product) {
  const modal = document.getElementById('editModal');
  const body = document.getElementById('editBody');
  
  const html = `
    <h3>Edit Product</h3>
    ${product.approval_status === 'approved' ? 
      '<div class="alert alert-info" style="margin-bottom:16px">ℹ️ Editing will reset approval status. Product will need admin approval again.</div>' : ''}
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="edit_product">
      <input type="hidden" name="product_id" value="${product.product_id}">
      <input type="hidden" name="current_image" value="${product.image || ''}">
      
      <label>Product Name *</label>
      <input type="text" name="product_name" value="${escapeHtml(product.product_name)}" required>
      
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px">
        <div>
          <label>Category *</label>
          <select name="category" required class="select">
            <option value="Vegetables" ${product.category === 'Vegetables' ? 'selected' : ''}>Vegetables</option>
            <option value="Fruits" ${product.category === 'Fruits' ? 'selected' : ''}>Fruits</option>
            <option value="Grains" ${product.category === 'Grains' ? 'selected' : ''}>Grains</option>
            <option value="Dairy" ${product.category === 'Dairy' ? 'selected' : ''}>Dairy</option>
            <option value="Meat" ${product.category === 'Meat' ? 'selected' : ''}>Meat</option>
            <option value="Other" ${product.category === 'Other' ? 'selected' : ''}>Other</option>
          </select>
        </div>
        
        <div>
          <label>Unit *</label>
          <select name="unit" required class="select">
            <option value="kg" ${product.unit === 'kg' ? 'selected' : ''}>kg</option>
            <option value="liter" ${product.unit === 'liter' ? 'selected' : ''}>liter</option>
            <option value="piece" ${product.unit === 'piece' ? 'selected' : ''}>piece</option>
            <option value="dozen" ${product.unit === 'dozen' ? 'selected' : ''}>dozen</option>
          </select>
        </div>
      </div>
      
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px">
        <div>
          <label>Your Price (Rs) *</label>
          <input type="number" name="price" step="0.01" min="0" value="${product.price}" required>
        </div>
        
        <div>
          <label>Quantity *</label>
          <input type="number" name="quantity" min="0" value="${product.quantity}" required>
        </div>
      </div>
      
      <label style="margin-top:12px">Description</label>
      <textarea name="description" rows="3">${escapeHtml(product.description || '')}</textarea>
      
      <label style="margin-top:12px">Replace Image (optional)</label>
      <input type="file" name="image" accept="image/*" onchange="previewEditImage(this)">
      <div id="editPreview" style="margin-top:12px">
        ${product.image ? `<img src="../uploads/${escapeHtml(product.image)}" style="max-width:200px;max-height:150px;border-radius:10px;object-fit:cover">` : ''}
      </div>
      
      <div style="margin-top:20px;display:flex;gap:10px;justify-content:flex-end">
        <button type="button" class="btn btn-ghost" onclick="closeModal('editModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  `;
  
  body.innerHTML = html;
  openModal('editModal');
}
</script>
</body>
</html>
