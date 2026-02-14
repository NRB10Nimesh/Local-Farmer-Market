<?php
// Updated buyer/process_order.php - Handles stock and profit tracking
session_start();

if (!isset($_SESSION['buyer_id'])) {
    header("Location: ../login_buyer.php");
    exit();
}

require_once '../db.php';

$buyer_id = intval($_SESSION['buyer_id']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: cart.php");
    exit();
}

$delivery_address = trim($_POST['delivery_address'] ?? '');
$payment_method = trim($_POST['payment_method'] ?? 'cash_on_delivery');

if (empty($delivery_address)) {
    $_SESSION['checkout_error'] = "Please provide a delivery address.";
    header("Location: checkout.php");
    exit();
}

// Begin DB transaction to ensure order processing is atomic
$conn->begin_transaction();

try {
    // Get cart items with admin prices
    $cart_query = $conn->prepare("SELECT c.cart_id, c.product_id, c.quantity, 
                                   p.product_name, p.price as farmer_price, 
                                   COALESCE(p.admin_price, p.price) as selling_price,
                                   p.quantity as available_stock, p.unit
                                   FROM cart c 
                                   JOIN products p ON c.product_id = p.product_id 
                                   WHERE c.buyer_id = ? AND p.approval_status = 'approved'");
    $cart_query->bind_param("i", $buyer_id);
    $cart_query->execute();
    $cart_items = $cart_query->get_result()->fetch_all(MYSQLI_ASSOC);
    $cart_query->close();
    
    if (empty($cart_items)) {
        throw new Exception("Your cart is empty.");
    }
    
    // Validate stock availability
    foreach ($cart_items as $item) {
        if ($item['quantity'] > $item['available_stock']) {
            throw new Exception("Insufficient stock for {$item['product_name']}. Only {$item['available_stock']} available.");
        }
    }
    
    // Calculate total using admin prices
    $total = 0;
    foreach ($cart_items as $item) {
        $total += $item['selling_price'] * $item['quantity'];
    }
    
    // Update buyer's address
    $update_address = $conn->prepare("UPDATE buyer SET address = ? WHERE buyer_id = ?");
    $update_address->bind_param("si", $delivery_address, $buyer_id);
    $update_address->execute();
    $update_address->close();
    
    // Create order
    $order_query = $conn->prepare("INSERT INTO orders (buyer_id, total_amount, payment_method, status) VALUES (?, ?, ?, 'Pending')");
    $order_query->bind_param("ids", $buyer_id, $total, $payment_method);
    $order_query->execute();
    $order_id = $conn->insert_id;
    $order_query->close();
    
    // Insert order details and update stock
    $order_detail_query = $conn->prepare("INSERT INTO order_details (order_id, product_id, quantity, price, farmer_price, admin_price, profit_per_unit) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    $stock_update_query = $conn->prepare("UPDATE products SET quantity = quantity - ?, sold_quantity = sold_quantity + ? WHERE product_id = ?");
    
    $notification_query = $conn->prepare("INSERT INTO notifications (user_type, user_id, title, message) VALUES ('farmer', ?, 'New Order', ?)");
    
    foreach ($cart_items as $item) {
        $profit_per_unit = $item['selling_price'] - $item['farmer_price'];
        
        // Insert order detail with price tracking
        $order_detail_query->bind_param("iiidddd", 
            $order_id, 
            $item['product_id'], 
            $item['quantity'], 
            $item['selling_price'],
            $item['farmer_price'],
            $item['selling_price'],
            $profit_per_unit
        );
        $order_detail_query->execute();
        
        // Reduce stock
        $stock_update_query->bind_param("iii", 
            $item['quantity'], 
            $item['quantity'], 
            $item['product_id']
        );
        $stock_update_query->execute();
        
        // Notify farmer
        $farmer_query = $conn->prepare("SELECT farmer_id FROM products WHERE product_id = ?");
        $farmer_query->bind_param("i", $item['product_id']);
        $farmer_query->execute();
        $farmer_id = $farmer_query->get_result()->fetch_assoc()['farmer_id'];
        $farmer_query->close();
        
        $notif_msg = "New order #{$order_id} received for {$item['product_name']}";
        $notification_query->bind_param("is", $farmer_id, $notif_msg);
        $notification_query->execute();
    }
    
    $order_detail_query->close();
    $stock_update_query->close();
    $notification_query->close();
    
    // Clear cart
    $clear_cart = $conn->prepare("DELETE FROM cart WHERE buyer_id = ?");
    $clear_cart->bind_param("i", $buyer_id);
    $clear_cart->execute();
    $clear_cart->close();
    
    // Commit transaction
    $conn->commit();
    
    // Redirect to success page
    header("Location: order_success.php?order_id=" . $order_id);
    exit();
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    $_SESSION['checkout_error'] = $e->getMessage();
    header("Location: checkout.php");
    exit();
}
