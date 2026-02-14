// Enhanced script.js - Complete functionality for Farmer & Buyer Dashboard

// ========== MODAL MANAGEMENT ==========
// In script.js, update the modals array to include the new modal IDs
window.onclick = (e) => {
  const modals = ['addModal', 'editModal', 'profileModal', 'orderModal', 'approveModal', 'rejectModal', 'commissionModal', 'stockModal'];
  modals.forEach(id => {
    const el = document.getElementById(id);
    if (el && e.target === el) {
      closeModal(id);
    }
  });
};

// Provide simple openModal/closeModal helpers if not already defined by other scripts
if (typeof openModal === 'undefined') {
  window.openModal = function(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('active');
    // prevent background scroll while modal open
    document.body.style.overflow = 'hidden';
  };
}
if (typeof closeModal === 'undefined') {
  window.closeModal = function(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('active');
    document.body.style.overflow = '';
  };
}

// Update the ESC key handler to include the new modal IDs
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    const modals = ['addModal', 'editModal', 'profileModal', 'orderModal', 'approveModal', 'rejectModal', 'commissionModal', 'stockModal'];
    modals.forEach(id => closeModal(id));
  }
});

// Close modal when clicking outside
window.onclick = (e) => {
  const modals = ['addModal', 'editModal', 'profileModal', 'orderModal'];
  modals.forEach(id => {
    const el = document.getElementById(id);
    if (el && e.target === el) {
      closeModal(id);
    }
  });
};

// Close modal on ESC key
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    const modals = ['addModal', 'editModal', 'profileModal', 'orderModal'];
    modals.forEach(id => closeModal(id));
  }
});

// ========== SEARCH & FILTER ==========
function applySearch() {
  const q = document.getElementById('q')?.value.trim() || '';
  const cat = document.getElementById('categoryFilter')?.value || '';
  let url = new URL(window.location.href);
  
  if (q) {
    url.searchParams.set('search', q);
  } else {
    url.searchParams.delete('search');
  }
  
  if (cat) {
    url.searchParams.set('category', cat);
  } else {
    url.searchParams.delete('category');
  }
  
  window.location.href = url.toString();
}

function applyFilter() {
  applySearch();
}

// Search on Enter key
document.addEventListener('DOMContentLoaded', () => {
  const searchInput = document.getElementById('q');
  if (searchInput) {
    searchInput.addEventListener('keypress', (e) => {
      if (e.key === 'Enter') {
        applySearch();
      }
    });
  }

  // Attach "View Details" handlers for order cards (safe initialization)
  function attachViewOrderHandlers() {
    document.querySelectorAll('.view-order-btn').forEach(btn => {
      // prevent double-binding
      if (btn.dataset.bound === 'true') return;
      btn.dataset.bound = 'true';

      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const orderCard = btn.closest('.order-card');
        if (!orderCard) return;
        const full = orderCard.querySelector('.order-full-items');
        const orderId = btn.dataset.orderId || '';
        const modalBody = document.getElementById('orderModalBody');
        if (modalBody && full) {
          modalBody.innerHTML = `
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
              <h3 style="margin:0">Order #${orderId}</h3>
              <button onclick="closeModal('orderModal')" style="border:none;background:none;font-size:1.5rem;cursor:pointer">&times;</button>
            </div>
          ` + full.innerHTML;

          const totalText = orderCard.querySelector('.order-total div')?.textContent || '';
          modalBody.innerHTML += `<div style="margin-top:12px;text-align:right;font-weight:700">${totalText}</div>`;
        }
        openModal('orderModal');
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', attachViewOrderHandlers);
  } else {
    attachViewOrderHandlers();
  }
});

// ========== IMAGE PREVIEW ==========
function previewAddImage(input) {
  const preview = document.getElementById('addPreview');
  if (!preview) return;
  
  preview.innerHTML = '';
  
  if (input.files && input.files[0]) {
    const file = input.files[0];
    
    // Validate file size (2MB)
    if (file.size > 2 * 1024 * 1024) {
      alert('File size must be less than 2MB');
      input.value = '';
      return;
    }
    
    // Validate file type
    const validTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!validTypes.includes(file.type)) {
      alert('Please upload a valid image file (JPEG, PNG, WEBP, or GIF)');
      input.value = '';
      return;
    }
    
    const reader = new FileReader();
    reader.onload = (e) => {
      const img = document.createElement('img');
      img.src = e.target.result;
      img.style.maxWidth = '200px';
      img.style.maxHeight = '150px';
      img.style.borderRadius = '10px';
      img.style.objectFit = 'cover';
      preview.appendChild(img);
    };
    reader.readAsDataURL(file);
  }
}

function previewEditImage(input) {
  const preview = document.getElementById('editPreview');
  if (!preview) return;
  
  preview.innerHTML = '';
  
  if (input.files && input.files[0]) {
    const file = input.files[0];
    
    // Validate file size (2MB)
    if (file.size > 2 * 1024 * 1024) {
      alert('File size must be less than 2MB');
      input.value = '';
      return;
    }
    
    // Validate file type
    const validTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!validTypes.includes(file.type)) {
      alert('Please upload a valid image file (JPEG, PNG, WEBP, or GIF)');
      input.value = '';
      return;
    }
    
    const reader = new FileReader();
    reader.onload = (e) => {
      const img = document.createElement('img');
      img.src = e.target.result;
      img.style.maxWidth = '200px';
      img.style.maxHeight = '150px';
      img.style.borderRadius = '10px';
      img.style.objectFit = 'cover';
      preview.appendChild(img);
    };
    reader.readAsDataURL(file);
  }
}

// ========== PRODUCT MANAGEMENT ==========
function openEdit(product) {
  const modal = document.getElementById('editModal');
  const body = document.getElementById('editBody');
  
  if (!modal || !body) return;
  
  const p = typeof product === 'string' ? JSON.parse(product) : product;
  
  const html = `
    <h3>Edit Product</h3>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="edit_product">
      <input type="hidden" name="product_id" value="${p.product_id}">
      
      <div class="form-row">
        <div class="col">
          <label>Product Name *</label>
          <input type="text" name="product_name" value="${escapeHtml(p.product_name)}" required>
        </div>
        <div style="width:120px">
          <label>Unit *</label>
          <input type="text" name="unit" value="${escapeHtml(p.unit || 'kg')}">
        </div>
      </div>
      
      <label style="margin-top:12px">Description</label>
      <textarea name="description" rows="3">${escapeHtml(p.description || '')}</textarea>
      
      <div class="form-row" style="margin-top:12px">
        <div class="col">
          <label>Price (Rs) *</label>
          <input type="number" step="0.01" name="price" value="${p.price}" required>
        </div>
        <div class="col">
          <label>Quantity *</label>
          <input type="number" name="quantity" value="${p.quantity}" required>
        </div>
        <div class="col">
          <label>Category *</label>
          <input type="text" name="category" value="${escapeHtml(p.category)}" required>
        </div>
      </div>
      
      <label style="margin-top:12px">Replace Image (optional, max 2MB)</label>
      <input type="file" name="image" accept="image/*" onchange="previewEditImage(this)">
      
      <div id="editPreview" style="margin-top:12px">
        ${p.image ? `<img src="../uploads/${escapeHtml(p.image)}" style="max-width:200px;max-height:150px;border-radius:10px;object-fit:cover">` : ''}
      </div>
      
      <div style="margin-top:20px;display:flex;gap:10px;justify-content:flex-end">
        <button type="button" class="btn btn-ghost" onclick="closeModal('editModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  `;
  
  body.innerHTML = html;
  openModal('editModal');
}
function updateStock(product) {
  try {
    if (!product) {
      console.error('No product data provided');
      return;
    }
    
    const productId = product.product_id;
    if (!productId) {
      console.error('Product ID is missing');
      return;
    }
    
    const stockProductId = document.getElementById('stock_product_id');
    const stockProductInfo = document.getElementById('stock_product_info');
    
    if (!stockProductId || !stockProductInfo) {
      console.error('Required elements not found');
      return;
    }
    
    stockProductId.value = productId;
    
    stockProductInfo.innerHTML = `
      <div><strong>Product:</strong> ${escapeHtml(product.product_name || '')}</div>
      <div><strong>Current Stock:</strong> ${product.quantity || 0}</div>
      <div><strong>Total Stock:</strong> ${product.total_stock || 0}</div>
      <div><strong>Sold:</strong> ${(product.total_stock || 0) - (product.quantity || 0)}</div>
    `;
    
    const newStockInput = document.getElementById('new_stock');
    if (newStockInput) {
      newStockInput.value = product.quantity || 0;
    }
    
    openModal('stockModal');
  } catch (error) {
    console.error('Error in updateStock:', error);
    alert('An error occurred while updating stock. Please check the console for details.');
  }
}

function updateCommission(product) {
  try {
    if (!product) {
      console.error('No product data provided');
      return;
    }
    
    const productId = product.product_id;
    if (!productId) {
      console.error('Product ID is missing');
      return;
    }
    
    const commissionProductId = document.getElementById('commission_product_id');
    const newCommissionInput = document.getElementById('new_commission');
    const commissionFarmerPrice = document.getElementById('commission_farmer_price');
    const commissionProductInfo = document.getElementById('commission_product_info');
    
    if (!commissionProductId || !newCommissionInput || !commissionFarmerPrice || !commissionProductInfo) {
      console.error('Required elements not found');
      return;
    }
    
    commissionProductId.value = productId;
    newCommissionInput.value = product.commission_rate || 5;
    commissionFarmerPrice.value = product.price || 0;
    
    commissionProductInfo.innerHTML = `
      <div><strong>Product:</strong> ${escapeHtml(product.product_name || '')}</div>
      <div><strong>Farmer's Price:</strong> Rs${parseFloat(product.price || 0).toFixed(2)}</div>
      <div><strong>Current Commission:</strong> ${parseFloat(product.commission_rate || 5).toFixed(1)}%</div>
      <div><strong>Current Buyer Price:</strong> Rs${parseFloat(product.admin_price || (product.price * 1.05)).toFixed(2)}</div>
    `;
    
    // Calculate new prices on commission change
    const breakdown = document.getElementById('new_price_breakdown');
    if (!breakdown) {
      console.error('Price breakdown element not found');
      return;
    }
    
    function updateNewBreakdown() {
      try {
        const commission = parseFloat(newCommissionInput.value) || 5;
        const farmerPrice = parseFloat(product.price || 0);
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
      } catch (error) {
        console.error('Error in updateNewBreakdown:', error);
      }
    }
    
    newCommissionInput.removeEventListener('input', updateNewBreakdown); // Remove existing listener to avoid duplicates
    newCommissionInput.addEventListener('input', updateNewBreakdown);
    updateNewBreakdown();
    
    openModal('commissionModal');
  } catch (error) {
    console.error('Error in updateCommission:', error);
    alert('An error occurred while updating commission. Please check the console for details.');
  }
}

function validateAddForm(form) {
  const name = form.product_name.value.trim();
  const price = parseFloat(form.price.value);
  const qty = parseInt(form.quantity.value);
  const category = form.category.value.trim();
  
  if (!name || name.length < 2) {
    alert('Please enter a valid product name (at least 2 characters)');
    return false;
  }
  
  if (isNaN(price) || price <= 0) {
    alert('Please enter a valid price greater than 0');
    return false;
  }
  
  if (isNaN(qty) || qty <= 0) {
    alert('Please enter a valid quantity greater than 0');
    return false;
  }
  
  if (!category) {
    alert('Please enter a category');
    return false;
  }
  
  return true;
}

// ========== ORDER MANAGEMENT ==========
function showOrder(orderData) {
  const o = typeof orderData === 'string' ? JSON.parse(orderData) : orderData;
  
  let details = `Order #${o.order_id}\n\n`;
  details += `Date: ${new Date(o.order_date).toLocaleString()}\n`;
  details += `Status: ${o.status}\n`;
  
  if (o.buyer_name) {
    details += `\nBuyer: ${o.buyer_name}\n`;
    details += `Contact: ${o.buyer_contact || 'N/A'}\n`;
    details += `Address: ${o.buyer_address || 'N/A'}\n`;
  }
  
  if (o.payment_method) {
    details += `\nPayment Method: ${o.payment_method.replace(/_/g, ' ')}\n`;
  }
  
  details += `\nItems:\n`;
  o.items.forEach(item => {
    details += `- ${item.product_name}: ${item.quantity} ${item.unit || ''} @ Rs${item.price}\n`;
  });
  
  details += `\nTotal: Rs${parseFloat(o.total_amount).toFixed(2)}`;
  
  alert(details);
}

function showOrderDetails(order) {
  showOrder(order);
}

// ========== UTILITY FUNCTIONS ==========
function escapeHtml(text) {
  if (!text) return '';
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function formatCurrency(amount) {
  return 'Rs' + parseFloat(amount).toFixed(2);
}

function formatDate(dateString) {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

// ========== CART MANAGEMENT ==========
function updateCartCount() {
  // This would typically be called after adding to cart
  const cartBadge = document.querySelector('.cart-count');
  if (cartBadge) {
    fetch('get_cart_count.php')
      .then(res => res.json())
      .then(data => {
        cartBadge.textContent = data.count;
        if (data.count > 0) {
          cartBadge.style.display = 'flex';
        } else {
          cartBadge.style.display = 'none';
        }
      });
  }
}

// ========== NOTIFICATIONS ==========
function showNotification(message, type = 'success') {
  const notification = document.createElement('div');
  notification.className = `alert alert-${type}`;
  notification.style.position = 'fixed';
  notification.style.top = '80px';
  notification.style.right = '20px';
  notification.style.zIndex = '9999';
  notification.style.minWidth = '300px';
  notification.style.animation = 'slideInRight 0.3s ease-out';
  
  const icon = type === 'success' ? '<span class="material-icons">check_circle</span>' : type === 'danger' ? '<span class="material-icons">cancel</span>' : '<span class="material-icons">info</span>';
  notification.innerHTML = `${icon} ${message}`;
  
  document.body.appendChild(notification);
  
  setTimeout(() => {
    notification.style.animation = 'slideOutRight 0.3s ease-in';
    setTimeout(() => {
      notification.remove();
    }, 300);
  }, 3000);
}

// ========== FORM VALIDATION ==========
function validateEmail(email) {
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return re.test(email);
}

function validatePhone(phone) {
  const re = /^[0-9]{10}$/;
  return re.test(phone.replace(/\s/g, ''));
}

// ========== LOADING STATE ==========
function setLoading(button, isLoading) {
  if (isLoading) {
    button.disabled = true;
    button.dataset.originalText = button.textContent;
    button.innerHTML = '<span class="spinner"></span> Loading...';
  } else {
    button.disabled = false;
    button.textContent = button.dataset.originalText;
  }
}

// ========== CONFIRMATION DIALOGS ==========
function confirmDelete(message = 'Are you sure you want to delete this item?') {
  return confirm(message);
}

function confirmAction(message) {
  return confirm(message);
}

// ========== ANIMATIONS ==========
const style = document.createElement('style');
style.textContent = `
  @keyframes slideInRight {
    from {
      transform: translateX(100%);
      opacity: 0;
    }
    to {
      transform: translateX(0);
      opacity: 1;
    }
  }
  
  @keyframes slideOutRight {
    from {
      transform: translateX(0);
      opacity: 1;
    }
    to {
      transform: translateX(100%);
      opacity: 0;
    }
  }
  
  @keyframes fadeIn {
    from {
      opacity: 0;
    }
    to {
      opacity: 1;
    }
  }
  
  .spinner {
    display: inline-block;
    width: 14px;
    height: 14px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
  }
  
  @keyframes spin {
    to { transform: rotate(360deg); }
  }
`;
document.head.appendChild(style);

// ========== INITIALIZATION ==========
document.addEventListener('DOMContentLoaded', () => {
  console.log('Local Farmer Market Dashboard Loaded');
  
  // Auto-dismiss alerts after 5 seconds
  document.querySelectorAll('.alert-success, .alert-info').forEach(alert => {
    setTimeout(() => {
      alert.style.transition = 'opacity 0.3s';
      alert.style.opacity = '0';
      setTimeout(() => alert.remove(), 300);
    }, 5000);
  });
  
  // Add smooth scroll
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
      e.preventDefault();
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        target.scrollIntoView({ behavior: 'smooth' });
      }
    });
  });
  
  // Form submit prevention for demo
  document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', (e) => {
      const button = form.querySelector('button[type="submit"]');
      if (button) {
        setLoading(button, true);
      }
    });
  });
});

console.log('Enhanced Dashboard JavaScript Loaded Successfully');