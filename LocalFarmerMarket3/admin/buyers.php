<?php
// admin/buyers.php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../db.php';

$admin_id = intval($_SESSION['admin_id']);
$page_title = 'Buyers Management - Admin Panel';
$message = '';
$errors = [];

// Handle buyer actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'toggle_status') {
        $buyer_id = intval($_POST['buyer_id']);
        $new_status = intval($_POST['new_status']);
        
        $stmt = $conn->prepare("UPDATE buyer SET is_active = ? WHERE buyer_id = ?");
        $stmt->bind_param("ii", $new_status, $buyer_id);
        
        if ($stmt->execute()) {
            $message = $new_status ? "Buyer activated successfully!" : "Buyer deactivated successfully!";
        } else {
            $errors[] = "Failed to update buyer status.";
        }
        $stmt->close();
    }
    elseif ($_POST['action'] === 'delete_buyer') {
        $buyer_id = intval($_POST['buyer_id']);
        
        // Check if buyer has orders
        $check = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE buyer_id = ?");
        $check->bind_param("i", $buyer_id);
        $check->execute();
        $has_orders = $check->get_result()->fetch_assoc()['count'] > 0;
        $check->close();
        
        if ($has_orders) {
            $errors[] = "Cannot delete buyer with existing orders.";
        } else {
            $stmt = $conn->prepare("DELETE FROM buyer WHERE buyer_id = ?");
            $stmt->bind_param("i", $buyer_id);
            
            if ($stmt->execute()) {
                $message = "Buyer deleted successfully!";
            } else {
                $errors[] = "Failed to delete buyer.";
            }
            $stmt->close();
        }
    }
}

// Filter parameters
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';

$sql = "SELECT b.*, 
        (SELECT COUNT(*) FROM orders WHERE buyer_id = b.buyer_id) as order_count,
        (SELECT SUM(total_amount) FROM orders WHERE buyer_id = b.buyer_id AND status = 'Delivered') as total_spent
        FROM buyer b 
        WHERE 1=1";

$params = [];
$types = "";

if ($search !== '') {
    $sql .= " AND (b.name LIKE ? OR b.contact LIKE ? OR b.address LIKE ?)";
    $pattern = "%{$search}%";
    $params[] = $pattern;
    $params[] = $pattern;
    $params[] = $pattern;
    $types .= "sss";
}

if ($status_filter === 'active') {
    $sql .= " AND b.is_active = 1";
} elseif ($status_filter === 'inactive') {
    $sql .= " AND b.is_active = 0";
}

$sql .= " ORDER BY b.created_at DESC";

$stmt = $conn->prepare($sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$buyers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get statistics
$stats = [
    'total' => $conn->query("SELECT COUNT(*) as c FROM buyer")->fetch_assoc()['c'],
    'active' => $conn->query("SELECT COUNT(*) as c FROM buyer WHERE is_active = 1")->fetch_assoc()['c'],
    'inactive' => $conn->query("SELECT COUNT(*) as c FROM buyer WHERE is_active = 0")->fetch_assoc()['c']
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
        <div class="small-muted"><span class="material-icons">shopping_cart</span> Total Buyers</div>
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
      <h2 style="margin-top:0"><span class="material-icons">shopping_cart</span> Buyers Management</h2>

      <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
      <?php endif; ?>
      <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div>
      <?php endforeach; ?>

      <!-- Filters -->
      <div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap">
        <input type="text" id="searchInput" placeholder="Search buyers..." 
               value="<?php echo htmlspecialchars($search); ?>"
               style="flex:1;min-width:200px;padding:10px;border:2px solid #e5e7eb;border-radius:8px">
        
        <select id="statusFilter" class="select">
          <option value="">All Status</option>
          <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
          <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
        </select>
        
        <button class="btn btn-primary" onclick="applyFilters()">Apply</button>
        
        <?php if ($status_filter || $search): ?>
          <a href="buyers.php" class="btn btn-ghost">Clear</a>
        <?php endif; ?>
      </div>

      <!-- Buyers List -->
      <?php if (!empty($buyers)): ?>
        <div style="overflow-x:auto">
          <table class="table-compact" style="width:100%;border-collapse:collapse">
            <thead>
              <tr style="background:#f9fafb;border-bottom:2px solid #e5e7eb">
                <th style="padding:12px;text-align:left">Buyer</th>
                <th style="padding:12px;text-align:left">Contact</th>
                <th style="padding:12px;text-align:left">Address</th>
                <th style="padding:12px;text-align:center">Orders</th>
                <th style="padding:12px;text-align:center">Total Spent</th>
                <th style="padding:12px;text-align:center">Status</th>
                <th style="padding:12px;text-align:center">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($buyers as $buyer): ?>
                <tr style="border-bottom:1px solid #e5e7eb">
                  <td style="padding:12px">
                    <div style="display:flex;gap:12px;align-items:center">
                      <div style="width:50px;height:50px;background:linear-gradient(135deg,#dbeafe,#bfdbfe);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.5rem">
                        <span class="material-icons">shopping_cart</span>
                      </div>
                      <div>
                        <div style="font-weight:700"><?php echo htmlspecialchars($buyer['name']); ?></div>
                        <div class="small-muted">ID: #<?php echo str_pad($buyer['buyer_id'], 4, '0', STR_PAD_LEFT); ?></div>
                      </div>
                    </div>
                  </td>
                  
                  <td style="padding:12px">
                    <div><?php echo htmlspecialchars($buyer['contact']); ?></div>
                  </td>
                  
                  <td style="padding:12px">
                    <div><?php echo htmlspecialchars($buyer['address']); ?></div>
                  </td>
                  
                  <td style="padding:12px;text-align:center">
                    <div style="font-weight:700"><?php echo $buyer['order_count']; ?></div>
                  </td>
                  
                  <td style="padding:12px;text-align:center">
                    <div style="font-weight:700;color:#16a34a">
                      Rs<?php echo number_format($buyer['total_spent'] ?? 0, 0); ?>
                    </div>
                  </td>
                  
                  <td style="padding:12px;text-align:center">
                    <?php if ($buyer['is_active']): ?>
                      <span class="badge badge-success">Active</span>
                    <?php else: ?>
                      <span class="badge badge-danger">Inactive</span>
                    <?php endif; ?>
                  </td>
                  
                  <td style="padding:12px;text-align:center">
                    <div class="actions-cell">
                      <button class="btn-icon btn-ghost" onclick='viewBuyer(<?php echo json_encode($buyer); ?>)' title="View" aria-label="View"><span class="material-icons">visibility</span></button>
                      <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="buyer_id" value="<?php echo $buyer['buyer_id']; ?>">
                        <input type="hidden" name="new_status" value="<?php echo $buyer['is_active'] ? 0 : 1; ?>">
                        <button class="btn-icon <?php echo $buyer['is_active'] ? 'btn-warning' : 'btn-success'; ?>" title="<?php echo $buyer['is_active'] ? 'Deactivate' : 'Activate'; ?>" aria-label="<?php echo $buyer['is_active'] ? 'Deactivate' : 'Activate'; ?>"><?php echo $buyer['is_active'] ? '<span class="material-icons">block</span>' : '<span class="material-icons">check_circle</span>'; ?></button>
                      </form>
                      <form method="POST" style="display:inline" onsubmit="return confirm('Delete this buyer? This cannot be undone.')">
                        <input type="hidden" name="action" value="delete_buyer">
                        <input type="hidden" name="buyer_id" value="<?php echo $buyer['buyer_id']; ?>">
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
          <p>No buyers found.</p>
        </div>
      <?php endif; ?>

  </div>
  </div>
</div>
</div>

<!-- View Buyer Modal -->
<div id="viewModal" class="modal">
  <div class="modal-body">
    <h3>Buyer Details</h3>
    <div id="buyer_details"></div>
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
  window.location.href = 'buyers.php?search=' + encodeURIComponent(search) + '&status=' + encodeURIComponent(status);
}

function viewBuyer(buyer) {
  document.getElementById('buyer_details').innerHTML = `
    <div style="background:#f9fafb;padding:16px;border-radius:8px;margin-bottom:16px">
      <div style="display:grid;gap:12px">
        <div>
          <div class="small-muted">Buyer Name</div>
          <div style="font-weight:700">${escapeHtml(buyer.name)}</div>
        </div>
        <div>
          <div class="small-muted">Contact</div>
          <div style="font-weight:600">${escapeHtml(buyer.contact)}</div>
        </div>
        <div>
          <div class="small-muted">Delivery Address</div>
          <div>${escapeHtml(buyer.address)}</div>
        </div>
        <div>
          <div class="small-muted">Member Since</div>
          <div>${new Date(buyer.created_at).toLocaleDateString('en-US', {year: 'numeric', month: 'long', day: 'numeric'})}</div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;padding-top:12px;border-top:2px solid #e5e7eb">
          <div>
            <div class="small-muted">Total Orders</div>
            <div style="font-size:1.5rem;font-weight:700;color:#3b82f6">${buyer.order_count}</div>
          </div>
          <div>
            <div class="small-muted">Total Spent</div>
            <div style="font-size:1.5rem;font-weight:700;color:#16a34a">Rs${parseFloat(buyer.total_spent || 0).toFixed(0)}</div>
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