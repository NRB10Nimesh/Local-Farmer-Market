<?php
// Buyer Dashboard - Modern UI
session_start();

if (!isset($_SESSION['buyer_id'])) {
    header("Location: ../login_buyer.php");
    exit();
}

require_once '../db.php';

$buyer_id = intval($_SESSION['buyer_id']);
$page_title = 'Shop - Buyer Dashboard';
$message = '';
$errors = [];

// Fetch buyer profile
$stmt = $conn->prepare("SELECT * FROM buyer WHERE buyer_id = ?");
$stmt->bind_param("i", $buyer_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity'] ?? 1);
    
    $stock_check = $conn->prepare("SELECT quantity FROM products WHERE product_id = ? AND approval_status = 'approved'");
    $stock_check->bind_param("i", $product_id);
    $stock_check->execute();
    $stock_result = $stock_check->get_result()->fetch_assoc();
    $stock_check->close();
    
    if (!$stock_result) {
        $errors[] = "Product not found or not available.";
    } elseif ($stock_result['quantity'] < $quantity) {
        $errors[] = "Only {$stock_result['quantity']} units available in stock.";
    } else {
        $check = $conn->prepare("SELECT cart_id, quantity FROM cart WHERE buyer_id = ? AND product_id = ?");
        $check->bind_param("ii", $buyer_id, $product_id);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();
        $check->close();
        
        if ($existing) {
            $new_quantity = $existing['quantity'] + $quantity;
            if ($new_quantity > $stock_result['quantity']) {
                $errors[] = "Cannot add more. Only {$stock_result['quantity']} units available.";
            } else {
                $update = $conn->prepare("UPDATE cart SET quantity = ? WHERE cart_id = ?");
                $update->bind_param("ii", $new_quantity, $existing['cart_id']);
                if ($update->execute()) {
                    $message = "Cart updated!";
                }
                $update->close();
            }
        } else {
            $insert = $conn->prepare("INSERT INTO cart (buyer_id, product_id, quantity) VALUES (?, ?, ?)");
            $insert->bind_param("iii", $buyer_id, $product_id, $quantity);
            if ($insert->execute()) {
                $message = "Added to cart!";
            }
            $insert->close();
        }
    }
}

// Fetch products
$search = trim($_GET['search'] ?? '');
$category_filter = trim($_GET['category'] ?? '');

$sql = "SELECT p.product_id, p.product_name, p.description, 
               COALESCE(p.admin_price, p.price) as display_price,
               p.quantity as available_stock, 
               p.total_stock,
               p.sold_quantity,
               p.category, p.image, p.unit,
               f.name as farmer_name, f.farmer_id
        FROM products p 
        JOIN farmer f ON p.farmer_id = f.farmer_id 
        WHERE p.approval_status = 'approved' 
        AND p.quantity > 0
        AND f.is_active = 1";

$params = [];
$types = "";

if ($search !== '') {
    $sql .= " AND (p.product_name LIKE ? OR p.description LIKE ? OR f.name LIKE ?)";
    $pattern = "%{$search}%";
    $params[] = $pattern;
    $params[] = $pattern;
    $params[] = $pattern;
    $types .= "sss";
}

if ($category_filter !== '') {
    $sql .= " AND p.category = ?";
    $params[] = $category_filter;
    $types .= "s";
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $conn->prepare($sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result();
$stmt->close();

// Get categories
$categories = $conn->query("SELECT DISTINCT category FROM products WHERE approval_status = 'approved' ORDER BY category");

// Get cart items for sidebar
$cart_stmt = $conn->prepare("
    SELECT c.cart_id, c.product_id, c.quantity,
           p.product_name, p.price, p.image, p.unit, p.quantity AS stock
    FROM cart c
    JOIN products p ON c.product_id = p.product_id
    WHERE c.buyer_id = ?
    ORDER BY c.cart_id DESC
");
$cart_stmt->bind_param("i", $buyer_id);
$cart_stmt->execute();
$cart_items = $cart_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$cart_stmt->close();

// Get cart count
$cart_count = count($cart_items);

include 'includes/header.php';
?>

<div class="main-wrapper">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main-content dashboard-page">
    <div style="display: flex; flex-direction: column; gap: var(--space-2xl);">
      <div class="page-header">
    <div>
      <h1 class="page-title">Shop Fresh Products</h1>
      <p class="page-subtitle">Browse and buy fresh products from local farmers</p>
    </div>
    <a href="checkout.php" class="btn primary" style="position: relative;">
      🛒 View Cart
      <?php if ($cart_count > 0): ?>
        <span style="background: var(--danger); color: white; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-size: var(--text-xs); font-weight: 700; position: absolute; top: -8px; right: -8px;">
          <?php echo $cart_count > 99 ? '99+' : $cart_count; ?>
        </span>
      <?php endif; ?>
    </a>
  </div>

  <?php if ($message): ?>
    <div class="alert success">
      <span class="alert-icon">✅</span>
      <div class="alert-content">
        <div class="alert-title">Success</div>
        <?php echo htmlspecialchars($message); ?>
      </div>
    </div>
  <?php endif; ?>
  
  <?php foreach ($errors as $err): ?>
    <div class="alert error">
      <span class="alert-icon">❌</span>
      <div class="alert-content">
        <div class="alert-title">Error</div>
        <?php echo htmlspecialchars($err); ?>
      </div>
    </div>
  <?php endforeach; ?>

  <!-- Filters -->
  <div class="card" style="margin-bottom: var(--space-3xl);">
    <form class="grid filters-form" style="grid-template-columns: 1fr 220px 120px; gap: var(--space-sm); align-items: end;">
      <div class="form-group">
        <label>Search Products</label>
        <input type="text" name="search" placeholder="Search by name, farmer, or details..." 
               value="<?php echo htmlspecialchars($search); ?>">
      </div>
      
      <div class="form-group">
        <label>Category</label>
        <select name="category">
          <option value="">All Categories</option>
          <?php while ($cat = $categories->fetch_assoc()): ?>
            <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                    <?php echo ($category_filter === $cat['category']) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($cat['category']); ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>
      
      <div class="filters-actions">
        <a href="dashboard.php" class="btn btn-clear" aria-label="Clear filters">Clear</a>
        <button type="submit" class="btn primary btn-search">Search</button>
      </div>
    </form>
  </div>

  <!-- Products Grid -->
  <div class="grid grid-cols-3" style="margin-bottom: var(--space-3xl);">
    <?php if ($products->num_rows > 0): ?>
      <?php while ($p = $products->fetch_assoc()): ?>
        <div class="product-card">
          <?php if ($p['image'] && file_exists(__DIR__ . '/../uploads/' . $p['image'])): ?>
            <img src="../uploads/<?php echo htmlspecialchars($p['image']); ?>" 
                 alt="<?php echo htmlspecialchars($p['product_name']); ?>" 
                 class="product-image">
          <?php else: ?>
            <div class="product-image" style="display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--gray-200), var(--gray-100)); font-size: 3rem;">
              📦
            </div>
          <?php endif; ?>
          
          <div class="product-info">
            <span class="product-category"><?php echo htmlspecialchars($p['category']); ?></span>
            <h3 class="product-name"><?php echo htmlspecialchars($p['product_name']); ?></h3>
            
            <p class="product-description">
              👨‍🌾 <?php echo htmlspecialchars($p['farmer_name']); ?>
            </p>
            
            <?php if ($p['description']): ?>
              <p class="product-description">
                <?php echo htmlspecialchars(substr($p['description'], 0, 100)); ?>
                <?php echo strlen($p['description']) > 100 ? '...' : ''; ?>
              </p>
            <?php endif; ?>
            
            <!-- Stock Status -->
            <?php if ($p['available_stock'] <= 10): ?>
              <div class="alert warning" style="margin-bottom: var(--space-md);">
                <span class="alert-icon">⚠️</span>
                <div class="alert-content">
                  <span style="font-size: var(--text-sm);">Only <?php echo htmlspecialchars($p['available_stock']); ?> left!</span>
                </div>
              </div>
            <?php endif; ?>
            
            <div class="product-price">Rs <?php echo number_format($p['display_price'], 2); ?></div>
            <p style="font-size: var(--text-sm); color: var(--gray-500); margin: 0;">Per <?php echo htmlspecialchars($p['unit']); ?></p>
            
            <!-- Add to cart form -->
            <form method="POST" class="product-footer" style="margin-top: auto;">
              <input type="hidden" name="action" value="add_to_cart">
              <input type="hidden" name="product_id" value="<?php echo $p['product_id']; ?>">
              
              <input type="number" 
                     name="quantity" 
                     value="1" 
                     min="1" 
                     max="<?php echo $p['available_stock']; ?>"
                     style="width: 70px;">
              
              <button type="submit" class="btn primary btn-add">
                Add to Cart
              </button> 
            </form>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div style="grid-column: 1 / -1; text-align: center; padding: var(--space-3xl);">
        <div style="font-size: 4rem; margin-bottom: var(--space-lg);">🛍️</div>
        <h2>No products available</h2>
        <p>Check back later for fresh products from local farmers</p>
        <?php if ($search || $category_filter): ?>
          <a href="dashboard.php" class="btn primary" style="margin-top: var(--space-lg);">
            View All Products
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
    </div>
    <?php include 'includes/right-panel.php'; ?>
  </div>
</div>

<script src="../assets/js/script.js"></script>
</body>
</html>
