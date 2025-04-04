<?php
include '../db.php'; // Ensure this file sets up $pdo with your PDO connection

// Fetch sales by product for the current day
$generalSales = [];
$stmt = $pdo->query("
    SELECT p.name AS product, SUM(oi.quantity) AS quantity
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE DATE(o.created_at) = CURDATE()
    GROUP BY p.name
");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $generalSales[$row['product']] = $row['quantity'];
}

// Fetch sales by time period for the current day
$summarySales = [
    'Morning' => 0,
    'Afternoon' => 0,
    'Evening' => 0,
];
$stmt = $pdo->query("
    SELECT HOUR(o.created_at) AS hour, SUM(o.total_amount) AS total
    FROM orders o
    WHERE DATE(o.created_at) = CURDATE()
    GROUP BY HOUR(o.created_at)
");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $hour = $row['hour'];
    if ($hour >= 6 && $hour < 12) {
        $summarySales['Morning'] += $row['total'];
    } elseif ($hour >= 12 && $hour < 18) {
        $summarySales['Afternoon'] += $row['total'];
    } elseif ($hour >= 18 && $hour < 24) {
        $summarySales['Evening'] += $row['total'];
    }
}

// Fetch detailed transactions for the current day
$transactions = [];
$stmt = $pdo->query("
    SELECT 
        o.id AS orderId, 
        o.created_at AS timestamp, 
        o.total_amount AS total,
        GROUP_CONCAT(
            CONCAT(
                p.name, 
                IF(oi.size IS NOT NULL, CONCAT(' (', oi.size, ')'), ''), 
                IF(p.category = 'Drink' AND oi.temperature IS NOT NULL, CONCAT(', ', oi.temperature), ''), 
                ' x', oi.quantity
            ) SEPARATOR ', '
        ) AS items
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE DATE(o.created_at) = CURDATE()
    GROUP BY o.id
");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $transactions[] = [
        'orderId' => $row['orderId'],
        'timestamp' => $row['timestamp'],
        'items' => $row['items'], // Already a string
        'total' => $row['total'],
    ];
}

// Calculate aggregated data for the current day
$totalRevenue = array_sum(array_column($transactions, 'total'));
$transactionCount = count($transactions);
$averageOrderValue = $transactionCount ? $totalRevenue / $transactionCount : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Daily Sales Report - Cafe POS Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="../vendor/twbs/bootstrap-icons/font/bootstrap-icons.css">
    <!-- Local Bootstrap CSS and Chart.js -->
    <link href="../bootstrap-5.3.3-dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="../chart.js-4.4.8/package/dist/chart.umd.js"></script>
</head>
<body>
  <?php include 'navbar.php'; ?>

  <div class="container mt-4">
    <h2>Daily Sales Report</h2>
    <!-- Display the current date -->
    <p class="text-muted">Date: <?php echo date('F j, Y'); ?></p>
    
    <a href="download_daily_sales.php" class="btn btn-primary mb-4">Download as JPEG</a>

    <div class="row mb-4">
        <!-- Total Revenue -->
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Total Revenue</h5>
                    <p class="card-text">₱<?php echo number_format($totalRevenue, 2); ?></p>
                </div>
            </div>
        </div>
        <!-- Number of Transactions -->
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Transactions</h5>
                    <p class="card-text"><?php echo $transactionCount; ?></p>
                </div>
            </div>
        </div>
        <!-- Average Order Value -->
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Average Order Value</h5>
                    <p class="card-text">₱<?php echo number_format($averageOrderValue, 2); ?></p>
                </div>
            </div>
        </div>
    </div>

    <h4>Sales by Product (Daily)</h4>
    <ul class="list-group mb-4">
        <?php foreach($generalSales as $product => $quantity): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <?php echo $product; ?>
            <span><?php echo $quantity; ?> units sold</span>
        </li>
        <?php endforeach; ?>
    </ul>
    <!-- Daily Sales Charts -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h4>General Sales Chart</h4>
            <canvas id="generalSalesChart" width="10" height="10"></canvas>
        </div>
        <div class="col-md-6">
            <h4>Time Period Sales Chart</h4>
            <canvas id="summarySalesChart" width="100" height="100"></canvas>
        </div>
    </div>
    <!-- Detailed Transactions -->
    <h2>Detailed Transactions</h2>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Timestamp</th>
                <th>Items</th>
                <th>Total (₱)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($transactions as $transaction): ?>
            <tr>
                <td><?php echo $transaction['orderId']; ?></td>
                <td><?php echo $transaction['timestamp']; ?></td>
                <td><?php echo htmlspecialchars($transaction['items']); ?></td>
                <td>₱<?php echo number_format($transaction['total'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
  </div>
  
  <script>
    // Function to generate a random color
    function getRandomColor() {
        return `#${Math.floor(Math.random() * 16777215).toString(16).padStart(6, '0')}`;
    }

    // Function to generate an array of unique colors
    function generateColors(count) {
        const colors = [];
        for (let i = 0; i < count; i++) {
            colors.push(getRandomColor());
        }
        return colors;
    }

    // Prepare data for the Daily General Sales Chart
    var generalLabels = <?php echo json_encode(array_keys($generalSales)); ?>;
    var generalData = <?php echo json_encode(array_values($generalSales)); ?>;
    var generalColors = generateColors(generalData.length);

    // Prepare data for the Daily Time Period Sales Chart
    var summaryLabels = <?php echo json_encode(array_keys($summarySales)); ?>;
    var summaryData = <?php echo json_encode(array_values($summarySales)); ?>;
    var summaryColors = generateColors(summaryData.length);

    // General Sales Chart
    if (generalData.length === 0) {
        document.getElementById('generalSalesChart').parentElement.innerHTML = '<p>No sales data available for today.</p>';
    } else {
        var ctxGeneral = document.getElementById('generalSalesChart').getContext('2d');
        var generalSalesChart = new Chart(ctxGeneral, {
            type: 'pie',
            data: {
                labels: generalLabels,
                datasets: [{
                    data: generalData,
                    backgroundColor: generalColors,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }

    // Time Period Sales Chart
    if (summaryData.length === 0) {
        document.getElementById('summarySalesChart').parentElement.innerHTML = '<p>No time period sales data available for today.</p>';
    } else {
        var ctxSummary = document.getElementById('summarySalesChart').getContext('2d');
        var summarySalesChart = new Chart(ctxSummary, {
            type: 'pie',
            data: {
                labels: summaryLabels,
                datasets: [{
                    data: summaryData,
                    backgroundColor: summaryColors,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }
  </script>
  
  <script src="../bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
