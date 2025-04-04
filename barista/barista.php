<?php
// barista.php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'barista') {
    header("Location: ../index.php");
    exit;
}
include '../db.php';

try {
    // Fetch orders with status 'Pending' in ascending order
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE status = 'Pending' ORDER BY created_at ASC");
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each order, fetch its order items with product name from products table
    foreach ($orders as &$order) {
        $stmt2 = $pdo->prepare("SELECT oi.*, p.name AS product_name 
                                FROM order_items oi 
                                JOIN products p ON oi.product_id = p.id 
                                WHERE oi.order_id = ?");
        $stmt2->execute([$order['id']]);
        $order['items'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    die("Error fetching orders: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Barista Orders - Cafe POS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Local Bootstrap CSS -->
    <link href="../bootstrap-5.3.3-dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons CSS (using CDN for simplicity) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
      body {
          background-color: #f8f9fa;
      }
      .order-card {
          margin-bottom: 1rem;
      }
      /* Fade out animation */
      .fade-out {
          opacity: 0;
          transition: opacity 0.5s ease-out;
      }
    </style>
</head>
<body>
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
      <div class="container-fluid">
          <a class="navbar-brand" href="#">Barista Panel</a>
          <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent"
                  aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
              <span class="navbar-toggler-icon"></span>
          </button>
          <div class="collapse navbar-collapse" id="navbarSupportedContent">
              <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                  <li class="nav-item">
                      <a class="nav-link" href="../logout.php" title="Logout">
                          <i class="bi bi-box-arrow-right"></i> Logout
                      </a>
                  </li>
              </ul>
          </div>
      </div>
  </nav>
  <div class="container mt-4">
      <div class="d-flex justify-content-between align-items-center mb-4">
          <h2>Orders for Barista</h2>
          <div>
              <span id="clock" class="fs-5 text-muted"></span> <!-- Clock -->
              <span id="orderCount" class="fs-5 ms-3 text-primary"></span> <!-- Order Counter -->
          </div>
      </div>
      <button id="enableAudio" class="btn btn-primary">Enable Audio</button>
      <audio id="newOrderSound" src="../audios/kedr_sfx_ui_computer_complex_beep_1.mp3" preload="auto"></audio>
      <div id="orders-container">
          <?php if (empty($orders)): ?>
              <p>No orders available.</p>
          <?php else: ?>
              <?php foreach ($orders as $order): ?>
                  <div class="card order-card" data-order-id="<?php echo $order['id']; ?>">
                      <div class="card-header">
                          Order ID: <?php echo $order['id']; ?> &nbsp;|&nbsp;
                          Customer: <?php echo htmlspecialchars($order['customer_name']); ?>
                          <span class="float-end"><?php echo date('g:i A', strtotime($order['created_at'])); ?></span>
                      </div>
                      <div class="card-body">
                          <ul class="list-group list-group-flush">
                              <?php foreach ($order['items'] as $item): ?>
                                  <li class="list-group-item">
                                      <?php 
                                      echo htmlspecialchars($item['product_name']);
                                      if (!empty($item['size'])) {
                                          echo " (" . htmlspecialchars($item['size']);
                                          if (!empty($item['temperature'])) {
                                              echo ", " . htmlspecialchars($item['temperature']);
                                          }
                                          echo ")";
                                      }
                                      echo " x" . htmlspecialchars($item['quantity']);
                                      ?>
                                  </li>
                              <?php endforeach; ?>
                          </ul>
                      </div>
                      <div class="card-footer text-end">
                          <button class="btn btn-success btn-sm mark-ready" data-order-id="<?php echo $order['id']; ?>">Mark as Ready</button>
                      </div>
                  </div>
              <?php endforeach; ?>
          <?php endif; ?>
      </div>
  </div>
  
  <!-- Local Bootstrap JS Bundle -->
  <script src="../bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
  <script>
      let previousOrderIds = []; // Track the IDs of orders already displayed

      function fetchOrders() {
          fetch('fetch_orders.php')
              .then(response => response.text())
              .then(html => {
                  const ordersContainer = document.getElementById('orders-container');
                  const tempDiv = document.createElement('div');
                  tempDiv.innerHTML = html;

                  // Get all new order cards
                  const newOrderCards = tempDiv.querySelectorAll('.order-card');
                  let newOrderAdded = false;

                  newOrderCards.forEach(orderCard => {
                      const orderId = orderCard.getAttribute('data-order-id');

                      // Append only if the order is not already displayed
                      if (!previousOrderIds.includes(orderId)) {
                          ordersContainer.appendChild(orderCard);
                          previousOrderIds.push(orderId); // Track the new order ID
                          newOrderAdded = true; // Mark that a new order was added
                      }
                  });

                  // Play audio if a new order was added
                  if (newOrderAdded) {
                      const newOrderSound = document.getElementById('newOrderSound');
                      newOrderSound.load(); // Ensure the audio is preloaded
                      newOrderSound.play().catch(error => {
                          console.error('Audio playback failed:', error);
                      });
                  }
              })
              .catch(error => console.error('Error fetching orders:', error));
      }

      // Fetch orders every 3 seconds
      setInterval(fetchOrders, 3000);

      // Mark order as ready functionality
      document.addEventListener('click', function(event) {
          if (event.target.classList.contains('mark-ready')) {
              const button = event.target;
              const orderId = button.getAttribute('data-order-id');

              if (confirm("Mark order " + orderId + " as ready?")) {
                  // Disable the button to prevent duplicate requests
                  button.disabled = true;

                  fetch('update_order_status.php', {
                      method: 'POST',
                      headers: {
                          'Content-Type': 'application/x-www-form-urlencoded'
                      },
                      body: new URLSearchParams({
                          order_id: orderId,
                          status: 'Ready'
                      })
                  })
                  .then(response => response.json())
                  .then(data => {
                      if (data.success) {
                          alert("Order marked as ready.");
                          const orderCard = document.querySelector(`.order-card[data-order-id="${orderId}"]`);
                          if (orderCard) {
                              orderCard.classList.add('fade-out');
                              setTimeout(() => {
                                  orderCard.remove();
                                  const index = previousOrderIds.indexOf(orderId);
                                  if (index > -1) {
                                      previousOrderIds.splice(index, 1);
                                  }
                              }, 500);
                          }
                      } else {
                          alert("Error: " + data.error);
                          button.disabled = false; // Re-enable the button on error
                      }
                  })
                  .catch(error => {
                      console.error('Error:', error);
                      alert("An error occurred. Please try again.");
                      button.disabled = false; // Re-enable the button on error
                  });
              }
          }
      });

      // Function to update the clock
      function updateClock() {
          const clockElement = document.getElementById('clock');
          const now = new Date();
          const hours = now.getHours().toString().padStart(2, '0');
          const minutes = now.getMinutes().toString().padStart(2, '0');
          const seconds = now.getSeconds().toString().padStart(2, '0');
          clockElement.textContent = `${hours}:${minutes}:${seconds}`;
      }

      // Enable audio functionality
      document.getElementById('enableAudio').addEventListener('click', () => {
          const newOrderSound = document.getElementById('newOrderSound');
          newOrderSound.play().then(() => {
              console.log('Audio enabled');
              document.getElementById('enableAudio').style.display = 'none'; // Hide the button
          }).catch(error => {
              console.error('Audio playback failed:', error);
          });
      });

      // Initialize the counter and clock
      fetchOrders();
      setInterval(updateClock, 1000);
      updateClock(); // Initialize clock immediately
  </script>
</body>
</html>
