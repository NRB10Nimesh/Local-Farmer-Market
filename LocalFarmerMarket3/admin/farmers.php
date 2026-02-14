<?php
// admin/farmers.php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../db.php';

$admin_id = intval($_SESSION['admin_id']);
$page_title = 'Farmers Management - Admin Panel';
$message = '';
$errors = [];

// Handle farmer actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'toggle_status') {
        $farmer_id = intval($_POST['farmer_id']);
        $new_status = intval($_POST['new_status']);
        
        $stmt = $conn->prepare("UPDATE farmer SET is_active = ? WHERE farmer_id = ?");
        $stmt->bind_param("ii", $new_status, $farmer_id);
        
        if ($stmt->execute()) {
            $message = $new_status ? "Farmer activated successfully!" : "Farmer deactivated successfully!";
        } else {
            $errors[] = "Failed to update farmer status.";
        }
        $stmt->close();
    }
    elseif ($_POST['action'] === 'delete_farmer') {
        $farmer_id = intval($_POST['farmer_id']);
        
        // Check if farmer has products
        $check = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE farmer_id = ?");
        $check->bind_param("i", $farmer_id);
        $check->execute();
        $has_products = $check->get_result()->fetch_assoc()['count'] > 0;
        $check->close();
        
        if ($has_products) {
            $errors[] = "Cannot delete farmer with existing products. Please delete products first.";
        } else {
            $stmt = $conn->prepare("DELETE FROM farmer WHERE farmer_id = ?");
            $stmt->bind_param("i", $farmer_id);
            
            if ($stmt->execute()) {
                $message = "Farmer deleted successfully!";
            } else {
                $errors[] = "Failed to delete farmer.";
            }
            $stmt->close();
        }
    }
}

// Filter parameters
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';

$sql = "SELECT f.*, 
        (SELECT COUNT(*) FROM products WHERE farmer_id = f.farmer_id) as product_count,
        (SELECT COUNT(*) FROM products WHERE farmer_id = f.farmer_id AND approval_status = 'approved') as approved_products,
        (SELECT SUM(od.quantity * od.price) 
         FROM order_details od 
         JOIN products p ON od.product_id = p.product_id 
         JOIN orders o ON od.order_id = o.order_id
         WHERE p.farmer_id = f.farmer_id AND o.status = 'Delivered') as total_revenue
        FROM farmer f 
        WHERE 1=1";

$params = [];
$types = "";

if ($search !== '') {
    $sql .= " AND (f.name LIKE ? OR f.contact LIKE ? OR f.address LIKE ?)";
    $pattern = "%{$search}%";
    $params[] = $pattern;
    $params[] = $pattern;
    $params[] = $pattern;
    $types .= "sss";
}

if ($status_filter === 'active') {
    $sql .= " AND f.is_active = 1";
} elseif ($status_filter === 'inactive') {
    $sql .= " AND f.is_active = 0";
}

$sql .= " ORDER BY f.created_at DESC";

$stmt = $conn->prepare($sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$farmers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get statistics
$stats = [
    'total' => $conn->query("SELECT COUNT(*) as c FROM farmer")->fetch_assoc()['c'],
    'active' => $conn->query("SELECT COUNT(*) as c FROM farmer WHERE is_active = 1")->fetch_assoc()['c'],
    'inactive' => $conn->query("SELECT COUNT(*) as c FROM farmer WHERE is_active = 0")->fetch_assoc()['c']
];

?>

<?php include 'includes/header.php'; ?>

<div class="main-wrapper">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main-content">
  <div style="max-width:1400px;margin:0 auto">
    
    <!-- Statistics Cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px">
      <div class="card" style="background:#dbeafe;border-left:4px solid #3b82f6">
        <div class="small-muted"><span class="material-icons">groups</span> Total Farmers</div>
        <div style="font-size:2rem;font-weight:700;color:#3b82f6"><?php echo $stats['total']; ?></div>
      </div>
      
      <div class="card" style="background:#d1fae5;border-left:4px solid #16a34a">
        <div class="small-muted"><span class="material-icons">check_circle</span> Active</div>
        <div style="font-size:2rem;font-weight:700;color:#16a34a"><?php echo $stats['active']; ?></div>
      </div>
      
      <div class="card" style="background:#fee2e2;border-left:4px solid #ef4444">
        <div class="small-muted"><span class="material-icons">cancel</span> Inactive</div>
        <div style="font-size:2rem;font-weight:700;color:#ef4444"><?php echo $stats['inactive']; ?></div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="card">
      <h2 style="margin-top:0"><span class="material-icons">local_farm</span> Farmers Management</h2>

      <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
      <?php endif; ?>
      <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div>
      <?php endforeach; ?>

      <!-- Filters -->
      <div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap">
        <input type="text" id="searchInput" placeholder="Search farmers..." 
               value="<?php echo htmlspecialchars($search); ?>"
               style="flex:1;min-width:200px;padding:10px;border:2px solid #e5e7eb;border-radius:8px">
        
        <select id="statusFilter" class="select">
          <option value="">All Status</option>
          <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
          <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
        </select>
        
        <button class="btn btn-primary" onclick="applyFilters()">Apply</button>
        
        <?php if ($status_filter || $search): ?>
          <a href="farmers.php" class="btn btn-ghost">Clear</a>
        <?php endif; ?>
      </div>

      <!-- Farmers List -->
      <?php if (!empty($farmers)): ?>
        <div style="overflow-x:auto">
          <table class="table-compact" style="width:100%;border-collapse:collapse">
            <thead>
              <tr style="background:#f9fafb;border-bottom:2px solid #e5e7eb">
                <th style="padding:12px;text-align:left">Farmer</th>
                <th style="padding:12px;text-align:left">Contact</th>
                <th style="padding:12px;text-align:left">Location</th>
                <th style="padding:12px;text-align:center">Products</th>
                <th style="padding:12px;text-align:center">Revenue</th>
                <th style="padding:12px;text-align:center">Status</th>
                <th style="padding:12px;text-align:center">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($farmers as $farmer): ?>
                <tr style="border-bottom:1px solid #e5e7eb">
                  <td style="padding:12px">
                    <div style="display:flex;gap:12px;align-items:center">
                      <div style="width:50px;height:50px;background:linear-gradient(135deg,#d1fae5,#a7f3d0);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.5rem">
                        <span class="material-icons" aria-hidden="true">local_farm</span>
                      </div>
                      <div>
                        <div style="font-weight:700"><?php echo htmlspecialchars($farmer['name']); ?></div>
                        <div class="small-muted">ID: #<?php echo str_pad($farmer['farmer_id'], 4, '0', STR_PAD_LEFT); ?></div>
                      </div>
                    </div>
                  </td>
                  
                  <td style="padding:12px">
                    <div><?php echo htmlspecialchars($farmer['contact']); ?></div>
                  </td>
                  
                  <td style="padding:12px">
                    <div><?php echo htmlspecialchars($farmer['address']); ?></div>
                    <?php if ($farmer['farm_type']): ?>
                      <div class="small-muted">Farm: <?php echo htmlspecialchars($farmer['farm_type']); ?></div>
                    <?php endif; ?>
                  </td>
                  
                  <td style="padding:12px;text-align:center">
                    <div style="font-weight:700"><?php echo $farmer['product_count']; ?> total</div>
                    <div class="small-muted"><?php echo $farmer['approved_products']; ?> approved</div>
                  </td>
                  
                  <td style="padding:12px;text-align:center">
                    <div style="font-weight:700;color:#16a34a">
                      Rs<?php echo number_format($farmer['total_revenue'] ?? 0, 0); ?>
                    </div>
                  </td>
                  
                  <td style="padding:12px;text-align:center">
                    <?php if ($farmer['is_active']): ?>
                      <span class="badge badge-success">Active</span>
                    <?php else: ?>
                      <span class="badge badge-danger">Inactive</span>
                    <?php endif; ?>
                  </td>
                  
                  <td style="padding:12px;text-align:center">
                    <div class="actions-cell">
                      <button class="btn-icon btn-ghost" onclick='viewFarmer(<?php echo json_encode($farmer); ?>)' title="View" aria-label="View"><span class="material-icons">visibility</span></button>
                      <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="farmer_id" value="<?php echo $farmer['farmer_id']; ?>">
                        <input type="hidden" name="new_status" value="<?php echo $farmer['is_active'] ? 0 : 1; ?>">
                        <button class="btn-icon <?php echo $farmer['is_active'] ? 'btn-warning' : 'btn-success'; ?>" title="<?php echo $farmer['is_active'] ? 'Deactivate' : 'Activate'; ?>" aria-label="<?php echo $farmer['is_active'] ? 'Deactivate' : 'Activate'; ?>"><?php echo $farmer['is_active'] ? '<span class="material-icons">block</span>' : '<span class="material-icons">check_circle</span>'; ?></button>
                      </form>
                      <form method="POST" style="display:inline" onsubmit="return confirm('Delete this farmer? This cannot be undone.')">
                        <input type="hidden" name="action" value="delete_farmer">
                        <input type="hidden" name="farmer_id" value="<?php echo $farmer['farmer_id']; ?>">
                        <button class="btn-icon btn-danger" title="Delete" aria-label="Delete"><span class="material-icons">delete</span></button>
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
          <p>No farmers found.</p>
        </div>
      <?php endif; ?>

  </div>
  </div>
</div>
</div>

<!-- View Farmer Modal -->
<div id="viewModal" class="modal">
  <div class="modal-body">
    <h3>Farmer Details</h3>
    <div id="farmer_details"></div>
    <div class="modal-footer">
      <button type="button" class="btn btn-ghost" onclick="closeModal('viewModal')">Close</button>
    </div>
  </div>
</div>

<script src="../assets/js/script.js"></script>
<script>
function applyFilters() {
  const search = document.getElementById('searchInput').value;
  const status = document.getElementById('statusFilter').value;
  window.location.href = 'farmers.php?search=' + encodeURIComponent(search) + '&status=' + encodeURIComponent(status);
}

function viewFarmer(farmer) {
  document.getElementById('farmer_details').innerHTML = `
    <div style="background:#f9fafb;padding:16px;border-radius:8px;margin-bottom:16px">
      <div style="display:grid;gap:12px">
        <div>
          <div class="small-muted">Farmer Name</div>
          <div style="font-weight:700">${escapeHtml(farmer.name)}</div>
        </div>
        <div>
          <div class="small-muted">Contact</div>
          <div style="font-weight:600">${escapeHtml(farmer.contact)}</div>
        </div>
        <div>
          <div class="small-muted">Address</div>
          <div>${escapeHtml(farmer.address)}</div>
        </div>
        <div>
          <div class="small-muted">Farm Type</div>
          <div>${escapeHtml(farmer.farm_type || 'Not specified')}</div>
        </div>
        <div>
          <div class="small-muted">Member Since</div>
          <div>${new Date(farmer.created_at).toLocaleDateString('en-US', {year: 'numeric', month: 'long', day: 'numeric'})}</div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;padding-top:12px;border-top:2px solid #e5e7eb">
          <div>
            <div class="small-muted">Total Products</div>
            <div style="font-size:1.5rem;font-weight:700;color:#3b82f6">${farmer.product_count}</div>
          </div>
          <div>
            <div class="small-muted">Total Revenue</div>
            <div style="font-size:1.5rem;font-weight:700;color:#16a34a">Rs${parseFloat(farmer.total_revenue || 0).toFixed(0)}</div>
          </div>
        </div>
      </div>
    </div>
  `;
  openModal('viewModal');
}
</script>
</body>
</html>