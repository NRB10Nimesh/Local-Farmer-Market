<?php
// Buyer sidebar component (navigation & profile preview)
if (!function_exists('e')) {
    function e($v) { 
        return htmlspecialchars($v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); 
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
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
      <p style="margin: 0 0 var(--space-sm) 0; font-size: var(--text-xs); color: var(--gray-500); text-transform: uppercase; font-weight: 600;"><span class="material-icons" aria-hidden="true">place</span> Address</p>
      <p style="margin: 0; font-size: var(--text-sm);"><?php echo e($profile['address']); ?></p>
    </div>
  </div>

  <!-- Navigation Menu -->
  <nav style="margin-top: var(--space-2xl);">
    <a href="dashboard.php" class="sidebar-item <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>">
      <span class="material-icons sidebar-icon" aria-hidden="true">shopping_bag</span>
      <span class="sidebar-label">Shop</span>
    </a>
    <a href="orders.php" class="sidebar-item <?php echo ($current_page === 'orders.php') ? 'active' : ''; ?>">
      <span class="material-icons sidebar-icon" aria-hidden="true">inventory_2</span>
      <span class="sidebar-label">My Orders</span>
    </a>
    <a href="checkout.php" class="sidebar-item <?php echo ($current_page === 'checkout.php') ? 'active' : ''; ?>">
      <span class="material-icons sidebar-icon" aria-hidden="true">shopping_cart</span>
      <span class="sidebar-label">Checkout</span>
    </a>
    <a href="profile.php" class="sidebar-item <?php echo ($current_page === 'profile.php') ? 'active' : ''; ?>">
      <span class="material-icons sidebar-icon" aria-hidden="true">account_circle</span>
      <span class="sidebar-label">Profile</span>
    </a>
  </nav>
</aside>