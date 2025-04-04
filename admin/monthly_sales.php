<?php
// monthly_sales.php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}
// Include the database connection
require_once '../db.php';

// Get current month and year
$currentMonth = date('m');
$currentYear = date('Y');

// Fetch current month revenue, transaction count, and average order value
$currentMonthQuery = "
    SELECT 
        SUM(total_amount) AS revenue, 
        COUNT(id) AS transaction_count, 
        AVG(total_amount) AS average_order_value 
    FROM orders 
    WHERE MONTH(created_at) = :currentMonth AND YEAR(created_at) = :currentYear";
$stmt = $pdo->prepare($currentMonthQuery);
$stmt->execute(['currentMonth' => $currentMonth, 'currentYear' => $currentYear]);
$currentMonthData = $stmt->fetch();

$currentMonthRevenue = $currentMonthData['revenue'] ?? 0;
$currentMonthTransactionCount = $currentMonthData['transaction_count'] ?? 0;
$currentMonthAverageOrderValue = $currentMonthData['average_order_value'] ?? 0;

// Fetch sales by product for the current month
$productSalesQuery = "
    SELECT 
        p.name AS product, 
        SUM(oi.quantity) AS quantity 
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE MONTH(o.created_at) = :currentMonth AND YEAR(o.created_at) = :currentYear
    GROUP BY p.name";
$stmt = $pdo->prepare($productSalesQuery);
$stmt->execute(['currentMonth' => $currentMonth, 'currentYear' => $currentYear]);
$currentMonthGeneralSales = [];
while ($row = $stmt->fetch()) {
    $currentMonthGeneralSales[$row['product']] = $row['quantity'];
}

// Fetch weekly sales summary for the current month
$weeklySalesQuery = "
    SELECT 
        WEEK(created_at, 1) AS week, 
        SUM(total_amount) AS revenue 
    FROM orders 
    WHERE MONTH(created_at) = :currentMonth AND YEAR(created_at) = :currentYear
    GROUP BY WEEK(created_at, 1)";
$stmt = $pdo->prepare($weeklySalesQuery);
$stmt->execute(['currentMonth' => $currentMonth, 'currentYear' => $currentYear]);
$currentMonthSummarySales = [];
while ($row = $stmt->fetch()) {
    $currentMonthSummarySales['Week ' . $row['week']] = $row['revenue'];
}

// Fetch previous month revenue, transaction count, and average order value
$previousMonth = $currentMonth - 1;
$previousYear = $currentYear;
if ($previousMonth == 0) {
    $previousMonth = 12;
    $previousYear -= 1;
}

$previousMonthQuery = "
    SELECT 
        SUM(total_amount) AS revenue, 
        COUNT(id) AS transaction_count, 
        AVG(total_amount) AS average_order_value 
    FROM orders 
    WHERE MONTH(created_at) = :previousMonth AND YEAR(created_at) = :previousYear";
$stmt = $pdo->prepare($previousMonthQuery);
$stmt->execute(['previousMonth' => $previousMonth, 'previousYear' => $previousYear]);
$previousMonthData = $stmt->fetch();

$previousMonthRevenue = $previousMonthData['revenue'] ?? 0;
$previousMonthTransactionCount = $previousMonthData['transaction_count'] ?? 0;
$previousMonthAverageOrderValue = $previousMonthData['average_order_value'] ?? 0;

// Calculate comparison percentages
$revenueComparison = $previousMonthRevenue ? (($currentMonthRevenue - $previousMonthRevenue) / $previousMonthRevenue) * 100 : 0;
$transactionComparison = $previousMonthTransactionCount ? (($currentMonthTransactionCount - $previousMonthTransactionCount) / $previousMonthTransactionCount) * 100 : 0;
$averageComparison = $previousMonthAverageOrderValue ? (($currentMonthAverageOrderValue - $previousMonthAverageOrderValue) / $previousMonthAverageOrderValue) * 100 : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Monthly Sales Report - Cafe POS Admin</title>
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
    <h2>Monthly Sales Report</h2>
    <p class="text-muted">Month: <?php echo date('F, Y'); ?></p>
    
    <a href="download_monthly_sales.php" class="btn btn-primary mb-4">Download as JPEG</a>
    
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-center">
              <div class="card-body">
                <h5 class="card-title">Total Revenue</h5>
                <p class="card-text">₱<?php echo number_format($currentMonthRevenue, 2); ?></p>
                <p class="card-text">
                  <?php
                  echo ($revenueComparison >= 0 ? '+' : '') . number_format($revenueComparison, 2) . '% vs previous month';
                  ?>
                </p>
              </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
              <div class="card-body">
                <h5 class="card-title">Transactions</h5>
                <p class="card-text"><?php echo $currentMonthTransactionCount; ?></p>
                <p class="card-text">
                  <?php
                  echo ($transactionComparison >= 0 ? '+' : '') . number_format($transactionComparison, 2) . '% vs previous month';
                  ?>
                </p>
              </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
              <div class="card-body">
                <h5 class="card-title">Average Order Value</h5>
                <p class="card-text">₱<?php echo number_format($currentMonthAverageOrderValue, 2); ?></p>
                <p class="card-text">
                  <?php
                  echo ($averageComparison >= 0 ? '+' : '') . number_format($averageComparison, 2) . '% vs previous month';
                  ?>
                </p>
              </div>
            </div>
        </div>
    </div>
    
    <h4>Sales by Product (Current Month)</h4>
    <ul class="list-group mb-4">
        <?php foreach($currentMonthGeneralSales as $product => $quantity): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <?php echo $product; ?>
            <span><?php echo $quantity; ?> units sold</span>
        </li>
        <?php endforeach; ?>
    </ul>
    
    <div class="row mb-4">
        <div class="col-md-6">
            <h4>Sales by Product Chart</h4>
            <canvas id="allTimeSalesChart"></canvas>
        </div>
        <div class="col-md-6">
            <h4>Weekly Breakdown</h4>
            <canvas id="weeklyBreakdownChart"></canvas>
        </div>
    </div>
  </div>
  
  <script>
    var allTimeLabels = <?php echo json_encode(array_keys($currentMonthGeneralSales)); ?>;
    var allTimeData = <?php echo json_encode(array_values($currentMonthGeneralSales)); ?>;
    var ctxAllTime = document.getElementById('allTimeSalesChart').getContext('2d');
    var allTimeSalesChart = new Chart(ctxAllTime, {
      type: 'pie',
      data: {
          labels: allTimeLabels,
          datasets: [{
              data: allTimeData,
              backgroundColor: ['#FF6384','#36A2EB','#FFCE56'],
          }]
      },
      options: {
          responsive: true,
          plugins: { legend: { position: 'bottom' } }
      }
    });

    var weeklyLabels = <?php echo json_encode(array_keys($currentMonthSummarySales)); ?>;
    var weeklyData = <?php echo json_encode(array_values($currentMonthSummarySales)); ?>;
    var ctxWeekly = document.getElementById('weeklyBreakdownChart').getContext('2d');
    var weeklyBreakdownChart = new Chart(ctxWeekly, {
      type: 'bar',
      data: {
          labels: weeklyLabels,
          datasets: [{
              label: 'Sales',
              data: weeklyData,
              backgroundColor: '#3498db'
          }]
      },
      options: {
          responsive: true,
          plugins: { legend: { display: false } },
          scales: {
              y: {
                  beginAtZero: true
              }
          }
      }
    });
  </script>
  
  <script src="../bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>