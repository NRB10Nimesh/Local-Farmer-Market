<?php
// Farmer Header Component
if (!isset($_SESSION['farmer_id'])) {
    header("Location: ../login_farmer.php");
    exit();
}

$farmer_name = $profile['name'] ?? 'Farmer';
$farmer_initial = strtoupper(substr($farmer_name, 0, 1));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?php echo $page_title ?? 'Farmer Dashboard'; ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/farmer.css">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<?php
// Allow pages to include extra CSS files via $page_css array (optional)
if (!empty($page_css) && is_array($page_css)) {
    foreach ($page_css as $css_file) {
        echo '<link rel="stylesheet" href="' . htmlspecialchars($css_file) . '">';
    }
}
?>
</head>
<body>

<header class="header">
  <div class="brand">
    <div class="brand-icon"><span class="material-icons" aria-hidden="true">local_farm</span></div>
    <span class="brand-text">Local Farmer Market</span>
  </div>
  
  <div class="header-nav">
    <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">My Products</a>
    <a href="orders.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'orders.php' ? 'active' : ''; ?>">Orders</a>
    <a href="profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : ''; ?>">Profile</a>
  </div>
  
  <div class="header-right">
    <div class="user-info">
      <span><?php echo htmlspecialchars($farmer_name); ?></span>
      <div class="user-avatar"><?php echo $farmer_initial; ?></div>
    </div>
    <a href="../logout.php" class="btn small primary">Logout</a>
  </div>
</header>