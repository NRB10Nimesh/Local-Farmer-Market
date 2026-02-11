<?php
session_start();

// Determine user type
$is_farmer = isset($_SESSION['farmer_id']);
$is_buyer = isset($_SESSION['buyer_id']);

// Destroy session
session_unset();
session_destroy();

// Redirect to appropriate login page
if ($is_farmer) {
    header("Location: ../login_farmer.php");
} elseif ($is_buyer) {
    header("Location: ../login_buyer.php");
} else {
    header("Location: ../login.php");
}
exit();
?>