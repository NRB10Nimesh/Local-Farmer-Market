<?php
// Farmer Sidebar
function e($v) { 
    return htmlspecialchars($v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); 
}

// Get current page
$current_page = basename($_SERVER['PHP_SELF']);
?>

<aside>
  <!-- Profile Card -->
  <div class="card">
    <div class="profile-photo"><?php echo strtoupper(substr($profile['name'], 0, 1)); ?></div>
    <h3 style="margin:0 0 4px 0"><?php echo e($profile['name']); ?></h3>
    <div class="kv"><strong>Contact</strong><?php echo e($profile['contact']); ?></div>
    <div class="kv"><strong>Address</strong><?php echo e($profile['address']); ?></div>
    <div class="kv"><strong>Farm Type</strong><?php echo e($profile['farm_type']); ?></div>
    
    <div class="actions">
      <a href="dashboard.php" class="btn <?php echo ($current_page === 'dashboard.php') ? 'btn-primary' : 'btn-ghost'; ?>" style="text-align:center">Products</a>
      <a href="orders.php" class="btn <?php echo ($current_page === 'orders.php') ? 'btn-primary' : 'btn-ghost'; ?>" style="text-align:center">Orders</a>
    </div>
    <div style="margin-top:8px">
      <a href="profile.php" class="btn <?php echo ($current_page === 'profile.php') ? 'btn-primary' : 'btn-ghost'; ?>" style="width:100%;text-align:center">Edit Profile</a>
    </div>
  </div>

 <div style="height:12px"></div>
  <div class="card">
    <h4 style="margin-top:0">Recent Orders</h4>
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
        <div style="padding:8px 0;border-bottom:1px dashed #eee">
          <div style="font-weight:700">
            Order #<?php echo e($o['order_id']); ?>
            <span style="float:right;color:var(--muted);font-size:0.85rem"><?php echo date('M d', strtotime($o['order_date'])); ?></span>
          </div>
          <div class="small-muted">Rs<?php echo e($o['total_amount']); ?></div>
        </div>
    <?php 
        endwhile;
    else: 
    ?>
        <div class="small-muted">No orders yet.</div>
    <?php endif; ?>
    
    <a href="orders.php" class="btn btn-ghost" style="width:100%;margin-top:10px;text-align:center">View All Orders</a>
  </div>
</aside>