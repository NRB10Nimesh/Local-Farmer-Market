<?php
session_start();

if (!isset($_SESSION['buyer_id'])) {
    header("Location: ../login_buyer.php");
    exit();
}

require_once '../db.php';

$buyer_id = intval($_SESSION['buyer_id']);
$order_id = intval($_GET['order_id'] ?? 0);
$page_title = 'Order Successful';

// Fetch order details
$stmt = $conn->prepare("SELECT o.*, 
                        (SELECT COUNT(*) FROM order_details WHERE order_id = o.order_id) as item_count
                        FROM orders o 
                        WHERE o.order_id = ? AND o.buyer_id = ?");
$stmt->bind_param("ii", $order_id, $buyer_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    header("Location: dashboard.php");
    exit();
}

// Fetch order items
$stmt = $conn->prepare("SELECT od.*, p.product_name, p.unit, f.name as farmer_name
                        FROM order_details od
                        JOIN products p ON od.product_id = p.product_id
                        JOIN Farmer f ON p.farmer_id = f.farmer_id
                        WHERE od.order_id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch buyer profile
$stmt = $conn->prepare("SELECT * FROM Buyer WHERE buyer_id = ?");
$stmt->bind_param("i", $buyer_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

include 'includes/header.php';
?>

<div class="main-wrapper">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main-content">
  <div class="wrap">

    <div style="max-width:800px;margin:0 auto">
        <!-- Success Message -->
        <div class="card" style="text-align:center;padding:40px 20px;margin-bottom:24px">
            <div style="font-size:5rem;margin-bottom:20px"><span class="material-icons" style="font-size:5rem;color:var(--success)">check_circle</span></div>
            <h1 style="margin:0 0 12px 0;color:var(--success)">Order Placed Successfully!</h1>
            <p style="font-size:1.1rem;color:var(--muted);margin-bottom:24px">
                Thank you for your order. We've received it and will start processing soon.
            </p>
            
            <div style="display:inline-block;background:#f0fdf4;padding:16px 32px;border-radius:12px;margin-bottom:24px">
                <div class="small-muted" style="margin-bottom:4px">Order Number</div>
                <div style="font-size:2rem;font-weight:700;color:var(--accent)">
                    #<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?>
                </div>
            </div>

            <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
                <a href="orders.php" class="btn btn-primary">View Order Details</a>
                <a href="dashboard.php" class="btn btn-ghost">Continue Shopping</a>
            </div>
        </div>

        <!-- Order Summary -->
        <div class="card">
            <h3 style="margin-top:0" img src="../icons/orderbox.png" alt="orderbox" style="height:40px; width: 40px; padding: 5px; ">Order Summary</h3>
            
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px;padding:20px;background:#f9fafb;border-radius:10px">
                <div>
                    <div class="small-muted">Order Date</div>
                    <div style="font-weight:600">
                        <?php echo date('F j, Y g:i A', strtotime($order['order_date'])); ?>
                    </div>
                </div>
                <div>
                    <div class="small-muted">Payment Method</div>
                    <div style="font-weight:600">
                        <?php 
                        $payment_methods = [
                            'cash_on_delivery' => ' Cash on Delivery',
                            'esewa' => '<span class="material-icons" aria-hidden="true">smartphone</span> eSewa',
                            'khalti' => '<span class="material-icons" aria-hidden="true">account_balance_wallet</span> Khalti',
                            'bank_transfer' => '<span class="material-icons" aria-hidden="true">account_balance</span> Bank Transfer'
                        ];
                        echo $payment_methods[$order['payment_method']] ?? $order['payment_method'];
                        ?>
                    </div>
                </div>
                <div>
                    <div class="small-muted">Items</div>
                    <div style="font-weight:600"><?php echo $order['item_count']; ?> products</div>
                </div>
                <div>
                    <div class="small-muted">Total Amount</div>
                    <div style="font-weight:700;color:var(--accent);font-size:1.2rem">
                        Rs<?php echo number_format($order['total_amount'], 2); ?>
                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <h4 style="margin:24px 0 12px 0">Ordered Items:</h4>
            <div>
                <?php foreach ($order_items as $item): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:14px;background:#f9fafb;border-radius:10px;margin-bottom:10px">
                        <div style="flex:1">
                            <div style="font-weight:600;margin-bottom:4px">
                                <?php echo htmlspecialchars($item['product_name']); ?>
                            </div>
                            <div class="small-muted">
                                From: <?php echo htmlspecialchars($item['farmer_name']); ?>
                            </div>
                            <div class="small-muted">
                                Rs<?php echo number_format($item['price'], 2); ?> × <?php echo $item['quantity']; ?> 
                                <?php echo htmlspecialchars($item['unit']); ?>
                            </div>
                        </div>
                        <div style="font-weight:700;font-size:1.1rem">
                            Rs<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Payment Status -->
            <?php if ($order['payment_method'] !== 'cash_on_delivery'): ?>
                <div class="alert alert-info" style="margin-top:20px">
                    <div style="font-weight:600;margin-bottom:4px">⏳ Payment Pending</div>
                    <div>Please complete your payment to confirm the order.</div>
                    <?php if ($order['payment_method'] === 'bank_transfer'): ?>
                        <div style="margin-top:12px;padding:12px;background:white;border-radius:8px">
                            <div style="font-weight:600;margin-bottom:8px">Bank Transfer Details:</div>
                            <div class="small">Bank: Nepal Bank Limited</div>
                            <div class="small">Account Name: Local Farmer Market</div>
                            <div class="small">Account Number: 1234567890</div>
                            <div class="small">Reference: Order #<?php echo $order['order_id']; ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- What's Next -->
        <div class="card" style="margin-top:24px">
            <h3 style="margin-top:0"><span class="material-icons" aria-hidden="true">assignment</span> What Happens Next?</h3>
            <div style="display:grid;gap:16px">
                <div style="display:flex;gap:16px;align-items:start">
                    <div style="flex-shrink:0;width:40px;height:40px;border-radius:50%;background:#dbeafe;display:flex;align-items:center;justify-content:center;font-size:1.5rem">
                        1️⃣
                    </div>
                    <div>
                        <div style="font-weight:600;margin-bottom:4px">Order Confirmation</div>
                        <div class="small-muted">The farmer will review and confirm your order</div>
                    </div>
                </div>
                <div style="display:flex;gap:16px;align-items:start">
                    <div style="flex-shrink:0;width:40px;height:40px;border-radius:50%;background:#dbeafe;display:flex;align-items:center;justify-content:center;font-size:1.5rem">
                        2️⃣
                    </div>
                    <div>
                        <div style="font-weight:600;margin-bottom:4px">Order Preparation</div>
                        <div class="small-muted">Your fresh products will be carefully prepared</div>
                    </div>
                </div>
                <div style="display:flex;gap:16px;align-items:start">
                    <div style="flex-shrink:0;width:40px;height:40px;border-radius:50%;background:#dbeafe;display:flex;align-items:center;justify-content:center;font-size:1.5rem">
                        3️⃣
                    </div>
                    <div>
                        <div style="font-weight:600;margin-bottom:4px">Delivery</div>
                        <div class="small-muted">Your order will be delivered to your address</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Help Section -->
        <div class="card" style="margin-top:24px;background:#f0fdf4;border-left:4px solid var(--success)">
            <div style="display:flex;align-items:start;gap:16px">
                <div style="font-size:2rem"><span class="material-icons" aria-hidden="true">chat</span></div>
                <div>
                    <div style="font-weight:600;margin-bottom:8px">Need Help?</div>
                    <div class="small-muted">
                        If you have any questions about your order, you can view order details and 
                        contact the farmer directly from your orders page.
                    </div>
                    <a href="orders.php" class="btn btn-success small-btn" style="margin-top:12px">
                        Go to My Orders
                    </a>
                </div>
            </div>
        </div>
    </div>
  </div>
  </div>
</div>
</div>

<script src="../assets/js/script.js"></script>
</body>
</html>