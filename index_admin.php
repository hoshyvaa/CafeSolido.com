<?php
session_start();
include "db.php";
include "functions.php";

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$is_logged_in = $user_id > 0;
$role = $is_logged_in ? currentRole() : 'Customer';
$user_name = $_SESSION['name'] ?? 'Account';

if (!$is_logged_in || $role !== 'Admin') {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'save_user') {

        if (!canDo($role, 'manage_users')) {
            header("Location: index.php");
            exit;
        }

        $account_id = (int)($_POST['account_id'] ?? 0);
        $fullname_input = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $user_role = $_POST['role'] ?? 'Customer';

        if (!in_array($user_role, ['Customer', 'Cashier', 'Manager', 'Admin'], true)) {
            $user_role = 'Customer';
        }

        if ($fullname_input === '' || $email === '') {
            header("Location: index_admin.php?user_error=1#accounts");
            exit;
        }

        if ($account_id > 0) {

            if ($password !== '') {
                $stmt = $conn->prepare("UPDATE accounts SET name = ?, role = ?, email = ?, password = ? WHERE id = ?");
                $stmt->bind_param("ssssi", $fullname_input, $user_role, $email, $password, $account_id);
            } else {
                $stmt = $conn->prepare("UPDATE accounts SET name = ?, role = ?, email = ? WHERE id = ?");
                $stmt->bind_param("sssi", $fullname_input, $user_role, $email, $account_id);
            }

            $stmt->execute();

        } else {

            if ($password === '') {
                $password = '123456';
            }

            $stmt = $conn->prepare("INSERT INTO accounts (name, role, email, password, created_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP())");
            $stmt->bind_param("ssss", $fullname_input, $user_role, $email, $password);
            $stmt->execute();
        }

        header("Location: index_admin.php?saved=1#accounts");
        exit;
    }

    if ($action === 'delete_user') {

        if (!canDo($role, 'manage_users')) {
            header("Location: index.php");
            exit;
        }

        $account_id = (int)($_POST['account_id'] ?? 0);
        $is_self = ($account_id === $user_id);

        if ($account_id > 0 && !$is_self) {
            $stmt = $conn->prepare("DELETE FROM accounts WHERE id = ?");
            $stmt->bind_param("i", $account_id);
            $stmt->execute();
        }

        header("Location: index_admin.php?deleted=1#accounts");
        exit;
    }

    if ($action === 'save_product') {

        if (!canDo($role, 'add_product') && !canDo($role, 'edit_product')) {
            header("Location: index.php");
            exit;
        }

        $product_id = (int)($_POST['product_id'] ?? 0);
        $product_name = trim($_POST['product_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $stock = (int)($_POST['stock'] ?? 0);
        $category_id = (int)($_POST['category_id'] ?? 0);
        $product_image = trim($_POST['existing_product_image'] ?? '');

        if (!empty($_FILES['product_image_file']) && $_FILES['product_image_file']['error'] === UPLOAD_ERR_OK) {

            $uploadDir = __DIR__ . '/products/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $originalName = basename($_FILES['product_image_file']['name']);
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            if (in_array($extension, $allowedExtensions, true)) {

                $safeBaseName = preg_replace('/[^A-Za-z0-9_\-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
                $finalName = $safeBaseName . '_' . time() . '.' . $extension;

                if (move_uploaded_file($_FILES['product_image_file']['tmp_name'], $uploadDir . $finalName)) {
                    $product_image = 'products/' . $finalName;
                }
            }
        }

        if ($product_name === '' || $price <= 0 || $category_id <= 0) {
            header("Location: index_admin.php?product_error=1#products");
            exit;
        }

        if ($product_id > 0) {

            if (!canDo($role, 'edit_product')) {
                header("Location: index.php");
                exit;
            }

            $stmt = $conn->prepare("UPDATE products SET product_name = ?, description = ?, price = ?, stock = ?, category_id = ?, product_image = ? WHERE product_id = ?");
            $stmt->bind_param("ssdiisi", $product_name, $description, $price, $stock, $category_id, $product_image, $product_id);
            $stmt->execute();

        } else {

            if (!canDo($role, 'add_product')) {
                header("Location: index.php");
                exit;
            }

            $stmt = $conn->prepare("INSERT INTO products (product_name, description, price, stock, category_id, product_image) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssdiis", $product_name, $description, $price, $stock, $category_id, $product_image);
            $stmt->execute();
        }

        header("Location: index_admin.php?product_saved=1#products");
        exit;
    }

    if ($action === 'update_stock') {

        if (!canDo($role, 'manage_inventory')) {
            header("Location: index.php");
            exit;
        }

        $product_id = (int)($_POST['product_id'] ?? 0);
        $stock = max(0, (int)($_POST['stock'] ?? 0));

        if ($product_id > 0) {
            $stmt = $conn->prepare("UPDATE products SET stock = ? WHERE product_id = ?");
            $stmt->bind_param("ii", $stock, $product_id);
            $stmt->execute();
        }

        header("Location: index_admin.php?stock_saved=1#inventory");
        exit;
    }

    if ($action === 'delete_product') {

        if ($role !== 'Admin') {
            header("Location: index.php");
            exit;
        }

        $product_id = (int)($_POST['product_id'] ?? 0);

        if ($product_id > 0) {
            $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
        }

        header("Location: index_admin.php?product_deleted=1#products");
        exit;
    }

    if ($action === 'save_category') {

        if (!canDo($role, 'manage_categories')) {
            header("Location: index.php");
            exit;
        }

        $category_id = (int)($_POST['category_id'] ?? 0);
        $category_name = trim($_POST['category_name'] ?? '');

        if ($category_name === '') {
            header("Location: index_admin.php?category_error=1#categories");
            exit;
        }

        if ($category_id > 0) {
            $stmt = $conn->prepare("UPDATE categories SET category_name = ? WHERE category_id = ?");
            $stmt->bind_param("si", $category_name, $category_id);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("INSERT INTO categories (category_name) VALUES (?)");
            $stmt->bind_param("s", $category_name);
            $stmt->execute();
        }

        header("Location: index_admin.php?category_saved=1#categories");
        exit;
    }

    if ($action === 'delete_category') {

        if ($role !== 'Admin') {
            header("Location: index.php");
            exit;
        }

        $category_id = (int)($_POST['category_id'] ?? 0);

        if ($category_id > 0) {
            $stmt = $conn->prepare("DELETE FROM categories WHERE category_id = ?");
            $stmt->bind_param("i", $category_id);
            $stmt->execute();
        }

        header("Location: index_admin.php?category_deleted=1#categories");
        exit;
    }

    if ($action === 'save_featured') {

        if ($role !== 'Admin') {
            header("Location: index.php");
            exit;
        }

        $featured_title = trim($_POST['featured_title'] ?? '');
        $featured_description = trim($_POST['featured_description'] ?? '');
        $featured_image = trim($_POST['existing_featured_image'] ?? '');

        if (!empty($_FILES['featured_image_file']) && $_FILES['featured_image_file']['error'] === UPLOAD_ERR_OK) {

            $uploadDir = __DIR__ . '/featured/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $originalName = basename($_FILES['featured_image_file']['name']);
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            if (in_array($extension, $allowedExtensions, true)) {

                $safeBaseName = preg_replace('/[^A-Za-z0-9_\-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
                $finalName = $safeBaseName . '_' . time() . '.' . $extension;

                if (move_uploaded_file($_FILES['featured_image_file']['tmp_name'], $uploadDir . $finalName)) {
                    $featured_image = 'featured/' . $finalName;
                }
            }
        }

        if ($featured_title === '') {
            header("Location: index_admin.php?featured_error=1#featured");
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO featured (id, title, description, image) VALUES (1, ?, ?, ?) ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description), image = VALUES(image)");
        $stmt->bind_param("sss", $featured_title, $featured_description, $featured_image);
        $stmt->execute();

        header("Location: index_admin.php?featured_saved=1#featured");
        exit;
    }

    if ($action === 'save_place_image') {

        if ($role !== 'Admin') {
            header("Location: index.php");
            exit;
        }

        $place_alt_text = trim($_POST['place_alt_text'] ?? '');
        $place_image = '';

        if (!empty($_FILES['place_image_file']) && $_FILES['place_image_file']['error'] === UPLOAD_ERR_OK) {

            $uploadDir = __DIR__ . '/place/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $originalName = basename($_FILES['place_image_file']['name']);
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            if (in_array($extension, $allowedExtensions, true)) {

                $safeBaseName = preg_replace('/[^A-Za-z0-9_\-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
                $finalName = $safeBaseName . '_' . time() . '.' . $extension;

                if (move_uploaded_file($_FILES['place_image_file']['tmp_name'], $uploadDir . $finalName)) {
                    $place_image = 'place/' . $finalName;
                }
            }
        }

        if ($place_image === '') {
            header("Location: index_admin.php?place_error=1#featured");
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO place_carousel (image, alt_text, sort_order) VALUES (?, ?, (SELECT * FROM (SELECT COALESCE(MAX(sort_order), 0) + 1 FROM place_carousel) t))");
        $stmt->bind_param("ss", $place_image, $place_alt_text);
        $stmt->execute();

        header("Location: index_admin.php?place_saved=1#featured");
        exit;
    }

    if ($action === 'delete_place_image') {

        if ($role !== 'Admin') {
            header("Location: index.php");
            exit;
        }

        $place_id = (int)($_POST['place_id'] ?? 0);

        if ($place_id > 0) {
            $stmt = $conn->prepare("DELETE FROM place_carousel WHERE id = ?");
            $stmt->bind_param("i", $place_id);
            $stmt->execute();
        }

        header("Location: index_admin.php?place_deleted=1#featured");
        exit;
    }
}

$categories = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_id ASC");
if (!$categories) die("Category error: " . $conn->error);

$cart_items = $_SESSION['cart'] ?? [];
$cart_count = count($cart_items);
$cart_preview = array_slice(array_reverse($cart_items), 0, 5);

$total_sales = 0;
$salesResult = $conn->query("SELECT COALESCE(SUM(total_amount), 0) AS total_sales FROM orders WHERE status = 'Paid'");
if ($salesResult) {
    $total_sales = (float)$salesResult->fetch_assoc()['total_sales'];
}

$total_products = 0;
$stockResult = $conn->query("SELECT COUNT(*) AS total_products FROM products");
if ($stockResult) {
    $total_products = (int)$stockResult->fetch_assoc()['total_products'];
}

$total_users = 0;
$totalUsersResult = $conn->query("SELECT COUNT(*) AS total_users FROM accounts");
if ($totalUsersResult) {
    $total_users = (int)$totalUsersResult->fetch_assoc()['total_users'];
}

$todays_sales = 0;
$todaysSalesResult = $conn->query("SELECT COALESCE(SUM(total_amount), 0) AS todays_sales FROM orders WHERE status = 'Paid' AND DATE(created_at) = CURDATE()");
if ($todaysSalesResult) {
    $todays_sales = (float)$todaysSalesResult->fetch_assoc()['todays_sales'];
}

$todays_orders = 0;
$todaysOrdersResult = $conn->query("SELECT COUNT(*) AS todays_orders FROM orders WHERE status = 'Paid' AND DATE(created_at) = CURDATE()");
if ($todaysOrdersResult) {
    $todays_orders = (int)$todaysOrdersResult->fetch_assoc()['todays_orders'];
}

$total_customers = 0;
$totalCustomersResult = $conn->query("SELECT COUNT(*) AS total_customers FROM accounts WHERE role = 'Customer'");
if ($totalCustomersResult) {
    $total_customers = (int)$totalCustomersResult->fetch_assoc()['total_customers'];
}

$users = [];
$userRows = $conn->query("SELECT id AS account_id, name AS fullname, email, role, created_at FROM accounts ORDER BY created_at DESC");
if ($userRows) {
    while ($row = $userRows->fetch_assoc()) {
        $users[] = $row;
    }
}

$dashboard_categories = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_id ASC");

$dashboard_products = $conn->query("
    SELECT p.product_id, p.product_name, p.description, p.price, p.stock, p.category_id, p.product_image, c.category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    ORDER BY p.product_id ASC
");

$recent_sales = $conn->query("
    SELECT order_id, customer_name, total_amount, payment_method, payment_amount, change_amount, receipt_number, created_at
    FROM orders
    WHERE status = 'Paid'
    ORDER BY order_id DESC
    LIMIT 20
");

$featured = $conn->query("SELECT * FROM featured WHERE id = 1")->fetch_assoc();
$place_images = $conn->query("SELECT * FROM place_carousel ORDER BY sort_order ASC, id ASC");

$show_saved = isset($_GET['saved']);
$show_deleted = isset($_GET['deleted']);
$show_user_error = isset($_GET['user_error']);
$show_product_error = isset($_GET['product_error']);
$show_product_saved = isset($_GET['product_saved']);
$show_product_deleted = isset($_GET['product_deleted']);
$show_category_error = isset($_GET['category_error']);
$show_category_saved = isset($_GET['category_saved']);
$show_category_deleted = isset($_GET['category_deleted']);
$show_stock_saved = isset($_GET['stock_saved']);
$show_featured_saved = isset($_GET['featured_saved']);
$show_featured_error = isset($_GET['featured_error']);
$show_place_saved = isset($_GET['place_saved']);
$show_place_deleted = isset($_GET['place_deleted']);
$show_place_error = isset($_GET['place_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin - Cafe Solido</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<header>
  <div class="nav-left">
    <a href="index.php"><img src="logo/logoNAME.png" alt="Cafe Solido Logo" class="logo"></a>
  </div>

    <div class="nav-center">
    <ul class="nav1">
      <li><a class="nav-link active" href="#dashboard_admin">Home</a></li>
      <li><a class="nav-link" href="#accounts">Accounts</a></li>
      <li><a class="nav-link" href="#products">Products</a></li>
      <li><a class="nav-link" href="#featured">Featured</a></li>
    </ul>
  </div>

  <div class="nav_right">
    <span class="nav_right_username">Hi, <?php echo htmlspecialchars($user_name); ?></span>

    <a href="https://www.instagram.com/cafe_solido_/?utm_source=ig_web_button_share_sheet&stkn=ZDNlZDc0MzIxNw==" target="_blank" title="Instagram">
      <button class="nav_right_iconbtn"><img src="icons/instagram.png" alt="Instagram Icon"></button>
    </a>

    <a href="logout.php" title="Log Out"><button class="nav_right_iconbtn"><img src="icons/logout.png" alt="Log Out Icon"></button></a>
  </div>
</header>

<section class="dashboard_admin" id="dashboard_admin">
<div class="dashboards">
  <div class="dashboard-grid">
    <div class="dashboard-card"><span><div class="icons"><img src="icons/sales.png" alt="Sales Icon">Total Sales</div></span><h3>₱<?php echo number_format($total_sales, 2); ?></h3><p>Paid orders</p></div>
    <div class="dashboard-card"><span><div class="icons"><img src="icons/stocks.png" alt="Stocks Icon">Total Products</div></span><h3><?php echo number_format($total_products); ?></h3><p>Available products</p></div>
    <div class="dashboard-card"><span><div class="icons"><img src="icons/accounts.png" alt="Accounts Icon">All Accounts</div></span><h3><?php echo number_format($total_users); ?></h3><p>Every role combined</p></div>
  </div>

  <div class="dashboard-grid">
    <div class="dashboard-card"><span><div class="icons"><img src="icons/today_sales.png" alt="Sales Icon">Today's Sales</div></span><h3>₱<?php echo number_format($todays_sales, 2); ?></h3><p>Paid orders today</p></div>
    <div class="dashboard-card"><span><div class="icons"><img src="icons/orders.png" alt="Orders Icon">Today's Orders</div></span><h3><?php echo number_format($todays_orders); ?></h3><p>Paid orders today</p></div>
    <div class="dashboard-card"><span><div class="icons"><img src="icons/custoemr.png" alt="Accounts Icon">Total Customers</div></span><h3><?php echo number_format($total_customers); ?></h3><p>Customer role only</p></div>
  </div>

  <?php if ($show_saved): ?><div class="notice notice-ok">Saved successfully.</div><?php endif; ?>
  <?php if ($show_deleted): ?><div class="notice notice-ok">Deleted successfully.</div><?php endif; ?>
  <?php if ($show_stock_saved): ?><div class="notice notice-ok">Stock updated.</div><?php endif; ?>
  <?php if ($show_product_saved): ?><div class="notice notice-ok">Product saved.</div><?php endif; ?>
  <?php if ($show_product_deleted): ?><div class="notice notice-ok">Product deleted.</div><?php endif; ?>
  <?php if ($show_category_saved): ?><div class="notice notice-ok">Category saved.</div><?php endif; ?>
  <?php if ($show_category_deleted): ?><div class="notice notice-ok">Category deleted.</div><?php endif; ?>
  <?php if ($show_user_error): ?><div class="notice notice-error">Full name and email are required.</div><?php endif; ?>
  <?php if ($show_product_error): ?><div class="notice notice-error">Product name, price and category are required.</div><?php endif; ?>
  <?php if ($show_category_error): ?><div class="notice notice-error">Category name is required.</div><?php endif; ?>
  <?php if ($show_featured_saved): ?><div class="notice notice-ok">Featured picture saved.</div><?php endif; ?>
  <?php if ($show_featured_error): ?><div class="notice notice-error">Featured title is required.</div><?php endif; ?>
  <?php if ($show_place_saved): ?><div class="notice notice-ok">Carousel image added.</div><?php endif; ?>
  <?php if ($show_place_deleted): ?><div class="notice notice-ok">Carousel image deleted.</div><?php endif; ?>
  <?php if ($show_place_error): ?><div class="notice notice-error">Please choose an image to upload.</div><?php endif; ?>
  
  <div class="two_dashboard">
  <div class="panel_edit_accounts" id="accounts">
    <h3>Add or Edit Account</h3>
    <form method="POST" id="userForm">
      <input type="hidden" name="action" value="save_user">
      <input type="hidden" name="account_id" id="adminAccountId" value="">
      <div class="form-grid">
        <div class="form-group"><label>Full Name</label><input type="text" name="fullname" id="adminFullname" class="form-input" required></div>
        <div class="form-group"><label>Email</label><input type="email" name="email" id="adminEmail" class="form-input" required></div>
        <div class="form-group"><label>Password (leave blank to keep current)</label><input type="password" name="password" id="adminPassword" class="form-input"></div>
        <div class="form-group">
          <label>Role</label>
          <select name="role" id="adminRole" class="form-input">
            <option value="Customer">Customer</option>
            <option value="Cashier">Cashier</option>
            <option value="Manager">Manager</option>
            <option value="Admin">Admin</option>
          </select>
        </div>
      </div>
      <button type="submit" class="save"><img src="icons/save.png" alt="Save Icon"></button>
      <button type="button" class="reset" onclick="clearUserForm()"><img src="icons/reset.png" alt="Reset Icon"></button>
    </form>
  </div>

  <div class="panel_view_accounts">
    <h3>All Accounts</h3>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Created</th><th>Action</th></tr></thead>
        <tbody>
          <?php if (!empty($users)): ?>
            <?php foreach ($users as $account): ?>
              <tr>
                <td><?php echo htmlspecialchars($account['fullname']); ?></td>
                <td><?php echo htmlspecialchars($account['email']); ?></td>
                <td><?php echo htmlspecialchars($account['role']); ?></td>
                <td><?php echo htmlspecialchars($account['created_at']); ?></td>
                <td class="action-cell">
                  <button type="button" class="edit" onclick='editUser(<?php echo (int)$account['account_id']; ?>, <?php echo json_encode($account['fullname']); ?>, <?php echo json_encode($account['email']); ?>, <?php echo json_encode($account['role']); ?>)'><img src="icons/edit.png" alt="Edit Icon"></button>
                  <?php if ((int)$account['account_id'] !== $user_id): ?>
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="action" value="delete_user">
                      <input type="hidden" name="account_id" value="<?php echo (int)$account['account_id']; ?>">
                      <button type="submit" class="delete" onclick="return confirm('Delete this account?')"><img src="icons/trash.png" alt="Delete Icon"></button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="5" class="empty">No accounts found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

  <div class="panel3" id="sales">
    <h3>Sales Dashboard</h3>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Receipt</th><th>Customer</th><th>Payment</th><th>Total</th><th>Given</th><th>Change</th><th>Date</th><th></th></tr></thead>
        <tbody>
          <?php if ($recent_sales && $recent_sales->num_rows > 0): ?>
            <?php while ($sale = $recent_sales->fetch_assoc()): ?>
              <tr>
                <td><?php echo htmlspecialchars($sale['receipt_number']); ?></td>
                <td><?php echo htmlspecialchars($sale['customer_name']); ?></td>
                <td><?php echo htmlspecialchars($sale['payment_method']); ?></td>
                <td>₱<?php echo number_format((float)$sale['total_amount'], 2); ?></td>
                <td>₱<?php echo number_format((float)$sale['payment_amount'], 2); ?></td>
                <td>₱<?php echo number_format((float)$sale['change_amount'], 2); ?></td>
                <td><?php echo htmlspecialchars($sale['created_at']); ?></td>
                <td><a href="receipt.php?order=<?php echo (int)$sale['order_id']; ?>" class="view"><img src="icons/view.png" alt="View Icon"></a></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="8" class="empty">No paid sales yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<div class="dashboard_products" id="products">

    <div class="dashboard_products_left">

      <div class="panel_edit_products" id="products">
        <h3>Add or Edit Product</h3>
        <form method="POST" enctype="multipart/form-data" id="productForm">
          <input type="hidden" name="action" value="save_product">
          <input type="hidden" name="product_id" id="adminProductId" value="">
          <input type="hidden" name="existing_product_image" id="adminExistingProductImage" value="">
          <div class="form-grid">
            <div class="form-group"><label>Product Name</label><input type="text" name="product_name" id="adminProductName" class="form-input" required></div>
            <div class="form-group">
              <label>Category</label>
              <select name="category_id" id="adminProductCategory" class="form-input" required>
                <?php $dashboard_categories->data_seek(0); while ($cat = $dashboard_categories->fetch_assoc()): ?>
                  <option value="<?php echo (int)$cat['category_id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="form-group"><label>Price</label><input type="number" step="0.01" min="0" name="price" id="adminProductPrice" class="form-input" required></div>
            <div class="form-group"><label>Stock</label><input type="number" min="0" name="stock" id="adminProductStock" class="form-input" required></div>
            <div class="form-group"><label>Image</label><input type="file" name="product_image_file" id="adminProductImageFile" class="form-input" accept="image/*"></div>
            <div class="form-group full"><label>Description</label><input type="text" name="description" id="adminProductDescription" class="form-input"></div>
          </div>
          <button type="submit" class="save"><img src="icons/save.png" alt="Save Icon"></button>
          <button type="button" class="reset" onclick="clearProductForm()"><img src="icons/reset.png" alt="Reset Icon"></button>
        </form>
      </div>

      <div class="panel_categories" id="categories">
        <h3>Add or Edit Category</h3>
        <form method="POST" id="categoryForm">
          <input type="hidden" name="action" value="save_category">
          <input type="hidden" name="category_id" id="adminCategoryId" value="">
          <div class="form-grid">
            <div class="form-group"><label>Category Name</label><input type="text" name="category_name" id="adminCategoryName" class="form-input" required></div>
          </div>
          <button type="submit" class="save"><img src="icons/save.png" alt="Save Icon"></button>
          <button type="button" class="reset" onclick="clearCategoryForm()"><img src="icons/reset.png" alt="Reset Icon"></button>
        </form>

        <div class="table-wrap" style="margin-top:20px;">
          <table>
            <thead><tr><th>Category Name</th><th>Action</th></tr></thead>
            <tbody>
              <?php $categories->data_seek(0); while ($cat = $categories->fetch_assoc()): ?>
                <tr>
                  <td><?php echo htmlspecialchars($cat['category_name']); ?></td>
                  <td class="action-cell">
                    <button type="button" class="edit" onclick='editCategory(<?php echo (int)$cat['category_id']; ?>, <?php echo json_encode($cat['category_name']); ?>)'><img src="icons/edit.png" alt="Edit Icon"></button>
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="action" value="delete_category">
                      <input type="hidden" name="category_id" value="<?php echo (int)$cat['category_id']; ?>">
                      <button type="submit" class="delete" onclick="return confirm('Delete this category?')"><img src="icons/trash.png" alt="Delete Icon"></button>
                    </form>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <div class="panel_view_products">
      <h3>All Products</h3>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Image</th><th>Action</th></tr></thead>
          <tbody>
            <?php if ($dashboard_products && $dashboard_products->num_rows > 0): ?>
              <?php $dashboard_products->data_seek(0); while ($prod = $dashboard_products->fetch_assoc()): ?>
                <tr>
                  <td><?php echo htmlspecialchars($prod['product_name']); ?></td>
                  <td><?php echo htmlspecialchars($prod['category_name'] ?? '-'); ?></td>
                  <td>₱<?php echo number_format((float)$prod['price'], 2); ?></td>
                  <td><?php echo (int)$prod['stock']; ?></td>
                  <td><img src="<?php echo htmlspecialchars($prod['product_image'] ?: 'MATCHA.png'); ?>" alt="" class="thumb"></td>
                  <td class="action-cell">
                    <button type="button" class="edit" onclick='editProduct(<?php echo (int)$prod['product_id']; ?>, <?php echo json_encode($prod['product_name']); ?>, <?php echo json_encode($prod['description']); ?>, <?php echo (float)$prod['price']; ?>, <?php echo (int)$prod['stock']; ?>, <?php echo (int)$prod['category_id']; ?>, <?php echo json_encode($prod['product_image']); ?>)'><img src="icons/edit.png" alt="Edit Icon"></button>
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="action" value="delete_product">
                      <input type="hidden" name="product_id" value="<?php echo (int)$prod['product_id']; ?>">
                      <button type="submit" class="delete" onclick="return confirm('Delete this product?')"><img src="icons/trash.png" alt="Delete Icon"></button>
                    </form>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="6" class="empty">No products found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="dashboard_products" id="featured">
    

    <div class="dashboard_products_left">
      <div class="panel_edit_products">
        <h3>Edit Featured Picture</h3>
        <form method="POST" enctype="multipart/form-data" id="featuredForm">
          <input type="hidden" name="action" value="save_featured">
          <input type="hidden" name="existing_featured_image" id="adminExistingFeaturedImage" value="<?php echo htmlspecialchars($featured['image'] ?? ''); ?>">
          <div class="form-grid">
            <div class="form-group"><label>Title</label><input type="text" name="featured_title" id="adminFeaturedTitle" class="form-input" value="<?php echo htmlspecialchars($featured['title'] ?? ''); ?>" required></div>
            <div class="form-group full"><label>Description</label><input type="text" name="featured_description" id="adminFeaturedDescription" class="form-input" value="<?php echo htmlspecialchars($featured['description'] ?? ''); ?>"></div>
            <div class="form-group"><label>Image</label><input type="file" name="featured_image_file" id="adminFeaturedImageFile" class="form-input" accept="image/*"></div>
          </div>
          <button type="submit" class="save"><img src="icons/save.png" alt="Save Icon"></button>
        </form>
        <?php if (!empty($featured['image'])): ?>
          <img src="<?php echo htmlspecialchars($featured['image']); ?>" alt="Current featured image" class="thumb">
        <?php endif; ?>
      </div>

      <div class="panel_categories">
        <h3>Add Carousel Image</h3>
        <form method="POST" enctype="multipart/form-data" id="placeForm">
          <input type="hidden" name="action" value="save_place_image">
          <div class="form-grid">
            <div class="form-group"><label>Image</label><input type="file" name="place_image_file" id="adminPlaceImageFile" class="form-input" accept="image/*" required></div>
            <div class="form-group"><label>Alt Text</label><input type="text" name="place_alt_text" id="adminPlaceAltText" class="form-input"></div>
          </div>
          <button type="submit" class="save"><img src="icons/save.png" alt="Save Icon"></button>
        </form>
      </div>

    </div>

    <div class="panel_view_products">
      <h3>Carousel Images</h3>
      <div class="table-wrap carousel-table-wrap">
        <table>
          <thead><tr><th>Image</th><th>Alt Text</th><th>Action</th></tr></thead>
          <tbody>
            <?php if ($place_images && $place_images->num_rows > 0): ?>
              <?php while ($place_img = $place_images->fetch_assoc()): ?>
                <tr>
                  <td><img src="<?php echo htmlspecialchars($place_img['image']); ?>" alt="" class="thumb"></td>
                  <td><?php echo htmlspecialchars($place_img['alt_text']); ?></td>
                  <td class="action-cell">
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="action" value="delete_place_image">
                      <input type="hidden" name="place_id" value="<?php echo (int)$place_img['id']; ?>">
                      <button type="submit" class="delete" onclick="return confirm('Delete this image?')"><img src="icons/trash.png" alt="Delete Icon"></button>
                    </form>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="3" class="empty">No carousel images yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

</section>

<script>
  document.querySelectorAll('.nav1 .nav-link').forEach(link => {
  link.addEventListener('click', () => {
    document.querySelectorAll('.nav1 .nav-link').forEach(item => item.classList.remove('active'));
    link.classList.add('active');
  });
});

function editUser(id, fullname, email, role) {
  document.getElementById('adminAccountId').value = id;
  document.getElementById('adminFullname').value = fullname;
  document.getElementById('adminEmail').value = email;
  document.getElementById('adminRole').value = role;
  document.getElementById('adminPassword').value = '';
  document.getElementById('userForm').scrollIntoView({ behavior: 'smooth' });
}

function clearUserForm() {
  document.getElementById('userForm').reset();
  document.getElementById('adminAccountId').value = '';
}

function editProduct(id, name, description, price, stock, categoryId, image) {
  document.getElementById('adminProductId').value = id;
  document.getElementById('adminProductName').value = name;
  document.getElementById('adminProductDescription').value = description || '';
  document.getElementById('adminProductPrice').value = price;
  document.getElementById('adminProductStock').value = stock;
  document.getElementById('adminProductCategory').value = categoryId;
  document.getElementById('adminExistingProductImage').value = image || '';
  document.getElementById('productForm').scrollIntoView({ behavior: 'smooth' });
}

function clearProductForm() {
  document.getElementById('productForm').reset();
  document.getElementById('adminProductId').value = '';
  document.getElementById('adminExistingProductImage').value = '';
}

function editCategory(id, name) {
  document.getElementById('adminCategoryId').value = id;
  document.getElementById('adminCategoryName').value = name;
  document.getElementById('categoryForm').scrollIntoView({ behavior: 'smooth' });
}

function clearCategoryForm() {
  document.getElementById('categoryForm').reset();
  document.getElementById('adminCategoryId').value = '';
}
</script>

</body>
</html>