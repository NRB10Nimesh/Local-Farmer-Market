<?php
// admin/dashboard.php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login_admin.php");
    exit();
}

require_once '../db.php';

$admin_id = intval($_SESSION['admin_id']);
$page_title = 'Dashboard - Admin Panel';
$message = '';

// Get comprehensive statistics
$stats = [];

// Pending products
$stats['pending_products'] = $conn->query("SELECT COUNT(*) as count FROM products WHERE approval_status = 'pending'")->fetch_assoc()['count'] ?? 0;

// Approved products
$stats['approved_products'] = $conn->query("SELECT COUNT(*) as count FROM products WHERE approval_status = 'approved'")->fetch_assoc()['count'] ?? 0;

// Total farmers
$stats['total_farmers'] = $conn->query("SELECT COUNT(*) as count FROM farmer")->fetch_assoc()['count'] ?? 0;

// Active farmers
$stats['active_farmers'] = $conn->query("SELECT COUNT(*) as count FROM farmer WHERE is_active = 1")->fetch_assoc()['count'] ?? 0;

// Total buyers
$stats['total_buyers'] = $conn->query("SELECT COUNT(*) as count FROM buyer")->fetch_assoc()['count'] ?? 0;

// Active buyers
$stats['active_buyers'] = $conn->query("SELECT COUNT(*) as count FROM buyer WHERE is_active = 1")->fetch_assoc()['count'] ?? 0;

// Total orders
$stats['total_orders'] = $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'] ?? 0;

// Pending orders
$stats['pending_orders'] = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'Pending'")->fetch_assoc()['count'] ?? 0;

// Total revenue
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

include 'inlcudes/header.php';
?>

<div class="wrap">
  <div style="max-width:1400px;margin:0 auto">
    
    <!-- Alert for pending items -->
    <?php if ($stats['pending_products'] > 0): ?>
      <div class="card" style="background:linear-gradient(135deg, #f59e0b 0%, #d97706 100%);color:white;margin-bottom:20px">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <div>
            <div style="font-size:1.1rem;font-weight:700;margin-bottom:4px">
              ⚠️ <?php echo $stats['pending_products']; ?> product(s) awaiting approval
            </div>
            <div style="opacity:0.9">
              Review and approve products to make them available to buyers
            </div>
          </div>
          <a href="products.php" class="btn" style="background:white;color:#f59e0b;font-weight:700">
            Review Products →
          </a>
        </div>
      </div>
    <?php endif; ?>

    <h2 style="margin:0 0 24px 0">📊 Dashboard Overview</h2>

    <!-- Statistics Grid -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin-bottom:32px">
      <!-- Products Stats -->
      <div class="card" style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);color:white">
        <div class="small" style="opacity:0.9">Total Products</div>
        <div style="font-size:2.5rem;font-weight:700;margin:8px 0">
          <?php echo $stats['approved_products'] + $stats['pending_products']; ?>
        </div>
        <div class="small" style="opacity:0.9">
          <?php echo $stats['approved_products']; ?> approved, 
          <?php echo $stats['pending_products']; ?> pending
        </div>
      </div>
      
      <!-- Farmers Stats -->
      <div class="card" style="background:linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);color:white">
        <div class="small" style="opacity:0.9">Total Farmers</div>
        <div style="font-size:2.5rem;font-weight:700;margin:8px 0">
          <?php echo $stats['total_farmers']; ?>
        </div>
        <div class="small" style="opacity:0.9">
          <?php echo $stats['active_farmers']; ?> active
        </div>
      </div>
      
      <!-- Buyers Stats -->
      <div class="card" style="background:linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);color:white">
        <div class="small" style="opacity:0.9">Total Buyers</div>
        <div style="font-size:2.5rem;font-weight:700;margin:8px 0">
          <?php echo $stats['total_buyers']; ?>
        </div>
        <div class="small" style="opacity:0.9">
          <?php echo $stats['active_buyers']; ?> active
        </div>
      </div>
      
      <!-- Orders Stats -->
      <div class="card" style="background:linear-gradient(135deg, #fa709a 0%, #fee140 100%);color:white">
        <div class="small" style="opacity:0.9">Total Orders</div>
        <div style="font-size:2.5rem;font-weight:700;margin:8px 0">
          <?php echo $stats['total_orders']; ?>
        </div>
        <div class="small" style="opacity:0.9">
          <?php echo $stats['pending_orders']; ?> pending
        </div>
      </div>
    </div>

    <!-- Revenue Card -->
    <div class="card" style="background:linear-gradient(135deg, #f093fb 0%, #f5576c 100%);color:white;margin-bottom:32px">
      <div class="small" style="opacity:0.9">Total Platform Revenue</div>
      <div style="font-size:3rem;font-weight:700;margin:8px 0">
        Rs<?php echo number_format($stats['total_revenue'], 2); ?>
      </div>
      <div class="small" style="opacity:0.9">From delivered orders</div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
      <!-- Pending Products -->
      <div class="card">
        <h3 style="margin-top:0">⏳ Pending Product Approvals</h3>
        
        <?php if (!empty($recent_products)): ?>
          <?php foreach ($recent_products as $product): ?>
            <div style="padding:12px;background:#f9fafb;border-radius:8px;margin-bottom:10px;border-left:4px solid #f59e0b">
              <div style="display:flex;justify-content:space-between;align-items:start">
                <div style="flex:1">
                  <div style="font-weight:700"><?php echo htmlspecialchars($product['product_name']); ?></div>
                  <div class="small-muted">
                    Farmer: <?php echo htmlspecialchars($product['farmer_name']); ?>
                  </div>
                  <div class="small-muted">
                    Requested Price: Rs<?php echo number_format($product['price'], 2); ?>
                  </div>
                </div>
                <a href="products.php" class="btn btn-primary small-btn">Review</a>
              </div>
            </div>
          <?php endforeach; ?>
          
          <?php if ($stats['pending_products'] > 5): ?>
            <a href="products.php" class="btn btn-ghost" style="width:100%;margin-top:10px">
              View All (<?php echo $stats['pending_products']; ?>)
            </a>
          <?php endif; ?>
        <?php else: ?>
          <div class="small-muted" style="text-align:center;padding:20px">
            No pending approvals
          </div>
        <?php endif; ?>
      </div>

      <!-- Recent Orders -->
      <div class="card">
        <h3 style="margin-top:0">📦 Recent Orders</h3>
        
        <?php if (!empty($recent_orders)): ?>
          <?php foreach ($recent_orders as $order): ?>
            <div style="padding:12px;background:#f9fafb;border-radius:8px;margin-bottom:10px">
              <div style="display:flex;justify-content:space-between;align-items:start">
                <div>
                  <div style="font-weight:700">Order #<?php echo $order['order_id']; ?></div>
                  <div class="small-muted">
                    Buyer: <?php echo htmlspecialchars($order['buyer_name']); ?>
                  </div>
                  <div class="small-muted">
                    <?php echo date('M j, Y', strtotime($order['order_date'])); ?>
                  </div>
                </div>
                <div style="text-align:right">
                  <div style="font-weight:700;color:#16a34a">
                    Rs<?php echo number_format($order['total_amount'], 2); ?>
                  </div>
                  <span class="badge badge-<?php 
                    echo $order['status'] === 'Delivered' ? 'success' : 
                         ($order['status'] === 'Pending' ? 'warning' : 'info'); 
                  ?>">
                    <?php echo $order['status']; ?>
                  </span>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
          
          <a href="orders.php" class="btn btn-ghost" style="width:100%;margin-top:10px">
            View All Orders
          </a>
        <?php else: ?>
          <div class="small-muted" style="text-align:center;padding:20px">
            No orders yet
          </div>
        <?php endif; ?>
      </div>
    </div>
    

  </div>
</div>

<?php include 'inlcudes/sidebar.php'; ?>

<script src="../assets/js/script.js"></script>
</body>
</html>