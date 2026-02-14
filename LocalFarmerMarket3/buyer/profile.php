<?php
session_start();

if (!isset($_SESSION['buyer_id'])) {
    header("Location: ../login_buyer.php");
    exit();
}

require_once '../db.php';

$buyer_id = intval($_SESSION['buyer_id']);
$message = '';
$errors = [];
$page_title = 'Profile - Buyer Dashboard';
$page_css = ['../assets/css/profile.css'];

// Load buyer profile from DB for display/edit
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
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE buyer_id = ?");
$stmt->bind_param("i", $buyer_id);
$stmt->execute();
$stats['orders'] = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Total spent
$stmt = $conn->prepare("SELECT SUM(total_amount) as total FROM orders WHERE buyer_id = ?");
$stmt->bind_param("i", $buyer_id);
$stmt->execute();
$stats['total_spent'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

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

<div class="main-wrapper profile-page">
  <?php include 'includes/sidebar.php'; ?>
  <main class="main-content">
    <div class="profile-container">

    <!-- Profile Header (emerald style via CSS) -->
    <div class="card profile-header">
      <div class="profile-header-inner">
        <div class="profile-avatar"><?php
            $initial = '';
            $nameSource = !empty($profile['name']) ? $profile['name'] : ($_SESSION['buyer_name'] ?? '');
            $nameSource = trim((string)$nameSource);
            if ($nameSource !== '') {
                $parts = preg_split('/\s+/u', $nameSource);
                if (count($parts) >= 2) {
                    $initial = mb_substr($parts[0], 0, 1) . mb_substr($parts[count($parts)-1], 0, 1);
                } else {
                    $initial = mb_substr($parts[0], 0, 2);
                }
            }
            $initial = strtoupper((string)$initial);
            $initial = preg_replace('/[^A-Z]/u', '', $initial);
            $initial = $initial !== '' ? mb_substr($initial, 0, 2) : 'U';
            echo '<span class="profile-initial">'.htmlspecialchars($initial).'</span>';
        ?>
        </div>
        <div class="profile-meta">
          <h2 class="profile-name"><?php echo htmlspecialchars($profile['name']); ?></h2>
          <div class="small-muted profile-subinfo"><span class="material-icons" aria-hidden="true">phone</span> <?php echo htmlspecialchars($profile['contact']); ?></div>
          <div class="small-muted profile-subinfo" style="margin-top:4px"><span class="material-icons" aria-hidden="true">place</span> <?php echo htmlspecialchars($profile['address']); ?></div>
        </div>
        <div class="profile-actions">
          <div class="member-since">Member since<br><strong><?php echo date('F j, Y', strtotime($profile['created_at'])); ?></strong></div>
        </div>
      </div>
    </div>

    <div class="profile-grid">
      <!-- Left Column: Edit Profile + Recent Orders -->
      <div>
        <div class="card profile-card card--spacious">
          <h3 style="margin-top:0"><span class="material-icons" aria-hidden="true">edit</span> Edit Profile Information</h3>

          <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
          <?php endif; ?>
          <?php foreach ($errors as $err): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div>
          <?php endforeach; ?>

          <form method="POST">
            <input type="hidden" name="action" value="edit_profile">

            <label>Full Name *</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($profile['name']); ?>" required>

            <div class="form-row" style="margin-top:12px">
              <div>
                <label>Contact Number *</label>
                <input type="text" name="contact" value="<?php echo htmlspecialchars($profile['contact']); ?>" required>
              </div>
              <div>
                <label>Address *</label>
                <input type="text" name="address" value="<?php echo htmlspecialchars($profile['address']); ?>" required>
              </div>
            </div>

            <div style="margin-top:12px">
              <label>Additional Notes</label>
              <textarea name="notes" rows="3" placeholder="Optional notes about your preferences or address instructions."></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="margin-top:16px">Save Changes</button>
          </form>
        </div>


      </div>

      <!-- Right Column: Stats -->
      <aside>
        <div class="card">
          <h3 style="margin-top:0"><span class="material-icons" aria-hidden="true">trending_up</span> Quick Stats</h3>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px">
            <div style="background:#f9fffb;padding:12px;border-radius:8px;border:1px solid #eaf8ee">
              <div class="small-muted">Total Orders</div>
              <div class="stat-value"><?php echo $stats['orders']; ?></div>
            </div>

            <div style="background:#fff6f6;padding:12px;border-radius:8px;border:1px solid #fdecea">
              <div class="small-muted">Total Spent</div>
              <div class="stat-value">Rs<?php echo number_format($stats['total_spent'],2); ?></div>
            </div>
          </div>

          <div style="margin-top:12px" class="card-sub">
            <div class="small-muted"><strong>Account ID:</strong> #<?php echo str_pad($buyer_id, 6, '0', STR_PAD_LEFT); ?></div>
          </div>
        </div>
      </aside>
    </div>

    </div> <!-- .profile-container -->
  </main>
</div>

<script src="../assets/js/script.js"></script>
</body>
</html>
