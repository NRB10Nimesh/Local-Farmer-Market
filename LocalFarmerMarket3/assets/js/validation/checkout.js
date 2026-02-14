// checkout.js - Payment method selection and instructions

document.addEventListener('DOMContentLoaded', () => {
  // Payment method selection
  const paymentMethods = document.querySelectorAll('.payment-method');
  const instructionsDiv = document.getElementById('payment-instructions');
  
  if (paymentMethods.length > 0 && instructionsDiv) {
    paymentMethods.forEach(method => {
      method.addEventListener('click', function() {
        // Remove selected class from all
        paymentMethods.forEach(m => m.classList.remove('selected'));
        
        // Add selected class to clicked
        this.classList.add('selected');
        
        // Check the radio button
        const radio = this.querySelector('input[type="radio"]');
        if (radio) {
          radio.checked = true;
        }
        
        // Show payment instructions
        showPaymentInstructions(radio.value);
      });
    });
  }
  
  function showPaymentInstructions(method) {
    if (!instructionsDiv) return;
    
    if (method === 'cash_on_delivery') {
      instructionsDiv.classList.remove('active');
      return;
    }
    
    let instructions = '';
    
    if (method === 'esewa') {
      instructions = `
        <div class="payment-instructions-title"><span class="material-icons" aria-hidden="true">smartphone</span> eSewa Payment Instructions:</div>
        <div class="small">1. You will be redirected to eSewa after placing order</div>
        <div class="small">2. Complete payment using your eSewa account</div>
        <div class="small">3. Your order will be confirmed after successful payment</div>
      `;
    } else if (method === 'khalti') {
      instructions = `
        <div class="payment-instructions-title"><span class="material-icons" aria-hidden="true">account_balance_wallet</span> Khalti Payment Instructions:</div>
        <div class="small">1. You will be redirected to Khalti after placing order</div>
        <div class="small">2. Complete payment using your Khalti account</div>
        <div class="small">3. Your order will be confirmed after successful payment</div>
      `;
    } else if (method === 'bank_transfer') {
      instructions = `
        <div class="payment-instructions-title"><span class="material-icons" aria-hidden="true">account_balance</span> Bank Transfer Instructions:</div>
        <div class="small">1. Transfer amount to: <strong>Bank Account: 1234567890</strong></div>
        <div class="small">2. Use Order ID as reference</div>
        <div class="small">3. Upload payment screenshot in order details</div>
      `;
    }
    
    instructionsDiv.innerHTML = instructions;
    instructionsDiv.classList.add('active');
  }
});

console.log('Checkout JavaScript Loaded');