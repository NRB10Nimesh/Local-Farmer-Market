<?php
// admin/commission_settings.php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../db.php';

$admin_id = intval($_SESSION['admin_id']);
$page_title = 'Commission Settings - Admin Panel';
$message = '';
$errors = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'update_category_commission') {
            $category = trim($_POST['category']);
            $default_rate = floatval($_POST['default_commission_rate']);
            $min_rate = floatval($_POST['min_rate']);
            $max_rate = floatval($_POST['max_rate']);
            
            if ($default_rate < 5 || $default_rate > 10) {
                $errors[] = "Default rate must be between 5% and 10%";
            } elseif ($min_rate < 5 || $min_rate > $max_rate) {
                $errors[] = "Invalid min/max rate range";
            } elseif ($max_rate > 10) {
                $errors[] = "Maximum rate cannot exceed 10%";
            } else {
                $stmt = $conn->prepare("INSERT INTO commission_settings (category, default_commission_rate, min_rate, max_rate) 
                                       VALUES (?, ?, ?, ?) 
                                       ON DUPLICATE KEY UPDATE 
                                       default_commission_rate = VALUES(default_commission_rate),
                                       min_rate = VALUES(min_rate),
                                       max_rate = VALUES(max_rate)");
                $stmt->bind_param("sddd", $category, $default_rate, $min_rate, $max_rate);
                
                if ($stmt->execute()) {
                    $message = "Commission settings updated for " . htmlspecialchars($category);
                } else {
                    $errors[] = "Failed to update settings";
                }
                $stmt->close();
            }
        }
        elseif ($_POST['action'] === 'apply_category_rates') {
            $category = trim($_POST['category']);
            
            // Get category default rate
            $rate_query = $conn->prepare("SELECT default_commission_rate FROM commission_settings WHERE category = ?");
            $rate_query->bind_param("s", $category);
            $rate_query->execute();
            $result = $rate_query->get_result();
            
            if ($result->num_rows > 0) {
                $default_rate = $result->fetch_assoc()['default_commission_rate'];
                
                // Update all approved products in this category
                $update_stmt = $conn->prepare("UPDATE products 
                                               SET commission_rate = ?,
                                                   admin_price = price * (1 + (? / 100))
                                               WHERE category = ? AND approval_status = 'approved'");
                $update_stmt->bind_param("dds", $default_rate, $default_rate, $category);
                
                if ($update_stmt->execute()) {
                    $affected = $update_stmt->affected_rows;
                    $message = "Applied {$default_rate}% commission to {$affected} products in " . htmlspecialchars($category);
                } else {
                    $errors[] = "Failed to apply rates";
                }
                $update_stmt->close();
            }
            $rate_query->close();
        }
        elseif ($_POST['action'] === 'bulk_update_commission') {
            $min_commission = floatval($_POST['bulk_min_commission']);
            $max_commission = floatval($_POST['bulk_max_commission']);
            $new_rate = floatval($_POST['bulk_new_rate']);
            
            if ($new_rate < 5 || $new_rate > 10) {
                $errors[] = "Commission rate must be between 5% and 10%";
            } else {
                $stmt = $conn->prepare("UPDATE products 
                                       SET commission_rate = ?,
                                           admin_price = price * (1 + (? / 100))
                                       WHERE approval_status = 'approved' 
                                       AND commission_rate >= ? 
                                       AND commission_rate <= ?");
                $stmt->bind_param("dddd", $new_rate, $new_rate, $min_commission, $max_commission);
                
                if ($stmt->execute()) {
                    $affected = $stmt->affected_rows;
                    $message = "Updated commission to {$new_rate}% for {$affected} products";
                } else {
                    $errors[] = "Failed to update commissions";
                }
                $stmt->close();
            }
        }
    }
}

// Get all commission settings
$settings = $conn->query("SELECT cs.*, 
                         COUNT(p.product_id) as product_count,
                         AVG(p.commission_rate) as avg_actual_rate
                         FROM commission_settings cs
                         LEFT JOIN products p ON cs.category = p.category AND p.approval_status = 'approved'
                         GROUP BY cs.category
                         ORDER BY cs.category")->fetch_all(MYSQLI_ASSOC);

// Get categories without settings
$categories_without_settings = $conn->query("SELECT DISTINCT p.category, COUNT(*) as count
                                            FROM products p
                                            LEFT JOIN commission_settings cs ON p.category = cs.category
                                            WHERE cs.category IS NULL
                                            GROUP BY p.category")->fetch_all(MYSQLI_ASSOC);

// Get overall commission statistics
$overall_stats = $conn->query("SELECT 
    AVG(commission_rate) as avg_rate,
    MIN(commission_rate) as min_rate,
    MAX(commission_rate) as max_rate,
    COUNT(*) as total_products,
    SUM(CASE WHEN commission_rate BETWEEN 5 AND 6 THEN 1 ELSE 0 END) as low_commission,
    SUM(CASE WHEN commission_rate > 6 AND commission_rate <= 8 THEN 1 ELSE 0 END) as medium_commission,
    SUM(CASE WHEN commission_rate > 8 THEN 1 ELSE 0 END) as high_commission
    FROM products 
    WHERE approval_status = 'approved'")->fetch_assoc();

include 'includes/header.php';
?>

<div class="main-wrapper">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main-content">
  <div style="max-width:1400px;margin:0 auto">
    
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
      <h2 style="margin:0">⚙️ Commission Settings</h2>
      <a href="products.php" class="btn btn-ghost">← Back to Products</a>
    </div>

    <?php if ($message): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php foreach ($errors as $err): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div>
    <?php endforeach; ?>

    <!-- Overall Statistics -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px">
      <div class="card" style="background:#dbeafe;border-left:4px solid #3b82f6">
        <div class="small-muted">Average Commission</div>
        <div style="font-size:2rem;font-weight:700;color:#3b82f6">
          <?php echo number_format($overall_stats['avg_rate'] ?? 0, 1); ?>%
        </div>
        <div class="small-muted">Across all products</div>
      </div>
      
      <div class="card" style="background:#d1fae5;border-left:4px solid #16a34a">
        <div class="small-muted">Low (5-6%)</div>
        <div style="font-size:2rem;font-weight:700;color:#16a34a">
          <?php echo $overall_stats['low_commission'] ?? 0; ?>
        </div>
        <div class="small-muted">Products</div>
      </div>
      
      <div class="card" style="background:#fef3c7;border-left:4px solid #f59e0b">
        <div class="small-muted">Medium (6-8%)</div>
        <div style="font-size:2rem;font-weight:700;color:#f59e0b">
          <?php echo $overall_stats['medium_commission'] ?? 0; ?>
        </div>
        <div class="small-muted">Products</div>
      </div>
      
      <div class="card" style="background:#fee2e2;border-left:4px solid #ef4444">
        <div class="small-muted">High (8-10%)</div>
        <div style="font-size:2rem;font-weight:700;color:#ef4444">
          <?php echo $overall_stats['high_commission'] ?? 0; ?>
        </div>
        <div class="small-muted">Products</div>
      </div>
    </div>

    <!-- Bulk Update Tool -->
    <div class="card" style="margin-bottom:24px;background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);color:white">
      <h3 style="margin-top:0">🔄 Bulk Commission Update</h3>
      <form method="POST" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;align-items:end">
        <input type="hidden" name="action" value="bulk_update_commission">
        
        <div>
          <label style="color:white;opacity:0.9">Min Current Rate (%)</label>
          <input type="number" name="bulk_min_commission" step="0.1" min="5" max="10" value="5" required>
        </div>
        
        <div>
          <label style="color:white;opacity:0.9">Max Current Rate (%)</label>
          <input type="number" name="bulk_max_commission" step="0.1" min="5" max="10" value="10" required>
        </div>
        
        <div>
          <label style="color:white;opacity:0.9">New Rate (%)</label>
          <input type="number" name="bulk_new_rate" step="0.1" min="5" max="10" required>
        </div>
        
        <button type="submit" class="btn" style="background:white;color:#667eea;font-weight:700" 
                onclick="return confirm('Update commission for all products in this range?')">
          Apply to All
        </button>
      </form>
      <div class="small" style="opacity:0.9;margin-top:12px">
        This will update commission rates for all approved products within the specified range
      </div>
    </div>

    <!-- Category Settings -->
    <div class="card" style="margin-bottom:24px">
      <h3 style="margin-top:0">📊 Category Commission Settings</h3>
      
      <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse">
          <thead>
            <tr style="background:#f9fafb;border-bottom:2px solid #e5e7eb">
              <th style="padding:12px;text-align:left">Category</th>
              <th style="padding:12px;text-align:center">Default Rate</th>
              <th style="padding:12px;text-align:center">Min Rate</th>
              <th style="padding:12px;text-align:center">Max Rate</th>
              <th style="padding:12px;text-align:center">Products</th>
              <th style="padding:12px;text-align:center">Actual Avg</th>
              <th style="padding:12px;text-align:center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($settings as $setting): ?>
              <tr style="border-bottom:1px solid #e5e7eb">
                <td style="padding:12px;font-weight:600">
                  <?php echo htmlspecialchars($setting['category']); ?>
                </td>
                <td style="padding:12px;text-align:center">
                  <span style="font-weight:700;color:#16a34a">
                    <?php echo number_format($setting['default_commission_rate'], 1); ?>%
                  </span>
                </td>
                <td style="padding:12px;text-align:center">
                  <?php echo number_format($setting['min_rate'], 1); ?>%
                </td>
                <td style="padding:12px;text-align:center">
                  <?php echo number_format($setting['max_rate'], 1); ?>%
                </td>
                <td style="padding:12px;text-align:center">
                  <?php echo $setting['product_count']; ?>
                </td>
                <td style="padding:12px;text-align:center">
                  <?php if ($setting['avg_actual_rate']): ?>
                    <span style="font-weight:600;color:#3b82f6">
                      <?php echo number_format($setting['avg_actual_rate'], 1); ?>%
                    </span>
                  <?php else: ?>
                    <span class="small-muted">N/A</span>
                  <?php endif; ?>
                </td>
                <td style="padding:12px;text-align:center">
                  <div style="display:flex;gap:4px;justify-content:center">
                    <button class="btn btn-ghost small-btn" 
                            onclick='editCategorySetting(<?php echo json_encode($setting); ?>)'>
                      Edit
                    </button>
                    <?php if ($setting['product_count'] > 0): ?>
                      <form method="POST" style="display:inline" 
                            onsubmit="return confirm('Apply <?php echo $setting['default_commission_rate']; ?>% to all <?php echo $setting['product_count']; ?> products?')">
                        <input type="hidden" name="action" value="apply_category_rates">
                        <input type="hidden" name="category" value="<?php echo htmlspecialchars($setting['category']); ?>">
                        <button class="btn btn-primary small-btn">Apply to Products</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Categories Without Settings -->
    <?php if (!empty($categories_without_settings)): ?>
      <div class="card" style="background:#fef3c7;border-left:4px solid #f59e0b">
        <h3 style="margin-top:0">⚠️ Categories Without Settings</h3>
        <p>These categories don't have commission settings configured yet. Click to configure:</p>
        
        <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:16px">
          <?php foreach ($categories_without_settings as $cat): ?>
            <button class="btn btn-ghost" 
                    onclick='addCategorySetting("<?php echo htmlspecialchars($cat['category']); ?>")'>
              <?php echo htmlspecialchars($cat['category']); ?> (<?php echo $cat['count']; ?> products)
            </button>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

  </div>
  </div>
</div>
</div>

<!-- Edit Category Commission Modal -->
<div id="editCategoryModal" class="modal">
  <div class="modal-body">
    <h3>Edit Category Commission Settings</h3>
    <form method="POST">
      <input type="hidden" name="action" value="update_category_commission">
      <input type="hidden" name="category" id="edit_category">
      
      <div style="background:#f9fafb;padding:12px;border-radius:8px;margin-bottom:16px">
        <div style="font-weight:700;margin-bottom:4px">Category</div>
        <div id="edit_category_display"></div>
      </div>
      
      <label>Default Commission Rate (%) *</label>
      <input type="number" name="default_commission_rate" id="edit_default_rate" 
             step="0.1" min="5" max="10" required>
      <div class="small-muted">This will be the default for new products in this category</div>
      
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:16px">
        <div>
          <label>Minimum Rate (%)</label>
          <input type="number" name="min_rate" id="edit_min_rate" 
                 step="0.1" min="5" max="10" value="5" required>
        </div>
        
        <div>
          <label>Maximum Rate (%)</label>
          <input type="number" name="max_rate" id="edit_max_rate" 
                 step="0.1" min="5" max="10" value="10" required>
        </div>
      </div>
      
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('editCategoryModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Settings</button>
      </div>
    </form>
  </div>
</div>

<script src="../assets/js/script.js"></script>
<script>
function editCategorySetting(setting) {
  document.getElementById('edit_category').value = setting.category;
  document.getElementById('edit_category_display').textContent = setting.category;
  document.getElementById('edit_default_rate').value = setting.default_commission_rate;
  document.getElementById('edit_min_rate').value = setting.min_rate;
  document.getElementById('edit_max_rate').value = setting.max_rate;
  
  openModal('editCategoryModal');
}

function addCategorySetting(category) {
  document.getElementById('edit_category').value = category;
  document.getElementById('edit_category_display').textContent = category;
  document.getElementById('edit_default_rate').value = 5.0;
  document.getElementById('edit_min_rate').value = 5.0;
  document.getElementById('edit_max_rate').value = 10.0;
  
  openModal('editCategoryModal');
}
</script>
</body>
</html>