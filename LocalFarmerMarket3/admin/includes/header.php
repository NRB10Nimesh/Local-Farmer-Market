<?php
// Admin Header Component
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

$admin_name = $profile['full_name'] ?? 'Admin';
$admin_initial = strtoupper(substr($admin_name, 0, 1));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?php echo $page_title ?? 'Admin Dashboard'; ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="../assets/css/admin.css">
<!-- Include shared site styles so admin modals and common UI elements (e.g. .modal-body) get correct styling -->
<link rel="stylesheet" href="../assets/css/style.css">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body>

<header class="header admin-header">
  <div class="brand">
    <div class="brand-icon"><span class="material-icons" aria-hidden="true">settings</span></div>
    <span>Admin Panel</span>
  </div>
  
  <div class="header-nav">
    <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a>
    <a href="products.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'products.php' ? 'active' : ''; ?>">Products</a>
    <a href="farmers.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'farmers.php' ? 'active' : ''; ?>">Farmers</a>
    <a href="buyers.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'buyers.php' ? 'active' : ''; ?>">Buyers</a>
    <a href="orders.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'orders.php' ? 'active' : ''; ?>">Orders</a>
    <a href="revenue.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'revenue.php' ? 'active' : ''; ?>">Revenue</a>
  </div>
  
  <div class="header-right">
    <div class="user-info">
      <span><?php echo htmlspecialchars($admin_name); ?></span>
      <div class="user-avatar"><?php echo $admin_initial; ?></div>
    </div>
    <a href="../logout.php" class="btn small primary">Logout</a>
  </div>
</header>