<?php
session_start();

// Redirect to farmer login if session is missing
if (!isset($_SESSION['farmer_id'])) {
    header("Location: ../login_farmer.php");
    exit();
}

require_once '../db.php';

$farmer_id = intval($_SESSION['farmer_id']);
$page_title = 'Profile - Farmer Dashboard';
// Include profile stylesheet for page-specific styling
$page_css = ['../assets/css/profile.css'];
$message = '';
$errors = [];

// Initialize default profile fields
$profile = [
    'name' => '',
    'email' => '',
    'contact' => '',
    'address' => '',
    'description' => '',
    'created_at' => date('Y-m-d H:i:s')
];

// Load farmer profile from DB and merge with defaults
$sql = "SELECT * FROM Farmer WHERE farmer_id = ?";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    die("Error preparing query: " . $conn->error);
}

$stmt->bind_param("i", $farmer_id);

if (!$stmt->execute()) {
    die("Error executing query: " . $stmt->error);
}

$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $db_profile = $result->fetch_assoc();
    // Merge DB values into defaults to avoid undefined keys
    $profile = array_merge($profile, array_intersect_key($db_profile, $profile));
}

$stmt->close();

// Handle profile edit POST and persist validated values to DB
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_profile') {
    $name = trim($_POST['name'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '' || $contact === '' || $address === '') {
        $errors[] = "Please fill all required fields.";
    } else {
        $stmt = $conn->prepare("UPDATE Farmer SET name=?, contact=?, address=?, description=? WHERE farmer_id=?");
        if ($stmt === false) {
            $err = $conn->error;
            error_log('Prepare failed (UPDATE Farmer): ' . $err);
            $errors[] = 'Unable to update profile at this time. DB error: ' . $err;
        } else {
            if (!$stmt->bind_param("ssssi", $name, $contact, $address, $description, $farmer_id)) {
                $err = $stmt->error ?: $conn->error;
                error_log('bind_param failed (UPDATE Farmer): ' . $err);
                $errors[] = 'Unable to update profile at this time. DB error: ' . $err;
            } elseif (!$stmt->execute()) {
                $err = $stmt->error ?: $conn->error;
                error_log('Execute failed (UPDATE Farmer): ' . $err);
                $errors[] = 'Failed to update profile. DB error: ' . $err;
            } else {
                $message = "Profile updated successfully!";
                $profile['name'] = $name;
                $profile['contact'] = $contact;
                $profile['address'] = $address;
                $profile['description'] = $description;
                $_SESSION['farmer_name'] = $name;
            }
            $stmt->close();
        }
    }
}

// Get comprehensive statistics
$stats_query = "SELECT 
    COUNT(DISTINCT p.product_id) as total_products,
    COUNT(DISTINCT o.order_id) as total_orders,
    SUM(CASE WHEN o.status = 'Delivered' THEN 1 ELSE 0 END) as completed_orders,
    SUM(od.quantity * od.price) as total_revenue,
    SUM(CASE WHEN o.status = 'Delivered' THEN od.quantity * od.price ELSE 0 END) as completed_revenue
    FROM products p
    LEFT JOIN order_details od ON p.product_id = od.product_id
    LEFT JOIN orders o ON od.order_id = o.order_id
    WHERE p.farmer_id = ?";

$stmt = $conn->prepare($stats_query);
if ($stmt === false) {
    error_log('Prepare failed (stats_query): ' . $conn->error);
    $stats = [
        'total_products' => 0,
        'total_orders' => 0,
        'completed_orders' => 0,
        'total_revenue' => 0,
        'completed_revenue' => 0,
    ];
} else {
    if (!$stmt->bind_param("i", $farmer_id) || !$stmt->execute()) {
        error_log('DB error (stats_query) - bind/execute: ' . $stmt->error);
        $stats = [
            'total_products' => 0,
            'total_orders' => 0,
            'completed_orders' => 0,
            'total_revenue' => 0,
            'completed_revenue' => 0,
        ];
    } else {
        $res = $stmt->get_result();
        $stats = $res ? $res->fetch_assoc() : [];
    }
    $stmt->close();
}

// Get monthly revenue (last 6 months)
$monthly_query = "SELECT 
    DATE_FORMAT(o.order_date, '%Y-%m') as month,
    SUM(od.quantity * od.price) as revenue
    FROM orders o
    JOIN order_details od ON o.order_id = od.order_id
    JOIN products p ON od.product_id = p.product_id
    WHERE p.farmer_id = ? 
    AND o.order_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    AND o.status = 'Delivered'
    GROUP BY DATE_FORMAT(o.order_date, '%Y-%m')
    ORDER BY month DESC";

$stmt = $conn->prepare($monthly_query);
if ($stmt === false) {
    error_log('Prepare failed (monthly_query): ' . $conn->error);
    $monthly_revenue = [];
} else {
    if (!$stmt->bind_param("i", $farmer_id) || !$stmt->execute()) {
        error_log('DB error (monthly_query) - bind/execute: ' . $stmt->error);
        $monthly_revenue = [];
    } else {
        $monthly_revenue = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
}

// Get top selling products
$top_products_query = "SELECT 
    p.product_name,
    p.image,
    SUM(od.quantity) as total_sold,
    SUM(od.quantity * od.price) as revenue
    FROM order_details od
    JOIN products p ON od.product_id = p.product_id
    JOIN orders o ON od.order_id = o.order_id
    WHERE p.farmer_id = ? AND o.status = 'Delivered'
    GROUP BY p.product_id
    ORDER BY total_sold DESC
    LIMIT 5";

$stmt = $conn->prepare($top_products_query);
if ($stmt === false) {
    error_log('Prepare failed (top_products_query): ' . $conn->error);
    $top_products = [];
} else {
    if (!$stmt->bind_param("i", $farmer_id) || !$stmt->execute()) {
        error_log('DB error (top_products_query) - bind/execute: ' . $stmt->error);
        $top_products = [];
    } else {
        $top_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
}

include 'includes/header.php';
?>

<div class="main-wrapper">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main-content">
  <div class="wrap">
  <div style="max-width:1200px;margin:0 auto">
    
    <!-- Profile Header Card -->
    <div class="card profile-header">
      <div class="profile-header-inner">
        <div class="profile-avatar"><?php
            $initial = '';
            $nameSource = !empty($profile['name']) ? $profile['name'] : ($_SESSION['farmer_name'] ?? '');
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
            $initial = $initial !== '' ? mb_substr($initial, 0, 2) : 'F';
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

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px">
      <!-- Left Column -->
      <div>
        <!-- Edit Profile Card -->
        <div class="card card--spacious">
          <h3 style="margin-top:0"><span class="material-icons" aria-hidden="true">edit</span> Edit Profile Information</h3>
          
          <?php if ($message): ?>
            <div class="alert-success"><?php echo htmlspecialchars($message); ?></div>
          <?php endif; ?>
          <?php foreach ($errors as $err): ?>
            <div class="alert-danger"><?php echo htmlspecialchars($err); ?></div>
          <?php endforeach; ?>

          <form method="POST">
            <input type="hidden" name="action" value="edit_profile">
            
            <label>Full Name *</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($profile['name']); ?>" required>
            
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px">
              <div>
                <label>Contact Number *</label>
                <input type="text" name="contact" value="<?php echo htmlspecialchars($profile['contact']); ?>" required>
              </div>
              <div>
                <label>Location *</label>
                <input type="text" name="address" value="<?php echo htmlspecialchars($profile['address']); ?>" required>
              </div>
            </div>

            <label style="margin-top:12px">Farm Description</label>
            <textarea name="description" rows="4" placeholder="Tell buyers about your farm..."><?php echo htmlspecialchars($profile['description'] ?? ''); ?></textarea>

            <button type="submit" class="btn btn-primary" style="margin-top:16px">Save Changes</button>
          </form>
        </div>

        <!-- Sales Statistics -->
        <div class="card" style="margin-top:24px">
          <h3 style="margin-top:0"><span class="material-icons" aria-hidden="true">analytics</span> Sales Overview</h3>
          
          <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px;margin-bottom:24px">
            <div style="background:#eff6ff;padding:18px;border-radius:10px;border-left:4px solid #3b82f6">
              <div class="small-muted">Total Revenue</div>
              <div style="font-size:2rem;font-weight:700;color:#3b82f6">
                Rs<?php echo number_format($stats['total_revenue'] ?? 0, 0); ?>
              </div>
              <div class="small-muted">All time</div>
            </div>
            
            <div style="background:#d1fae5;padding:18px;border-radius:10px;border-left:4px solid #16a34a">
              <div class="small-muted">Completed Orders</div>
              <div style="font-size:2rem;font-weight:700;color:#16a34a">
                <?php echo $stats['completed_orders'] ?? 0; ?>
              </div>
              <div class="small-muted">Successfully delivered</div>
            </div>
          </div>

          <?php if (!empty($monthly_revenue)): ?>
            <h4 style="margin:20px 0 12px 0">Monthly Revenue (Last 6 Months)</h4>
            <div style="background:#f9fafb;padding:16px;border-radius:8px">
              <?php foreach ($monthly_revenue as $month): ?>
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #e5e7eb">
                  <span><?php echo date('F Y', strtotime($month['month'] . '-01')); ?></span>
                  <span style="font-weight:700;color:#16a34a">Rs<?php echo number_format($month['revenue'], 2); ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Right Column -->
      <div>
        <!-- Quick Stats -->
        <div class="card">
          <h3 style="margin-top:0"><span class="material-icons" aria-hidden="true">trending_up</span> Quick Stats</h3>
          
          <div style="padding:16px;background:#fef3c7;border-radius:8px;margin-bottom:12px">
            <div class="small-muted">Total Products</div>
            <div style="font-size:2rem;font-weight:700;color:#f59e0b">
              <?php echo $stats['total_products'] ?? 0; ?>
            </div>
          </div>
          
          <div style="padding:16px;background:#dbeafe;border-radius:8px;margin-bottom:12px">
            <div class="small-muted">Total Orders</div>
            <div style="font-size:2rem;font-weight:700;color:#3b82f6">
              <?php echo $stats['total_orders'] ?? 0; ?>
            </div>
          </div>
          
          <div style="padding:16px;background:#e0e7ff;border-radius:8px">
            <div class="small-muted">Completed Revenue</div>
            <div style="font-size:1.5rem;font-weight:700;color:#6366f1">
              Rs<?php echo number_format($stats['completed_revenue'] ?? 0, 0); ?>
            </div>
          </div>
        </div>

        <!-- Top Products -->
        <?php if (!empty($top_products)): ?>
          <div class="card" style="margin-top:16px">
            <h3 style="margin-top:0"><span class="material-icons" aria-hidden="true">emoji_events</span> Top Selling Products</h3>
            
            <?php foreach ($top_products as $index => $product): ?>
              <div style="display:flex;gap:12px;padding:12px;background:#f9fafb;border-radius:8px;margin-bottom:8px">
                <div style="font-size:1.5rem;font-weight:700;color:#9ca3af;width:30px">
                  #<?php echo $index + 1; ?>
                </div>
                
                <?php if ($product['image'] && file_exists(__DIR__ . '/../uploads/' . $product['image'])): ?>
                  <img src="../uploads/<?php echo htmlspecialchars($product['image']); ?>" 
                       style="width:50px;height:50px;object-fit:cover;border-radius:6px">
                <?php else: ?>
                  <div style="width:50px;height:50px;background:#e5e7eb;border-radius:6px"></div>
                <?php endif; ?>
                
                <div style="flex:1">
                  <div style="font-weight:700;font-size:0.9rem">
                    <?php echo htmlspecialchars($product['product_name']); ?>
                  </div>
                  <div class="small-muted">
                    Sold: <?php echo $product['total_sold']; ?> units
                  </div>
                  <div style="font-weight:700;color:#16a34a;font-size:0.9rem">
                    Rs<?php echo number_format($product['revenue'], 0); ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <!-- Account Info -->
        <div class="card" style="margin-top:16px">
          <h3 style="margin-top:0"><span class="material-icons" aria-hidden="true">account_circle</span> Account Info</h3>
          <div class="small-muted">
            <strong>Member Since:</strong><br>
            <?php echo date('F j, Y', strtotime($profile['created_at'])); ?>
          </div>
          <div class="small-muted" style="margin-top:12px">
            <strong>Account ID:</strong><br>
            #<?php echo str_pad($farmer_id, 6, '0', STR_PAD_LEFT); ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  </div>
</div>
</div>

<script src="../assets/js/script.js"></script>
</body>
</html>