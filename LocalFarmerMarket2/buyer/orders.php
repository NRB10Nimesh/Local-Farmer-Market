<?php
session_start();

// Check if logged in as buyer
if (!isset($_SESSION['buyer_id'])) {
    header("Location: ../login_buyer.php");
    exit();
}

require_once '../db.php';

$buyer_id = intval($_SESSION['buyer_id']);
$page_title = 'My Orders - Buyer Dashboard';
$message = '';
$errors = [];

// Fetch buyer profile
$stmt = $conn->prepare("SELECT * FROM buyer WHERE buyer_id = ?");
$stmt->bind_param("i", $buyer_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Filter parameters with validation
$valid_statuses = ['Pending', 'Confirmed', 'Preparing', 'Ready', 'Shipped', 'Delivered', 'Cancelled'];
$status_filter = !empty($_GET['status']) ? trim($_GET['status']) : '';
$date_filter = !empty($_GET['date']) ? trim($_GET['date']) : '';

// Validate status filter
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

// Build the orders query
$sql = "SELECT o.*, 
        (SELECT COUNT(*) FROM order_details WHERE order_id = o.order_id) as item_count
        FROM orders o 
        WHERE o.buyer_id = ?";
$params = [$buyer_id];
$types = "i";

// Add status filter if provided
if (!empty($status_filter)) {
    $sql .= " AND o.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Add date filter
if ($date_filter === 'today') {
    $sql .= " AND DATE(o.order_date) = CURDATE()";
} elseif ($date_filter === 'week') {
    $sql .= " AND o.order_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($date_filter === 'month') {
    $sql .= " AND o.order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

$sql .= " ORDER BY o.order_date DESC";

// Execute the query
$orders = [];
try {
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    // Bind parameters if there are any
    if (!empty($params)) {
        $bind_names = [$types];
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

// Function to get order items
function get_order_items($conn, $order_id) {
    $stmt = $conn->prepare("SELECT od.*, p.product_name, p.image 
                           FROM order_details od 
                           JOIN products p ON od.product_id = p.product_id 
                           WHERE od.order_id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .order-card {
            margin-bottom: 20px;
            background: var(--bg-primary);
            border-radius: 12px;
            border: 1px solid rgba(15,23,42,0.04);
            box-shadow: 0 8px 22px rgba(15,23,42,0.06);
            overflow: visible;
            display: flex;
            flex-direction: column;
            /* Fill the grid row height so all cards in a row match */
            height: 100%;
            min-height: 160px;
        }
        .order-header {
            padding: 14px 16px;
            background: #ffffff;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 8px;
        }
        .order-id {
            margin: 0 0 6px 0;
            font-size: 1.05rem;
            font-weight: 700;
        }
        .order-header .small-muted { margin-top: 2px; color: #6b7280; }
        .order-status {
            padding: 6px 12px;
            border-radius: 14px;
            font-size: 0.85rem;
            font-weight: 700;
            align-self: flex-start;
        }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-confirmed { background: #dbeafe; color: #1e40af; }
        .status-preparing { background: #ede9fe; color: #5b21b6; }
        .status-ready { background: #cffafe; color: #0e7490; }
        .status-shipped { background: #e0e7ff; color: #3730a3; }
        .status-delivered { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }

        .order-items {
            padding: 12px 16px;
            flex: 1;
            overflow-y: auto;
            max-height: 220px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            background: transparent;
        }
        .order-items-title {
            font-weight: 700;
            margin-bottom: 6px;
            color: #374151;
        }
        .order-item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f3f4f6;
            gap: 12px;
            font-size: 0.95rem;
            line-height: 1.25;
        }
        .order-item-row:last-child {
            border-bottom: none;
        }
        .order-item-left { display:flex; align-items:center; gap:10px; min-width:0; }
        .order-item-thumb { width:42px; height:42px; border-radius:8px; overflow:hidden; background:#f3f4f6; display:flex;align-items:center;justify-content:center;flex-shrink:0 }
        .order-item-thumb img { width:100%; height:100%; object-fit:cover; display:block }
        .order-item-name { font-weight:600; color:#111827; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; text-overflow:ellipsis; max-width:260px }
        .order-item-right { color:#6b7280; font-size:0.92rem; text-align:right; white-space:nowrap }
        .order-item-row .small-muted { color:#6b7280; font-size:0.85rem; }
        .more-items-link { color:var(--primary); font-weight:700; text-decoration:none; }
        .more-items-link:hover { text-decoration:underline; }
        
        .order-total {
            padding: 12px;
            background: #ffffff;
            border-top: 1px solid #f3f4f6;
            font-weight: 700;
            text-align: right;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        .view-order-btn {
            margin-right: auto;
        }
        /* Ensure mobile-friendly behavior */
        @media (max-width: 768px) {
            .order-card { height: auto; }
            .order-items { overflow: visible; }
        }
        .empty-orders {
            text-align: center;
            padding: 60px 20px;
        }
        .empty-icon {
            font-size: 3.5rem;
            margin-bottom: 15px;
            opacity: 0.7;
        }
        .empty-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: #111827;
        }
        .filters {
            margin-bottom: 25px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .filters select {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            background: white;
            font-size: 0.95rem;
        }
        /* Header above filters: title centered, clear button right-aligned */
        .filters .filters-header { display:flex; align-items:center; justify-content:center; position:relative; margin-bottom:12px; }
        .filters .filters-title { margin:0; font-size:1.5rem; font-weight:600; text-align:center; }
        .filters .btn-clear { position:absolute; right:0; top:0; }
        @media (max-width: 768px) {
            .filters .btn-clear { position:static; margin-left:auto; margin-top:8px; }
        }
        /* Orders grid to match product card sizing */
        .orders-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 28px;
            /* Stretch items so every card in a row has equal height */
            align-items: stretch;
            grid-auto-rows: 1fr;
        }
        @media (max-width: 1024px) {
            .orders-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .orders-grid { grid-template-columns: 1fr; }
            /* On mobile allow cards to size naturally */
            .order-card { height: auto; }
        }
        .btn-clear {
            background: #f3f4f6;
            color: #4b5563;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.95rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-clear:hover {
            background: #e5e7eb;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <div class="content">
            <!-- Filters -->
            <div class="filters">
                <div class="filters-header">
                    <h2 class="filters-title">My Orders</h2>
                    <?php if ($status_filter || $date_filter): ?>
                        <a href="orders.php" class="btn-clear"><span>Clear Filters</span></a>
                    <?php endif; ?>
                </div>

                <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                    <select class="select" onchange="window.location.href='orders.php?status=' + encodeURIComponent(this.value) + '&date=<?php echo urlencode($date_filter); ?>'">
                        <option value="">All Statuses</option>
                        <?php foreach ($valid_statuses as $status): ?>
                            <option value="<?php echo htmlspecialchars($status); ?>" 
                                    <?php echo $status_filter === $status ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($status); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select class="select" onchange="window.location.href='orders.php?status=<?php echo urlencode($status_filter); ?>&date=' + encodeURIComponent(this.value)">
                        <option value="">All Time</option>
                        <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                    </select>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endforeach; ?>

            <?php if (!empty($orders)): ?>
                <div class="orders-grid">
                <?php foreach ($orders as $order): ?>
                    <?php $items = get_order_items($conn, $order['order_id']); ?>
                    
                    <div class="card order-card">
                        <!-- Order Header -->
                        <div class="order-header">
                            <div>
                                <h3 class="order-id">Order #<?php echo htmlspecialchars($order['order_id']); ?></h3>
                                <div class="small-muted">
                                    <?php echo date('F j, Y g:i A', strtotime($order['order_date'])); ?>
                                </div>
                            </div>

                            <span class="order-status status-<?php echo strtolower($order['status']); ?>">
                                <?php echo htmlspecialchars($order['status']); ?>
                            </span>
                        </div>

                        <!-- Order Items -->
                        <div class="order-items">
                            <div class="order-items-title">Order Items (<?php echo $order['item_count']; ?>):</div>

                            <?php
                            // show the first 3 items in the compact card (more visible content)
                            $display_items = array_slice($items, 0, 3);
                            foreach ($display_items as $item): ?>
                                <div class="order-item-row">
                                    <div class="order-item-left">
                                        <?php if (!empty($item['image']) && file_exists(__DIR__ . '/../uploads/' . $item['image'])): ?>
                                            <div class="order-item-thumb"><img src="../uploads/<?php echo htmlspecialchars($item['image']); ?>" alt=""></div>
                                        <?php else: ?>
                                            <div class="order-item-thumb">📦</div>
                                        <?php endif; ?>
                                        <div class="order-item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                    </div>
                                    <div class="order-item-right">
                                        Rs<?php echo number_format($item['price'], 2); ?> × <?php echo $item['quantity']; ?> = Rs<?php echo number_format($item['quantity'] * $item['price'], 2); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php if (count($items) > 3): ?>
                                <div class="order-item-row">
                                    <a href="#" class="more-items-link view-order-btn" data-order-id="<?php echo $order['order_id']; ?>">+ <?php echo count($items) - 3; ?> more item(s) — View</a>
                                    <span></span>
                                </div>
                            <?php endif; ?>

                            <!-- Hidden full items markup used by the details modal -->
                            <div class="order-full-items" style="display:none;">
                                <?php foreach ($items as $item): ?>
                                    <div class="order-item-row">
                                        <span><?php echo htmlspecialchars($item['product_name']); ?></span>
                                        <span class="small-muted">Rs<?php echo number_format($item['price'], 2); ?> × <?php echo $item['quantity']; ?> = Rs<?php echo number_format($item['quantity'] * $item['price'], 2); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div> 
                        </div>

                        <!-- Total -->
                        <div class="order-total">
                            <div>
                              <a href="#" class="btn primary small-btn view-order-btn" data-order-id="<?php echo $order['order_id']; ?>">View Details</a>
                            </div>
                            <div class="order-amount">Total: <span style="font-weight:800;color:var(--accent)">Rs<?php echo number_format($order['total_amount'], 2); ?></span></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-orders">
                    <div class="empty-icon">📦</div>
                    <div class="empty-title">No orders found</div>
                    <p class="small-muted">When you place orders, they will appear here.</p>
                    <a href="../products.php" class="btn" style="margin-top: 15px;">Continue Shopping</a>
                </div>
            <?php endif; ?>
        </div>
    </div>


<!-- Order Details Modal -->
<div id="orderModal" class="modal">
  <div class="modal-body">
    <div id="orderModalBody"></div>
    <div style="margin-top:12px;text-align:right">
      <button type="button" class="btn btn-ghost" onclick="closeModal('orderModal')">Close</button>
    </div>
  </div>
</div>
<script src="../assets/js/script.js"></script>
</body>
</html>