document.addEventListener('DOMContentLoaded', function() {
    const quantityInputs = document.querySelectorAll('.quantity-input');
    const subtotalDisplay = document.getElementById('cart-subtotal');
    
    function formatCurrency(amount) {
        return '₱' + parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    function updateCartSubtotal() {
        let newSubtotal = 0;
        document.querySelectorAll('.cart-item').forEach(item => {
            const itemTotalElement = item.querySelector('.item-total-display');
            if (itemTotalElement) {
                const total = parseFloat(itemTotalElement.getAttribute('data-total-price'));
                newSubtotal += total;
            }
        });
        
        if (subtotalDisplay) {
            subtotalDisplay.textContent = formatCurrency(newSubtotal);
        }
    }

    function sendQuantityUpdate(itemId, variantId, newQuantity, inputElement) {
        fetch('update_cart_quantity.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                product_id: itemId,
                variant_id: variantId,
                quantity: newQuantity
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {

                const cartItem = inputElement.closest('.cart-item');
                const basePrice = parseFloat(cartItem.getAttribute('data-base-price'));
                const newTotal = basePrice * data.new_quantity;
                

                const totalDisplay = cartItem.querySelector('.item-total-display');
                totalDisplay.textContent = formatCurrency(newTotal);
                totalDisplay.setAttribute('data-total-price', newTotal);


                updateCartSubtotal();
            } else {
    
                alert('Update failed: ' + data.message);
                
               
                if (data.max_stock !== undefined) {
                    inputElement.value = data.max_stock;
                    sendQuantityUpdate(itemId, variantId, data.max_stock, inputElement);
                }
                
            }
        })
        .catch(error => {
            console.error('Error updating quantity:', error);
            alert('An unexpected error occurred. Please try again.');
        });
    }

    quantityInputs.forEach(input => {
        input.addEventListener('change', function() {
            const newQuantity = parseInt(this.value);
            const itemId = parseInt(this.closest('.cart-item').getAttribute('data-product-id'));
            const variantId = parseInt(this.closest('.cart-item').getAttribute('data-variant-id'));
            const maxStock = parseInt(this.getAttribute('max'));

            if (isNaN(newQuantity) || newQuantity < 1) {
                this.value = 1;
                sendQuantityUpdate(itemId, variantId, 1, this);
            } else if (newQuantity > maxStock) {
                alert(`Maximum stock available is ${maxStock}.`);
                this.value = maxStock;
                sendQuantityUpdate(itemId, variantId, maxStock, this);
            } else {
                sendQuantityUpdate(itemId, variantId, newQuantity, this);
            }
        });
    });
});