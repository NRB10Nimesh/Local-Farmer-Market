<?php
session_start();
require_once '../db.php';

$buyer_id = intval($_SESSION['buyer_id']);
$message = '';
$errors = [];
$page_title = 'Profile - Buyer Dashboard';

// Fetch buyer profile
$stmt = $conn->prepare("SELECT * FROM buyer WHERE buyer_id = ?");
$stmt->bind_param("i", $buyer_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ========== UPDATE PROFILE ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_profile') {
    $name = trim($_POST['name'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($name === '' || $contact === '' || $address === '') {
        $errors[] = "Please fill all required fields.";
    } else {
        $stmt = $conn->prepare("UPDATE buyer SET name=?, contact=?, address=? WHERE buyer_id=?");
        $stmt->bind_param("sssi", $name, $contact, $address, $buyer_id);

        if ($stmt->execute()) {
            $message = "Profile updated successfully!";
            $profile['name'] = $name;
            $profile['contact'] = $contact;
            $profile['address'] = $address;
            $_SESSION['buyer_name'] = $name;
        } else {
            $errors[] = "Failed to update profile.";
        }
        $stmt->close();
    }
}

// Get statistics
$stats = [
    'orders' => 0,
    'total_spent' => 0
];

// Total orders
$stmt = $conn->query("SELECT COUNT(*) as count FROM orders WHERE buyer_id = $buyer_id");
$stats['orders'] = $stmt->fetch_assoc()['count'];

// Total spent
$stmt = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE buyer_id = $buyer_id");
$stats['total_spent'] = $stmt->fetch_assoc()['total'] ?? 0;

// Fetch cart (for sidebar)
$cart_q = $conn->prepare("
    SELECT c.cart_id, c.product_id, c.quantity,
           p.product_name, p.price, p.image, p.unit, p.quantity AS stock
    FROM cart c
    JOIN products p ON c.product_id = p.product_id
    WHERE c.buyer_id = ?
");
$cart_q->bind_param("i", $buyer_id);
$cart_q->execute();
$cart_items = $cart_q->get_result()->fetch_all(MYSQLI_ASSOC);
$cart_q->close();

$cart_total = 0.0;
foreach ($cart_items as $ci) {
    $cart_total += $ci['price'] * $ci['quantity'];
}

include 'includes/header.php';
?>

<div class="wrap">
  <div class="grid grid-buyer">

    <main>

      <!-- Profile Information -->
      <div class="card">
        <h2 class="page-title">Profile Information</h2>

        <?php if ($message): ?>
          <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php foreach ($errors as $err): ?>
          <div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div>
        <?php endforeach; ?>

        <form method="POST" class="profile-form">
          <input type="hidden" name="action" value="edit_profile">

          <div class="form-row">
            <div class="col">
              <label>Full Name *</label>
              <input type="text" name="name" value="<?php echo htmlspecialchars($profile['name']); ?>" required>
            </div>

            <div class="col">
              <label>Contact Number *</label>
              <input type="text" name="contact" value="<?php echo htmlspecialchars($profile['contact']); ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label>Address *</label>
            <textarea name="address" rows="3" required><?php echo htmlspecialchars($profile['address']); ?></textarea>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
          </div>
        </form>
      </div>

      <!-- Account Statistics -->
      <div class="card account-stats">
        <h2 class="page-title">Account Statistics</h2>

        <div class="stats-grid">
          <div class="stat-card stat-orders">
            <div class="small-muted">Total Orders</div>
            <div class="stat-value"><?php echo $stats['orders']; ?></div>
          </div>

          <div class="stat-card stat-spent">
            <div class="small-muted">Total Spent</div>
            <div class="stat-value">
              Rs<?php echo number_format($stats['total_spent'], 2); ?>
            </div>
          </div>
        </div>

        <div class="member-since">
          <div class="small-muted">
            <strong>Member since:</strong>
            <?php echo date('F j, Y', strtotime($profile['created_at'])); ?>
          </div>
        </div>
      </div>

    </main>

    <?php include 'includes/sidebar.php'; ?>

  </div>
</div>

<script src="../assets/js/script.js"></script>
</body>
</html>
