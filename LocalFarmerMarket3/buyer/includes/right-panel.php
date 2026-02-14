<?php
// Buyer right-panel component — shopping cart and quick stats
if (!function_exists('e')) {
    function e($v) { 
        return htmlspecialchars($v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); 
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="right-panel">
  <!-- Cart Summary Card -->
  <div class="card card-compact">
    <h4 style="margin: 0 0 var(--space-lg) 0; display: flex; justify-content: space-between; align-items: center;">
      <span><span class="material-icons" aria-hidden="true">shopping_cart</span> Shopping Cart</span>
        <span class="badge badge-success"><?php echo count($cart_items); ?></span>
    </h4>
    
    <?php 
    // Calculate cart total from items
    $cart_total = 0;
    if (!empty($cart_items)) {
      foreach ($cart_items as $ci) {
        $cart_total += floatval($ci['price'] ?? 0) * intval($ci['quantity'] ?? 0);
      }
    }
    
    if (!empty($cart_items)): 
    ?>
      <div class="cart-list">
        <?php foreach ($cart_items as $ci): ?>
          <div class="cart-item">
            <?php if (isset($ci['image']) && !empty($ci['image']) && file_exists(__DIR__ . '/../../uploads/' . $ci['image'])): ?>
              <img src="../uploads/<?php echo e($ci['image']); ?>" alt="<?php echo e($ci['product_name']); ?>">
            <?php else: ?>
              <div class="cart-item-placeholder"><span class="material-icons" aria-hidden="true">inventory_2</span></div>
            <?php endif; ?>
            
            <div class="cart-item-content">
              <div class="cart-item-name"><?php echo e($ci['product_name']); ?></div>
              <div class="cart-item-price small-muted mb-6">
                Rs<?php echo number_format($ci['price'], 2); ?> × <?php echo $ci['quantity']; ?> 
                = <strong>Rs<?php echo number_format($ci['price'] * $ci['quantity'], 2); ?></strong>
              </div>
            </div>

            <div class="cart-item-actions">
                <form method="POST" action="" class="cart-item-quantity-form">
                  <input type="hidden" name="action" value="update_cart" />
                  <input type="hidden" name="cart_id" value="<?php echo e($ci['cart_id']); ?>" />
                  <input type="number" name="quantity" min="1" max="<?php echo intval($ci['stock']); ?>" value="<?php echo intval($ci['quantity']); ?>" class="cart-item-quantity-input" />
                  <button class="btn btn-ghost small-btn" type="submit">Update</button>
                </form>
                
                <form method="POST" action="" onsubmit="return confirm('Remove this item?')">
                  <input type="hidden" name="action" value="remove_from_cart" />
                  <input type="hidden" name="cart_id" value="<?php echo e($ci['cart_id']); ?>" />
                  <button class="btn btn-danger small-btn" type="submit">Remove</button>
                </form>
              </div>
          </div>
        <?php endforeach; ?>
      </div>
      
      <!-- Cart Total & Checkout -->
      <div class="cart-summary">
        <div class="cart-subtotal-row">
          <span class="small-muted">Subtotal:</span>
          <span><strong>Rs<?php echo number_format($cart_total, 2); ?></strong></span>
        </div>
        
        <?php 
        $delivery_fee = $cart_total >= 500 ? 0 : 50;
        $final_total = $cart_total + $delivery_fee;
        ?>
        
        <div class="cart-delivery-row">
          <span class="small-muted">Delivery:</span>
          <span class="<?php echo $delivery_fee === 0 ? 'stat-value success' : ''; ?>">
            <strong><?php echo $delivery_fee === 0 ? 'FREE' : 'Rs' . number_format($delivery_fee, 2); ?></strong>
          </span>
        </div>
        
        <div class="cart-total">
          Total: Rs<?php echo number_format($final_total, 2); ?>
        </div>
        
        <a href="checkout.php" class="btn btn-primary full-width">
          Proceed to Checkout →
        </a>
        
        <?php if ($delivery_fee > 0): ?>
          <div class="cart-delivery-tip small-muted">
            <span class="material-icons" aria-hidden="true">lightbulb</span> Add Rs<?php echo number_format(500 - $cart_total, 2); ?> more for free delivery!
          </div>
        <?php endif; ?>
      </div>
      
    <?php else: ?>
      <div class="cart-empty">
        <div class="cart-empty-icon"><span class="material-icons" aria-hidden="true">shopping_cart</span></div>
        <div class="cart-empty-title">Your cart is empty</div>
        <div class="cart-empty-desc small-muted mb-16">Start shopping to add items</div>
        <a href="dashboard.php" class="btn btn-primary small-btn">Browse Products</a>
      </div>
    <?php endif; ?>
  </div>

  <!-- Quick Stats -->
  <?php if ($current_page === 'profile.php'): ?>
    <div class="card mt-12">
      <h4><span class="material-icons" aria-hidden="true">bar_chart</span> Quick Stats</h4>
      <?php
      $orders_count = $conn->query("SELECT COUNT(*) as count FROM orders WHERE buyer_id = $buyer_id")->fetch_assoc()['count'];
      $total_spent = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE buyer_id = $buyer_id AND status = 'Delivered'")->fetch_assoc()['total'] ?? 0;
      ?>
      <div class="quick-stats-box success mb-8">
        <div class="small-muted">Total Orders</div>
        <div class="quick-stats-value stat-value success"><?php echo $orders_count; ?></div>
      </div>
      <div class="quick-stats-box warning">
        <div class="small-muted">Total Spent</div>
        <div class="quick-stats-value stat-value warning">Rs<?php echo number_format($total_spent, 0); ?></div>
      </div>
    </div>
  <?php endif; ?>
</div>
