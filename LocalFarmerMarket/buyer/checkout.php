<?php
session_start();

if (!isset($_SESSION['buyer_id'])) {
    header("Location: ../login_buyer.php");
    exit();
}

require_once '../db.php';

$buyer_id = intval($_SESSION['buyer_id']);
$message = '';
$errors = [];
$page_title = 'Checkout - Complete Your Order';

// Fetch buyer profile
$stmt = $conn->prepare("SELECT * FROM Buyer WHERE buyer_id = ?");
$stmt->bind_param("i", $buyer_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch cart items
$cart_q = $conn->prepare("SELECT c.cart_id, c.product_id, c.quantity, 
                          p.product_name, p.price, p.image, p.unit, p.quantity AS stock,
                          f.name AS farmer_name, f.contact AS farmer_contact
                          FROM cart c 
                          JOIN products p ON c.product_id = p.product_id 
                          JOIN Farmer f ON p.farmer_id = f.farmer_id
                          WHERE c.buyer_id = ?");
$cart_q->bind_param("i", $buyer_id);
$cart_q->execute();
$cart_items = $cart_q->get_result()->fetch_all(MYSQLI_ASSOC);
$cart_q->close();

// If cart is empty, redirect
if (empty($cart_items)) {
    header("Location: dashboard.php");
    exit();
}

$cart_total = 0;
$cart_count = 0;
foreach ($cart_items as $ci) {
    $cart_total += $ci['price'] * $ci['quantity'];
    $cart_count += $ci['quantity'];
}

// Calculate delivery fee
$delivery_fee = $cart_total > 500 ? 0 : 50;
$final_total = $cart_total + $delivery_fee;

// Process order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'place_order') {
    $payment_method = $_POST['payment_method'] ?? 'cash_on_delivery';
    $delivery_address = trim($_POST['delivery_address'] ?? $profile['address']);
    $delivery_notes = trim($_POST['delivery_notes'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? $profile['contact']);
    
    // Validate stock availability
    $stock_errors = [];
    foreach ($cart_items as $item) {
        if ($item['quantity'] > $item['stock']) {
            $stock_errors[] = $item['product_name'] . " - Only " . $item['stock'] . " available";
        }
    }
    
    if (!empty($stock_errors)) {
        $errors = $stock_errors;
    } else {
        $conn->begin_transaction();
        try {
            // Create order
            $stmt = $conn->prepare("INSERT INTO orders (buyer_id, total_amount, order_date, status, payment_method, payment_status, delivery_notes) VALUES (?, ?, NOW(), 'Pending', ?, 'pending', ?)");
            $stmt->bind_param("idss", $buyer_id, $final_total, $payment_method, $delivery_notes);
            $stmt->execute();
            $order_id = $conn->insert_id;
            $stmt->close();
            
            // Insert order details
            foreach ($cart_items as $item) {
                $stmt = $conn->prepare("INSERT INTO order_details (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
                $stmt->execute();
                $stmt->close();
                
                // Update product stock
                $stmt = $conn->prepare("UPDATE products SET quantity = quantity - ? WHERE product_id = ?");
                $stmt->bind_param("ii", $item['quantity'], $item['product_id']);
                $stmt->execute();
                $stmt->close();
            }
            
            // Clear cart
            $stmt = $conn->prepare("DELETE FROM cart WHERE buyer_id = ?");
            $stmt->bind_param("i", $buyer_id);
            $stmt->execute();
            $stmt->close();
            
            // Create notification for farmers
            foreach ($cart_items as $item) {
                $farmer_id_stmt = $conn->prepare("SELECT farmer_id FROM products WHERE product_id = ?");
                $farmer_id_stmt->bind_param("i", $item['product_id']);
                $farmer_id_stmt->execute();
                $farmer_id = $farmer_id_stmt->get_result()->fetch_assoc()['farmer_id'];
                $farmer_id_stmt->close();
                
                $notif_msg = "New order #$order_id received for " . $item['product_name'];
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_type, user_id, title, message) VALUES ('farmer', ?, 'New Order', ?)");
                $notif_stmt->bind_param("is", $farmer_id, $notif_msg);
                $notif_stmt->execute();
                $notif_stmt->close();
            }
            
            $conn->commit();
            
            // Redirect to success page
            header("Location: order_success.php?order_id=$order_id");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Order failed. Please try again.";
        }
    }
}

include 'includes/header.php';
?>

<div class="wrap">
    <div class="checkout-steps">
        <div class="checkout-step completed">
            <div class="step-circle">✓</div>
            <div class="step-label">Cart</div>
        </div>
        <div class="checkout-step active">
            <div class="step-circle">2</div>
            <div class="step-label">Checkout</div>
        </div>
        <div class="checkout-step">
            <div class="step-circle">3</div>
            <div class="step-label">Confirmation</div>
        </div>
    </div>

    <div class="grid grid-buyer">
        <main>
            <div class="card">
                <h2>Complete Your Order</h2>
                
                <?php if (!empty($errors)): ?>
                    <?php foreach ($errors as $err): ?>
                        <div class="alert alert-danger">⚠️ <?php echo htmlspecialchars($err); ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="action" value="place_order">
                    
                    <!-- Delivery Information -->
                    <div class="form-group">
                        <h3>📍 Delivery Information</h3>
                        
                        <label>Contact Number *</label>
                        <input type="text" name="contact_number" value="<?php echo htmlspecialchars($profile['contact']); ?>" required>
                        
                        <label class="mt-12">Delivery Address *</label>
                        <textarea name="delivery_address" rows="3" required><?php echo htmlspecialchars($profile['address']); ?></textarea>
                        
                        <label class="mt-12">Delivery Notes (Optional)</label>
                        <textarea name="delivery_notes" rows="2" placeholder="e.g., Call before delivery, Gate code, etc."></textarea>
                    </div>

                    <!-- Payment Method -->
                    <div class="form-group mt-32">
                        <h3>💳 Payment Method</h3>
                        
                        <div class="payment-methods">
                            <label class="payment-method selected">
                                <input type="radio" name="payment_method" value="cash_on_delivery" checked>
                                <div class="payment-content">
                                    <div class="payment-icon">💵</div>
                                    <div class="payment-name">Cash on Delivery</div>
                                    <div class="payment-desc">Pay when you receive</div>
                                </div>
                            </label>
                            
                            <label class="payment-method">
                                <input type="radio" name="payment_method" value="esewa">
                                <div class="payment-content">
                                    <div class="payment-icon">📱</div>
                                    <div class="payment-name">eSewa</div>
                                    <div class="payment-desc">Digital wallet</div>
                                </div>
                            </label>
                            
                            <label class="payment-method">
                                <input type="radio" name="payment_method" value="khalti">
                                <div class="payment-content">
                                    <div class="payment-icon">💰</div>
                                    <div class="payment-name">Khalti</div>
                                    <div class="payment-desc">Digital payment</div>
                                </div>
                            </label>
                            
                            <label class="payment-method">
                                <input type="radio" name="payment_method" value="bank_transfer">
                                <div class="payment-content">
                                    <div class="payment-icon">🏦</div>
                                    <div class="payment-name">Bank Transfer</div>
                                    <div class="payment-desc">Direct bank payment</div>
                                </div>
                            </label>
                        </div>
                        
                        <div id="payment-instructions" class="payment-instructions">
                            <!-- Payment instructions will be shown here via JS -->
                        </div>
                    </div>

                    <div class="form-actions mt-32">
                        <a href="dashboard.php" class="btn btn-ghost">← Back to Shopping</a>
                        <button type="submit" class="btn btn-primary" style="padding: 14px 40px;">
                            Place Order - Rs<?php echo number_format($final_total, 2); ?>
                        </button>
                    </div>
                </form>
            </div>
        </main>

        <aside>
            <!-- Order Summary -->
            <div class="card">
                <h3>📦 Order Summary</h3>
                
                <div class="mb-16">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="order-item-box">
                            <div class="order-item-info">
                                <div class="order-item-title"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                <div class="small-muted">
                                    Rs<?php echo number_format($item['price'], 2); ?> × <?php echo $item['quantity']; ?>
                                </div>
                            </div>
                            <div class="order-item-total">
                                Rs<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="cart-summary">
                    <div class="cart-subtotal-row mb-8">
                        <span class="small-muted">Subtotal (<?php echo $cart_count; ?> items)</span>
                        <span><strong>Rs<?php echo number_format($cart_total, 2); ?></strong></span>
                    </div>
                    <div class="cart-delivery-row mb-12">
                        <span class="small-muted">Delivery Fee</span>
                        <span class="<?php echo $delivery_fee === 0 ? 'stat-value success' : ''; ?>">
                            <strong><?php echo $delivery_fee === 0 ? 'FREE' : 'Rs' . number_format($delivery_fee, 2); ?></strong>
                        </span>
                    </div>
                    <?php if ($delivery_fee > 0): ?>
                        <div class="alert alert-info mb-12" style="padding: 10px; font-size: 0.85rem;">
                            💡 Free delivery on orders above Rs500
                        </div>
                    <?php endif; ?>
                    <div style="display: flex; justify-content: space-between; padding-top: 12px; border-top: 2px solid var(--border);">
                        <span style="font-size: 1.1rem; font-weight: 700;">Total</span>
                        <span class="order-total">Rs<?php echo number_format($final_total, 2); ?></span>
                    </div>
                </div>
            </div>

            <!-- Security Badge -->
            <div class="card mt-16 text-center">
                <div style="font-size: 2rem; margin-bottom: 8px;">🔒</div>
                <div style="font-weight: 600; margin-bottom: 4px;">Secure Checkout</div>
                <div class="small-muted">Your information is protected</div>
            </div>
        </aside>
    </div>
</div>

<script src="../assets/js/script.js"></script>
<script src="../assets/js/checkout.js"></script>
</body>
</html>