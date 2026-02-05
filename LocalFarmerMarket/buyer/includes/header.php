<?php
// Buyer Header - No Inline Styles
if (!isset($_SESSION['buyer_id'])) {
    header("Location: ../login_buyer.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);

// Get cart count for badge
$cart_count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM cart WHERE buyer_id = ?");
$cart_count_stmt->bind_param("i", $buyer_id);
$cart_count_stmt->execute();
$cart_count = $cart_count_stmt->get_result()->fetch_assoc()['count'] ?? 0;
$cart_count_stmt->close();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?php echo $page_title ?? 'Buyer Dashboard'; ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<header class="header">
  <div class="brand">🛒 Local Farmer Market</div>
  <nav>
    <a href="dashboard.php" class="<?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>">Shop</a>
    <a href="orders.php" class="<?php echo ($current_page === 'orders.php') ? 'active' : ''; ?>">My Orders</a>
    <a href="profile.php" class="<?php echo ($current_page === 'profile.php') ? 'active' : ''; ?>">Profile</a>
  </nav>
  <div class="header-right">
    <!-- Cart Button -->
    <a href="checkout.php" class="cart-button">
      🛒 Cart
      <?php if ($cart_count > 0): ?>
        <span class="cart-badge">
          <?php echo $cart_count > 99 ? '99+' : $cart_count; ?>
        </span>
      <?php endif; ?>
    </a>
    
    <span>Hello, <?php echo htmlspecialchars($_SESSION['buyer_name'] ?? $profile['name'] ?? 'Buyer', ENT_QUOTES); ?></span>
    <a href="../logout.php" class="btn btn-ghost">Logout</a>
  </div>
</header>