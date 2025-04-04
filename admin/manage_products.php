<?php
session_start();
// Check if the user is logged in and has admin privileges

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}
// Include the database connection (db.php)
include '../db.php';

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit;
}

$editProduct = '';

// ----- Handle Update Product Submission -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    $updateId = (int) ($_POST['product_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? 'Drink';

    // Set prices based on category
    if ($category === 'Food') {
        $food_price = trim($_POST['price_food'] ?? '');
        $price_16oz = null;
        $price_20oz = null;
    } else {
        $food_price = null;
        $price_16oz = trim($_POST['price_16oz'] ?? '');
        $price_20oz = trim($_POST['price_20oz'] ?? '');
    }

    if (empty($name) || ($category === 'Drink' && ($price_16oz === '' || $price_20oz === '')) || ($category === 'Food' && $food_price === '')) {
        $error = "Please fill in the required fields.";
    } else {
        $old_file_path = $_POST['current_file_path'] ?? '';
        $file_path = $old_file_path;

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'products/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $filename = basename($_FILES['image']['name']);
            $targetFilePath = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFilePath)) {
                $file_path = $targetFilePath;
                if (!empty($old_file_path) && file_exists($old_file_path) && $old_file_path !== $targetFilePath) {
                    unlink($old_file_path);
                }
            }
        }
        $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, category = ?, price_16oz = ?, price_20oz = ?, food_price = ?, file_path = ? WHERE id = ?");
        $stmt->execute([$name, $description, $category, $price_16oz, $price_20oz, $food_price, $file_path, $updateId]);
        header("Location: manage_products.php");
        exit;
    }
}

// ----- Handle Add New Product Submission -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? 'Drink';

    // Set prices based on category
    if ($category === 'Food') {
        $food_price = trim($_POST['price_food'] ?? '');
        $price_16oz = null;
        $price_20oz = null;
    } else {
        $food_price = null;
        $price_16oz = trim($_POST['price_16oz'] ?? '');
        $price_20oz = trim($_POST['price_20oz'] ?? '');
    }

    if (empty($name) || ($category === 'Drink' && ($price_16oz === '' || $price_20oz === '')) || ($category === 'Food' && $food_price === '')) {
        $error = "Please fill in the required fields.";
    } else {
        $file_path = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'products/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $filename = basename($_FILES['image']['name']);
            $targetFilePath = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFilePath)) {
                $file_path = $targetFilePath;
            }
        }
        $stmt = $pdo->prepare("INSERT INTO products (name, description, category, price_16oz, price_20oz, food_price, file_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $description, $category, $price_16oz, $price_20oz, $food_price, $file_path]);
        header("Location: manage_products.php");
        exit;
    }
}

// ----- Handle Product Deletion -----
if (isset($_GET['delete'])) {
    $deleteId = (int) $_GET['delete'];
    
    // Retrieve the product's file path
    $stmt = $pdo->prepare("SELECT file_path FROM products WHERE id = ?");
    $stmt->execute([$deleteId]);
    $productToDelete = $stmt->fetch();
    
    // Delete the image file if it exists
    if ($productToDelete && !empty($productToDelete['file_path']) && file_exists($productToDelete['file_path'])) {
        unlink($productToDelete['file_path']);
    }
    
    // Now, delete the product record from the database
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$deleteId]);
    header("Location: manage_products.php");
    exit;
}

// ----- Check if We're Editing a Product -----
if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$editId]);
    $editProduct = $stmt->fetch();
    if (!$editProduct) {
        header("Location: manage_products.php");
        exit;
    }
}

// Fetch all products for display
$stmt = $pdo->query("SELECT * FROM products ORDER BY id DESC");
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Products - Cafe POS Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Local Bootstrap CSS -->
    <link href="../bootstrap-5.3.3-dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="../vendor/twbs/bootstrap-icons/font/bootstrap-icons.css">
</head>
<body>
  <?php include 'navbar.php'; ?>

  <div class="container mt-4">
    <h2>Manage Products</h2>
    <?php if($error): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($editProduct): ?>
      <!-- Edit Product Form -->
      <h4>Edit Product</h4>
      <form class="row g-3 mb-4" action="manage_products.php?edit=<?php echo $editProduct['id']; ?>" method="post" enctype="multipart/form-data">
          <input type="hidden" name="product_id" value="<?php echo $editProduct['id']; ?>">
          <input type="hidden" name="current_file_path" value="<?php echo htmlspecialchars($editProduct['file_path']); ?>">
          <div class="col-md-3">
              <label for="name" class="form-label">Product Name</label>
              <input type="text" name="name" id="name" class="form-control" value="<?php echo htmlspecialchars($editProduct['name']); ?>" required>
          </div>
          <div class="col-md-3">
              <label for="description" class="form-label">Description</label>
              <input type="text" name="description" id="description" class="form-control" value="<?php echo htmlspecialchars($editProduct['description']); ?>">
          </div>
          <div class="col-md-2">
              <label for="editCategory" class="form-label">Category</label>
              <select name="category" id="editCategory" class="form-select" disabled>
                  <option value="Drink" <?php echo ($editProduct['category'] === 'Drink') ? 'selected' : ''; ?>>Drink</option>
                  <option value="Food" <?php echo ($editProduct['category'] === 'Food') ? 'selected' : ''; ?>>Food</option>
              </select>
              <input type="hidden" name="category" value="<?php echo htmlspecialchars($editProduct['category']); ?>">
          </div>
          <!-- Price inputs for Drink -->
          <?php if ($editProduct['category'] === 'Drink'): ?>
              <div id="editDrinkPrices" class="row g-3">
                  <div class="col-md-2">
                      <label for="price_16oz" class="form-label">Price (16oz)</label>
                      <input type="number" name="price_16oz" id="price_16oz" step="0.01" class="form-control" value="<?php echo htmlspecialchars($editProduct['price_16oz']); ?>" required>
                  </div>
                  <div class="col-md-2">
                      <label for="price_20oz" class="form-label">Price (20oz)</label>
                      <input type="number" name="price_20oz" id="price_20oz" step="0.01" class="form-control" value="<?php echo htmlspecialchars($editProduct['price_20oz']); ?>" required>
                  </div>
              </div>
          <?php elseif ($editProduct['category'] === 'Food'): ?>
              <!-- Single price input for Food -->
              <div id="editFoodPrice" class="col-md-4">
                  <label for="price_food" class="form-label">Price</label>
                  <input type="number" name="price_food" id="price_food" step="0.01" class="form-control" value="<?php echo htmlspecialchars($editProduct['food_price']); ?>" required>
              </div>
          <?php endif; ?>
          <div class="col-md-2">
              <label for="image" class="form-label">Product Image</label>
              <input type="file" name="image" id="image" class="form-control">
              <?php if ($editProduct['file_path']): ?>
                  <img src="./<?php echo htmlspecialchars($editProduct['file_path']); ?>" alt="<?php echo htmlspecialchars($editProduct['name']); ?>" class="img-thumbnail mt-2" style="max-width: 100px;">
              <?php endif; ?>
          </div>
          <div class="col-md-12">
              <button type="submit" name="update_product" class="btn btn-primary w-100">Update Product</button>
              <a href="manage_products.php" class="btn btn-secondary w-100 mt-2">Cancel Edit</a>
          </div>
      </form>
    <?php else: ?>
      <!-- Add New Product Form -->
      <h4>Add New Product</h4>
      <form class="row g-3 mb-4" action="manage_products.php" method="post" enctype="multipart/form-data">
          <div class="col-md-3">
              <input type="text" name="name" class="form-control" placeholder="Product Name" required>
          </div>
          <div class="col-md-3">
              <input type="text" name="description" class="form-control" placeholder="Description">
          </div>
          <div class="col-md-2">
              <!-- Category Drop-down -->
              <select name="category" id="category" class="form-select" required>
                  <option value="Drink">Drink</option>
                  <option value="Food">Food</option>
              </select>
          </div>
          <!-- Price inputs for Drink -->
          <div id="drinkPrices" class="row g-3">
              <div class="col-md-2">
                  <input type="number" name="price_16oz" step="0.01" class="form-control" placeholder="Price (16oz)" required>
              </div>
              <div class="col-md-2">
                  <input type="number" name="price_20oz" step="0.01" class="form-control" placeholder="Price (20oz)" required>
              </div>
          </div>
          <!-- Single price input for Food -->
          <div id="foodPrice" class="col-md-4" style="display: none;">
              <input type="number" name="price_food" step="0.01" class="form-control" placeholder="Price">
          </div>
          <div class="col-md-2">
              <input type="file" name="image" class="form-control">
          </div>
          <div class="col-md-12">
              <button type="submit" name="add_product" class="btn btn-success w-100">Add Product</button>
          </div>
      </form>
    <?php endif; ?>

    <!-- Existing Products Section -->
    <h4>Existing Products</h4>
    <ul class="list-group">
        <?php foreach ($products as $product): ?>
        <li class="list-group-item">
            <div class="row align-items-center">
                <div class="col-md-2">
                    <?php if ($product['file_path']): ?>
                        <img src="./<?php echo htmlspecialchars($product['file_path']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="img-thumbnail" style="max-width: 100px;">
                    <?php else: ?>
                        <span>No image</span>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <h5><?php echo htmlspecialchars($product['name']); ?></h5>
                    <p><?php echo htmlspecialchars($product['description']); ?></p>
                    <p><strong>Category:</strong> <?php echo htmlspecialchars($product['category']); ?></p>
                </div>
                <?php if ($product['category'] === 'Drink'): ?>
                    <div class="col-md-2">
                        <p><strong>16oz:</strong><br>₱<?php echo number_format($product['price_16oz'], 2); ?></p>
                    </div>
                    <div class="col-md-2">
                        <p><strong>20oz:</strong><br>₱<?php echo number_format($product['price_20oz'], 2); ?></p>
                    </div>
                <?php elseif ($product['category'] === 'Food'): ?>
                    <div class="col-md-2">
                        <p><strong>Food Price:</strong><br>₱<?php echo number_format($product['food_price'], 2); ?></p>
                    </div>
                <?php endif; ?>
                <div class="col-md-2 text-end">
                    <a href="manage_products.php?edit=<?php echo $product['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                    <a href="manage_products.php?delete=<?php echo $product['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this product?');">Delete</a>
                </div>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
  </div>

  <!-- JavaScript for toggling price fields -->
  <script>
    // For Add New Product form
    const categorySelect = document.getElementById('category');
    const drinkPrices = document.getElementById('drinkPrices');
    const foodPrice = document.getElementById('foodPrice');

    categorySelect.addEventListener('change', function () {
        if (this.value === 'Food') {
            drinkPrices.style.display = 'none';
            foodPrice.style.display = 'block';
            foodPrice.querySelector('input[name="price_food"]').required = true;
            drinkPrices.querySelector('input[name="price_16oz"]').required = false;
            drinkPrices.querySelector('input[name="price_20oz"]').required = false;
        } else {
            drinkPrices.style.display = 'flex';
            foodPrice.style.display = 'none';
            drinkPrices.querySelector('input[name="price_16oz"]').required = true;
            drinkPrices.querySelector('input[name="price_20oz"]').required = true;
            foodPrice.querySelector('input[name="price_food"]').required = false;
        }
    });
    categorySelect.dispatchEvent(new Event('change'));

    // For Edit Product form
    const editCategorySelect = document.getElementById('editCategory');
    const editDrinkPrices = document.getElementById('editDrinkPrices');
    const editFoodPrice = document.getElementById('editFoodPrice');

    if(editCategorySelect) {
        editCategorySelect.addEventListener('change', function () {
            if (this.value === 'Food') {
                editDrinkPrices.style.display = 'none';
                editFoodPrice.style.display = 'block';
                editFoodPrice.querySelector('input[name="price_food"]').required = true;
                editDrinkPrices.querySelector('input[name="price_16oz"]').required = false;
                editDrinkPrices.querySelector('input[name="price_20oz"]').required = false;
            } else {
                editDrinkPrices.style.display = 'flex';
                editFoodPrice.style.display = 'none';
                editDrinkPrices.querySelector('input[name="price_16oz"]').required = true;
                editDrinkPrices.querySelector('input[name="price_20oz"]').required = true;
                editFoodPrice.querySelector('input[name="price_food"]').required = false;
            }
        });
        editCategorySelect.dispatchEvent(new Event('change'));
    }
  </script>
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
