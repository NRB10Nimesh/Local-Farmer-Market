<?php
// Admin sidebar component (navigation & quick stats)
function e($v) { 
    return htmlspecialchars($v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); 
}

$current_page = basename($_SERVER['PHP_SELF']);

// Get quick stats
$pending_products = $conn->query("SELECT COUNT(*) as count FROM products WHERE approval_status = 'pending'")->fetch_assoc()['count'] ?? 0;
$pending_farmers = $conn->query("SELECT COUNT(*) as count FROM farmer WHERE is_active = 0")->fetch_assoc()['count'] ?? 0;
$total_orders = $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'] ?? 0;
?>

<aside class="sidebar admin-sidebar">
  <!-- Admin Profile Card -->
  <div class="card card-compact">
    <div style="text-align: center; margin-bottom: var(--space-lg);">
      <div class="user-avatar" style="width: 60px; height: 60px; font-size: var(--text-2xl); margin: 0 auto var(--space-md); background: linear-gradient(135deg, var(--admin), var(--admin-light)); color: white;">
        <span class="material-icons">settings</span>
      </div>
      <h4 style="margin: 0 0 var(--space-xs) 0;"><?php echo e($_SESSION['admin_name'] ?? 'Administrator'); ?></h4>
      <p style="margin: 0; color: var(--gray-500); font-size: var(--text-sm);">System Admin</p>
    </div>
  </div>

  <!-- Navigation Menu -->
  <nav style="margin-top: var(--space-2xl);">
    <a href="dashboard.php" class="sidebar-item <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>">
      <span class="material-icons sidebar-icon">bar_chart</span>
      <span class="sidebar-label">Dashboard</span>
    </a>
    <a href="products.php" class="sidebar-item <?php echo ($current_page === 'products.php') ? 'active' : ''; ?>">
      <span class="material-icons sidebar-icon">inventory_2</span>
      <span class="sidebar-label">Products</span>
      <?php if ($pending_products > 0): ?>
        <span class="sidebar-badge"><?php echo $pending_products; ?></span>
      <?php endif; ?>
    </a>
    <a href="farmers.php" class="sidebar-item <?php echo ($current_page === 'farmers.php') ? 'active' : ''; ?>">
      <span class="material-icons sidebar-icon">local_farm</span>
      <span class="sidebar-label">Farmers</span>
      <?php if ($pending_farmers > 0): ?>
        <span class="sidebar-badge"><?php echo $pending_farmers; ?></span>
      <?php endif; ?>
    </a>
    <a href="buyers.php" class="sidebar-item <?php echo ($current_page === 'buyers.php') ? 'active' : ''; ?>">
      <span class="material-icons sidebar-icon">shopping_cart</span>
      <span class="sidebar-label">Buyers</span>
    </a>
    <a href="orders.php" class="sidebar-item <?php echo ($current_page === 'orders.php') ? 'active' : ''; ?>">
      <span class="material-icons sidebar-icon">assignment</span>
      <span class="sidebar-label">Orders</span>
    </a>
    <a href="revenue.php" class="sidebar-item <?php echo ($current_page === 'revenue.php') ? 'active' : ''; ?>">
      <span class="material-icons sidebar-icon">attach_money</span>
      <span class="sidebar-label">Revenue</span>
    </a>
  </nav>

  <!-- Quick Stats Card -->
  <div class="card card-compact" style="margin-top: var(--space-3xl);">
    <h4 style="margin: 0 0 var(--space-lg) 0;"><span class="material-icons">trending_up</span> Quick Stats</h4>
    <div style="display: flex; justify-content: space-between; margin-bottom: var(--space-md);">
      <div style="text-align: center;">
        <p style="margin: 0; color: var(--admin); font-weight: 700; font-size: var(--text-lg);"><?php echo $pending_products; ?></p>
        <p style="margin: var(--space-xs) 0 0 0; font-size: var(--text-xs); color: var(--gray-500);">Pending</p>
      </div>
      <div style="text-align: center;">
        <p style="margin: 0; color: var(--admin); font-weight: 700; font-size: var(--text-lg);"><?php echo $total_orders; ?></p>
        <p style="margin: var(--space-xs) 0 0 0; font-size: var(--text-xs); color: var(--gray-500);">Orders</p>
      </div>
      <div style="text-align: center;">
        <p style="margin: 0; color: var(--admin); font-weight: 700; font-size: var(--text-lg);"><?php echo $pending_farmers; ?></p>
        <p style="margin: var(--space-xs) 0 0 0; font-size: var(--text-xs); color: var(--gray-500);">Inactive</p>
      </div>
    </div>
  </div>
</aside>