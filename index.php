<?php
// index.php
session_start();

// Include the database connection
require_once 'db.php';

// If already logged in, redirect based on role
if (isset($_SESSION['user'])) {
    switch ($_SESSION['user']['role']) {
        case 'admin':
            header("Location: admin/manage_products.php");
            break;
        case 'cashier':
            header("Location: cashier.php");
            break;
        case 'barista':
            header("Location: barista/barista.php");
            break;
        default:
            header("Location: index.php");
    }
    exit;
}

$error = ''; // To hold error messages

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve form data
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    // Basic validation
    if (empty($username) || empty($password) || empty($role)) {
        $error = "Please fill in all fields.";
    } else {
        try {
            // Fetch user record by username and role
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = ?");
            $stmt->execute([$username, $role]);
            $userData = $stmt->fetch();

            if ($userData && password_verify($password, $userData['password'])) {
                // Successful authentication
                $_SESSION['user'] = [
                    'id'       => $userData['id'],
                    'username' => $userData['username'],
                    'role'     => $userData['role']
                ];
                // Redirect based on role
                switch ($userData['role']) {
                    case 'admin':
                        header("Location: admin/manage_products.php");
                        break;
                    case 'cashier':
                        header("Location: cashier.php");
                        break;
                    case 'barista':
                        header("Location: barista/barista.php");
                        break;
                    default:
                        header("Location: index.php");
                }
                exit;
            } else {
                $error = "Invalid username or password.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Cafe POS Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Local Bootstrap CSS -->
  <link href="bootstrap-5.3.3-dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Local Bootstrap Icons -->
  <link rel="stylesheet" href="vendor/twbs/bootstrap-icons/font/bootstrap-icons.css">
  <style>
    body {
      background: #1a1a1a;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      margin: 0;
      color: #ffffff;
      padding: 1rem;
    }
    .login-card {
      width: 100%;
      max-width: 400px;
      background: #1a1a1a;
      border-radius: 0.5rem;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.6);
      padding: 2rem;
    }
    .form-control {
      background-color: #2a2a2a;
      border: 1px solid #444;
      color: #fff;
    }
    .form-control:focus {
      background-color: #333;
      border-color: #666;
      box-shadow: none;
    }
    .btn-primary {
      background-color: #3498db;
      border: none;
      margin-bottom: 50px;
    }
    .btn-primary:hover {
      background-color: #2980b9;
    }
    a {
      color: #3498db;
    }
  </style>
</head>
<body>
  <div class="login-card">
    <div class="text-center mb-4">
      <!-- Replace with your actual logo path -->
      <img src="OrionNoBG.png" alt="Orion Tech Solutions Logo" style="max-width: 150px;" class="img-fluid">
      <h5 class="mt-2">Point of Sale System</h5>
    </div>
    <h3 class="text-center mb-4">Cafe POS Login</h3>
    <?php if ($error): ?>
      <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    <form action="index.php" method="post">
      <div class="mb-3">
        <label for="username" class="form-label">Enter Username</label>
        <input type="text" name="username" id="username" class="form-control" required>
      </div>
      <div class="mb-3">
        <label for="password" class="form-label">Enter Password</label>
        <!-- Password input group with toggle button -->
        <div class="input-group">
          <input type="password" name="password" id="password" class="form-control" required>
          <button type="button" class="btn btn-outline-secondary" id="togglePassword">
            <i class="bi bi-eye"></i>
          </button>
        </div>
      </div>
      <div class="mb-3">
        <label for="role" class="form-label">Select Role</label>
        <select class="form-select text-white bg-dark border-secondary" name="role" id="role" required>
          <option value="" disabled selected>Select your role</option>
          <option value="cashier">Cashier</option>
          <option value="barista">Barista</option>
          <option value="admin">Admin</option>
        </select>
      </div>
      <div class="d-grid">
        <button type="submit" class="btn btn-primary">Login</button>
      </div>
      <p class="text-center mt-3">
        developed by <a href="https://www.facebook.com/profile.php?id=61572938338382">Orion Tech Solutions</a>
      </p>
    </form>
  </div>
  <!-- Local Bootstrap JS Bundle -->
  <script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Toggle password visibility
    document.getElementById('togglePassword').addEventListener('click', function () {
        var passwordInput = document.getElementById('password');
        var icon = this.querySelector('i');
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    });
  </script>
</body>
</html>
