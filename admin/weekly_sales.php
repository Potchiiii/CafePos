<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}
include '../db.php'; // Ensure this file sets up $pdo with your PDO connection

// Get the start and end dates for the current week
$currentWeekStart = date('Y-m-d 00:00:00', strtotime('monday this week'));
$currentWeekEnd = date('Y-m-d 23:59:59', strtotime('sunday this week'));

// Get the start and end dates for the previous week
$previousWeekStart = date('Y-m-d 00:00:00', strtotime('monday last week'));
$previousWeekEnd = date('Y-m-d 23:59:59', strtotime('sunday last week'));

// Fetch current week sales data
$stmt = $pdo->prepare("
    SELECT SUM(o.total_amount) AS revenue, COUNT(o.id) AS transactions,
           AVG(o.total_amount) AS average_order_value
    FROM orders o
    WHERE o.created_at BETWEEN ? AND ?
");
$stmt->execute([$currentWeekStart, $currentWeekEnd]);
$currentWeekData = $stmt->fetch(PDO::FETCH_ASSOC);

$currentWeekRevenue = $currentWeekData['revenue'] ?? 0;
$currentWeekTransactionCount = $currentWeekData['transactions'] ?? 0;
$currentWeekAverageOrderValue = $currentWeekData['average_order_value'] ?? 0;

// Fetch previous week sales data
$stmt = $pdo->prepare("
    SELECT SUM(o.total_amount) AS revenue, COUNT(o.id) AS transactions,
           AVG(o.total_amount) AS average_order_value
    FROM orders o
    WHERE o.created_at BETWEEN ? AND ?
");
$stmt->execute([$previousWeekStart, $previousWeekEnd]);
$previousWeekData = $stmt->fetch(PDO::FETCH_ASSOC);

$previousWeekRevenue = $previousWeekData['revenue'] ?? 0;
$previousWeekTransactionCount = $previousWeekData['transactions'] ?? 0;
$previousWeekAverageOrderValue = $previousWeekData['average_order_value'] ?? 0;

// Calculate comparison percentages
$revenueComparison = $previousWeekRevenue ? (($currentWeekRevenue - $previousWeekRevenue) / $previousWeekRevenue) * 100 : 0;
$transactionComparison = $previousWeekTransactionCount ? (($currentWeekTransactionCount - $previousWeekTransactionCount) / $previousWeekTransactionCount) * 100 : 0;
$averageComparison = $previousWeekAverageOrderValue ? (($currentWeekAverageOrderValue - $previousWeekAverageOrderValue) / $previousWeekAverageOrderValue) * 100 : 0;

// Fetch sales by product for the current week
$currentWeekGeneralSales = [];
$stmt = $pdo->prepare("
    SELECT p.name AS product, SUM(oi.quantity) AS quantity
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.created_at BETWEEN ? AND ?
    GROUP BY p.name
");
$stmt->execute([$currentWeekStart, $currentWeekEnd]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $currentWeekGeneralSales[$row['product']] = $row['quantity'];
}

// Fetch sales by time period for the current week
$currentWeekSummarySales = [
    'Morning' => 0,
    'Afternoon' => 0,
    'Evening' => 0,
];
$stmt = $pdo->prepare("
    SELECT HOUR(o.created_at) AS hour, SUM(o.total_amount) AS total
    FROM orders o
    WHERE o.created_at BETWEEN ? AND ?
    GROUP BY HOUR(o.created_at)
");
$stmt->execute([$currentWeekStart, $currentWeekEnd]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $hour = $row['hour'];
    if ($hour >= 6 && $hour < 12) {
        $currentWeekSummarySales['Morning'] += $row['total'];
    } elseif ($hour >= 12 && $hour < 18) {
        $currentWeekSummarySales['Afternoon'] += $row['total'];
    } elseif ($hour >= 18 && $hour < 24) {
        $currentWeekSummarySales['Evening'] += $row['total'];
    }
}

// Fetch sales by day of the week for the current week
$currentWeekDaySales = [];
$stmt = $pdo->prepare("
    SELECT DAYNAME(o.created_at) AS day, SUM(o.total_amount) AS total
    FROM orders o
    WHERE o.created_at BETWEEN ? AND ?
    GROUP BY DAYNAME(o.created_at)
    ORDER BY FIELD(DAYNAME(o.created_at), 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')
");
$stmt->execute([$currentWeekStart, $currentWeekEnd]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $currentWeekDaySales[$row['day']] = $row['total'];
}

// Fetch detailed transactions for the current week
$transactions = [];
$stmt = $pdo->prepare("
    SELECT o.id AS orderId, o.created_at AS timestamp, o.total_amount AS total,
           GROUP_CONCAT(CONCAT(p.name, ' (', oi.size, ', ', oi.temperature, ') x', oi.quantity) SEPARATOR ', ') AS items
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE o.created_at BETWEEN ? AND ?
    GROUP BY o.id
");
$stmt->execute([$currentWeekStart, $currentWeekEnd]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $transactions[] = [
        'orderId' => $row['orderId'],
        'timestamp' => $row['timestamp'],
        'items' => $row['items'],
        'total' => $row['total'],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Weekly Sales Report - Cafe POS Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Local Bootstrap CSS -->
    <link href="../bootstrap-5.3.3-dist/css/bootstrap.min.css" rel="stylesheet">
   <!-- Bootstrap Icons -->
   <link rel="stylesheet" href="../vendor/twbs/bootstrap-icons/font/bootstrap-icons.css">
    <!-- Local Chart.js -->
    <script src="../chart.js-4.4.8/package/dist/chart.umd.js"></script>
</head>
<body>
  <?php include 'navbar.php'; ?>

  <div class="container mt-4">
    <h2>Weekly Sales Report</h2>
    <!-- Display current date (adjust to a week range if needed) -->
    <p class="text-muted">Date: <?php echo date('F j, Y'); ?></p>
    
    <a href="download_weekly_sales.php" class="btn btn-primary mb-4">Download as JPEG</a>
    
    <!-- Aggregated Metrics and Comparisons -->
    <div class="row mb-4">
        <!-- Total Revenue -->
        <div class="col-md-4">
            <div class="card text-center">
              <div class="card-body">
                <h5 class="card-title">Total Revenue</h5>
                <p class="card-text">₱<?php echo number_format($currentWeekRevenue, 2); ?></p>
                <p class="card-text">
                  <?php
                  echo ($revenueComparison >= 0 ? '+' : '') . number_format($revenueComparison, 2) . '% vs previous week';
                  ?>
                </p>
              </div>
            </div>
        </div>
        <!-- Transactions -->
        <div class="col-md-4">
            <div class="card text-center">
              <div class="card-body">
                <h5 class="card-title">Transactions</h5>
                <p class="card-text"><?php echo $currentWeekTransactionCount; ?></p>
                <p class="card-text">
                  <?php
                  echo ($transactionComparison >= 0 ? '+' : '') . number_format($transactionComparison, 2) . '% vs previous week';
                  ?>
                </p>
              </div>
            </div>
        </div>
        <!-- Average Order Value -->
        <div class="col-md-4">
            <div class="card text-center">
              <div class="card-body">
                <h5 class="card-title">Average Order Value</h5>
                <p class="card-text">₱<?php echo number_format($currentWeekAverageOrderValue, 2); ?></p>
                <p class="card-text">
                  <?php
                  echo ($averageComparison >= 0 ? '+' : '') . number_format($averageComparison, 2) . '% vs previous week';
                  ?>
                </p>
              </div>
            </div>
        </div>
    </div>
    
    <h4>Sales by Product (Current Week)</h4>
    <ul class="list-group mb-4">
        <?php foreach($currentWeekGeneralSales as $product => $quantity): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <?php echo $product; ?>
            <span><?php echo $quantity; ?> units sold</span>
        </li>
        <?php endforeach; ?>
    </ul>
    
    <h4>Top-Selling Products</h4>
    <ul class="list-group mb-4">
        <?php
        arsort($currentWeekGeneralSales); // Sort products by quantity sold in descending order
        $topProducts = array_slice($currentWeekGeneralSales, 0, 5, true); // Get top 5 products
        foreach ($topProducts as $product => $quantity): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <?php echo $product; ?>
            <span><?php echo $quantity; ?> units sold</span>
        </li>
        <?php endforeach; ?>
    </ul>
    
    <!-- Weekly Sales Charts -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h4>General Sales Chart</h4>
            <canvas id="generalSalesChart"></canvas>
        </div>
        <div class="col-md-6">
            <h4>Sales by Day of the Week</h4>
            <canvas id="summarySalesChart"></canvas>
        </div>
    </div>
    
    <h4>Weekly Comparison</h4>
    <canvas id="weeklyComparisonChart"></canvas>
    <script>
        var weekLabels = <?php echo json_encode($lastFourWeeksLabels); ?>;
        var revenueData = <?php echo json_encode($lastFourWeeksRevenue); ?>;

        var ctxWeekly = document.getElementById('weeklyComparisonChart').getContext('2d');
        var weeklyComparisonChart = new Chart(ctxWeekly, {
            type: 'bar',
            data: {
                labels: weekLabels,
                datasets: [{
                    label: 'Revenue (₱)',
                    data: revenueData,
                    backgroundColor: '#FF6384',
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: { title: { display: true, text: 'Week' } },
                    y: { title: { display: true, text: 'Revenue (₱)' } }
                }
            }
        });
    </script>
  </div>
  
  <script>
    // Weekly General Sales Chart
    var generalLabels = <?php echo json_encode(array_keys($currentWeekGeneralSales)); ?>;
    var generalData = <?php echo json_encode(array_values($currentWeekGeneralSales)); ?>;

    // Weekly Sales by Day Chart
    var dayLabels = <?php echo json_encode(array_keys($currentWeekDaySales)); ?>;
    var dayData = <?php echo json_encode(array_values($currentWeekDaySales)); ?>;

    var ctxGeneral = document.getElementById('generalSalesChart').getContext('2d');
    var generalSalesChart = new Chart(ctxGeneral, {
        type: 'pie',
        data: {
            labels: generalLabels,
            datasets: [{
                data: generalData,
                backgroundColor: ['#FF6384','#36A2EB','#FFCE56'],
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } }
        }
    });

    // Weekly Sales by Day Chart
    var ctxSummary = document.getElementById('summarySalesChart').getContext('2d');
    var summarySalesChart = new Chart(ctxSummary, {
        type: 'bar',
        data: {
            labels: dayLabels,
            datasets: [{
                label: 'Sales (₱)',
                data: dayData,
                backgroundColor: ['#4BC0C0','#9966FF','#FF9F40','#FF6384','#36A2EB','#FFCE56','#C9CBCF'],
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                x: { title: { display: true, text: 'Day of the Week' } },
                y: { title: { display: true, text: 'Sales (₱)' } }
            }
        }
    });
  </script>
  
  <!-- Local Bootstrap JS -->
  <script src="../bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
