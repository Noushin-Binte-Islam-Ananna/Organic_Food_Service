// cart.js

const SHIPPING_FEE = 60;   // keep in sync with SHIPPING_FEE in config.php
const TAX_RATE = 0.05;     // keep in sync with TAX_RATE in config.php

document.addEventListener("DOMContentLoaded", function () {
    // Load cart on cart.html
    if (window.location.pathname.includes("cart.html")) {
        loadCart();
        handlePaymentReturn();   // NEW: shows result when coming back from SSLCommerz

        // Pressing Enter inside the shipping form must not submit it natively
        const shippingForm = document.getElementById("shipping-form");
        if (shippingForm) {
            shippingForm.addEventListener("submit", function (e) {
                e.preventDefault();
                checkout();
            });
        }
    }

    // Add to Cart on shop.html
    const addToCartButtons = document.querySelectorAll('.add-to-cart');
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function () {
            const productCard = button.parentElement;
            const productName = productCard.querySelector('h3').innerText;
            // FIX: case-insensitive, so "300Tk", "750TK" and "65tk" are all read correctly
            const productPrice = parseFloat(productCard.querySelector('.price').innerText.replace(/tk/i, ''));

            addToCart(productName, productPrice);
        });
    });
});

// FIX: names are put into innerHTML below, so escape them first
function escapeHtml(text) {
    return String(text).replace(/[&<>"']/g, ch => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[ch]));
}

// FIX: a corrupted localStorage value used to crash every page that read the cart
function getCart() {
    try {
        const cart = JSON.parse(localStorage.getItem('cart'));
        return Array.isArray(cart) ? cart : [];
    } catch (e) {
        return [];
    }
}

// Add item to cart (localStorage)
function addToCart(name, price) {
    let cart = getCart();
    let product = cart.find(item => item.name === name);

    if (product) {
        product.quantity += 1;
    } else {
        cart.push({ name: name, price: price, quantity: 1 });
    }

    localStorage.setItem('cart', JSON.stringify(cart));
    alert(`${name} added to cart!`);
}

// Load cart on cart.html
function loadCart() {
    let cart = getCart();
    const tbody = document.querySelector('tbody');

    tbody.innerHTML = ''; // Clear table before loading

    if (cart.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4">Your cart is empty. <a href="shop.html">Go to the shop</a></td></tr>';
    }

    let rows = '';
    cart.forEach((item, index) => {
        rows += `
            <tr>
                <td>${escapeHtml(item.name)}</td>
                <td><input type="number" value="${item.quantity}" min="1" onchange="updateQuantity(${index}, this.value)"></td>
                <td>${item.price * item.quantity} Tk</td>
                <td><button class="remove-item" onclick="removeFromCart(${index})">Remove</button></td>
            </tr>
        `;
    });
    tbody.innerHTML += rows;   // FIX: one write instead of one per item

    calculateTotal();
}

// Update item quantity
function updateQuantity(index, quantity) {
    let cart = getCart();
    quantity = parseInt(quantity, 10);
    if (quantity > 0) {
        cart[index].quantity = quantity;
    } else {
        cart.splice(index, 1);  // Remove if quantity is zero
    }
    localStorage.setItem('cart', JSON.stringify(cart));
    loadCart();
}

// Remove item from cart
function removeFromCart(index) {
    let cart = getCart();
    cart.splice(index, 1);
    localStorage.setItem('cart', JSON.stringify(cart));
    loadCart();
}

// Calculate Total Cost (with tax)
function calculateTotal() {
    let cart = getCart();
    let subtotal = cart.reduce((sum, item) => sum + item.price * item.quantity, 0);
    let tax = subtotal * TAX_RATE;
    let total = subtotal + tax;
    let shipping = cart.length ? SHIPPING_FEE : 0;
    let grandTotal = total + shipping;

    // FIX: the old code replaced the whole .total-cost box, which deleted the
    // "Proceed to Checkout" button. Now only the numbers are updated.
    const set = (id, text) => { const el = document.getElementById(id); if (el) el.textContent = text; };
    set('subtotal', `${subtotal.toFixed(2)} Tk`);
    set('tax', `${tax.toFixed(2)} Tk`);
    set('total', `${total.toFixed(2)} Tk`);

    // FIX: the Order Summary used to be hard-coded (1799TK / 1859TK)
    set('summary-items', `${subtotal.toFixed(2)} Tk`);
    set('summary-tax', `${tax.toFixed(2)} Tk`);
    set('summary-shipping', `${shipping.toFixed(2)} Tk`);
    set('summary-total', `${grandTotal.toFixed(2)} Tk`);
}

// Place the order. Sends the cart + shipping details + payment method to PHP.
// Cash on Delivery / bKash -> order saved, confirmation shown.
// SSLCommerz -> PHP returns the gateway URL and we redirect the browser to it.
async function checkout() {
    const cart = getCart();
    const form = document.getElementById('shipping-form');
    const button = document.querySelector('.place-order-btn');

    if (cart.length === 0) {
        alert('Your cart is empty.');
        return;
    }
    if (!form.reportValidity()) {   // shows the browser's "please fill this field" hints
        return;
    }

    const method = form.querySelector('input[name="payment-method"]:checked').value;
    const bkashId = form.elements['bkash-number'].value.trim();
    if (method === 'bkash' && !bkashId) {
        alert('Please enter your bKash Transaction ID.');
        form.elements['bkash-number'].focus();
        return;
    }

    const payload = {
        cart: cart,
        payment_method: method,
        bkash_trx_id: bkashId,
        customer: {
            name: form.elements['name'].value.trim(),
            address: form.elements['address'].value.trim(),
            phone: form.elements['phone'].value.trim(),
            email: form.elements['email'].value.trim()
        }
    };

    const originalText = button.textContent;
    button.disabled = true;                 // FIX: prevents double orders from double clicks
    button.textContent = 'Processing...';

    try {
        const response = await fetch('process_checkout.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await response.json();

        if (data.status === 'redirect') {
            window.location.href = data.url;          // go to SSLCommerz payment page
            return;                                   // (button stays disabled while redirecting)
        }
        if (data.status === 'success') {
            localStorage.removeItem('cart');
            showConfirmation(data.order_id);
            return;
        }
        // FIX: the old code said "Order placed successfully!" even when the server failed
        alert(data.message || 'Sorry, we could not place your order. Please try again.');
    } catch (error) {
        alert('Could not reach the server. Please check your connection and try again.');
    }
    button.disabled = false;
    button.textContent = originalText;
}

// Show the "Thank you" box (it is hidden until an order really succeeds)
function showConfirmation(orderId) {
    const confirmation = document.querySelector('.order-confirmation');
    const idEl = document.getElementById('order-id');
    if (idEl) idEl.textContent = '#' + orderId;

    ['.cart-summary', '.total-cost', '.checkout'].forEach(selector => {
        const el = document.querySelector(selector);
        if (el) el.style.display = 'none';
    });
    confirmation.classList.add('show');
    confirmation.scrollIntoView({ behavior: 'smooth' });
}

// When SSLCommerz sends the customer back: cart.html?status=success|failed|cancelled&order=...
function handlePaymentReturn() {
    const params = new URLSearchParams(window.location.search);
    const status = params.get('status');
    if (!status) return;

    if (status === 'success') {
        const orderId = (params.get('order') || '').replace(/[^A-Za-z0-9_-]/g, '');
        localStorage.removeItem('cart');
        showConfirmation(orderId);
    } else {
        const box = document.getElementById('payment-alert');
        if (!box) return;
        box.textContent = status === 'cancelled'
            ? 'You cancelled the payment. Your cart is still saved - you can try again.'
            : 'The payment could not be completed. You have not been charged. Please try again.';
        box.hidden = false;
        box.scrollIntoView({ behavior: 'smooth' });
    }
    // remove ?status=... so a refresh does not show the message again
    window.history.replaceState({}, '', window.location.pathname);
}
