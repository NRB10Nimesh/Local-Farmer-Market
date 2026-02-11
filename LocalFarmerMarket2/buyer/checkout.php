<?php
// buyer/checkout.php - FIXED: Removed duplicate revenue insertion
session_start();

if (!isset($_SESSION['buyer_id'])) {
    header("Location: ../login_buyer.php");
    exit();
}

require_once '../db.php';

$buyer_id = intval($_SESSION['buyer_id']);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $delivery_address = trim($_POST['delivery_address']);
    $payment_method = $_POST['payment_method'];
    
    if (empty($delivery_address)) {
        $errors[] = "Delivery address is required";
    }
    
    if (!in_array($payment_method, ['cash_on_delivery', 'esewa', 'khalti', 'bank_transfer'])) {
        $errors[] = "Invalid payment method";
    }
    
    // Get cart items with commission info
    $cart_query = $conn->prepare("SELECT c.*, p.product_name, p.price as farmer_price, 
                                  p.admin_price, p.unit, p.quantity as available_stock,
                                  p.commission_rate, p.category,
                                  (p.admin_price - p.price) as commission_per_unit,
                                  f.farmer_id, f.name as farmer_name
                                  FROM cart c
                                  JOIN products p ON c.product_id = p.product_id
                                  JOIN farmer f ON p.farmer_id = f.farmer_id
                                  WHERE c.buyer_id = ? AND p.approval_status = 'approved'");
    $cart_query->bind_param("i", $buyer_id);
    $cart_query->execute();
    $cart_items = $cart_query->get_result()->fetch_all(MYSQLI_ASSOC);
    $cart_query->close();
    
    if (empty($cart_items)) {
        $errors[] = "Your cart is empty";
    }
    
    // Validate stock availability
    foreach ($cart_items as $item) {
        if ($item['quantity'] > $item['available_stock']) {
            $errors[] = "Insufficient stock for " . $item['product_name'];
        }
    }
    
    if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Calculate total, commission and farmer payout using commission_rate
            $total_amount = 0;
            $total_commission = 0;
            $total_farmer_amount = 0;
            
            foreach ($cart_items as $item) {
                $item_total = $item['admin_price'] * $item['quantity'];
                $commission_rate = isset($item['commission_rate']) ? $item['commission_rate'] : 0.0;
                $item_commission = ($commission_rate / 100.0) * ($item['admin_price'] * $item['quantity']);
                $item_farmer_amount = ($item['admin_price'] * $item['quantity']) - $item_commission;
                
                $total_amount += $item_total;
                $total_commission += $item_commission;
                $total_farmer_amount += $item_farmer_amount;
            }
            
            // Create order
            $order_stmt = $conn->prepare("INSERT INTO orders (buyer_id, delivery_notes, payment_method, total_amount, status) 
                                         VALUES (?, ?, ?, ?, 'Pending')");
            $order_stmt->bind_param("issd", $buyer_id, $delivery_address, $payment_method, $total_amount);
            $order_stmt->execute();
            $order_id = $conn->insert_id;
            $order_stmt->close();
            
            // Insert order details ONLY (Revenue is now handled by DB trigger on completion)
            $detail_stmt = $conn->prepare("INSERT INTO order_details (order_id, product_id, quantity, price, farmer_price, admin_price, profit_per_unit) 
                                          VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            foreach ($cart_items as $item) {
                // Insert order detail (include farmer/admin price and profit per unit)
                $profit_per_unit = ($item['admin_price'] - $item['farmer_price']);
                $detail_stmt->bind_param("iiidddd", 
                    $order_id, 
                    $item['product_id'], 
                    $item['quantity'], 
                    $item['admin_price'],
                    $item['farmer_price'],
                    $item['admin_price'],
                    $profit_per_unit
                );
                $detail_stmt->execute();
                
                // Update product stock
                $update_stock = $conn->prepare("UPDATE products 
                                               SET quantity = quantity - ? 
                                               WHERE product_id = ?");
                $update_stock->bind_param("ii", $item['quantity'], $item['product_id']);
                $update_stock->execute();
                $update_stock->close();
            }
            
            $detail_stmt->close();
            
            // Clear cart
            $clear_cart = $conn->prepare("DELETE FROM cart WHERE buyer_id = ?");
            $clear_cart->bind_param("i", $buyer_id);
            $clear_cart->execute();
            $clear_cart->close();
            
            // Commit transaction
            $conn->commit();
            
            // Redirect to success page
            $_SESSION['order_success'] = [
                'order_id' => $order_id,
                'total_amount' => $total_amount,
                'commission_earned' => $total_commission,
                'farmer_payment' => $total_farmer_amount
            ];
            
            header("Location: order_success.php?order_id=" . $order_id);
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Failed to process order. Please try again.";
            error_log("Order processing error: " . $e->getMessage());
        }
    }
}

// Display checkout form if not processing POST
if (empty($_SESSION['buyer_id'])) {
    header("Location: ../login_buyer.php");
    exit();
}

require_once '../db.php';
$buyer_id = intval($_SESSION['buyer_id']);

// Fetch buyer profile
$stmt = $conn->prepare("SELECT * FROM buyer WHERE buyer_id = ?");
$stmt->bind_param("i", $buyer_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get cart items
$cart_query = $conn->prepare("SELECT c.*, p.product_name, p.image, p.unit, 
                              COALESCE(p.admin_price, p.price) as display_price
                              FROM cart c 
                              JOIN products p ON c.product_id = p.product_id 
                              WHERE c.buyer_id = ? AND p.approval_status = 'approved'");
$cart_query->bind_param("i", $buyer_id);
$cart_query->execute();
$cart_items = $cart_query->get_result()->fetch_all(MYSQLI_ASSOC);
$cart_query->close();

$page_title = 'Checkout - Farmer Market';
$page_css = ['../assets/css/checkout.css'];
include 'includes/header.php';
?>

<main class="main-content container checkout-page">

<div class="checkout-container">
    <header class="checkout-header">
        <h1>Checkout</h1>
    </header>

    <?php if (!empty($errors)): ?>
        <div class="errors">
            <?php foreach ($errors as $error): ?>
                <p class="error-msg"><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="checkout-grid">
        <aside class="buyer-info">
            <h2>Delivery Information</h2>
            <?php if ($profile): ?>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($profile['name']); ?></p>
                <p><strong>Contact:</strong> <?php echo htmlspecialchars($profile['contact']); ?></p>
                <p><strong>Address:</strong> <?php echo htmlspecialchars($profile['address']); ?></p>
            <?php endif; ?>
        </aside>

        <section class="checkout-main">
            <form method="POST" action="">
                <div class="form-group">
                    <label for="delivery_address">Delivery Address:</label>
                    <textarea id="delivery_address" name="delivery_address" required><?php echo isset($_POST['delivery_address']) ? htmlspecialchars($_POST['delivery_address']) : (isset($profile['address']) ? htmlspecialchars($profile['address']) : ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="payment_method">Payment Method:</label>
                    <select id="payment_method" name="payment_method" required>
                        <option value="">-- Select Payment Method --</option>
                        <option value="cash_on_delivery" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] === 'cash_on_delivery') ? 'selected' : ''; ?>>Cash on Delivery</option>
                        <option value="esewa" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] === 'esewa') ? 'selected' : ''; ?>>eSewa</option>
                        <option value="khalti" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] === 'khalti') ? 'selected' : ''; ?>>Khalti</option>
                        <option value="bank_transfer" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] === 'bank_transfer') ? 'selected' : ''; ?>>Bank Transfer</option>
                    </select>
                </div>

                <div class="cart-summary">
                    <h2>Order Summary</h2>
                    <?php if (!empty($cart_items)): ?>
                        <table class="cart-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Unit</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $order_total = 0;
                                foreach ($cart_items as $item): 
                                    $subtotal = $item['display_price'] * $item['quantity'];
                                    $order_total += $subtotal;
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['unit']); ?></td>
                                        <td><?php echo intval($item['quantity']); ?></td>
                                        <td>Rs<?php echo number_format($item['display_price'], 2); ?></td>
                                        <td>Rs<?php echo number_format($subtotal, 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div class="order-total">
                            <h3>Total: Rs<?php echo number_format($order_total, 2); ?></h3>
                        </div>

                        <div class="checkout-actions">
                            <button type="submit" class="btn btn-primary">Place Order</button>
                            <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                        </div>

                    <?php else: ?>
                        <p class="info-msg">Your cart is empty. <a href="dashboard.php">Continue Shopping</a></p>
                    <?php endif; ?>
                </div>
            </form>
        </section>
    </div>
</div>
</main>
</body>
</html>
