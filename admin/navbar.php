<?php
// navbar.php - common navigation bar for the admin panel with active state
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">Cafe POS Admin</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarAdmin" aria-controls="navbarAdmin" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarAdmin">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item me-3">
          <a class="nav-link <?php echo ($currentPage == 'manage_products.php') ? 'active' : ''; ?>" href="manage_products.php">
            Manage Products
          </a>
        </li>
        <li class="nav-item me-3">
          <a class="nav-link <?php echo ($currentPage == 'manage_staff.php') ? 'active' : ''; ?>" href="manage_staff.php">
            Manage Staff
          </a>
        </li>
        <li class="nav-item me-3">
          <a class="nav-link <?php echo ($currentPage == 'daily_sales.php') ? 'active' : ''; ?>" href="daily_sales.php">
            Daily Sales Report
          </a>
        </li>
        <li class="nav-item me-3">
          <a class="nav-link <?php echo ($currentPage == 'weekly_sales.php') ? 'active' : ''; ?>" href="weekly_sales.php">
             Weekly Sales Report 
          </a>
        </li>
        <li class="nav-item me-3">
          <a class="nav-link <?php echo ($currentPage == 'monthly_sales.php') ? 'active' : ''; ?>" href="monthly_sales.php">
            Monthly Sales Report 
          </a>
        </li>
        <li class="nav-item me-3">
          <a class="nav-link <?php echo ($currentPage == 'attendance.php') ? 'active' : ''; ?>" href="attendance.php">
            Staff Attendance
          </a>
      </ul>
      <!-- Logout button -->
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
