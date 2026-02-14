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

// ========== IMAGE PREVIEW & REMOVE ==========
function createPreviewWithRemove(previewEl, imgSrc) {
  previewEl.innerHTML = '';
  const wrap = document.createElement('div');
  wrap.style.position = 'relative';
  const img = document.createElement('img');
  img.src = imgSrc;
  img.style.maxWidth = '200px';
  img.style.maxHeight = '150px';
  img.style.borderRadius = '10px';
  img.style.objectFit = 'cover';
  wrap.appendChild(img);

  const removeBtn = document.createElement('button');
  removeBtn.type = 'button';
  removeBtn.innerText = 'Remove';
  removeBtn.className = 'btn btn-ghost';
  removeBtn.style.position = 'absolute';
  removeBtn.style.right = '-6px';
  removeBtn.style.top = '-10px';
  removeBtn.onclick = () => {
    // if input exists clear it
    const input = previewEl.previousElementSibling;
    if (input && input.tagName === 'INPUT' && input.type === 'file') input.value = '';
    previewEl.innerHTML = '';
  };
  wrap.appendChild(removeBtn);
  previewEl.appendChild(wrap);
}

function previewAddImage(input) {
  const preview = document.getElementById('addPreview');
  if (!preview) return;
  preview.innerHTML = '';
  if (input.files && input.files[0]) {
    const file = input.files[0];
    if (file.size > 2 * 1024 * 1024) {
      showNotification('File size must be less than 2MB', 'danger');
      input.value = '';
      return;
    }
    const validTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!validTypes.includes(file.type)) {
      showNotification('Please upload a valid image file (JPEG, PNG, WEBP, or GIF)', 'danger');
      input.value = '';
      return;
    }
    const reader = new FileReader();
    reader.onload = (e) => createPreviewWithRemove(preview, e.target.result);
    reader.readAsDataURL(file);
  }
}

function previewEditImage(input) {
  const preview = document.getElementById('editPreview');
  if (!preview) return;
  preview.innerHTML = '';
  if (input.files && input.files[0]) {
    const file = input.files[0];
    if (file.size > 2 * 1024 * 1024) {
      showNotification('File size must be less than 2MB', 'danger');
      input.value = '';
      return;
    }
    const validTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!validTypes.includes(file.type)) {
      showNotification('Please upload a valid image file (JPEG, PNG, WEBP, or GIF)', 'danger');
      input.value = '';
      return;
    }
    const reader = new FileReader();
    reader.onload = (e) => createPreviewWithRemove(preview, e.target.result);
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
    <div class="modal-header">
      <button onclick="closeModal('editModal')" class="modal-close modal-close-left">&times;</button>
      <h3 class="modal-title">Edit Product</h3>
    </div>
    ${p.approval_status === 'approved' ? '<div class="alert alert-info modal-alert">Editing will reset approval status. Product will need admin approval again.</div>' : ''}
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="edit_product">
      <input type="hidden" name="product_id" value="${p.product_id}">
      <input type="hidden" name="current_image" value="${escapeHtml(p.image || '')}">
      
      <div class="form-grid">
        <div>
          <label>Product Name *</label>
          <input type="text" name="product_name" value="${escapeHtml(p.product_name)}" required>
        </div>
        <div>
          <label>Category *</label>
          <select name="category" required class="select">
            <option value="Vegetables" ${p.category === 'Vegetables' ? 'selected' : ''}>Vegetables</option>
            <option value="Fruits" ${p.category === 'Fruits' ? 'selected' : ''}>Fruits</option>
            <option value="Grains" ${p.category === 'Grains' ? 'selected' : ''}>Grains</option>
            <option value="Dairy" ${p.category === 'Dairy' ? 'selected' : ''}>Dairy</option>
            <option value="Meat" ${p.category === 'Meat' ? 'selected' : ''}>Meat</option>
            <option value="Other" ${p.category === 'Other' ? 'selected' : ''}>Other</option>
          </select>
        </div>
        <div>
          <label>Unit *</label>
          <select name="unit" required class="select">
            <option value="kg" ${p.unit === 'kg' ? 'selected' : ''}>kg</option>
            <option value="liter" ${p.unit === 'liter' ? 'selected' : ''}>liter</option>
            <option value="piece" ${p.unit === 'piece' ? 'selected' : ''}>piece</option>
            <option value="dozen" ${p.unit === 'dozen' ? 'selected' : ''}>dozen</option>
          </select>
        </div>
        <div>
          <label>Your Price (Rs) *</label>
          <input type="number" step="0.01" name="price" value="${p.price}" required>
        </div>
        <div>
          <label>Quantity *</label>
          <input type="number" name="quantity" value="${p.quantity}" required>
        </div>
        <div>
          <label>Description</label>
          <textarea name="description" rows="3">${escapeHtml(p.description || '')}</textarea>
        </div>
        <div>
          <label>Replace Image (optional, max 2MB)</label>
          <input type="file" name="image" accept="image/*" onchange="previewEditImage(this)">
        </div>
        <div id="editPreview" style="margin-top:12px">${p.image ? `<img src="../uploads/${escapeHtml(p.image)}" style="max-width:200px;max-height:150px;border-radius:10px;object-fit:cover">` : ''}</div>
      </div>
      
      <div class="modal-actions">
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

function clearInlineErrors(form) {
  form.querySelectorAll('.field-error').forEach(el => el.textContent = '');
}

function showInlineError(fieldId, message) {
  const el = document.getElementById(fieldId);
  if (el) el.textContent = message;
}

function validateAddFormData(data) {
  const errors = {};
  if (!data.product_name || data.product_name.trim().length < 2) errors.product_name = 'Enter product name (min 2 chars)';
  if (!data.category || data.category.trim() === '') errors.category = 'Select a category';
  if (!data.unit || data.unit.trim() === '') errors.unit = 'Select a unit';
  if (!data.price || isNaN(data.price) || parseFloat(data.price) <= 0) errors.price = 'Enter valid price > 0';
  if (!data.quantity || isNaN(data.quantity) || parseInt(data.quantity) <= 0) errors.quantity = 'Enter valid quantity';
  return errors;
}

function handleAddSubmit(e) {
  // allow programmatic trigger or button click
  let form = document.getElementById('addForm');
  if (!form) return false;
  clearInlineErrors(form);

  const data = {
    product_name: form.product_name.value,
    category: form.category.value,
    unit: form.unit.value,
    price: form.price.value,
    quantity: form.quantity.value,
    description: form.description.value
  };

  const errors = validateAddFormData(data);
  if (Object.keys(errors).length) {
    Object.entries(errors).forEach(([k,v]) => showInlineError('err_add_' + k, v));
    return false;
  }

  // show confirmation modal with summary
  const confirmBody = document.getElementById('confirmBody');
  confirmBody.innerHTML = `<div style="line-height:1.6"><strong>${escapeHtml(data.product_name)}</strong><div>Category: ${escapeHtml(data.category)}</div><div>Unit: ${escapeHtml(data.unit)}</div><div>Price: Rs${parseFloat(data.price).toFixed(2)}</div><div>Quantity: ${parseInt(data.quantity)}</div></div>`;

  const confirmYes = document.getElementById('confirmYes');
  confirmYes.onclick = () => form.submit();
  openModal('confirmModal');
  return false; // prevent default
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

  // Helper functions to open modals from product json/id
  function openApproveModalFromProduct(product) {
    try {
      const idEl = document.getElementById('approve_product_id'); if (idEl) idEl.value = product.product_id;
      const info = document.getElementById('approve_product_info'); if (info) info.innerHTML = `
        <div><strong>Product:</strong> ${escapeHtml(product.product_name)}</div>
        <div><strong>Farmer:</strong> ${escapeHtml(product.farmer_name)}</div>
        <div><strong>Farmer's Price:</strong> Rs${parseFloat(product.price).toFixed(2)}</div>
        <div><strong>Category:</strong> ${escapeHtml(product.category)}</div>
      `;
      const commissionInput = document.getElementById('commission_rate'); if (commissionInput) commissionInput.value = product.commission_rate || 5;
      const breakdown = document.getElementById('price_breakdown'); if (breakdown && commissionInput) {
        const commission = parseFloat(commissionInput.value) || 5;
        const farmerPrice = parseFloat(product.price) || 0;
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
      openModal('approveModal');
    } catch (err) { console.error('openApproveModalFromProduct error', err); }
  }

  function openRejectModalById(pid) {
    try { document.getElementById('reject_product_id').value = pid; openModal('rejectModal'); } catch(err) { console.error('openRejectModalById error', err); }
  }

  // Delegate click handler for admin product actions (approve/reject) using data attributes
  document.addEventListener('click', (e) => {
    // Class-based handlers (preferred) -------------------------------------------------
    const approveBtn = e.target.closest('.js-approve-product');
    if (approveBtn) {
      const json = approveBtn.getAttribute('data-product');
      try {
        const product = JSON.parse(json);
        openApproveModalFromProduct(product);
      } catch (err) {
        console.error('Invalid product JSON on .js-approve-product', err, json);
      }
      return;
    }

    const rejectBtn = e.target.closest('.js-reject-product');
    if (rejectBtn) {
      const pid = rejectBtn.getAttribute('data-product-id');
      openRejectModalById(pid);
      return;
    }

    // Legacy data-action handler removed — use `.js-approve-product` and `.js-reject-product` buttons for admin actions.
  });
});

