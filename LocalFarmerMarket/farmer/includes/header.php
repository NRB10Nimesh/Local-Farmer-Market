<?php
// Farmer Header - No Inline Styles
if (!isset($_SESSION['farmer_id'])) {
    header("Location: ../login_farmer.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?php echo $page_title ?? 'Farmer Dashboard'; ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<header class="header">
  <div class="brand">🌾 Local Farmer Market</div>
  <nav>
    <a href="dashboard.php" class="<?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>">Products</a>
    <a href="orders.php" class="<?php echo ($current_page === 'orders.php') ? 'active' : ''; ?>">Orders</a>
    <a href="profile.php" class="<?php echo ($current_page === 'profile.php') ? 'active' : ''; ?>">Profile</a>
  </nav>
  <div class="header-right">
    <span>Hello, <?php echo htmlspecialchars($_SESSION['farmer_name'] ?? 'Farmer', ENT_QUOTES); ?></span>
    <a href="../logout.php" class="btn btn-ghost">Logout</a>
  </div>
</header>