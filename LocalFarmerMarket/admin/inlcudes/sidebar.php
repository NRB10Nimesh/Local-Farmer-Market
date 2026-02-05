<?php
// Admin Sidebar - includes/sidebar.php
function e($v) { 
    return htmlspecialchars($v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); 
}

$current_page = basename($_SERVER['PHP_SELF']);

// Get quick stats
$pending_products = $conn->query("SELECT COUNT(*) as count FROM products WHERE approval_status = 'pending'")->fetch_assoc()['count'] ?? 0;
$pending_farmers = $conn->query("SELECT COUNT(*) as count FROM farmer WHERE is_active = 0")->fetch_assoc()['count'] ?? 0;
$total_orders = $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'] ?? 0;
?>

<aside>
  <!-- Admin Profile Card -->
  <div class="card">
    <div class="profile-photo" style="background: linear-gradient(135deg, #e0c3fc, #8ec5fc);">
        🔐
    </div>
    <h3 style="margin:8px 0 4px 0"><?php echo e($_SESSION['admin_name'] ?? 'Administrator'); ?></h3>
    <div class="small-muted" style="margin-bottom: 16px">System Administrator</div>
    
    <div class="actions">
      <a href="dashboard.php" class="btn <?php echo ($current_page === 'dashboard.php') ? 'btn-primary' : 'btn-ghost'; ?>" style="text-align:center">
        📊 Dashboard
      </a>
      <a href="products.php" class="btn <?php echo ($current_page === 'products.php') ? 'btn-primary' : 'btn-ghost'; ?>" style="text-align:center">
        📦 Products
        <?php if ($pending_products > 0): ?>
          <span class="badge badge-warning" style="margin-left: 8px"><?php echo $pending_products; ?></span>
        <?php endif; ?>
      </a>
    </div>
    
    <div style="margin-top:8px">
      <a href="farmers.php" class="btn <?php echo ($current_page === 'farmers.php') ? 'btn-primary' : 'btn-ghost'; ?>" style="width:100%;text-align:center">
        🧑‍🌾 Farmers
        <?php if ($pending_farmers > 0): ?>
          <span class="badge badge-warning" style="margin-left: 8px"><?php echo $pending_farmers; ?></span>
        <?php endif; ?>
      </a>
    </div>
    
    <div style="margin-top:8px">
      <a href="buyers.php" class="btn <?php echo ($current_page === 'buyers.php') ? 'btn-primary' : 'btn-ghost'; ?>" style="width:100%;text-align:center">
        🛒 Buyers
      </a>
    </div>
    
    <div style="margin-top:8px">
      <a href="orders.php" class="btn <?php echo ($current_page === 'orders.php') ? 'btn-primary' : 'btn-ghost'; ?>" style="width:100%;text-align:center">
        📋 Orders
      </a>
    </div>
  </div>

  <!-- Quick Stats -->
  <div class="card" style="margin-top:12px">
    <h4 style="margin-top:0">⚡ Quick Stats</h4>
    
    <?php if ($pending_products > 0): ?>
    <div class="quick-stats-box warning mb-8">
      <div class="small-muted">⏳ Pending Approvals</div>
      <div class="quick-stats-value stat-value warning"><?php echo $pending_products; ?></div>
    </div>
    <?php endif; ?>
    
    <div class="quick-stats-box info mb-8">
      <div class="small-muted">📦 Total Orders</div>
      <div class="quick-stats-value stat-value info"><?php echo $total_orders; ?></div>
    </div>
    
    <div class="quick-stats-box success">
      <div class="small-muted">👥 Active Users</div>
      <div class="quick-stats-value stat-value success">
        <?php 
        $active_users = $conn->query("SELECT 
            (SELECT COUNT(*) FROM farmer WHERE is_active = 1) + 
            (SELECT COUNT(*) FROM buyer WHERE is_active = 1) as total")->fetch_assoc()['total'] ?? 0;
        echo $active_users;
        ?>
      </div>
    </div>
  </div>
</aside>