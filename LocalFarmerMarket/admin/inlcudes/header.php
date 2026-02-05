<?php
// Admin Header - includes/header.php
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login_admin.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?php echo $page_title ?? 'Admin Dashboard'; ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="../assets/css/style.css">
<style>
.header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.admin-badge { 
    background: rgba(255,255,255,0.2); 
    padding: 4px 12px; 
    border-radius: 20px; 
    font-size: 0.85rem;
    font-weight: 600;
}
</style>
</head>
<body>

<header class="header">
  <div class="brand">🔐 Admin Panel - Local Farmer Market</div>
  <nav>
    <a href="/LocalFarmerMarket/admin/dashboard.php" class="<?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>">Dashboard</a>
    <a href="/LocalFarmerMarket/admin/products.php" class="<?php echo ($current_page === 'products.php') ? 'active' : ''; ?>">Products</a>
    <a href="/LocalFarmerMarket/admin/farmers.php" class="<?php echo ($current_page === 'farmers.php') ? 'active' : ''; ?>">Farmers</a>
    <a href="/LocalFarmerMarket/admin/buyers.php" class="<?php echo ($current_page === 'buyers.php') ? 'active' : ''; ?>">Buyers</a>
    <a href="/LocalFarmerMarket/admin/orders.php" class="<?php echo ($current_page === 'orders.php') ? 'active' : ''; ?>">Orders</a>
  </nav>
  <div class="header-right">
    <span class="admin-badge">ADMIN</span>
    <span>Hello, <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin', ENT_QUOTES); ?></span>
    <a href="../logout.php" class="btn btn-ghost">Logout</a>
  </div>
</header>
