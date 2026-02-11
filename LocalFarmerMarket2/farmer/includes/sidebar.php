<?php
// Farmer Sidebar Component
function e($v) { 
    return htmlspecialchars($v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); 
}

$current_page = basename($_SERVER['PHP_SELF']);

// Ensure $profile exists and has safe defaults to avoid undefined index notices
if (!isset($profile) || !is_array($profile)) {
    $profile = [
        'name' => 'Farmer',
        'contact' => '',
        'farm_type' => 'Not specified',
        'address' => 'Not provided'
    ];
} else {
    $profile['name'] = $profile['name'] ?? 'Farmer';
    $profile['contact'] = $profile['contact'] ?? '';
    $profile['farm_type'] = $profile['farm_type'] ?? 'Not specified';
    $profile['address'] = $profile['address'] ?? 'Not provided';
}
?>

<aside class="sidebar">
  <!-- Profile Card -->
  <div class="card card-compact">
    <div style="text-align: center; margin-bottom: var(--space-lg);">
      <div class="user-avatar" style="width: 60px; height: 60px; font-size: var(--text-2xl); margin: 0 auto var(--space-md);">
        <?php echo strtoupper(substr($profile['name'], 0, 1)); ?>
      </div>
      <h4 style="margin: 0 0 var(--space-xs) 0;"><?php echo e($profile['name']); ?></h4>
      <p style="margin: 0; color: var(--gray-500); font-size: var(--text-sm);"><?php echo e($profile['contact']); ?></p>
    </div>
    
    <div style="padding: var(--space-md) 0; border-top: 1px solid var(--gray-200); border-bottom: 1px solid var(--gray-200); margin-bottom: var(--space-lg);">
      <p style="margin: 0 0 var(--space-xs) 0; font-size: var(--text-xs); color: var(--gray-500); text-transform: uppercase; font-weight: 600;">🏡 Farm Info</p>
      <p style="margin: var(--space-xs) 0; font-size: var(--text-sm);"><strong>Farm Type:</strong> <?php echo e($profile['farm_type']); ?></p>
      <p style="margin: 0; font-size: var(--text-sm);"><strong>Address:</strong> <?php echo e($profile['address']); ?></p>
    </div>
  </div>

  <!-- Navigation Menu -->
  <nav style="margin-top: var(--space-2xl);">
    <a href="dashboard.php" class="sidebar-item <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>">
      <span class="sidebar-icon">📦</span>
      <span class="sidebar-label">My Products</span>
    </a>
    <a href="orders.php" class="sidebar-item <?php echo ($current_page === 'orders.php') ? 'active' : ''; ?>">
      <span class="sidebar-icon">📝</span>
      <span class="sidebar-label">Orders</span>
    </a>
    <a href="profile.php" class="sidebar-item <?php echo ($current_page === 'profile.php') ? 'active' : ''; ?>">
      <span class="sidebar-icon">👤</span>
      <span class="sidebar-label">Profile</span>
    </a>
  </nav>

  <!-- Recent Orders Card -->
  <div class="card card-compact" style="margin-top: var(--space-3xl);">
    <h4 style="margin: 0 0 var(--space-lg) 0;">📊 Recent Orders</h4>
    <?php
    // Fetch recent orders
    $order_sql = "SELECT o.order_id, o.order_date, o.total_amount 
                  FROM orders o 
                  JOIN order_details od ON od.order_id = o.order_id
                  JOIN products p ON p.product_id = od.product_id
                  WHERE p.farmer_id = ?
                  GROUP BY o.order_id
                  ORDER BY o.order_date DESC
                  LIMIT 5";
    $st = $conn->prepare($order_sql);
    $st->bind_param("i", $farmer_id);
    $st->execute();
    $recent_orders = $st->get_result();
    $st->close();
    
    if ($recent_orders->num_rows > 0):
        while ($o = $recent_orders->fetch_assoc()):
    ?>
      <div style="padding: var(--space-md) 0; border-bottom: 1px solid var(--gray-200);">
        <h5 style="margin: 0 0 var(--space-xs) 0; font-weight: 600;">Order #<?php echo e($o['order_id']); ?></h5>
        <p style="margin: 0; color: var(--gray-600); font-size: var(--text-sm);">
          Rs <?php echo number_format($o['total_amount'], 2); ?> • <?php echo date('M d', strtotime($o['order_date'])); ?>
        </p>
      </div>
    <?php 
        endwhile;
    else: 
    ?>
      <div style="padding: var(--space-lg); text-align: center; color: var(--gray-500);">
        No orders yet
      </div>
    <?php endif; ?>
  </div>
</aside>