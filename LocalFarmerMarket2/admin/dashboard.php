<?php
// Admin Dashboard - Modern UI
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../db.php';

$admin_id = intval($_SESSION['admin_id']);
$page_title = 'Dashboard - Admin Panel';
$message = '';

// Fetch admin profile
$stmt = $conn->prepare("SELECT * FROM admin WHERE admin_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get comprehensive statistics
$stats = [];

$stats['pending_products'] = $conn->query("SELECT COUNT(*) as count FROM products WHERE approval_status = 'pending'")->fetch_assoc()['count'] ?? 0;
$stats['approved_products'] = $conn->query("SELECT COUNT(*) as count FROM products WHERE approval_status = 'approved'")->fetch_assoc()['count'] ?? 0;
$stats['total_farmers'] = $conn->query("SELECT COUNT(*) as count FROM farmer")->fetch_assoc()['count'] ?? 0;
$stats['active_farmers'] = $conn->query("SELECT COUNT(*) as count FROM farmer WHERE is_active = 1")->fetch_assoc()['count'] ?? 0;
$stats['total_buyers'] = $conn->query("SELECT COUNT(*) as count FROM buyer")->fetch_assoc()['count'] ?? 0;
$stats['active_buyers'] = $conn->query("SELECT COUNT(*) as count FROM buyer WHERE is_active = 1")->fetch_assoc()['count'] ?? 0;
$stats['total_orders'] = $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'] ?? 0;
$stats['pending_orders'] = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'Pending'")->fetch_assoc()['count'] ?? 0;
$stats['total_revenue'] = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE status = 'Delivered'")->fetch_assoc()['total'] ?? 0;

// Recent pending products
$recent_products = $conn->query("SELECT p.*, f.name as farmer_name 
                                 FROM products p 
                                 JOIN farmer f ON p.farmer_id = f.farmer_id 
                                 WHERE p.approval_status = 'pending' 
                                 ORDER BY p.created_at DESC 
                                 LIMIT 5")->fetch_all(MYSQLI_ASSOC);

// Recent orders
$recent_orders = $conn->query("SELECT o.*, b.name as buyer_name 
                               FROM orders o 
                               JOIN buyer b ON o.buyer_id = b.buyer_id 
                               ORDER BY o.order_date DESC 
                               LIMIT 5")->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
?>

<div class="main-wrapper">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main-content">
  <div class="page-header">
    <div>
      <h1 class="page-title">Dashboard</h1>
      <p class="page-subtitle">System overview and analytics</p>
    </div>
  </div>

  <!-- Alert for pending items -->
  <?php if ($stats['pending_products'] > 0): ?>
    <div class="alert warning" style="margin-bottom: var(--space-3xl);">
      <span class="alert-icon">⚠️</span>
      <div class="alert-content">
        <div class="alert-title"><?php echo $stats['pending_products']; ?> Product(s) Awaiting Approval</div>
        <p>Review and approve products to make them available to buyers.</p>
        <a href="products.php" class="btn primary small" style="margin-top: var(--space-sm);">Review Products →</a>
      </div>
    </div>
  <?php endif; ?>

  <!-- Statistics Grid -->
  <div class="grid grid-cols-4" style="margin-bottom: var(--space-3xl);">
    <!-- Products -->
    <div class="stat-card admin">
      <div class="stat-value"><?php echo $stats['approved_products'] + $stats['pending_products']; ?></div>
      <div class="stat-label">Total Products</div>
      <p style="margin-top: var(--space-md); font-size: var(--text-sm); opacity: 0.9;">
        📦 <?php echo $stats['approved_products']; ?> approved · ⏳ <?php echo $stats['pending_products']; ?> pending
      </p>
    </div>
    
    <!-- Farmers -->
    <div class="stat-card secondary">
      <div class="stat-value"><?php echo $stats['total_farmers']; ?></div>
      <div class="stat-label">Total Farmers</div>
      <p style="margin-top: var(--space-md); font-size: var(--text-sm); opacity: 0.9;">
        ✅ <?php echo $stats['active_farmers']; ?> active
      </p>
    </div>
    
    <!-- Buyers -->
    <div class="stat-card">
      <div class="stat-value"><?php echo $stats['total_buyers']; ?></div>
      <div class="stat-label">Total Buyers</div>
      <p style="margin-top: var(--space-md); font-size: var(--text-sm); opacity: 0.9;">
        ✅ <?php echo $stats['active_buyers']; ?> active
      </p>
    </div>
    
    <!-- Orders -->
    <div class="stat-card warning">
      <div class="stat-value"><?php echo $stats['total_orders']; ?></div>
      <div class="stat-label">Total Orders</div>
      <p style="margin-top: var(--space-md); font-size: var(--text-sm); opacity: 0.9;">
        ⏳ <?php echo $stats['pending_orders']; ?> pending
      </p>
    </div>
  </div>

  <!-- Revenue Card (green, no white accents) -->
  <div class="card revenue-card--narrow" style="margin-bottom: var(--space-3xl); background: linear-gradient(135deg,#10b981,#34d399); color: rgba(255,255,255,0.95); padding: var(--space-3xl); box-shadow: 0 10px 30px rgba(16,185,129,0.08); border: none;">
    <h3 style="margin-top: 0; color: rgba(255,255,255,0.95);">💰 Total Platform Revenue</h3>
    <div style="font-size: var(--text-4xl); font-weight: 700; margin: var(--space-lg) 0; color: #ffffff;">
      Rs <?php echo number_format($stats['total_revenue'], 2); ?>
    </div>
    <p style="margin: 0; opacity: 0.9; color: rgba(255,255,255,0.9);">From completed delivered orders</p>
  </div>

  <!-- Recent Activity Grid -->
  <div class="grid grid-cols-2" style="margin-bottom: var(--space-3xl);">
    <!-- Pending Products -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">⏳ Pending Approvals</h3>
      </div>
      
      <?php if (!empty($recent_products)): ?>
        <div style="max-height: 400px; overflow-y: auto;">
          <?php foreach ($recent_products as $product): ?>
            <div style="padding: var(--space-lg); border-bottom: 1px solid var(--gray-200); display: grid; grid-template-columns: 1fr auto; gap: var(--space-md); align-items: center;">
              <div>
                <h4 style="margin: 0 0 var(--space-xs) 0;"><?php echo htmlspecialchars($product['product_name']); ?></h4>
                <p style="margin: 0; font-size: var(--text-sm); color: var(--gray-600);">
                  👨‍🌾 <?php echo htmlspecialchars($product['farmer_name']); ?>
                </p>
                <p style="margin: var(--space-xs) 0 0 0; font-size: var(--text-sm); color: var(--gray-600);">
                  💰 Rs <?php echo number_format($product['price'], 2); ?>
                </p>
              </div>
              <a href="products.php" class="btn primary small">Review</a>
            </div>
          <?php endforeach; ?>
        </div>
        
        <?php if ($stats['pending_products'] > 5): ?>
          <div style="padding: var(--space-lg); text-align: center; border-top: 1px solid var(--gray-200);">
            <a href="products.php" class="btn outline small">View All (<?php echo $stats['pending_products']; ?>)</a>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <div style="padding: var(--space-3xl); text-align: center;">
          <p style="margin: 0; color: var(--gray-500);">✅ No pending approvals</p>
        </div>
      <?php endif; ?>
    </div>

    <!-- Recent Orders -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">📦 Recent Orders</h3>
      </div>
      
      <?php if (!empty($recent_orders)): ?>
        <div style="max-height: 400px; overflow-y: auto;">
          <?php foreach ($recent_orders as $order): ?>
            <div style="padding: var(--space-lg); border-bottom: 1px solid var(--gray-200); display: grid; grid-template-columns: 1fr auto; gap: var(--space-md); align-items: center;">
              <div>
                <h4 style="margin: 0 0 var(--space-xs) 0;">Order #<?php echo $order['order_id']; ?></h4>
                <p style="margin: 0; font-size: var(--text-sm); color: var(--gray-600);">
                  👤 <?php echo htmlspecialchars($order['buyer_name']); ?>
                </p>
                <p style="margin: var(--space-xs) 0 0 0; font-size: var(--text-sm); color: var(--gray-600);">
                  📅 <?php echo date('M j, Y', strtotime($order['order_date'])); ?>
                </p>
              </div>
              <div style="text-align: right;">
                <div style="font-weight: 700; color: var(--primary); margin-bottom: var(--space-sm);">
                  Rs <?php echo number_format($order['total_amount'], 2); ?>
                </div>
                <span class="badge <?php 
                  echo $order['status'] === 'Delivered' ? 'approved' : 
                       ($order['status'] === 'Pending' ? 'pending' : 'active'); 
                ?>">
                  <?php echo $order['status']; ?>
                </span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        
        <div style="padding: var(--space-lg); text-align: center; border-top: 1px solid var(--gray-200);">
          <a href="orders.php" class="btn outline small">View All Orders</a>
        </div>
      <?php else: ?>
        <div style="padding: var(--space-3xl); text-align: center;">
          <p style="margin: 0; color: var(--gray-500);">No orders yet</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="../assets/js/script.js"></script>
</body>
</html>