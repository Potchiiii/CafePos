<?php
// cashier.php

include 'db.php'; // Ensure this file sets up $pdo with your PDO connection

// If this is a POST request for checkout, process the order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    header('Content-Type: application/json');
    
    try {
        $pdo->beginTransaction();
        
        // Get data from POST
        $customer_name = $_POST['customer_name'] ?? '';
        $order_items = json_decode($_POST['order_items'], true);
        $total_amount = $_POST['total_amount'] ?? 0;
        
        if (!$customer_name || !is_array($order_items) || count($order_items) === 0) {
            throw new Exception("Invalid order data.");
        }
        
        // Insert new order into `orders` table
        $stmt = $pdo->prepare("INSERT INTO orders (customer_name, total_amount) VALUES (?, ?)");
        $stmt->execute([$customer_name, $total_amount]);
        $order_id = $pdo->lastInsertId();
        
        // Prepare insert for order items into `order_items` table
        $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, size, temperature, quantity, price) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($order_items as $item) {
            // For food items, size will be null and temperature defaults to "hot"
            $stmt->execute([
                $order_id,
                $item['product_id'],
                $item['size'], // For drinks: "16oz" or "20oz"; For food: null
                strtolower($item['temperature']), // store as lowercase e.g., "hot" or "iced"
                $item['quantity'],
                $item['price']
            ]);
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'order_id' => $order_id]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// For GET requests, fetch products to display on the page
try {
    $stmt = $pdo->query("SELECT * FROM products");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching products: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mobile Cashier - Cafe POS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap CSS for mobile responsiveness -->
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        /* Ensure enough space at the bottom for the fixed buttons */
        .product-container {
            padding-bottom: 90px;
        }
        /* Fixed container for the buttons at the bottom */
        .fixed-bottom-buttons {
            position: fixed;
            bottom: 10px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1050;
        }
        /* Styling for the product image so it's responsive and consistent */
        .product-img {
            max-height: 80px;
            object-fit: contain;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-light bg-light">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">Cafe POS</a>
    <a href="logout.php" class="btn btn-outline-danger">Logout</a>
  </div>
</nav>
<div class="container mt-2 product-container">
    <?php if ($products): ?>
        <?php foreach ($products as $product): ?>
        <div class="card mb-3">
            <div class="row g-0 align-items-center">
                <!-- Product image column -->
                <div class="col-4 d-flex align-items-center justify-content-center">
                    <img src="admin/<?php echo htmlspecialchars($product['file_path']); ?>" class="img-fluid product-img" alt="<?php echo htmlspecialchars($product['name']); ?>">
                </div>
                <!-- Product details column -->
                <div class="col-8">
                    <div class="card-body">
                        <h5 class="card-title product-name"><?php echo htmlspecialchars($product['name']); ?></h5>
                        <?php if ($product['category'] === 'Drink'): ?>
                            <p class="card-text mb-1">₱<span class="price_16oz"><?php echo number_format($product['price_16oz'], 2); ?></span></p>
                        <?php elseif ($product['category'] === 'Food'): ?>
                            <p class="card-text mb-1">₱<span class="food-price"><?php echo number_format($product['food_price'], 2); ?></span></p>
                        <?php endif; ?>
                        <p class="card-text"><?php echo htmlspecialchars($product['description']); ?></p>
                        
                        <!-- Hidden input to store product category -->
                        <input type="hidden" class="product-category" value="<?php echo htmlspecialchars($product['category']); ?>">
                        
                        <?php if ($product['category'] === 'Drink'): ?>
                            <!-- Size selection for drinks -->
                            <select class="form-select form-select-sm mb-2 product-size" aria-label="Size">
                                <option value="" selected disabled>Select Size</option>
                                <option value="small">16 oz</option>
                                <option value="medium">20 oz</option>
                            </select>
                            <!-- Temperature selection for drinks -->
                            <select class="form-select form-select-sm mb-2 product-temp" aria-label="Temperature">
                                <option value="" selected disabled>Select Temperature</option>
                                <option value="hot">Hot</option>
                                <option value="iced">Iced</option>
                            </select>
                        <?php else: ?>
                            <!-- For food items, no size or temperature selection -->
                            <p class="mb-2"><strong>Food Item</strong></p>
                        <?php endif; ?>
                        
                        <!-- Quantity input -->
                        <div class="input-group input-group-sm mb-2">
                            <span class="input-group-text">Qty</span>
                            <input type="number" class="form-control product-qty" value="1" min="1">
                        </div>
                        <!-- Hidden field to store product ID and price_20oz if available -->
                        <input type="hidden" class="product-id" value="<?php echo htmlspecialchars($product['id']); ?>">
                        <?php if(isset($product['price_20oz'])): ?>
                            <input type="hidden" class="price_20oz" value="<?php echo number_format($product['price_20oz'], 2, '.', ''); ?>">
                        <?php endif; ?>
                        <button class="btn btn-primary btn-sm w-100 add-to-order">Add to Order</button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No products found.</p>
    <?php endif; ?>
</div>

<!-- Fixed buttons container at the bottom -->
<div class="fixed-bottom-buttons d-flex gap-2">
    <!-- Gcash Payment button -->
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#gcashQRModal">
        Gcash Payment
    </button>
    <button class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#orderSummaryModal" id="viewOrderSummary">
        View Order Summary
    </button>
</div>

<!-- Order Summary Modal -->
<div class="modal fade" id="orderSummaryModal" tabindex="-1" aria-labelledby="orderSummaryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="orderSummaryModalLabel">Order Summary</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Customer Name Input -->
                <div class="mb-3">
                    <label for="customerName" class="form-label">Customer Name</label>
                    <input type="text" class="form-control" id="customerName" placeholder="Enter customer name">
                </div>
                <ul class="list-group" id="orderList">
                    <!-- Order items will be dynamically injected here -->
                </ul>
                <div class="mt-3 d-flex justify-content-between">
                    <strong>Total:</strong>
                    <strong id="orderTotal">₱0.00</strong>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-success w-100" id="checkoutButton">Checkout</button>
            </div>
        </div>
    </div>
</div>

<!-- Gcash QR Code Modal -->
<div class="modal fade" id="gcashQRModal" tabindex="-1" aria-labelledby="gcashQRModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="gcashQRModalLabel">Gcash Payment QR</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img src="Gcash/GcashQR.jpg" alt="Gcash QR Code" class="img-fluid">
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
<script>
// Array to hold order items
let orderItems = [];

// Function to update the order summary modal list and total
function updateOrderSummary() {
    const orderList = document.getElementById('orderList');
    const orderTotalEl = document.getElementById('orderTotal');
    orderList.innerHTML = '';
    let total = 0;

    orderItems.forEach((item, index) => {
        total += item.price * item.quantity;

        // Create list item
        const li = document.createElement('li');
        li.className = 'list-group-item d-flex justify-content-between align-items-center';
        li.innerHTML = `
            <div>
                ${item.name} ${item.size ? '(' + item.size + ', ' + item.temperature + ')' : '(Food Item)'} x${item.quantity} 
                <span class="ms-2">₱${(item.price * item.quantity).toFixed(2)}</span>
            </div>
            <button class="btn btn-link text-danger p-0" title="Remove item" data-index="${index}">
                <i class="bi bi-x"></i>
            </button>
        `;
        orderList.appendChild(li);
    });
    orderTotalEl.textContent = '₱' + total.toFixed(2);
}

// Event delegation for removing items from order summary
document.getElementById('orderList').addEventListener('click', function(e) {
    if (e.target.closest('button')) {
        const btn = e.target.closest('button');
        const index = btn.getAttribute('data-index');
        if (confirm('Are you sure you want to remove this order?')) {
            orderItems.splice(index, 1);
            updateOrderSummary();
        }
    }
});

// Attach event listeners to all "Add to Order" buttons
document.querySelectorAll('.add-to-order').forEach((button) => {
    button.addEventListener('click', function() {
        const cardBody = this.closest('.card-body');
        const name = cardBody.querySelector('.product-name').textContent;
        const product_id = cardBody.querySelector('.product-id').value;
        const category = cardBody.querySelector('.product-category').value;
        const qtyInput = cardBody.querySelector('.product-qty');
        const quantity = parseInt(qtyInput.value, 10);
        let price = 0;
        let size = null;
        let temperature = "hot"; // default for food

        if (category === 'Drink') {
            const sizeSelect = cardBody.querySelector('.product-size');
            const tempSelect = cardBody.querySelector('.product-temp');
            const sizeVal = sizeSelect.value;
            const tempVal = tempSelect.value;

            if (!sizeVal) {
                alert('Please select a size.');
                return;
            }
            if (!tempVal) {
                alert('Please select a temperature.');
                return;
            }

            // Determine price based on selected size
            if (sizeVal === 'small') {
                price = parseFloat(cardBody.querySelector('.price_16oz').textContent.replace(/,/g, ''));
                size = '16oz';
            } else if (sizeVal === 'medium') {
                const price20ozEl = cardBody.querySelector('.price_20oz');
                if (price20ozEl) {
                    price = parseFloat(price20ozEl.value);
                } else {
                    price = parseFloat(cardBody.querySelector('.price_16oz').textContent.replace(/,/g, ''));
                }
                size = '20oz';
            }
            // Format temperature: first letter uppercase for display, but stored as lowercase later
            temperature = tempVal.charAt(0).toUpperCase() + tempVal.slice(1);
        } else if (category === 'Food') {
            // For food items, get the price from the displayed food price
            const priceText = cardBody.querySelector('.food-price').textContent.replace(/[^0-9\.]/g, '');
            if (priceText === "" || isNaN(priceText)) {
                alert('Price for this food item is not set.');
                return;
            }
            price = parseFloat(priceText);
            // For food, size remains null and temperature defaults to "hot"
        }

        if (quantity < 1) {
            alert('Quantity must be at least 1.');
            return;
        }

        // Build order item object
        const orderItem = {
            product_id: product_id,
            name: name,
            size: size, // For drinks: "16oz"/"20oz"; for food: null
            temperature: temperature, // For drinks: based on selection; for food: default "hot"
            quantity: quantity,
            price: price
        };

        // Add the item to orderItems array
        orderItems.push(orderItem);
        alert('Item added to order!');
        // Optionally, reset selections
        if (category === 'Drink') {
            cardBody.querySelector('.product-size').selectedIndex = 0;
            cardBody.querySelector('.product-temp').selectedIndex = 0;
        }
        qtyInput.value = 1;
    });
});

// Update order summary modal content whenever it is shown
document.getElementById('viewOrderSummary').addEventListener('click', updateOrderSummary);

// Checkout functionality
document.getElementById('checkoutButton').addEventListener('click', function() {
    const customerName = document.getElementById('customerName').value.trim();
    if (!customerName) {
        alert("Please enter the customer's name.");
        return;
    }
    if (orderItems.length === 0) {
        alert("No items in the order.");
        return;
    }
    
    const totalAmount = orderItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);

    // Send order to the server using fetch and URLSearchParams
    fetch('cashier.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            checkout: true,
            customer_name: customerName,
            order_items: JSON.stringify(orderItems),
            total_amount: totalAmount
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert("Order successfully placed! Order ID: " + data.order_id);
            
            // Clear the orderItems array and update order summary
            orderItems = [];
            updateOrderSummary();

            // Clear the customer name input field
            document.getElementById('customerName').value = '';

            // Reset all product input fields (for drinks)
            document.querySelectorAll('.product-size').forEach(select => select.selectedIndex = 0);
            document.querySelectorAll('.product-temp').forEach(select => select.selectedIndex = 0);
            document.querySelectorAll('.product-qty').forEach(input => input.value = 1);
        } else {
            alert("Error: " + data.error);
        }
    })
    .catch(error => console.error('Error:', error));
});

// Auto logout when leaving the page using sendBeacon
window.addEventListener("unload", function() {
    navigator.sendBeacon("logout.php");
});
</script>
</body>
</html>
