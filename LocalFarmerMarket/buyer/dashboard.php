<?php
// buyer/dashboard.php - Shows admin prices and real-time stock
session_start();

if (!isset($_SESSION['buyer_id'])) {
    header("Location: ../login_buyer.php");
    exit();
}

require_once '../db.php';

$buyer_id   = intval($_SESSION['buyer_id']);
$page_title = 'Shop - Buyer Dashboard';
$message    = '';
$errors     = [];

// Enable error logging (avoid stray output)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php-error.log');

// Fetch buyer profile
$stmt = $conn->prepare("SELECT * FROM buyer WHERE buyer_id = ?");
$stmt->bind_param("i", $buyer_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    $product_id = intval($_POST['product_id']);
    $quantity   = intval($_POST['quantity'] ?? 1);

    // Check product stock availability
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
        // Check if already in cart
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

// Fetch products - ONLY show admin-approved products with admin prices
$search          = trim($_GET['search'] ?? '');
$category_filter = trim($_GET['category'] ?? '');

$sql = "SELECT p.product_id, p.product_name, p.description,
               COALESCE(p.admin_price, p.price) as display_price,
               p.quantity as available_stock,
               p.total_stock, p.sold_quantity,
               p.category, p.image, p.unit,
               f.name as farmer_name, f.farmer_id
        FROM products p
        JOIN farmer f ON p.farmer_id = f.farmer_id
        WHERE p.approval_status = 'approved'
        AND p.quantity > 0
        AND f.is_active = 1";

$params = [];
$types  = "";

if ($search !== '') {
    $sql .= " AND (p.product_name LIKE ? OR p.description LIKE ? OR f.name LIKE ?)";
    $pattern = "%{$search}%";
    $params  = [$pattern, $pattern, $pattern];
    $types  .= "sss";
}

if ($category_filter !== '') {
    $sql .= " AND p.category = ?";
    $params[] = $category_filter;
    $types   .= "s";
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

// Get cart count
$cart_count_query = $conn->prepare("SELECT COUNT(*) as count FROM cart WHERE buyer_id = ?");
$cart_count_query->bind_param("i", $buyer_id);
$cart_count_query->execute();
$cart_count_row = $cart_count_query->get_result()->fetch_assoc();
$cart_count     = $cart_count_row ? $cart_count_row['count'] : 0;
$cart_count_query->close();

include 'includes/header.php';
?>

<div class="wrap">
  <div style="max-width:1400px;margin:0 auto">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
      <h2 style="margin:0">🛒 Shop Fresh Products</h2>
      <a href="checkout.php" class="btn btn-primary" style="position:relative">
        🛒 View Cart
        <?php if ($cart_count > 0): ?>
          <span style="position:absolute;top:-8px;right:-8px;background:#ef4444;color:white;border-radius:50%;width:24px;height:24px;display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:700">
            <?php echo $cart_count; ?>
          </span>
        <?php endif; ?>
      </a>
    </div>

    <?php if ($message): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php foreach ($errors as $err): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div>
    <?php endforeach; ?>

    <!-- Filters -->
    <div class="card" style="margin-bottom:20px">
      <div style="display:flex;gap:12px;flex-wrap:wrap">
        <input type="text" id="searchInput" placeholder="Search products or farmers..."
               value="<?php echo htmlspecialchars($search); ?>"
               style="flex:1;min-width:200px;padding:10px;border:2px solid #e5e7eb;border-radius:8px">

        <select id="categoryFilter" class="select">
          <option value="">All Categories</option>
          <?php while ($cat = $categories->fetch_assoc()): ?>
            <option value="<?php echo htmlspecialchars($cat['category']); ?>"
                    <?php echo ($category_filter === $cat['category']) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($cat['category']); ?>
            </option>
          <?php endwhile; ?>
        </select>

        <button class="btn btn-primary" onclick="applySearch()">Search</button>

        <?php if ($search || $category_filter): ?>
          <a href="dashboard.php" class="btn btn-ghost">Clear</a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Products Grid -->
    <div class="products-grid">
      <?php if ($products->num_rows > 0): ?>
        <?php while ($p = $products->fetch_assoc()): ?>
          <div class="product">
            <?php if (!empty($p['image']) && file_exists(__DIR__ . '/../uploads/' . $p['image'])): ?>
              <img src="../uploads/<?php echo htmlspecialchars($p['image']); ?>" alt="<?php echo htmlspecialchars($p['product_name']); ?>">
            <?php else: ?>
              <div class="product-placeholder">📦</div>
            <?php endif; ?>

            <h4><?php echo htmlspecialchars($p['product_name']); ?></h4>

            <div class="meta">🧑‍🌾 <?php echo htmlspecialchars($p['farmer_name']); ?></div>
            <div class="meta">📁 <?php echo htmlspecialchars($p['category']); ?></div>

            <!-- Show admin-set price -->
            <div class="product-price-box">
              <div>
                <div class="product-price-label">Price</div>
                <div class="product-price-value">
                  Rs<?php echo number_format($p['display_price'], 2); ?>
                </div>
              </div>
              <div class="product-price-unit">per <?php echo htmlspecialchars($p['unit']); ?></div>
            </div>

            <!-- Real-time stock display -->
            <div style="background:#f0fdf4;padding:8px;border-radius:6px;margin:8px 0;border-left:3px solid #16a34a">
              <div class="meta" style="font-weight:600;color:#065f46">
                📦 <?php echo $p['available_stock']; ?> <?php echo htmlspecialchars($p['unit']); ?> available
              </div>
              <?php if ($p['available_stock'] <= 10): ?>
                <div class="              <?php if ($p['available_stock'] <= 10): ?>
                <div class="meta" style="color:#f59e0b;font-weight:600">
                  ⚠️ Low stock - Order soon!
                </div>
              <?php endif; ?>
            </div>

            <?php if ($p['description']): ?>
              <div class="product-description small-muted">
                <?php echo htmlspecialchars(substr($p['description'], 0, 80)); ?>
                <?php echo strlen($p['description']) > 80 ? '...' : ''; ?>
              </div>
            <?php endif; ?>

            <!-- Add to cart form -->
            <form method="POST" class="product-quantity-form">
              <input type="hidden" name="action" value="add_to_cart">
              <input type="hidden" name="product_id" value="<?php echo $p['product_id']; ?>">

              <input type="number"
                     name="quantity"
                     value="1"
                     min="1"
                     max="<?php echo $p['available_stock']; ?>"
                     class="product-quantity-input"
                     required>

              <button type="submit" class="btn btn-primary product-add-btn">
                Add to Cart
              </button>
            </form>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div style="grid-column:1/-1;text-align:center;padding:60px 20px">
          <div style="font-size:3.5rem;margin-bottom:16px">🛒</div>
          <h3>No products available</h3>
          <p class="small-muted">Check back later for fresh products from local farmers</p>
          <?php if ($search || $category_filter): ?>
            <a href="dashboard.php" class="btn btn-primary" style="margin-top:16px">
              View All Products
            </a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include 'includes/sidebar.php'; ?>

<script src="../assets/js/script.js"></script>
<script>
function applySearch() {
  const search = document.getElementById('searchInput').value;
  const category = document.getElementById('categoryFilter').value;
  window.location.href = 'dashboard.php?search=' + encodeURIComponent(search) + '&category=' + encodeURIComponent(category);
}

// Real-time stock check before adding to cart
document.querySelectorAll('.product-quantity-form').forEach(form => {
  form.addEventListener('submit', function(e) {
    const qtyInput = this.querySelector('input[name="quantity"]');
    const max = parseInt(qtyInput.getAttribute('max'));
    const value = parseInt(qtyInput.value);

    if (value > max) {
      e.preventDefault();
      alert(`Only ${max} units available in stock.`);
      qtyInput.value = max;
    }
  });
});
</script>
</body>
</html>