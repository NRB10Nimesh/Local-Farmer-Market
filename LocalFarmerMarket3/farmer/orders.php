<?php
session_start();

// Redirect to farmer login if session is missing
if (!isset($_SESSION['farmer_id'])) {
    header("Location: ../login_farmer.php");
    exit();
}

require_once '../db.php';

$farmer_id = intval($_SESSION['farmer_id']);
$page_title = 'Orders - Farmer Dashboard';
$message = '';
$errors = [];

// Load farmer profile from DB for display
$stmt = $conn->prepare("SELECT * FROM Farmer WHERE farmer_id = ?");
$stmt->bind_param("i", $farmer_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle order status updates with validation and buyer notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $order_id = intval($_POST['order_id']);
    $new_status = trim($_POST['status']);
    
    // Allowed statuses (must match DB enum)
    $allowed_statuses = ['Pending', 'Processing', 'Completed', 'Cancelled'];
    
    if (in_array($new_status, $allowed_statuses)) {
        // Ensure the order contains products owned by this farmer
        $verify = $conn->prepare("SELECT COUNT(DISTINCT o.order_id) as count
                                  FROM orders o
                                  JOIN order_details od ON o.order_id = od.order_id
                                  JOIN products p ON od.product_id = p.product_id
                                  WHERE o.order_id = ? AND p.farmer_id = ?");
        $verify->bind_param("ii", $order_id, $farmer_id);
        $verify->execute();
        $is_valid = $verify->get_result()->fetch_assoc()['count'] > 0;
        $verify->close();
        
        if ($is_valid) {
            // Retrieve current order status before applying the change
            $old_stmt = $conn->prepare("SELECT status FROM orders WHERE order_id = ?");
            $old_stmt->bind_param("i", $order_id);
            $old_stmt->execute();
            $old_status = $old_stmt->get_result()->fetch_assoc()['status'];
            $old_stmt->close();
            
            // Persist new order status and record change
            $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
            $stmt->bind_param("si", $new_status, $order_id);
            
            if ($stmt->execute()) {
                // Insert a status-change entry into order_status_history for auditing
                $history = $conn->prepare("INSERT INTO order_status_history (order_id, old_status, new_status, changed_by) VALUES (?, ?, ?, 'farmer')");
                $history->bind_param("iss", $order_id, $old_status, $new_status);
                $history->execute();
                $history->close();
                
                // Lookup buyer_id so we can notify them about the status change
                $buyer_stmt = $conn->prepare("SELECT buyer_id FROM orders WHERE order_id = ?");
                $buyer_stmt->bind_param("i", $order_id);
                $buyer_stmt->execute();
                $buyer_id = $buyer_stmt->get_result()->fetch_assoc()['buyer_id'];
                $buyer_stmt->close();
                
                // Create a buyer notification for the order update
                $notif = $conn->prepare("INSERT INTO notifications (user_type, user_id, title, message) VALUES ('buyer', ?, 'Order Update', ?)");
                $notif_msg = "Your order #{$order_id} status changed to: {$new_status}";
                $notif->bind_param("is", $buyer_id, $notif_msg);
                $notif->execute();
                $notif->close();
                
                $message = "Order status updated to: $new_status";
            } else {
                $errors[] = "Failed to update status: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $errors[] = "Invalid order.";
        }
    } else {
        $errors[] = "Invalid status selected.";
    }
}

// Enable detailed PHP error reporting for local debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Sanitize and validate query parameters used for filtering orders
$status_filter = !empty($_GET['status']) ? trim($_GET['status']) : '';
$date_filter = !empty($_GET['date']) ? trim($_GET['date']) : '';

// Validate status filter against allowed values
$valid_statuses = ['Pending', 'Processing', 'Completed', 'Cancelled'];
if ($status_filter && !in_array($status_filter, $valid_statuses)) {
    $status_filter = '';
    $errors[] = 'Invalid status filter';
}

// Validate date filter
$valid_date_filters = ['today', 'week', 'month'];
if ($date_filter && !in_array($date_filter, $valid_date_filters)) {
    $date_filter = '';
    $errors[] = 'Invalid date filter';
}

// Build base SQL to fetch orders that include this farmer's products
$sql = "SELECT DISTINCT o.order_id, o.buyer_id, o.total_amount, o.order_date, o.status, 
               b.address as buyer_address, b.contact as buyer_phone, o.payment_method,
               b.name as buyer_name, b.contact as buyer_contact,
               (SELECT COUNT(*) FROM order_details od2 
                JOIN products p2 ON od2.product_id = p2.product_id 
                WHERE od2.order_id = o.order_id AND p2.farmer_id = ?) as farmer_items_count,
               (SELECT SUM(od2.quantity * od2.price) 
                FROM order_details od2 
                JOIN products p2 ON od2.product_id = p2.product_id 
                WHERE od2.order_id = o.order_id AND p2.farmer_id = ?) as farmer_total
        FROM orders o
        JOIN order_details od ON o.order_id = od.order_id
        JOIN products p ON od.product_id = p.product_id
        JOIN buyer b ON o.buyer_id = b.buyer_id
        WHERE p.farmer_id = ?";

$params = [$farmer_id, $farmer_id, $farmer_id];
$types = "iii";

// Append status filter to SQL when provided by the user
if (!empty($status_filter)) {
    $sql .= " AND o.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Append date range filter when specified (today / week / month)
if ($date_filter === 'today') {
    $sql .= " AND DATE(o.order_date) = CURDATE()";
} elseif ($date_filter === 'week') {
    $sql .= " AND o.order_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($date_filter === 'month') {
    $sql .= " AND o.order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

$sql .= " ORDER BY o.order_date DESC";

// Initialize orders array
$orders = [];

// Prepare and execute the statement
try {
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    // Dynamically bind prepared statement parameters when present
    if (!empty($params)) {
        $bind_names[] = $types;
        for ($i = 0; $i < count($params); $i++) {
            $bind_name = 'bind' . $i;
            $$bind_name = $params[$i];
            $bind_names[] = &$$bind_name;
        }
        
        if (!call_user_func_array([$stmt, 'bind_param'], $bind_names)) {
            throw new Exception("Binding parameters failed: " . $stmt->error);
        }
    }

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    if ($result === false) {
        throw new Exception("Get result failed: " . $stmt->error);
    }

    $orders = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $errors[] = "An error occurred while fetching orders. Please try again later.";
    if (isset($stmt) && $stmt) {
        $stmt->close();
    }
}

// Helper: fetch line items from this order that belong to the current farmer
function get_farmer_order_items($conn, $order_id, $farmer_id) {
    $stmt = $conn->prepare("SELECT od.*, p.product_name, p.unit, p.image
                            FROM order_details od 
                            JOIN products p ON od.product_id = p.product_id 
                            WHERE od.order_id = ? AND p.farmer_id = ?");
    $stmt->bind_param("ii", $order_id, $farmer_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $result;
}

// Aggregate order statistics used for the farmer dashboard
$stats_query = "SELECT 
    COUNT(DISTINCT o.order_id) as total_orders,
    SUM(CASE WHEN o.status = 'Pending' THEN 1 ELSE 0 END) as pending_orders,
    SUM(CASE WHEN o.status = 'Completed' THEN 1 ELSE 0 END) as completed_orders,
    SUM(od.quantity * od.price) as total_revenue
    FROM orders o
    JOIN order_details od ON o.order_id = od.order_id
    JOIN products p ON od.product_id = p.product_id
    WHERE p.farmer_id = ?";

$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $farmer_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>

<?php include 'includes/header.php'; ?>

<!-- Orders styles moved to assets/css/farmer.css -->
<div class="main-wrapper">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main-content">
  <div class="wrap">
  <div style="max-width:1400px;margin:0 auto">
    
    <!-- Statistics Cards -->
    <div class="stats-grid" style="margin-bottom:24px">
      <div class="card" style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);color:white">
        <div class="small" style="opacity:0.9">Total Orders</div>
        <div class="stat-value"><?php echo $stats['total_orders'] ?? 0; ?></div>
        <div class="small" style="opacity:0.9">All time</div>
      </div>
      
      <div class="card" style="background:linear-gradient(135deg, #f093fb 0%, #f5576c 100%);color:white">
        <div class="small" style="opacity:0.9">Pending Orders</div>
        <div class="stat-value"><?php echo $stats['pending_orders'] ?? 0; ?></div>
        <div class="small" style="opacity:0.9">Needs attention</div>
      </div>
      
      <div class="card" style="background:linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);color:white">
        <div class="small" style="opacity:0.9">Completed</div>
        <div class="stat-value"><?php echo $stats['completed_orders'] ?? 0; ?></div>
        <div class="small" style="opacity:0.9">Completed orders</div>
      </div>
      
      <div class="card" style="background:linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);color:white">
        <div class="small" style="opacity:0.9">Total Revenue</div>
        <div class="stat-value">Rs<?php echo number_format($stats['total_revenue'] ?? 0, 0); ?></div>
        <div class="small" style="opacity:0.9">From your products</div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="card">
        <div class="filters">
        <div class="filters-header">
          <h2 class="filters-title"><span class="material-icons" aria-hidden="true">inventory_2</span> Order Management</h2>
          <?php if ($status_filter || $date_filter): ?>
            <a href="orders.php" class="btn-clear">Clear Filters</a>
          <?php endif; ?>
        </div>

        <div class="controls">
          <select class="select" onchange="window.location.href='orders.php?status=' + encodeURIComponent(this.value) + '&date=<?php echo urlencode($date_filter); ?>'">
            <option value="">All Statuses</option>
            <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="Processing" <?php echo $status_filter === 'Processing' ? 'selected' : ''; ?>>Processing</option>
            <option value="Completed" <?php echo $status_filter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
            <option value="Cancelled" <?php echo $status_filter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
          </select>

          <select class="select" onchange="window.location.href='orders.php?status=<?php echo htmlspecialchars($status_filter); ?>&date=' + this.value">
            <option value="">All Time</option>
            <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
            <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
            <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
          </select>
        </div>
      </div>

      <?php if ($message): ?>
        <div class="alert-success"><?php echo htmlspecialchars($message); ?></div>
      <?php endif; ?>
      <?php foreach ($errors as $err): ?>
        <div class="alert-danger"><?php echo htmlspecialchars($err); ?></div>
      <?php endforeach; ?>

      <!-- Orders List -->
      <?php if (!empty($orders)): ?>
        <div class="orders-grid">
        <?php foreach ($orders as $order): ?>
          <?php $items = get_farmer_order_items($conn, $order['order_id'], $farmer_id); ?>
          
          <div class="card order-card" style="margin-bottom:20px;border-left:4px solid 
            <?php 
            switch($order['status']) {
              case 'Pending': echo '#f59e0b'; break;
              case 'Processing': echo '#3b82f6'; break;
              case 'Completed': echo '#10b981'; break;
              default: echo '#ef4444';
            }
            ?>">
            
            <!-- Order Header -->
            <div class="order-header">
              <div>
                <h3 class="order-id">Order #<?php echo htmlspecialchars($order['order_id']); ?></h3>
                <div class="small-muted">
                  <span class="material-icons" aria-hidden="true">event</span> <?php echo date('F j, Y g:i A', strtotime($order['order_date'])); ?>
                </div>
                <div class="small-muted" style="margin-top:4px">
                  <span class="material-icons" aria-hidden="true">person</span> <?php echo htmlspecialchars($order['buyer_name']); ?> 
                  • <span class="material-icons" aria-hidden="true">phone</span> <?php echo htmlspecialchars($order['buyer_contact']); ?>
                </div>
              </div>
              
              <?php 
                $status_cls = 'status-cancelled';
                switch($order['status']) {
                  case 'Pending': $status_cls = 'status-pending'; break;
                  case 'Processing': $status_cls = 'status-processing'; break;
                  case 'Completed': $status_cls = 'status-completed'; break;
                }
              ?>
              <span class="order-status <?php echo $status_cls; ?>">
                <?php echo htmlspecialchars($order['status']); ?>
              </span>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:20px">
              <!-- Your Products in This Order -->
              <div>
                <div style="font-weight:700;margin-bottom:12px;color:#374151"><span class="material-icons" aria-hidden="true">inventory_2</span> Your Products in This Order:</div>
                <?php 
                $farmer_total = 0;
                foreach ($items as $item): 
                  $item_total = $item['quantity'] * $item['price'];
                  $farmer_total += $item_total;
                ?>
                  <div style="display:flex;gap:12px;padding:12px;background:#f9fafb;border-radius:8px;margin-bottom:8px">
                    <?php if ($item['image'] && file_exists(__DIR__ . '/../uploads/' . $item['image'])): ?>
                      <img src="../uploads/<?php echo htmlspecialchars($item['image']); ?>" style="width:60px;height:60px;object-fit:cover;border-radius:6px">
                    <?php else: ?>
                      <div style="width:60px;height:60px;background:#e5e7eb;border-radius:6px"></div>
                    <?php endif; ?>
                    
                    <div style="flex:1">
                      <div style="font-weight:700"><?php echo htmlspecialchars($item['product_name']); ?></div>
                      <div class="small-muted">
                        Qty: <?php echo $item['quantity']; ?> <?php echo htmlspecialchars($item['unit']); ?> 
                        @ Rs<?php echo number_format($item['price'], 2); ?>
                      </div>
                      <div style="font-weight:700;color:#16a34a;margin-top:4px">
                        Rs<?php echo number_format($item_total, 2); ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
                
                <div style="padding-top:12px;border-top:2px solid #e5e7eb;margin-top:12px">
                  <div style="font-weight:700;font-size:1.1rem;color:#16a34a">
                    Your Revenue: Rs<?php echo number_format($farmer_total, 2); ?>
                  </div>
                </div>
              </div>

              <!-- Delivery & Payment Info -->
              <div>
                <div style="font-weight:700;margin-bottom:12px;color:#374151"><span class="material-icons" aria-hidden="true">local_shipping</span> Delivery Information:</div>
                <div style="padding:12px;background:#f9fafb;border-radius:8px;margin-bottom:12px">
                  <div class="small" style="margin-bottom:6px">
                    <strong>Address:</strong><br>
                    <?php echo nl2br(htmlspecialchars($order['buyer_address'] ?? 'Not provided')); ?>
                  </div>
                  <div class="small">
                    <strong>Phone:</strong> <?php echo htmlspecialchars($order['buyer_phone'] ?? 'Not provided'); ?>
                  </div>
                </div>

                <div style="font-weight:700;margin-bottom:12px;color:#374151"><span class="material-icons" aria-hidden="true">payment</span> Payment:</div>
                <div style="padding:12px;background:#f9fafb;border-radius:8px">
                  <div class="small">
                    <strong>Method:</strong> 
                    <?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?>
                  </div>
                  <div class="small" style="margin-top:6px">
                    <strong>Order Total:</strong> Rs<?php echo number_format($order['total_amount'], 2); ?>
                  </div>
                </div>
              </div>
            </div>

            <!-- Update Status -->
            <form method="POST" style="display:flex;gap:8px;align-items:center;padding-top:16px;border-top:2px solid #e5e7eb">
              <input type="hidden" name="action" value="update_status">
              <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
              
              <label style="font-weight:700;margin-right:8px">Update Status:</label>
              <select name="status" class="select" style="flex:1;max-width:200px" required>
                <option value="Pending" <?php echo $order['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="Processing" <?php echo $order['status'] === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                <option value="Completed" <?php echo $order['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                <option value="Cancelled" <?php echo $order['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
              </select>
              
              <button type="submit" class="btn btn-primary">Update</button>
            </form>

          </div>
        <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div style="text-align:center;padding:60px 20px">
          <div style="font-size:3.5rem;margin-bottom:14px"><span class="material-icons" style="font-size:3.5rem;">inventory_2</span></div>
          <div style="font-size:1.2rem;font-weight:700;margin-bottom:8px;color:#111">No orders found</div>
          <div class="small-muted">Orders containing your products will appear here</div>
        </div>
      <?php endif; ?>
    </div>
  </div>
  </div>
</div>
</div>

<script src="../assets/js/script.js"></script>
</body>
</html>