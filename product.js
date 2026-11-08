document.addEventListener('DOMContentLoaded', () => {
    const cart = JSON.parse(localStorage.getItem('cart')) || [];

    // Add to Cart button functionality
    const addToCartButtons = document.querySelectorAll('.add-to-cart');

    addToCartButtons.forEach(button => {
        button.addEventListener('click', (e) => {
            const product = e.target.closest('.product');
            
            const productDetails = {
                id: product.querySelector('h1') ? product.querySelector('h1').textContent : 'Unnamed Product',
                price: product.querySelector('.price') ? parseFloat(product.querySelector('.price').textContent.replace(/tk/i, '')) || 0 : 0,
                description: product.querySelector('.description') ? product.querySelector('.description').textContent : 'No Description',
                image: product.querySelector('img') ? product.querySelector('img').src : 'default-image.jpg'
            };                   

            // Add product to cart array (pass parsed price directly)
            // FIX: addToCart() already shows the alert; the extra one here made it pop up twice
            addToCart(productDetails.id, productDetails.price);
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
