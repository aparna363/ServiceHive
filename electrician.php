<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Electrician Services</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background: #f5f5f5;
        }

        .header {
            background: white;
            padding: 20px;
            border-bottom: 1px solid #eee;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .rating {
            color: #6c47ff;
            font-weight: bold;
        }

        .container {
            display: grid;
            grid-template-columns: 300px 1fr 300px;
            gap: 20px;
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
            min-height: calc(100vh - 80px);
        }

        .panel {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .service-card {
            display: flex;
            align-items: center;
            padding: 15px;
            margin: 10px 0;
            border: 1px solid #eee;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            color: black;
            transition: all 0.3s ease;
        }

        .service-card:hover {
            background: #f8f8f8;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .service-icon {
            width: 50px;
            height: 50px;
            margin-right: 15px;
            background: #f0f0f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .service-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #eee;
        }

        .service-details {
            flex: 1;
        }
        
        .service-details h3 {
            margin-bottom: 5px;
            color: #333;
        }

        .service-rating {
            color: #6c47ff;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .service-price {
            color: #333;
            font-weight: bold;
            font-size: 14px;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            background: #f8f8f8;
            border-radius: 6px;
            overflow: hidden;
        }

        .quantity-btn {
            border: none;
            background: none;
            color: #6c47ff;
            width: 36px;
            height: 36px;
            font-size: 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quantity-btn:hover {
            background: #f0f0f0;
        }

        .quantity-input {
            width: 40px;
            text-align: center;
            border: none;
            background: transparent;
            font-size: 16px;
        }

        .add-btn {
            background: #6c47ff;
            color: white;
            border: none;
            padding: 8px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s ease;
        }

        .add-btn:hover {
            background: #5a3cc7;
        }

        .add-btn.added {
            background: #4CAF50;
        }

        .cart {
            padding: 20px 0;
        }

        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .cart-item-details {
            flex: 1;
            margin-right: 15px;
        }

        .cart-item-name {
            margin-bottom: 5px;
            font-size: 14px;
        }

        .savings-message {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 12px;
            border-radius: 6px;
            margin: 15px 0;
            text-align: center;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .savings-message::before {
            content: "💰";
            margin-right: 8px;
        }

        .cart-summary-button {
            width: 100%;
            padding: 15px;
            background: #6c47ff;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            margin-top: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cart-summary-button:hover {
            background: #5a3cc7;
        }

        .section-title {
            margin-bottom: 20px;
            color: #333;
            font-size: 18px;
        }

        .empty-cart-message {
            color: #666;
            text-align: center;
            padding: 20px 0;
        }
        .add-btn {
        background: #6c47ff;
        color: white;
        border: none;
        padding: 8px 24px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        transition: background 0.3s ease;
    }

    .add-btn:hover {
        background: #5a3cc7;
    }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1>Electrician</h1>
            <div class="rating">★ 4.83 (4.4M bookings)</div>
        </div>
    </header>

    <div class="container">
        <div class="panel">
            <h2 class="section-title">Select a service</h2>
            <div id="service-list"></div>
        </div>

        <div class="panel" id="service-details">
            <h2 class="section-title">Select a service from the left panel</h2>
            <div id="service-items"></div>
        </div>

        <div class="panel">
            <h2 class="section-title">Cart</h2>
            <div id="cart-items">
                <p class="empty-cart-message">No items in your cart</p>
            </div>
            <div id="savings-message" class="savings-message" style="display: none">
                Add ₹2 more to save on visitation fee
            </div>
            <button class="cart-summary-button">
                <span id="cart-total">₹0</span>
                <span>View Cart</span>
            </button>
        </div>
    </div>

    <script>
        // Service data
        
            // Add more service details for other categories
       
        let cart = new Map();

        function renderServices() {
            const serviceList = document.getElementById('service-list');
            serviceList.innerHTML = services.map(service => `
                <div class="service-card" data-id="${service.id}">
                    <div class="service-icon">
                        <img src="${service.image}" alt="${service.name}" />
                    </div>
                    <span>${service.name}</span>
                </div>
            `).join('');

            document.querySelectorAll('.service-card').forEach(card => {
                card.addEventListener('click', () => {
                    const serviceId = parseInt(card.dataset.id);
                    showServiceDetails(serviceId);
                    document.querySelectorAll('.service-card').forEach(c => 
                        c.style.background = c === card ? '#f8f8f8' : 'white');
                    scrollToService(serviceId);
                });
            });
        }

        function showServiceDetails(serviceId) {
            const service = services.find(s => s.id === serviceId);
            const details = serviceDetails[serviceId] || [];
            
            const detailsContainer = document.getElementById('service-items');
            document.querySelector('#service-details .section-title').textContent = service.name;
            
            detailsContainer.innerHTML = details.map(item => `
                <div class="service-item" data-item-id="${item.id}">
                    <div class="service-details">
                        <h3>${item.name}</h3>
                        <div class="service-rating">★ ${item.rating} (${item.reviews} reviews)</div>
                        <div class="service-price">₹${item.price} • ${item.duration}</div>
                    </div>
                    ${serviceId === 2 || serviceId === 3 ? 
                        `<div class="quantity-control" data-id="${item.id}">
                            <button class="quantity-btn" onclick="updateQuantity(${item.id}, -1)">-</button>
                            <input type="text" class="quantity-input" value="${cart.get(item.id) || 0}" readonly>
                            <button class="quantity-btn" onclick="updateQuantity(${item.id}, 1)">+</button>
                        </div>` :
                        `<button class="add-btn ${cart.has(item.id) ? 'added' : ''}" 
                            onclick="toggleService(${item.id})">
                            ${cart.has(item.id) ? 'Added' : 'Add'}
                        </button>`
                    }
                </div>
            `).join('');
        }

        function toggleService(itemId) {
            if (cart.has(itemId)) {
                cart.delete(itemId);
            } else {
                cart.set(itemId, 1);
            }
            updateCartDisplay();
            
            // Update button state
            const btn = document.querySelector(`[data-item-id="${itemId}"] .add-btn`);
            if (btn) {
                btn.classList.toggle('added');
                btn.textContent = cart.has(itemId) ? 'Added' : 'Add';
            }
        }

        function updateQuantity(itemId, change) {
            const currentQty = cart.get(itemId) || 0;
            const newQty = Math.max(0, currentQty + change);
            
            if (newQty === 0) {
                cart.delete(itemId);
            } else {
                cart.set(itemId, newQty);
            }
            
            updateCartDisplay();
            
            const qtyInputs = document.querySelectorAll(`[data-id="${itemId}"] .quantity-input`);
            qtyInputs.forEach(input => input.value = newQty);
        }

        function updateCartDisplay() {
            const cartContainer = document.getElementById('cart-items');
            let total = 0;
            let cartHtml = '';

            cart.forEach((qty, itemId) => {
                const item = findItem(itemId);
                if (item) {
                    const itemTotal = item.price * qty;
                    total += itemTotal;
                    const serviceId = getServiceIdForItem(itemId);
                    
                    cartHtml += `
                        <div class="cart-item">
                            <div class="cart-item-details">
                                <div class="cart-item-name">${item.name}</div>
                                <div class="service-price">₹${itemTotal}</div>
                            </div>
                            ${serviceId === 2 || serviceId === 3 ?
                                `<div class="quantity-control" data-id="${itemId}">
                                    <button class="quantity-btn" onclick="updateQuantity(${itemId}, -1)">-</button>
                                    <input type="text" class="quantity-input" value="${qty}" readonly>
                                    <button class="quantity-btn" onclick="updateQuantity(${itemId}, 1)">+</button>
                                </div>` :
                                `<button class="add-btn added" onclick="toggleService(${itemId})">Added</button>`
                            }
                        </div>
                    `;
                }
            });

            cartContainer.innerHTML = cartHtml || '<p class="empty-cart-message">No items in your cart</p>';
            document.getElementById('cart-total').textContent = `₹${total}`;
            
            document.getElementById('savings-message').style.display = 
                total > 0 && total < 500 ? 'block' : 'none';
        }

        function getServiceIdForItem(itemId) {
            for (const [serviceId, items] of Object.entries(serviceDetails)) {
                if (items.some(item => item.id === itemId)) {
                    return parseInt(serviceId);
                }
            }
            return null;
        }

        function findItem(itemId) {
            for (const category of Object.values(serviceDetails)) {
                const item = category.find(i => i.id === itemId);
                if (item) return item;
            }
            return null;
        }

        function scrollToService(serviceId) {
            const middlePanel = document.getElementById('service-details');
            middlePanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        
        function updateQuantity(itemId, change) {
            const currentQty = cart.get(itemId) || 0;
            const newQty = Math.max(0, currentQty + change);
            
            if (newQty === 0) {
                cart.delete(itemId);
            } else {
                cart.set(itemId, newQty);
            }
            
            updateCartDisplay();
            
            // Update quantity input in service details
            const qtyInputs = document.querySelectorAll(`[data-id="${itemId}"] .quantity-input`);
            qtyInputs.forEach(input => input.value = newQty);
        }

        function updateCartDisplay() {
            const cartContainer = document.getElementById('cart-items');
            let total = 0;
            let cartHtml = '';

            cart.forEach((qty, itemId) => {
                const item = findItem(itemId);
                if (item) {
                    const itemTotal = item.price * qty;
                    total += itemTotal;
                    cartHtml += `
                        <div class="cart-item">
                            <div class="cart-item-details">
                                <div class="cart-item-name">${item.name}</div>
                                <div class="service-price">₹${itemTotal}</div>
                            </div>
                            <div class="quantity-control" data-id="${itemId}">
                                <button class="quantity-btn" onclick="updateQuantity(${itemId}, -1)">-</button>
                                <input type="text" class="quantity-input" value="${qty}" readonly>
                                <button class="quantity-btn" onclick="updateQuantity(${itemId}, 1)">+</button>
                            </div>
                        </div>
                    `;
                }
            });

            cartContainer.innerHTML = cartHtml || '<p class="empty-cart-message">No items in your cart</p>';
            document.getElementById('cart-total').textContent = `₹${total}`;
            
            // Show/hide savings message
            document.getElementById('savings-message').style.display = 
                total > 0 && total < 500 ? 'block' : 'none';
        }
        

        function findItem(itemId) {
            for (const category of Object.values(serviceDetails)) {
                const item = category.find(i => i.id === itemId);
                if (item) return item;
            }
            return null;
        }
        // Initialize the page
        renderServices();
    </script>
</body>
</html>