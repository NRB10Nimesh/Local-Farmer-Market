<?php
// admin/orders.php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../db.php';

$admin_id = intval($_SESSION['admin_id']);
$page_title = 'Orders Management - Admin Panel';
$message = '';
$errors = [];

// Filter parameters
$status_filter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');
$date_filter = $_GET['date'] ?? '';

$sql = "SELECT o.*, b.name as buyer_name, b.contact as buyer_contact, b.address as buyer_address,
        (SELECT COUNT(*) FROM order_details WHERE order_id = o.order_id) as item_count
        FROM orders o
        JOIN buyer b ON o.buyer_id = b.buyer_id
        WHERE 1=1";

$params = [];
$types = "";

if ($search !== '') {
    $sql .= " AND (b.name LIKE ? OR o.order_id = ?)";
    $pattern = "%{$search}%";
    $params[] = $pattern;
    $params[] = $search;
    $types .= "si";
}

if ($status_filter && in_array($status_filter, ['Pending', 'Confirmed', 'Preparing', 'Ready', 'Shipped', 'Delivered', 'Cancelled'])) {
    $sql .= " AND o.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($date_filter === 'today') {
    $sql .= " AND DATE(o.order_date) = CURDATE()";
} elseif ($date_filter === 'week') {
    $sql .= " AND o.order_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($date_filter === 'month') {
    $sql .= " AND o.order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

$sql .= " ORDER BY o.order_date DESC";

$stmt = $conn->prepare($sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get statistics
$stats = [
    'total' => $conn->query("SELECT COUNT(*) as c FROM orders")->fetch_assoc()['c'],
    'pending' => $conn->query("SELECT COUNT(*) as c FROM orders WHERE status = 'Pending'")->fetch_assoc()['c'],
    'delivered' => $conn->query("SELECT COUNT(*) as c FROM orders WHERE status = 'Delivered'")->fetch_assoc()['c'],
    'total_revenue' => $conn->query("SELECT SUM(total_amount) as t FROM orders WHERE status = 'Delivered'")->fetch_assoc()['t'] ?? 0
];

// Function to get order items
function get_order_items($conn, $order_id) {
    $stmt = $conn->prepare("SELECT od.*, p.product_name, p.unit, f.name as farmer_name
                            FROM order_details od 
                            JOIN products p ON od.product_id = p.product_id 
                            JOIN farmer f ON p.farmer_id = f.farmer_id
                            WHERE od.order_id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $result;
}
?>

<?php include 'includes/header.php'; ?>

<div class="main-wrapper">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main-content">
  <div style="max-width:1400px;margin:0 auto">
    
    <!-- Statistics Cards -->
    <div style="display:flex;gap:16px;margin-bottom:24px;flex-wrap:nowrap;overflow-x:auto;align-items:stretch">
      <div class="card" style="background:#dbeafe;border-left:4px solid #3b82f6;min-width:220px;flex:1 0 220px">
        <div class="small-muted"><span class="material-icons">inventory_2</span> Total Orders</div>
        <div style="font-size:2rem;font-weight:700;color:#3b82f6"><?php echo $stats['total']; ?></div>
      </div>
      
      <div class="card" style="background:#fef3c7;border-left:4px solid #f59e0b;min-width:220px;flex:1 0 220px">
        <div class="small-muted">⏳ Pending</div>
        <div style="font-size:2rem;font-weight:700;color:#f59e0b"><?php echo $stats['pending']; ?></div>
      </div>
      
      <div class="card" style="background:#d1fae5;border-left:4px solid #16a34a;min-width:220px;flex:1 0 220px">
        <div class="small-muted"><span class="material-icons">check_circle</span> Delivered</div>
        <div style="font-size:2rem;font-weight:700;color:#16a34a"><?php echo $stats['delivered']; ?></div>
      </div>
      
      <div class="card" style="background:linear-gradient(135deg, #f093fb 0%, #f5576c 100%);color:white;min-width:220px;flex:1 0 220px">
        <div class="small" style="opacity:0.9"><span class="material-icons">attach_money</span> Total Revenue</div>
        <div style="font-size:2rem;font-weight:700;margin:4px 0">Rs<?php echo number_format($stats['total_revenue'], 0); ?></div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="card">
      <h2 style="margin-top:0"><span class="material-icons">assignment</span> Orders Management</h2>

      <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
      <?php endif; ?>

      <!-- Filters -->
      <div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap">
        <input type="text" id="searchInput" placeholder="Search by order ID or buyer..." 
               value="<?php echo htmlspecialchars($search); ?>"
               style="flex:1;min-width:200px;padding:10px;border:2px solid #e5e7eb;border-radius:8px">
        
        <select id="statusFilter" class="select">
          <option value="">All Status</option>
          <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
          <option value="Confirmed" <?php echo $status_filter === 'Confirmed' ? 'selected' : ''; ?>>Confirmed</option>
          <option value="Preparing" <?php echo $status_filter === 'Preparing' ? 'selected' : ''; ?>>Preparing</option>
          <option value="Ready" <?php echo $status_filter === 'Ready' ? 'selected' : ''; ?>>Ready</option>
          <option value="Shipped" <?php echo $status_filter === 'Shipped' ? 'selected' : ''; ?>>Shipped</option>
          <option value="Delivered" <?php echo $status_filter === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
          <option value="Cancelled" <?php echo $status_filter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
        </select>
        
        <select id="dateFilter" class="select">
          <option value="">All Time</option>
          <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
          <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
          <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
        </select>
        
        <button class="btn btn-primary" onclick="applyFilters()">Apply</button>
        
        <?php if ($status_filter || $search || $date_filter): ?>
          <a href="orders.php" class="btn btn-ghost">Clear</a>
        <?php endif; ?>
      </div>

      <!-- Orders List -->
      <?php if (!empty($orders)): ?>
        <?php foreach ($orders as $order): ?>
          <?php $items = get_order_items($conn, $order['order_id']); ?>
          
          <div class="card" style="margin-bottom:20px;border-left:4px solid 
            <?php 
            switch($order['status']) {
              case 'Pending': echo '#f59e0b'; break;
              case 'Confirmed': echo '#3b82f6'; break;
              case 'Preparing': echo '#8b5cf6'; break;
              case 'Ready': echo '#06b6d4'; break;
              case 'Shipped': echo '#6366f1'; break;
              case 'Delivered': echo '#10b981'; break;
              default: echo '#ef4444';
            }
            ?>">
            
            <!-- Order Header -->
            <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:16px;flex-wrap:wrap;gap:12px">
              <div>
                <h3 style="margin:0 0 8px 0">Order #<?php echo htmlspecialchars($order['order_id']); ?></h3>
                <div class="small-muted">
                  <span class="material-icons">event</span> <?php echo date('F j, Y g:i A', strtotime($order['order_date'])); ?>
                </div>
              </div>
              
              <span class="badge badge-<?php 
                echo $order['status'] === 'Delivered' ? 'success' : 
                     ($order['status'] === 'Pending' ? 'warning' : 
                     ($order['status'] === 'Cancelled' ? 'danger' : 'info')); 
              ?>">
                <?php echo htmlspecialchars($order['status']); ?>
              </span>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:20px">
              <!-- Buyer Info -->
              <div>
                <div style="font-weight:700;margin-bottom:12px;color:#374151"><span class="material-icons">person</span> Buyer Information</div>
                <div style="padding:12px;background:#f9fafb;border-radius:8px">
                  <div class="small" style="margin-bottom:6px">
                    <strong>Name:</strong> <?php echo htmlspecialchars($order['buyer_name']); ?>
                  </div>
                  <div class="small" style="margin-bottom:6px">
                    <strong>Contact:</strong> <?php echo htmlspecialchars($order['buyer_contact']); ?>
                  </div>
                  <div class="small">
                    <strong>Address:</strong><br>
                    <?php echo nl2br(htmlspecialchars($order['buyer_address'])); ?>
                  </div>
                </div>
              </div>

              <!-- Order Items -->
              <div>
                <div style="font-weight:700;margin-bottom:12px;color:#374151"><span class="material-icons">inventory_2</span> Order Items (<?php echo count($items); ?>)</div>
                <?php foreach ($items as $item): ?>
                  <div style="padding:8px;background:#f9fafb;border-radius:6px;margin-bottom:6px">
                    <div style="font-weight:600"><?php echo htmlspecialchars($item['product_name']); ?></div>
                    <div class="small-muted">
                      Farmer: <?php echo htmlspecialchars($item['farmer_name']); ?>
                    </div>
                    <div class="small-muted">
                      Qty: <?php echo $item['quantity']; ?> <?php echo htmlspecialchars($item['unit']); ?> 
                      @ Rs<?php echo number_format($item['price'], 2); ?>
                      = <strong>Rs<?php echo number_format($item['quantity'] * $item['price'], 2); ?></strong>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Order Footer -->
            <div style="padding-top:16px;border-top:2px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
              <div>
                <div class="small-muted">Payment Method</div>
                <div style="font-weight:600">
                  <?php 
                  $payment_methods = [
                    'cash_on_delivery' => '<span class="material-icons" aria-hidden="true">money</span> Cash on Delivery',
                    'esewa' => '<span class="material-icons" aria-hidden="true">smartphone</span> eSewa',
                    'khalti' => '<span class="material-icons" aria-hidden="true">account_balance_wallet</span> Khalti',
                    'bank_transfer' => '<span class="material-icons" aria-hidden="true">account_balance</span> Bank Transfer'
                  ];
                  echo $payment_methods[$order['payment_method']] ?? $order['payment_method'];
                  ?>
                </div>
              </div>
              
              <div style="text-align:right">
                <div class="small-muted">Total Amount</div>
                <div style="font-size:1.5rem;font-weight:700;color:#16a34a">
                  Rs<?php echo number_format($order['total_amount'], 2); ?>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div style="text-align:center;padding:60px 20px">
          <div style="font-size:3.5rem;margin-bottom:14px"><span class="material-icons" style="font-size:3.5rem;">inventory_2</span></div>
          <div style="font-size:1.2rem;font-weight:700;margin-bottom:8px;color:#111">No orders found</div>
          <div class="small-muted">Orders will appear here once customers start purchasing</div>
        </div>
      <?php endif; ?>
    </div>

  </div>
  </div>
</div>
</div>

<script src="../assets/js/script.js"></script>
<script>
function applyFilters() {
  const search = document.getElementById('searchInput').value;
  const status = document.getElementById('statusFilter').value;
  const date = document.getElementById('dateFilter').value;
  window.location.href = 'orders.php?search=' + encodeURIComponent(search) + 
                         '&status=' + encodeURIComponent(status) + 
                         '&date=' + encodeURIComponent(date);
}
</script>
</body>
</html>