// cart.js

document.addEventListener("DOMContentLoaded", function () {
    // Load cart on cart.html
    if (window.location.pathname.includes("cart.html")) {
        loadCart();
    }

    // Add to Cart on shop.html
    const addToCartButtons = document.querySelectorAll('.add-to-cart');
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function () {
            const productCard = button.parentElement;
            const productName = productCard.querySelector('h3').innerText;
            const productPrice = parseFloat(productCard.querySelector('.price').innerText.replace('Tk', ''));

            addToCart(productName, productPrice);
        });
    });
});

// Add item to cart (localStorage)
function addToCart(name, price) {
    let cart = JSON.parse(localStorage.getItem('cart')) || [];
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
    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    const tbody = document.querySelector('tbody');
    const totalCostElement = document.querySelector('.total-cost p strong');

    let totalCost = 0;
    tbody.innerHTML = ''; // Clear table before loading

    cart.forEach((item, index) => {
        const row = `
            <tr>
                <td>${item.name}</td>
                <td><input type="number" value="${item.quantity}" min="1" onchange="updateQuantity(${index}, this.value)"></td>
                <td>${item.price * item.quantity} Tk</td>
                <td><button onclick="removeFromCart(${index})">Remove</button></td>
            </tr>
        `;
        tbody.innerHTML += row;
        totalCost += item.price * item.quantity;
    });

    totalCostElement.innerText = `Subtotal: ${totalCost} Tk`;
    calculateTotal();
}

// Update item quantity
function updateQuantity(index, quantity) {
    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    if (quantity > 0) {
        cart[index].quantity = parseInt(quantity);
    } else {
        cart.splice(index, 1);  // Remove if quantity is zero
    }
    localStorage.setItem('cart', JSON.stringify(cart));
    loadCart();
}

// Remove item from cart
function removeFromCart(index) {
    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    cart.splice(index, 1);
    localStorage.setItem('cart', JSON.stringify(cart));
    loadCart();
}

// Calculate Total Cost (with tax)
function calculateTotal() {
    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    let subtotal = cart.reduce((sum, item) => sum + item.price * item.quantity, 0);
    let tax = subtotal * 0.05;
    let total = subtotal + tax;

    document.querySelector('.total-cost').innerHTML = `
        <p><strong>Subtotal:</strong> ${subtotal.toFixed(2)} Tk</p>
        <p><strong>Tax (5%):</strong> ${tax.toFixed(2)} Tk</p>
        <p><strong>Total:</strong> ${total.toFixed(2)} Tk</p>
    `;
}

// Proceed to Checkout (Pass cart to PHP)
function checkout() {
    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    fetch('process_checkout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ cart: cart })
    }).then(response => response.text())
      .then(data => {
          alert('Order placed successfully!');
          localStorage.removeItem('cart');
          window.location.href = 'index.html';
      });
}
