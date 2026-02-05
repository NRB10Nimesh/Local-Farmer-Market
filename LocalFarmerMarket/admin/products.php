<?php
// admin/products.php - Updated with commission system
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login_admin.php");
    exit();
}

require_once '../db.php';

$admin_id = intval($_SESSION['admin_id']);
$page_title = 'Products Management - Admin Panel';
$message = '';
$errors = [];

// Handle product approval/rejection with commission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'approve_product') {
        $product_id = intval($_POST['product_id']);
        $commission_rate = floatval($_POST['commission_rate']);
        
        // Validate commission rate
        if ($commission_rate < 5 || $commission_rate > 10) {
            $errors[] = "Commission rate must be between 5% and 10%";
        } else {
            // Get farmer's price
            $farmer_price_query = $conn->prepare("SELECT price FROM products WHERE product_id = ?");
            $farmer_price_query->bind_param("i", $product_id);
            $farmer_price_query->execute();
            $farmer_price = $farmer_price_query->get_result()->fetch_assoc()['price'];
            $farmer_price_query->close();
            
            // Calculate admin price based on commission
            $commission_multiplier = 1 + ($commission_rate / 100);
            $admin_price = $farmer_price * $commission_multiplier;
            
            $stmt = $conn->prepare("UPDATE products SET approval_status = 'approved', admin_price = ?, commission_rate = ?, approved_by = ?, approved_at = NOW() WHERE product_id = ?");
            $stmt->bind_param("ddii", $admin_price, $commission_rate, $admin_id, $product_id);
            
            if ($stmt->execute()) {
                $commission_amount = $admin_price - $farmer_price;
                $message = "Product approved! Commission: {$commission_rate}% (Rs" . number_format($commission_amount, 2) . " per unit)";
            } else {
                $errors[] = "Failed to approve product.";
            }
            $stmt->close();
        }
    }
    elseif ($_POST['action'] === 'update_commission') {
        $product_id = intval($_POST['product_id']);
        $new_commission = floatval($_POST['new_commission']);
        
        if ($new_commission < 5 || $new_commission > 10) {
            $errors[] = "Commission rate must be between 5% and 10%";
        } else {
            // Get farmer price and recalculate admin price
            $check_query = $conn->prepare("SELECT price FROM products WHERE product_id = ?");
            $check_query->bind_param("i", $product_id);
            $check_query->execute();
            $farmer_price = $check_query->get_result()->fetch_assoc()['price'];
            $check_query->close();
            
            $new_admin_price = $farmer_price * (1 + ($new_commission / 100));
            
            $stmt = $conn->prepare("UPDATE products SET commission_rate = ?, admin_price = ? WHERE product_id = ?");
            $stmt->bind_param("ddi", $new_commission, $new_admin_price, $product_id);
            
            if ($stmt->execute()) {
                $message = "Commission updated successfully!";
            } else {
                $errors[] = "Failed to update commission.";
            }
            $stmt->close();
        }
    }
    elseif ($_POST['action'] === 'reject_product') {
        $product_id = intval($_POST['product_id']);
        $rejection_reason = trim($_POST['rejection_reason'] ?? '');
        
        $stmt = $conn->prepare("UPDATE products SET approval_status = 'rejected', rejection_reason = ?, approved_by = ?, approved_at = NOW() WHERE product_id = ?");
        $stmt->bind_param("sii", $rejection_reason, $admin_id, $product_id);
        
        if ($stmt->execute()) {
            $message = "Product rejected.";
        } else {
            $errors[] = "Failed to reject product.";
        }
        $stmt->close();
    }
    elseif ($_POST['action'] === 'update_stock') {
        $product_id = intval($_POST['product_id']);
        $new_stock = intval($_POST['new_stock']);
        
        if ($new_stock >= 0) {
            $stmt = $conn->prepare("UPDATE products SET quantity = ?, total_stock = GREATEST(total_stock, ?) WHERE product_id = ?");
            $stmt->bind_param("iii", $new_stock, $new_stock, $product_id);
            
            if ($stmt->execute()) {
                $message = "Stock updated successfully!";
            } else {
                $errors[] = "Failed to update stock.";
            }
            $stmt->close();
        }
    }
    elseif ($_POST['action'] === 'delete_product') {
        $product_id = intval($_POST['product_id']);
        
        $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
        $stmt->bind_param("i", $product_id);
        
        if ($stmt->execute()) {
            $message = "Product deleted successfully!";
        } else {
            $errors[] = "Failed to delete product.";
        }
        $stmt->close();
    }
}

// Filter parameters
$status_filter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');
$category_filter = trim($_GET['category'] ?? '');

$sql = "SELECT p.*, f.name as farmer_name, f.contact as farmer_contact,
        COALESCE(p.admin_price, p.price) as display_price,
        COALESCE(p.commission_rate, 5.00) as commission_rate,
        (p.admin_price - p.price) as commission_amount,
        (p.total_stock - p.quantity) as total_sold
        FROM products p 
        JOIN farmer f ON p.farmer_id = f.farmer_id 
        WHERE 1=1";

$params = [];
$types = "";

if ($status_filter && in_array($status_filter, ['pending', 'approved', 'rejected'])) {
    $sql .= " AND p.approval_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($search !== '') {
    $sql .= " AND (p.product_name LIKE ? OR f.name LIKE ?)";
    $pattern = "%{$search}%";
    $params[] = $pattern;
    $params[] = $pattern;
    $types .= "ss";
}

if ($category_filter !== '') {
    $sql .= " AND p.category = ?";
    $params[] = $category_filter;
    $types .= "s";
}

$sql .= " ORDER BY 
    CASE p.approval_status 
        WHEN 'pending' THEN 1 
        WHEN 'approved' THEN 2 
        WHEN 'rejected' THEN 3 
    END, 
    p.created_at DESC";

$stmt = $conn->prepare($sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get categories with default commission rates
$categories = $conn->query("SELECT DISTINCT p.category, COALESCE(cs.default_commission_rate, 5.00) as default_rate 
                            FROM products p 
                            LEFT JOIN commission_settings cs ON p.category = cs.category 
                            ORDER BY p.category")->fetch_all(MYSQLI_ASSOC);

// Get statistics with commission calculations
$stats = [
    'pending' => $conn->query("SELECT COUNT(*) as c FROM products WHERE approval_status = 'pending'")->fetch_assoc()['c'],
    'approved' => $conn->query("SELECT COUNT(*) as c FROM products WHERE approval_status = 'approved'")->fetch_assoc()['c'],
    'rejected' => $conn->query("SELECT COUNT(*) as c FROM products WHERE approval_status = 'rejected'")->fetch_assoc()['c'],
    'low_stock' => $conn->query("SELECT COUNT(*) as c FROM products WHERE quantity < 10 AND approval_status = 'approved'")->fetch_assoc()['c']
];

// Calculate commission stats
$commission_query = "SELECT 
    SUM((admin_price - price) * quantity) as potential_commission,
    AVG(commission_rate) as avg_commission_rate,
    SUM(admin_price * quantity) as total_value
    FROM products 
    WHERE approval_status = 'approved' AND admin_price IS NOT NULL";
$commission_stats = $conn->query($commission_query)->fetch_assoc();

include 'inlcudes/header.php';
?>

<div class="wrap">
  <div style="max-width:1400px;margin:0 auto">
    
    <!-- Statistics Cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px">
      <div class="card" style="background:#fef3c7;border-left:4px solid #f59e0b">
        <div class="small-muted">⏳ Pending Approval</div>
        <div style="font-size:2rem;font-weight:700;color:#f59e0b"><?php echo $stats['pending']; ?></div>
      </div>
      
      <div class="card" style="background:#d1fae5;border-left:4px solid #16a34a">
        <div class="small-muted">✅ Approved</div>
        <div style="font-size:2rem;font-weight:700;color:#16a34a"><?php echo $stats['approved']; ?></div>
      </div>
      
      <div class="card" style="background:#fee2e2;border-left:4px solid #ef4444">
        <div class="small-muted">❌ Rejected</div>
        <div style="font-size:2rem;font-weight:700;color:#ef4444"><?php echo $stats['rejected']; ?></div>
      </div>
      
      <div class="card" style="background:#dbeafe;border-left:4px solid #3b82f6">
        <div class="small-muted">⚠️ Low Stock</div>
        <div style="font-size:2rem;font-weight:700;color:#3b82f6"><?php echo $stats['low_stock']; ?></div>
      </div>
      
      <div class="card" style="background:linear-gradient(135deg, #10b981 0%, #059669 100%);color:white">
        <div class="small" style="opacity:0.9">💰 Potential Commission</div>
        <div style="font-size:1.5rem;font-weight:700;margin:4px 0">
          Rs<?php echo number_format($commission_stats['potential_commission'] ?? 0, 0); ?>
        </div>
        <div class="small" style="opacity:0.9">
          Avg rate: <?php echo number_format($commission_stats['avg_commission_rate'] ?? 5, 1); ?>%
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
        <h2 style="margin:0">📦 Product Management</h2>
        <div style="display:flex;gap:12px">
          <a href="commission_settings.php" class="btn btn-ghost">⚙️ Commission Settings</a>
          <a href="revenue.php" class="btn btn-primary">💰 Revenue Report</a>
        </div>
      </div>

      <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
      <?php endif; ?>
      <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div>
      <?php endforeach; ?>

      <!-- Filters -->
      <div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap">
        <input type="text" id="searchInput" placeholder="Search products or farmers..." 
               value="<?php echo htmlspecialchars($search); ?>"
               style="flex:1;min-width:200px;padding:10px;border:2px solid #e5e7eb;border-radius:8px">
        
        <select id="statusFilter" class="select">
          <option value="">All Status</option>
          <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
          <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
          <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
        </select>
        
        <select id="categoryFilter" class="select">
          <option value="">All Categories</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                    <?php echo $category_filter === $cat['category'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($cat['category']); ?> (<?php echo $cat['default_rate']; ?>%)
            </option>
          <?php endforeach; ?>
        </select>
        
        <button class="btn btn-primary" onclick="applyFilters()">Apply</button>
        
        <?php if ($status_filter || $search || $category_filter): ?>
          <a href="products.php" class="btn btn-ghost">Clear</a>
        <?php endif; ?>
      </div>

      <!-- Products List -->
      <?php if (!empty($products)): ?>
        <div style="overflow-x:auto">
          <table style="width:100%;border-collapse:collapse">
            <thead>
              <tr style="background:#f9fafb;border-bottom:2px solid #e5e7eb">
                <th style="padding:12px;text-align:left">Product</th>
                <th style="padding:12px;text-align:left">Farmer</th>
                <th style="padding:12px;text-align:center">Farmer Price</th>
                <th style="padding:12px;text-align:center">Commission</th>
                <th style="padding:12px;text-align:center">Buyer Price</th>
                <th style="padding:12px;text-align:center">Stock</th>
                <th style="padding:12px;text-align:center">Status</th>
                <th style="padding:12px;text-align:center">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($products as $product): ?>
                <tr style="border-bottom:1px solid #e5e7eb">
                  <td style="padding:12px">
                    <div style="display:flex;gap:12px;align-items:center">
                      <?php if ($product['image'] && file_exists(__DIR__ . '/../uploads/' . $product['image'])): ?>
                        <img src="../uploads/<?php echo htmlspecialchars($product['image']); ?>" 
                             style="width:50px;height:50px;object-fit:cover;border-radius:6px">
                      <?php else: ?>
                        <div style="width:50px;height:50px;background:#f3f4f6;border-radius:6px;display:flex;align-items:center;justify-content:center">📦</div>
                      <?php endif; ?>
                      <div>
                        <div style="font-weight:700"><?php echo htmlspecialchars($product['product_name']); ?></div>
                        <div class="small-muted"><?php echo htmlspecialchars($product['category']); ?> • <?php echo htmlspecialchars($product['unit']); ?></div>
                      </div>
                    </div>
                  </td>
                  
                  <td style="padding:12px">
                    <div style="font-weight:600"><?php echo htmlspecialchars($product['farmer_name']); ?></div>
                    <div class="small-muted"><?php echo htmlspecialchars($product['farmer_contact']); ?></div>
                  </td>
                  
                  <td style="padding:12px;text-align:center">
                    <div style="font-weight:700">Rs<?php echo number_format($product['price'], 2); ?></div>
                  </td>
                  
                  <td style="padding:12px;text-align:center">
                    <div style="font-weight:700;color:#f59e0b">
                      <?php echo number_format($product['commission_rate'], 1); ?>%
                    </div>
                    <?php if ($product['commission_amount']): ?>
                      <div class="small-muted">Rs<?php echo number_format($product['commission_amount'], 2); ?>/unit</div>
                    <?php endif; ?>
                  </td>
                  
                  <td style="padding:12px;text-align:center">
                    <?php if ($product['admin_price']): ?>
                      <div style="font-weight:700;color:#16a34a">Rs<?php echo number_format($product['admin_price'], 2); ?></div>
                    <?php else: ?>
                      <span class="small-muted">Not set</span>
                    <?php endif; ?>
                  </td>
                  
                  <td style="padding:12px;text-align:center">
                    <div style="<?php echo $product['quantity'] < 10 ? 'color:#ef4444;font-weight:700' : 'font-weight:600'; ?>">
                      <?php echo $product['quantity']; ?> / <?php echo $product['total_stock']; ?>
                    </div>
                    <div class="small-muted">Sold: <?php echo $product['total_sold']; ?></div>
                  </td>
                  
                  <td style="padding:12px;text-align:center">
                    <span class="badge badge-<?php 
                      echo $product['approval_status'] === 'approved' ? 'success' : 
                           ($product['approval_status'] === 'pending' ? 'warning' : 'danger'); 
                    ?>">
                      <?php echo ucfirst($product['approval_status']); ?>
                    </span>
                  </td>
                  
                  <td style="padding:12px;text-align:center">
                    <div style="display:flex;gap:4px;justify-content:center;flex-wrap:wrap">
                      <?php if ($product['approval_status'] === 'pending'): ?>
                        <button class="btn btn-primary small-btn" 
                                onclick='approveProduct(<?php echo json_encode($product); ?>)'>
                          Approve
                        </button>
                        <button class="btn btn-danger small-btn" 
                                onclick='rejectProduct(<?php echo $product['product_id']; ?>)'>
                          Reject
                        </button>
                      <?php elseif ($product['approval_status'] === 'approved'): ?>
                        <button class="btn btn-ghost small-btn" 
                                onclick='updateCommission(<?php echo json_encode($product); ?>)'>
                          Update Commission
                        </button>
                        <button class="btn btn-ghost small-btn" 
                                onclick='updateStock(<?php echo json_encode($product); ?>)'>
                          Update Stock
                        </button>
                      <?php endif; ?>
                      <form method="POST" style="display:inline" onsubmit="return confirm('Delete this product?')">
                        <input type="hidden" name="action" value="delete_product">
                        <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                        <button class="btn btn-danger small-btn">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div style="text-align:center;padding:40px">
          <p>No products found.</p>
        </div>
      <?php endif; ?>
    </div>

  </div>
</div>

 <?php include 'inlcudes/sidebar.php'?>

<!-- Approve Product Modal -->
<div id="approveModal" class="modal">
  <div class="modal-body">
    <h3>Approve Product</h3>
    <form method="POST" id="approveForm">
      <input type="hidden" name="action" value="approve_product">
      <input type="hidden" name="product_id" id="approve_product_id">
      
      <div id="approve_product_info" style="background:#f9fafb;padding:12px;border-radius:8px;margin-bottom:16px"></div>
      
      <label>Set Commission Rate (%) *</label>
      <input type="number" name="commission_rate" id="commission_rate" step="0.1" min="5" max="10" value="5" required>
      <div class="small-muted">Commission must be between 5% and 10%</div>
      
      <div id="price_breakdown" style="background:#f0fdf4;border:2px solid #10b981;border-radius:8px;padding:12px;margin-top:16px"></div>
      
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('approveModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Approve Product</button>
      </div>
    </form>
  </div>
</div>

<!-- Reject Product Modal -->
<div id="rejectModal" class="modal">
  <div class="modal-body">
    <h3>Reject Product</h3>
    <form method="POST">
      <input type="hidden" name="action" value="reject_product">
      <input type="hidden" name="product_id" id="reject_product_id">
      
      <label>Reason for Rejection (Optional)</label>
      <textarea name="rejection_reason" rows="3" placeholder="Enter reason..."></textarea>
      
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('rejectModal')">Cancel</button>
        <button type="submit" class="btn btn-danger">Reject Product</button>
      </div>
    </form>
  </div>
</div>

<!-- Update Commission Modal -->
<div id="commissionModal" class="modal">
  <div class="modal-body">
    <h3>Update Commission Rate</h3>
    <form method="POST">
      <input type="hidden" name="action" value="update_commission">
      <input type="hidden" name="product_id" id="commission_product_id">
      <input type="hidden" id="commission_farmer_price">
      
      <div id="commission_product_info" style="background:#f9fafb;padding:12px;border-radius:8px;margin-bottom:16px"></div>
      
      <label>New Commission Rate (%) *</label>
      <input type="number" name="new_commission" id="new_commission" step="0.1" min="5" max="10" required>
      <div class="small-muted">Commission must be between 5% and 10%</div>
      
      <div id="new_price_breakdown" style="background:#f0fdf4;border:2px solid #10b981;border-radius:8px;padding:12px;margin-top:16px"></div>
      
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('commissionModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Update Commission</button>
      </div>
    </form>
  </div>
</div>

<!-- Update Stock Modal -->
<div id="stockModal" class="modal">
  <div class="modal-body">
    <h3>Update Stock Quantity</h3>
    <form method="POST">
      <input type="hidden" name="action" value="update_stock">
      <input type="hidden" name="product_id" id="stock_product_id">
      
      <div id="stock_product_info" style="background:#f9fafb;padding:12px;border-radius:8px;margin-bottom:16px"></div>
      
      <label>New Stock Quantity *</label>
      <input type="number" name="new_stock" id="new_stock" min="0" required>
      <div class="small-muted">This will update the available stock for buyers</div>
      
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('stockModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Update Stock</button>
      </div>
    </form>
  </div>
</div>

<script src="../assets/js/script.js"></script>
<script>
function applyFilters() {
  const search = document.getElementById('searchInput').value;
  const status = document.getElementById('statusFilter').value;
  const category = document.getElementById('categoryFilter').value;
  window.location.href = 'products.php?search=' + encodeURIComponent(search) + 
                         '&status=' + encodeURIComponent(status) + 
                         '&category=' + encodeURIComponent(category);
}

function approveProduct(product) {
  document.getElementById('approve_product_id').value = product.product_id;
  
  document.getElementById('approve_product_info').innerHTML = `
    <div><strong>Product:</strong> ${escapeHtml(product.product_name)}</div>
    <div><strong>Farmer:</strong> ${escapeHtml(product.farmer_name)}</div>
    <div><strong>Farmer's Price:</strong> Rs${parseFloat(product.price).toFixed(2)}</div>
    <div><strong>Category:</strong> ${escapeHtml(product.category)}</div>
  `;
  
  // Calculate prices on commission change
  const commissionInput = document.getElementById('commission_rate');
  const breakdown = document.getElementById('price_breakdown');
  
  function updateBreakdown() {
    const commission = parseFloat(commissionInput.value) || 5;
    const farmerPrice = parseFloat(product.price);
    const buyerPrice = farmerPrice * (1 + (commission / 100));
    const commissionAmount = buyerPrice - farmerPrice;
    
    breakdown.innerHTML = `
      <div style="font-weight:700;margin-bottom:8px;color:#10b981">Price Breakdown</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
        <div>
          <div class="small-muted">Farmer Price</div>
          <div style="font-weight:600">Rs${farmerPrice.toFixed(2)}</div>
        </div>
        <div>
          <div class="small-muted">Commission (${commission}%)</div>
          <div style="font-weight:600;color:#f59e0b">Rs${commissionAmount.toFixed(2)}</div>
        </div>
        <div>
          <div class="small-muted">Buyer Price</div>
          <div style="font-weight:700;color:#16a34a">Rs${buyerPrice.toFixed(2)}</div>
        </div>
        <div>
          <div class="small-muted">Per Unit Profit</div>
          <div style="font-weight:700;color:#10b981">Rs${commissionAmount.toFixed(2)}</div>
        </div>
      </div>
    `;
  }
  
  commissionInput.addEventListener('input', updateBreakdown);
  updateBreakdown();
  
  openModal('approveModal');
}

function rejectProduct(productId) {
  document.getElementById('reject_product_id').value = productId;
  openModal('rejectModal');
}

function updateCommission(product) {
  document.getElementById('commission_product_id').value = product.product_id;
  document.getElementById('new_commission').value = product.commission_rate;
  document.getElementById('commission_farmer_price').value = product.price;
  
  document.getElementById('commission_product_info').innerHTML = `
    <div><strong>Product:</strong> ${escapeHtml(product.product_name)}</div>
    <div><strong>Farmer's Price:</strong> Rs${parseFloat(product.price).toFixed(2)}</div>
    <div><strong>Current Commission:</strong> ${parseFloat(product.commission_rate).toFixed(1)}%</div>
    <div><strong>Current Buyer Price:</strong> Rs${parseFloat(product.admin_price).toFixed(2)}</div>
  `;
  
  // Calculate new prices on commission change
  const commissionInput = document.getElementById('new_commission');
  const breakdown = document.getElementById('new_price_breakdown');
  
  function updateNewBreakdown() {
    const commission = parseFloat(commissionInput.value) || 5;
    const farmerPrice = parseFloat(product.price);
    const buyerPrice = farmerPrice * (1 + (commission / 100));
    const commissionAmount = buyerPrice - farmerPrice;
    
    breakdown.innerHTML = `
      <div style="font-weight:700;margin-bottom:8px;color:#10b981">New Price Breakdown</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
        <div>
          <div class="small-muted">Farmer Price</div>
          <div style="font-weight:600">Rs${farmerPrice.toFixed(2)}</div>
        </div>
        <div>
          <div class="small-muted">Commission (${commission}%)</div>
          <div style="font-weight:600;color:#f59e0b">Rs${commissionAmount.toFixed(2)}</div>
        </div>
        <div>
          <div class="small-muted">New Buyer Price</div>
          <div style="font-weight:700;color:#16a34a">Rs${buyerPrice.toFixed(2)}</div>
        </div>
        <div>
          <div class="small-muted">Per Unit Profit</div>
          <div style="font-weight:700;color:#10b981">Rs${commissionAmount.toFixed(2)}</div>
        </div>
      </div>
    `;
  }