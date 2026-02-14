<?php
// Buyer Header Component
if (!isset($_SESSION['buyer_id'])) {
    header("Location: ../login_buyer.php");
    exit();
}

$buyer_id = intval($_SESSION['buyer_id']);

// Process cart update/remove POST actions from header
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_cart') {
        $cart_id = intval($_POST['cart_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 0);
        
        if ($cart_id > 0 && $quantity > 0) {
            $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE cart_id = ? AND buyer_id = ?");
            $stmt->bind_param("iii", $quantity, $cart_id, $buyer_id);
            $stmt->execute();
            $stmt->close();
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    } elseif ($_POST['action'] === 'remove_from_cart') {
        $cart_id = intval($_POST['cart_id'] ?? 0);
        
        if ($cart_id > 0) {
            $stmt = $conn->prepare("DELETE FROM cart WHERE cart_id = ? AND buyer_id = ?");
            $stmt->bind_param("ii", $cart_id, $buyer_id);
            $stmt->execute();
            $stmt->close();
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }
}

$buyer_name = $profile['name'] ?? 'Buyer';
$buyer_initial = strtoupper(substr($buyer_name, 0, 1));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?php echo $page_title ?? 'Buyer Dashboard'; ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="../assets/css/buyer.css">
<link rel="stylesheet" href="../assets/css/profile.css">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<?php
// Load any additional CSS files specified by the page via $page_css
if (!empty($page_css) && is_array($page_css)) {
    foreach ($page_css as $css_file) {
        // allow passing absolute or relative paths
        // avoid printing literal "\n" which appears on the page in some browsers
        echo '<link rel="stylesheet" href="' . htmlspecialchars($css_file) . '">';
    }
}
?>
</head>
<body>

<header class="header">
  <div class="brand">
    <div class="brand-icon"><span class="material-icons" aria-hidden="true">shopping_cart</span></div>
    <span class="brand-text">Local Farmer Market</span>
  </div>
  
  <div class="header-nav">
    <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">Shop</a>
    <a href="orders.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'orders.php' ? 'active' : ''; ?>">My Orders</a>
    <a href="checkout.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'checkout.php' ? 'active' : ''; ?>">Checkout</a>
    <a href="profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : ''; ?>">Profile</a>
  </div>
  
  <div class="header-right">
    <div class="user-info">
      <span><?php echo htmlspecialchars($buyer_name); ?></span>
      <div class="user-avatar"><?php echo $buyer_initial; ?></div>
    </div>
    <a href="../logout.php" class="btn small primary">Logout</a>
  </div>
</header>