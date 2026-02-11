<?php
// admin/commission_analytics.php - Detailed commission analytics
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../db.php';

$admin_id = intval($_SESSION['admin_id']);
$page_title = 'Commission Analytics - Admin Panel';

// Date filter
$date_filter = $_GET['date'] ?? 'month';
$date_sql = "";
$date_label = "";

switch ($date_filter) {
    case 'today':
        $date_sql = "AND ar.created_at >= CURDATE()";
        $date_label = "Today";
        break;
    case 'week':
        $date_sql = "AND ar.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $date_label = "Last 7 Days";
        break;
    case 'month':
        $date_sql = "AND ar.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $date_label = "Last 30 Days";
        break;
    case 'year':
        $date_sql = "AND ar.created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)";
        $date_label = "Last Year";
        break;
    default:
        $date_label = "All Time";
}

// Overall commission statistics
$overall_query = "SELECT 
    COUNT(DISTINCT ar.order_id) as total_orders,
    SUM(ar.quantity) as total_items,
    SUM(ar.farmer_final_amount) as total_farmer_revenue,
    SUM(ar.commission_amount) as total_commission,
    SUM(ar.admin_price * ar.quantity) as total_buyer_revenue,
    AVG(ar.commission_rate) as avg_commission_rate,
    MIN(ar.commission_rate) as min_commission_rate,
    MAX(ar.commission_rate) as max_commission_rate
    FROM admin_revenue ar
    WHERE 1=1 $date_sql";

$overall_stats = $conn->query($overall_query)->fetch_assoc();

// Commission by category
$category_query = "SELECT 
    p.category,
    COUNT(DISTINCT ar.order_id) as orders,
    SUM(ar.quantity) as items_sold,
    AVG(ar.commission_rate) as avg_commission,
    SUM(ar.commission_amount) as total_commission,
    SUM(ar.farmer_final_amount) as farmer_revenue,
    SUM(ar.admin_price * ar.quantity) as buyer_revenue
    FROM admin_revenue ar
    JOIN products p ON ar.product_id = p.product_id
    WHERE 1=1 $date_sql
    GROUP BY p.category
    ORDER BY total_commission DESC";

$category_stats = $conn->query($category_query)->fetch_all(MYSQLI_ASSOC);

// Top performing products by commission
$product_query = "SELECT 
    p.product_id,
    p.product_name,
    p.category,
    f.name as farmer_name,
    SUM(ar.quantity) as total_sold,
    AVG(ar.commission_rate) as avg_commission,
    ar.commission_per_unit,
    SUM(ar.commission_amount) as total_commission_earned,
    COUNT(DISTINCT ar.order_id) as order_count
    FROM admin_revenue ar
    JOIN products p ON ar.product_id = p.product_id
    JOIN farmer f ON p.farmer_id = f.farmer_id
    WHERE 1=1 $date_sql
    GROUP BY ar.product_id
    ORDER BY total_commission_earned DESC
    LIMIT 20";

$top_products = $conn->query($product_query)->fetch_all(MYSQLI_ASSOC);

// Commission by farmer
$farmer_query = "SELECT 
    f.farmer_id,
    f.name as farmer_name,
    f.contact,
    COUNT(DISTINCT ar.order_id) as orders,
    SUM(ar.quantity) as items_sold,
    AVG(ar.commission_rate) as avg_commission,
    SUM(ar.farmer_final_amount) as farmer_revenue,
    SUM(ar.commission_amount) as commission_earned
    FROM admin_revenue ar
    JOIN products p ON ar.product_id = p.product_id
    JOIN farmer f ON p.farmer_id = f.farmer_id
    WHERE 1=1 $date_sql
    GROUP BY f.farmer_id
    ORDER BY commission_earned DESC
    LIMIT 15";

$farmer_stats = $conn->query($farmer_query)->fetch_all(MYSQLI_ASSOC);

// Daily commission trend (last 30 days)
$daily_trend_query = "SELECT 
    DATE(ar.created_at) as date,
    COUNT(DISTINCT ar.order_id) as orders,
    SUM(ar.quantity) as items,
    SUM(ar.commission_amount) as commission,
    AVG(ar.commission_rate) as avg_rate
    FROM admin_revenue ar
    WHERE ar.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(ar.created_at)
    ORDER BY date DESC";

$daily_trends = $conn->query($daily_trend_query)->fetch_all(MYSQLI_ASSOC);

// Commission rate distribution
$distribution_query = "SELECT 
    CASE 
        WHEN commission_rate >= 5 AND commission_rate < 6 THEN '5.0-5.9%'
        WHEN commission_rate >= 6 AND commission_rate < 7 THEN '6.0-6.9%'
        WHEN commission_rate >= 7 AND commission_rate < 8 THEN '7.0-7.9%'
        WHEN commission_rate >= 8 AND commission_rate < 9 THEN '8.0-8.9%'
        WHEN commission_rate >= 9 AND commission_rate <= 10 THEN '9.0-10.0%'
    END as rate_range,
    COUNT(*) as count,
    SUM(commission_amount) as total_commission
    FROM admin_revenue
    WHERE 1=1 $date_sql
    GROUP BY rate_range
    ORDER BY rate_range";

$distribution = $conn->query($distribution_query)->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
?>

<div class="main-wrapper">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main-content">
  <div style="max-width:1600px;margin:0 auto">
    
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
      <h2 style="margin:0">📊 Commission Analytics Dashboard</h2>
      <div style="display:flex;gap:12px">
        <a href="commission_settings.php" class="btn btn-ghost">⚙️ Settings</a>
        <a href="products.php" class="btn btn-ghost">← Products</a>
      </div>
    </div>

    <!-- Date Filter -->
    <div class="card" style="margin-bottom:24px">
      <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
        <span style="font-weight:600">Period:</span>
        <a href="?date=today" class="btn <?php echo $date_filter === 'today' ? 'btn-primary' : 'btn-ghost'; ?> small-btn">
          Today
        </a>
        <a href="?date=week" class="btn <?php echo $date_filter === 'week' ? 'btn-primary' : 'btn-ghost'; ?> small-btn">
          Last 7 Days
        </a>
        <a href="?date=month" class="btn <?php echo $date_filter === 'month' ? 'btn-primary' : 'btn-ghost'; ?> small-btn">
          Last 30 Days
        </a>
        <a href="?date=year" class="btn <?php echo $date_filter === 'year' ? 'btn-primary' : 'btn-ghost'; ?> small-btn">
          Last Year
        </a>
        <a href="?date=all" class="btn <?php echo $date_filter === 'all' ? 'btn-primary' : 'btn-ghost'; ?> small-btn">
          All Time
        </a>
      </div>
    </div>

    <!-- Overall Statistics -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin-bottom:32px">
      <div class="card" style="background:linear-gradient(135deg, #10b981 0%, #059669 100%);color:white">
        <div class="small" style="opacity:0.9">💰 Total Commission Earned</div>
        <div style="font-size:2.5rem;font-weight:700;margin:8px 0">
          Rs<?php echo number_format($overall_stats['total_commission'] ?? 0, 0); ?>
        </div>
        <div class="small" style="opacity:0.9"><?php echo $date_label; ?></div>
      </div>
      
      <div class="card" style="background:linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);color:white">
        <div class="small" style="opacity:0.9">📦 Total Orders</div>
        <div style="font-size:2.5rem;font-weight:700;margin:8px 0">
          <?php echo number_format($overall_stats['total_orders'] ?? 0); ?>
        </div>
        <div class="small" style="opacity:0.9">
          <?php echo number_format($overall_stats['total_items'] ?? 0); ?> items sold
        </div>
      </div>
      
      <div class="card" style="background:linear-gradient(135deg, #f59e0b 0%, #d97706 100%);color:white">
        <div class="small" style="opacity:0.9">📊 Average Commission Rate</div>
        <div style="font-size:2.5rem;font-weight:700;margin:8px 0">
          <?php echo number_format($overall_stats['avg_commission_rate'] ?? 0, 2); ?>%
        </div>
        <div class="small" style="opacity:0.9">
          Range: <?php echo number_format($overall_stats['min_commission_rate'] ?? 0, 1); ?>% - 
          <?php echo number_format($overall_stats['max_commission_rate'] ?? 0, 1); ?>%
        </div>
      </div>
      
      <div class="card" style="background:linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);color:white">
        <div class="small" style="opacity:0.9">💵 Buyer Revenue</div>
        <div style="font-size:2rem;font-weight:700;margin:8px 0">
          Rs<?php echo number_format($overall_stats['total_buyer_revenue'] ?? 0, 0); ?>
        </div>
        <div class="small" style="opacity:0.9">Total sales amount</div>
      </div>
      
      <div class="card" style="background:#dbeafe;border-left:4px solid #3b82f6">
        <div class="small-muted">🧑‍🌾 Farmer Revenue</div>
        <div style="font-size:2rem;font-weight:700;color:#3b82f6;margin:8px 0">
          Rs<?php echo number_format($overall_stats['total_farmer_revenue'] ?? 0, 0); ?>
        </div>
        <div class="small-muted">Paid to farmers</div>
      </div>
      
      <div class="card" style="background:#d1fae5;border-left:4px solid #10b981">
        <div class="small-muted">📈 Commission Margin</div>
        <div style="font-size:2rem;font-weight:700;color:#10b981;margin:8px 0">
          <?php 
          $margin = $overall_stats['total_buyer_revenue'] > 0 
            ? ($overall_stats['total_commission'] / $overall_stats['total_buyer_revenue'] * 100) 
            : 0;
          echo number_format($margin, 2); 
          ?>%
        </div>
        <div class="small-muted">Of total revenue</div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:32px">
      <!-- Commission by Category -->
      <div class="card">
        <h3 style="margin-top:0">📊 Commission by Category</h3>
        
        <?php if (!empty($category_stats)): ?>
          <div style="overflow-x:auto">
            <table style="width:100%;font-size:0.9rem">
              <thead>
                <tr style="background:#f9fafb;border-bottom:2px solid #e5e7eb">
                  <th style="padding:10px;text-align:left">Category</th>
                  <th style="padding:10px;text-align:center">Avg Rate</th>
                  <th style="padding:10px;text-align:center">Items</th>
                  <th style="padding:10px;text-align:right">Commission</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($category_stats as $cat): ?>
                  <tr style="border-bottom:1px solid #e5e7eb">
                    <td style="padding:10px;font-weight:600">
                      <?php echo htmlspecialchars($cat['category']); ?>
                    </td>
                    <td style="padding:10px;text-align:center;color:#f59e0b;font-weight:600">
                      <?php echo number_format($cat['avg_commission'], 1); ?>%
                    </td>
                    <td style="padding:10px;text-align:center">
                      <?php echo $cat['items_sold']; ?>
                    </td>
                    <td style="padding:10px;text-align:right;font-weight:700;color:#10b981">
                      Rs<?php echo number_format($cat['total_commission'], 0); ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="small-muted">No data available</p>
        <?php endif; ?>
      </div>

      <!-- Commission Rate Distribution -->
      <div class="card">
        <h3 style="margin-top:0">📈 Commission Rate Distribution</h3>
        
        <?php if (!empty($distribution)): ?>
          <?php 
          $max_commission = max(array_column($distribution, 'total_commission'));
          ?>
          <?php foreach ($distribution as $dist): ?>
            <?php 
            $percentage = $max_commission > 0 ? ($dist['total_commission'] / $max_commission * 100) : 0;
            ?>
            <div style="margin-bottom:16px">
              <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                <span style="font-weight:600"><?php echo $dist['rate_range']; ?></span>
                <span style="font-weight:700;color:#10b981">
                  Rs<?php echo number_format($dist['total_commission'], 0); ?>
                </span>
              </div>
              <div style="background:#e5e7eb;height:24px;border-radius:12px;overflow:hidden">
                <div style="background:linear-gradient(90deg, #10b981, #059669);height:100%;width:<?php echo $percentage; ?>%;
                            display:flex;align-items:center;justify-content:center;color:white;font-size:0.8rem;font-weight:600">
                  <?php echo $dist['count']; ?> orders
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="small-muted">No data available</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Top Products by Commission -->
    <div class="card" style="margin-bottom:32px">
      <h3 style="margin-top:0">🏆 Top Products by Commission Earned</h3>
      
      <?php if (!empty($top_products)): ?>
        <div style="overflow-x:auto">
          <table style="width:100%">
            <thead>
              <tr style="background:#f9fafb;border-bottom:2px solid #e5e7eb">
                <th style="padding:12px;text-align:left">Product</th>
                <th style="padding:12px;text-align:left">Farmer</th>
                <th style="padding:12px;text-align:center">Category</th>
                <th style="padding:12px;text-align:center">Sold</th>
                <th style="padding:12px;text-align:center">Avg Rate</th>
                <th style="padding:12px;text-align:center">Commission/Unit</th>
                <th style="padding:12px;text-align:right">Total Commission</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($top_products as $idx => $prod): ?>
                <tr style="border-bottom:1px solid #e5e7eb;<?php echo $idx < 3 ? 'background:#f0fdf4' : ''; ?>">
                  <td style="padding:12px">
                    <?php if ($idx < 3): ?>
                      <span style="font-size:1.2rem">
                        <?php echo $idx === 0 ? '🥇' : ($idx === 1 ? '🥈' : '🥉'); ?>
                      </span>
                    <?php endif; ?>
                    <span style="font-weight:700"><?php echo htmlspecialchars($prod['product_name']); ?></span>
                  </td>
                  <td style="padding:12px">
                    <?php echo htmlspecialchars($prod['farmer_name']); ?>
                  </td>
                  <td style="padding:12px;text-align:center">
                    <span class="badge badge-info"><?php echo htmlspecialchars($prod['category']); ?></span>
                  </td>
                  <td style="padding:12px;text-align:center">
                    <?php echo $prod['total_sold']; ?> units
                  </td>
                  <td style="padding:12px;text-align:center;font-weight:600;color:#f59e0b">
                    <?php echo number_format($prod['avg_commission'], 1); ?>%
                  </td>
                  <td style="padding:12px;text-align:center;font-weight:600">
                    Rs<?php echo number_format($prod['commission_per_unit'], 2); ?>
                  </td>
                  <td style="padding:12px;text-align:right;font-weight:700;color:#10b981;font-size:1.1rem">
                    Rs<?php echo number_format($prod['total_commission_earned'], 0); ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="small-muted">No data available</p>
      <?php endif; ?>
    </div>

    <!-- Commission by Farmer -->
    <div class="card" style="margin-bottom:32px">
      <h3 style="margin-top:0">🧑‍🌾 Commission by Farmer</h3>
      
      <?php if (!empty($farmer_stats)): ?>
        <div style="overflow-x:auto">
          <table style="width:100%">
            <thead>
              <tr style="background:#f9fafb;border-bottom:2px solid #e5e7eb">
                <th style="padding:12px;text-align:left">Farmer</th>
                <th style="padding:12px;text-align:center">Orders</th>
                <th style="padding:12px;text-align:center">Items Sold</th>
                <th style="padding:12px;text-align:center">Avg Commission</th>
                <th style="padding:12px;text-align:right">Farmer Revenue</th>
                <th style="padding:12px;text-align:right">Commission Earned</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($farmer_stats as $farmer): ?>
                <tr style="border-bottom:1px solid #e5e7eb">
                  <td style="padding:12px">
                    <div style="font-weight:700"><?php echo htmlspecialchars($farmer['farmer_name']); ?></div>
                    <div class="small-muted"><?php echo htmlspecialchars($farmer['contact']); ?></div>
                  </td>
                  <td style="padding:12px;text-align:center">
                    <?php echo $farmer['orders']; ?>
                  </td>
                  <td style="padding:12px;text-align:center">
                    <?php echo $farmer['items_sold']; ?>
                  </td>
                  <td style="padding:12px;text-align:center;font-weight:600;color:#f59e0b">
                    <?php echo number_format($farmer['avg_commission'], 1); ?>%
                  </td>
                  <td style="padding:12px;text-align:right;font-weight:600;color:#3b82f6">
                    Rs<?php echo number_format($farmer['farmer_revenue'], 0); ?>
                  </td>
                  <td style="padding:12px;text-align:right;font-weight:700;color:#10b981">
                    Rs<?php echo number_format($farmer['commission_earned'], 0); ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="small-muted">No data available</p>
      <?php endif; ?>
    </div>

    <!-- Daily Commission Trend -->
    <?php if (!empty($daily_trends)): ?>
      <div class="card">
        <h3 style="margin-top:0">📅 Daily Commission Trend (Last 30 Days)</h3>
        
        <div style="overflow-x:auto">
          <table style="width:100%;font-size:0.9rem">
            <thead>
              <tr style="background:#f9fafb;border-bottom:2px solid #e5e7eb">
                <th style="padding:10px;text-align:left">Date</th>
                <th style="padding:10px;text-align:center">Orders</th>
                <th style="padding:10px;text-align:center">Items</th>
                <th style="padding:10px;text-align:center">Avg Rate</th>
                <th style="padding:10px;text-align:right">Commission</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($daily_trends as $trend): ?>
                <tr style="border-bottom:1px solid #e5e7eb">
                  <td style="padding:10px;font-weight:600">
                    <?php echo date('M j, Y', strtotime($trend['date'])); ?>
                  </td>
                  <td style="padding:10px;text-align:center">
                    <?php echo $trend['orders']; ?>
                  </td>
                  <td style="padding:10px;text-align:center">
                    <?php echo $trend['items']; ?>
                  </td>
                  <td style="padding:10px;text-align:center;color:#f59e0b;font-weight:600">
                    <?php echo number_format($trend['avg_rate'], 1); ?>%
                  </td>
                  <td style="padding:10px;text-align:right;font-weight:700;color:#10b981">
                    Rs<?php echo number_format($trend['commission'], 0); ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

  </div>
  </div>
</div>
</div>

<script src="../assets/js/script.js"></script>
</body>
</html>