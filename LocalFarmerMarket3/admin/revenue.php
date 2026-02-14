<?php
// admin/revenue.php - Admin profit/revenue tracking
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../db.php';

$admin_id = intval($_SESSION['admin_id']);
$page_title = 'Revenue & Profit Report - Admin Panel';

// Date filter
$date_filter = $_GET['date'] ?? 'all';
$date_sql = "";

if ($date_filter === 'today') {
    $date_sql = "AND DATE(ar.created_at) = CURDATE()";
} elseif ($date_filter === 'week') {
    $date_sql = "AND ar.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($date_filter === 'month') {
    $date_sql = "AND ar.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
} elseif ($date_filter === 'year') {
    $date_sql = "AND ar.created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)";
}

// Get overall revenue statistics
$overall_stats_query = "SELECT 
    COUNT(DISTINCT ar.order_id) as total_orders,
    SUM(ar.quantity) as total_items_sold,
    SUM(ar.farmer_price * ar.quantity) as total_farmer_revenue,
    SUM(ar.admin_price * ar.quantity) as total_buyer_revenue,
    SUM(ar.profit_amount) as total_admin_profit
    FROM admin_revenue ar
    WHERE 1=1 $date_sql";

$overall_stats = $conn->query($overall_stats_query)->fetch_assoc();

// Get revenue by product
$product_revenue_query = "SELECT 
    p.product_id,
    p.product_name,
    p.category,
    p.unit,
    f.name as farmer_name,
    SUM(ar.quantity) as total_sold,
    AVG(ar.farmer_price) as farmer_price,
    AVG(ar.admin_price) as admin_price,
    AVG(ar.admin_price - ar.farmer_price) as profit_per_unit,
    SUM(ar.profit_amount) as total_profit
    FROM admin_revenue ar
    JOIN products p ON ar.product_id = p.product_id
    JOIN farmer f ON p.farmer_id = f.farmer_id
    WHERE 1=1 $date_sql
    GROUP BY p.product_id, p.product_name, p.category, p.unit, f.name
    ORDER BY total_profit DESC";

$product_revenues = $conn->query($product_revenue_query)->fetch_all(MYSQLI_ASSOC);

// Get revenue by farmer
$farmer_revenue_query = "SELECT 
    f.farmer_id,
    f.name as farmer_name,
    COUNT(DISTINCT ar.order_id) as orders_count,
    SUM(ar.quantity) as items_sold,
    SUM(ar.farmer_price * ar.quantity) as farmer_revenue,
    SUM(ar.profit_amount) as admin_profit
    FROM admin_revenue ar
    JOIN products p ON ar.product_id = p.product_id
    JOIN farmer f ON p.farmer_id = f.farmer_id
    WHERE 1=1 $date_sql
    GROUP BY f.farmer_id, f.name
    ORDER BY admin_profit DESC";

$farmer_revenues = $conn->query($farmer_revenue_query)->fetch_all(MYSQLI_ASSOC);

// Get monthly trend
$monthly_trend_query = "SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    COUNT(DISTINCT order_id) as orders,
    SUM(quantity) as items_sold,
    SUM(profit_amount) as profit
    FROM admin_revenue
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12";

$monthly_trends = $conn->query($monthly_trend_query)->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
?>

<div class="main-wrapper">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main-content">
  <div style="max-width:1400px;margin:0 auto">
    
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
      <h2 style="margin:0"><span class="material-icons" aria-hidden="true">attach_money</span> Revenue & Profit Report</h2>
      <a href="products.php" class="btn btn-ghost">← Back to Products</a>
    </div>

    <!-- Date Filter -->
    <div class="card" style="margin-bottom:24px">
      <div style="display:flex;gap:12px;flex-wrap:wrap">
        <a href="revenue.php?date=all" 
           class="btn <?php echo $date_filter === 'all' ? 'btn-primary' : 'btn-ghost'; ?>">
          All Time
        </a>
        <a href="revenue.php?date=today" 
           class="btn <?php echo $date_filter === 'today' ? 'btn-primary' : 'btn-ghost'; ?>">
          Today
        </a>
        <a href="revenue.php?date=week" 
           class="btn <?php echo $date_filter === 'week' ? 'btn-primary' : 'btn-ghost'; ?>">
          Last 7 Days
        </a>
        <a href="revenue.php?date=month" 
           class="btn <?php echo $date_filter === 'month' ? 'btn-primary' : 'btn-ghost'; ?>">
          Last 30 Days
        </a>
        <a href="revenue.php?date=year" 
           class="btn <?php echo $date_filter === 'year' ? 'btn-primary' : 'btn-ghost'; ?>">
          Last Year
        </a>
      </div>
    </div>

    <!-- Overall Statistics -->
    <div style="display:flex;gap:20px;margin-bottom:32px;flex-wrap:nowrap;overflow-x:auto;align-items:stretch">
      <div class="card" style="background:linear-gradient(135deg, #10b981 0%, #059669 100%);color:white;min-width:220px;flex:1 0 220px">
        <div class="small" style="opacity:0.9">Admin Profit</div>
        <div style="font-size:2.5rem;font-weight:700;margin:8px 0">
          Rs<?php echo number_format($overall_stats['total_admin_profit'] ?? 0, 2); ?>
        </div>
        <div class="small" style="opacity:0.9">From completed orders</div>
      </div>
      
      <div class="card" style="background:linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);color:white;min-width:220px;flex:1 0 220px">
        <div class="small" style="opacity:0.9">Total Orders</div>
        <div style="font-size:2.5rem;font-weight:700;margin:8px 0">
          <?php echo $overall_stats['total_orders'] ?? 0; ?>
        </div>
        <div class="small" style="opacity:0.9">Completed orders</div>
      </div>
      
      <div class="card" style="background:linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);color:white;min-width:220px;flex:1 0 220px">
        <div class="small" style="opacity:0.9">Items Sold</div>
        <div style="font-size:2.5rem;font-weight:700;margin:8px 0">
          <?php echo $overall_stats['total_items_sold'] ?? 0; ?>
        </div>
        <div class="small" style="opacity:0.9">Total units</div>
      </div>
      
      <div class="card" style="background:#dbeafe;border-left:4px solid #3b82f6;min-width:220px;flex:1 0 220px">
        <div class="small-muted">Farmer Revenue</div>
        <div style="font-size:2rem;font-weight:700;color:#3b82f6;margin:8px 0">
          Rs<?php echo number_format($overall_stats['total_farmer_revenue'] ?? 0, 0); ?>
        </div>
        <div class="small-muted">Paid to farmers</div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:32px">
      <!-- Product Performance -->
      <div class="card">
        <h3 style="margin-top:0"><span class="material-icons" aria-hidden="true">inventory_2</span> Revenue by Product</h3>
        
        <?php if (!empty($product_revenues)): ?>
          <div style="overflow-x:auto">
            <table style="width:100%;font-size:0.9rem">
              <thead>
                <tr style="background:#f9fafb;border-bottom:2px solid #e5e7eb">
                  <th style="padding:8px;text-align:left">Product</th>
                  <th style="padding:8px;text-align:center">Sold</th>
                  <th style="padding:8px;text-align:center">Profit/Unit</th>
                  <th style="padding:8px;text-align:right">Total Profit</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach (array_slice($product_revenues, 0, 10) as $prod): ?>
                  <tr style="border-bottom:1px solid #e5e7eb">
                    <td style="padding:8px">
                      <div style="font-weight:600"><?php echo htmlspecialchars($prod['product_name']); ?></div>
                      <div class="small-muted"><?php echo htmlspecialchars($prod['farmer_name']); ?></div>
                    </td>
                    <td style="padding:8px;text-align:center">
                      <?php echo $prod['total_sold']; ?> <?php echo htmlspecialchars($prod['unit']); ?>
                    </td>
                    <td style="padding:8px;text-align:center">
                      <span style="color:#10b981;font-weight:600">
                        Rs<?php echo number_format($prod['profit_per_unit'], 2); ?>
                      </span>
                    </td>
                    <td style="padding:8px;text-align:right;font-weight:700;color:#10b981">
                      Rs<?php echo number_format($prod['total_profit'], 2); ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="small-muted">No sales data available</p>
        <?php endif; ?>
      </div>

      <!-- Farmer Performance -->
      <div class="card">
        <h3 style="margin-top:0"><span class="material-icons" aria-hidden="true">local_farm</span> Revenue by Farmer</h3>
        
        <?php if (!empty($farmer_revenues)): ?>
          <div style="overflow-x:auto">
            <table style="width:100%;font-size:0.9rem">
              <thead>
                <tr style="background:#f9fafb;border-bottom:2px solid #e5e7eb">
                  <th style="padding:8px;text-align:left">Farmer</th>
                  <th style="padding:8px;text-align:center">Orders</th>
                  <th style="padding:8px;text-align:right">Admin Profit</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($farmer_revenues as $farmer): ?>
                  <tr style="border-bottom:1px solid #e5e7eb">
                    <td style="padding:8px">
                      <div style="font-weight:600"><?php echo htmlspecialchars($farmer['farmer_name']); ?></div>
                      <div class="small-muted"><?php echo $farmer['items_sold']; ?> items sold</div>
                    </td>
                    <td style="padding:8px;text-align:center">
                      <?php echo $farmer['orders_count']; ?>
                    </td>
                    <td style="padding:8px;text-align:right;font-weight:700;color:#10b981">
                      Rs<?php echo number_format($farmer['admin_profit'], 2); ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="small-muted">No farmer data available</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Monthly Trend -->
    <?php if (!empty($monthly_trends)): ?>
      <div class="card">
        <h3 style="margin-top:0"><span class="material-icons" aria-hidden="true">trending_up</span> Monthly Profit Trend (Last 12 Months)</h3>
        
        <div style="overflow-x:auto">
          <table style="width:100%">
            <thead>
              <tr style="background:#f9fafb;border-bottom:2px solid #e5e7eb">
                <th style="padding:12px;text-align:left">Month</th>
                <th style="padding:12px;text-align:center">Orders</th>
                <th style="padding:12px;text-align:center">Items Sold</th>
                <th style="padding:12px;text-align:right">Profit</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($monthly_trends as $trend): ?>
                <tr style="border-bottom:1px solid #e5e7eb">
                  <td style="padding:12px;font-weight:600">
                    <?php echo date('F Y', strtotime($trend['month'] . '-01')); ?>
                  </td>
                  <td style="padding:12px;text-align:center">
                    <?php echo $trend['orders']; ?>
                  </td>
                  <td style="padding:12px;text-align:center">
                    <?php echo $trend['items_sold']; ?>
                  </td>
                  <td style="padding:12px;text-align:right;font-weight:700;color:#10b981">
                    Rs<?php echo number_format($trend['profit'], 2); ?>
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
