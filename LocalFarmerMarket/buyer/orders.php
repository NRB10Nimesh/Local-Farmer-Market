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
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
        }
        .order-header {
            padding: 15px;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .order-id {
            margin: 0;
            font-size: 1.1rem;
        }
        .order-status {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-confirmed { background: #dbeafe; color: #1e40af; }
        .status-preparing { background: #ede9fe; color: #5b21b6; }
        .status-ready { background: #cffafe; color: #0e7490; }
        .status-shipped { background: #e0e7ff; color: #3730a3; }
        .status-delivered { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        .order-items {
            padding: 15px;
        }
        .order-items-title {
            font-weight: 600;
            margin-bottom: 10px;
            color: #374151;
        }
        .order-item-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .order-item-row:last-child {
            border-bottom: none;
        }
        .order-total {
            padding: 15px;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
            font-weight: 600;
            text-align: right;
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
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h2 style="margin: 0; font-size: 1.5rem;">My Orders</h2>
                    <?php if ($status_filter || $date_filter): ?>
                        <a href="orders.php" class="btn-clear">
                            <span>Clear Filters</span>
                        </a>
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

                            <?php foreach ($items as $item): ?>
                                <div class="order-item-row">
                                    <span>
                                        <?php echo htmlspecialchars($item['product_name']); ?>
                                    </span>
                                    <span class="small-muted">
                                        Rs<?php echo number_format($item['price'], 2); ?>
                                        × <?php echo $item['quantity']; ?>
                                        = Rs<?php echo number_format($item['quantity'] * $item['price'], 2); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Total -->
                        <div class="order-total">
                            Total: Rs<?php echo number_format($order['total_amount'], 2); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
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

    <?php include 'includes/footer.php'; ?>
</body>
</html>