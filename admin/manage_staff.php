<?php
// manage_staff.php
session_start();
// Check if the user is logged in and has admin privileges

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}
include '../db.php';

// Handle form submission for adding a new staff member
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    if (empty($username) || empty($password) || empty($role)) {
        $error = "Please fill in all required fields.";
    } else {
        // Hash the password for security
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // Insert new staff member into the database (using the users table)
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        if ($stmt->execute([$username, $passwordHash, $role])) {
            // Redirect to avoid form resubmission on refresh
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $error = "Failed to add staff member.";
        }
    }
}

// Retrieve staff records from the database (using the 'users' table)
$stmt = $pdo->query("SELECT id, username, role FROM users ORDER BY id ASC");
$staff = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Staff - Cafe POS Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Local Bootstrap CSS -->
    <link href="../bootstrap-5.3.3-dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="../vendor/twbs/bootstrap-icons/font/bootstrap-icons.css">
    <style>
      body {
          background-color: #f8f9fa;
      }
    </style>
</head>
<body>
  <?php include 'navbar.php'; ?>

  <div class="container mt-4">
    <h2>Manage Staff</h2>
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    <!-- Form for adding a new staff member -->
    <form action="manage_staff.php" method="post" class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <input type="text" name="username" class="form-control" placeholder="Staff Username" required>
        </div>
        <div class="col-12 col-md-4">
            <!-- Password input with toggle button -->
            <div class="input-group">
                <input type="password" name="password" id="passwordField" class="form-control" placeholder="Password" required>
                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <select class="form-select" name="role" required>
                <option value="" disabled selected>Select Role</option>
                <option value="Cashier">Cashier</option>
                <option value="Barista">Barista</option>
                <option value="Admin">Admin</option>
            </select>
        </div>
        <div class="col-12 col-md-1">
            <button type="submit" class="btn btn-success w-100">Add</button>
        </div>
    </form>
    
    <!-- Staff list table wrapped in a responsive container -->
    <div class="table-responsive">
      <table class="table table-striped">
          <thead>
              <tr>
                  <th>ID</th>
                  <th>Username</th>
                  <th>Role</th>
                  <th>Actions</th>
              </tr>
          </thead>
          <tbody>
              <?php foreach($staff as $s): ?>
              <tr>
                  <td><?php echo $s['id']; ?></td>
                  <td><?php echo htmlspecialchars($s['username']); ?></td>
                  <td><?php echo htmlspecialchars($s['role']); ?></td>
                  <td>
                      <button class="btn btn-danger btn-sm" onclick="if(confirm('Are you sure you want to delete <?php echo addslashes($s['username']); ?>?')) { window.location.href = 'delete_staff.php?id=<?php echo $s['id']; ?>'; }">
                          Delete
                      </button>
                  </td>
              </tr>
              <?php endforeach; ?>
          </tbody>
      </table>
    </div>
  </div>

  <!-- Local Bootstrap JS -->
  <script src="../bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Toggle password visibility for the add staff form
    document.getElementById('togglePassword').addEventListener('click', function () {
        var passwordField = document.getElementById('passwordField');
        var icon = this.querySelector('i');
        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            passwordField.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    });
  </script>
</body>
</html>
