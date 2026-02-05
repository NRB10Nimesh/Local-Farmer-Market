<?php
session_start();

// Check if logged in as farmer
if (!isset($_SESSION['farmer_id'])) {
    header("Location: ../login_farmer.php");
    exit();
}

require_once '../db.php';

$farmer_id = intval($_SESSION['farmer_id']);
$page_title = 'Profile - Farmer Dashboard';
$message = '';
$errors = [];

// Initialize default profile fields
$profile = [
    'name' => '',
    'email' => '',
    'contact' => '',
    'location' => '',
    'description' => '',
    'created_at' => date('Y-m-d H:i:s')
];

// Try to fetch farmer profile
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
    // Merge with default values, only including keys that exist in both arrays
    $profile = array_merge($profile, array_intersect_key($db_profile, $profile));
}

$stmt->close();

// Update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_profile') {
    $name = trim($_POST['name'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '' || $contact === '' || $location === '') {
        $errors[] = "Please fill all required fields.";
    } else {
        $stmt = $conn->prepare("UPDATE Farmer SET name=?, contact=?, location=?, description=? WHERE farmer_id=?");
        $stmt->bind_param("ssssi", $name, $contact, $location, $description, $farmer_id);
        
        if ($stmt->execute()) {
            $message = "Profile updated successfully!";
            $profile['name'] = $name;
            $profile['contact'] = $contact;
            $profile['location'] = $location;
            $profile['description'] = $description;
            $_SESSION['farmer_name'] = $name;
        } else {
            $errors[] = "Failed to update profile.";
        }
        $stmt->close();
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
$stmt->bind_param("i", $farmer_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

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
$stmt->bind_param("i", $farmer_id);
$stmt->execute();
$monthly_revenue = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

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
$stmt->bind_param("i", $farmer_id);
$stmt->execute();
$top_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include 'includes/header.php';
?>

<div class="wrap">
  <div style="max-width:1200px;margin:0 auto">
    
    <!-- Profile Header Card -->
    <div class="card" style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);color:white;margin-bottom:24px">
      <div style="display:flex;align-items:center;gap:24px">
        <div style="width:80px;height:80px;background:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2rem">
          🧑‍🌾
        </div>
        <div style="flex:1">
          <h2 style="margin:0 0 8px 0;color:white"><?php echo htmlspecialchars($profile['name']); ?></h2>
          <div style="opacity:0.9">📞 <?php echo htmlspecialchars($profile['contact']); ?></div>
          <div style="opacity:0.9">📍 <?php echo htmlspecialchars($profile['location']); ?></div>
        </div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px">
      <!-- Left Column -->
      <div>
        <!-- Edit Profile Card -->
        <div class="card">
          <h3 style="margin-top:0">📝 Edit Profile Information</h3>
          
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
                <input type="text" name="location" value="<?php echo htmlspecialchars($profile['location']); ?>" required>
              </div>
            </div>

            <label style="margin-top:12px">Farm Description</label>
            <textarea name="description" rows="4" placeholder="Tell buyers about your farm..."><?php echo htmlspecialchars($profile['description'] ?? ''); ?></textarea>

            <button type="submit" class="btn btn-primary" style="margin-top:16px">Save Changes</button>
          </form>
        </div>

        <!-- Sales Statistics -->
        <div class="card" style="margin-top:24px">
          <h3 style="margin-top:0">📊 Sales Overview</h3>
          
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
          <h3 style="margin-top:0">📈 Quick Stats</h3>
          
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
            <h3 style="margin-top:0">🏆 Top Selling Products</h3>
            
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
          <h3 style="margin-top:0">👤 Account Info</h3>
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

<script src="../assets/js/script.js"></script>
</body>
</html>