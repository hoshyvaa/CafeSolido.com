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

if (!$is_logged_in || $role !== 'Manager') {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

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

        header("Location: index_manager.php?stock_saved=1#inventory");
        exit;
    }
}

$cart_items = $_SESSION['cart'] ?? [];
$cart_count = count($cart_items);
$cart_preview = array_slice(array_reverse($cart_items), 0, 5);

$total_sales = 0;
$salesResult = $conn->query("SELECT COALESCE(SUM(total_amount), 0) AS total_sales FROM orders WHERE status = 'Paid'");
if ($salesResult) {
    $total_sales = (float)$salesResult->fetch_assoc()['total_sales'];
}

$total_stocks = 0;
$stockResult = $conn->query("SELECT COALESCE(SUM(stock), 0) AS total_stock FROM products");
if ($stockResult) {
    $total_stocks = (int)$stockResult->fetch_assoc()['total_stock'];
}

$total_customers = 0;
$customerCountResult = $conn->query("SELECT COUNT(*) AS total_customers FROM accounts WHERE role = 'Customer'");
if ($customerCountResult) {
    $total_customers = (int)$customerCountResult->fetch_assoc()['total_customers'];
}

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

$show_stock_saved = isset($_GET['stock_saved']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manager - Cafe Solido</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<header>
  <div class="nav-left">
    <a href="index.php"><img src="logo/logoNAME.png" alt="Cafe Solido Logo" class="logo"></a>
  </div>

  <div class="nav-center">
    <ul class="nav1">
      <li><a class="nav-link active" href="#dashboard_manager">Home</a></li>
      <li><a class="nav-link" href="#inventory">Inventory</a></li>
      <li><a class="nav-link" href="#sales">Sales</a></li>
    </ul>
  </div>

  <div class="nav_right">
    <span class="nav_right_username">Hi, <?php echo htmlspecialchars($user_name); ?></span>

    <div class="nav_right_cartwrap">
      <a class="contact1" href="cart.php">
        <button class="nav_right_iconbtn">
          <img src="icons/cart.png" alt="Cart Icon">
          <?php if ($cart_count > 0): ?><span class="cart_count_badge"><?php echo $cart_count; ?></span><?php endif; ?>
        </button>
      </a>
      <div class="nav_right_cartdropdown">
        <div class="nav_right_cartdropdown_inner">
          <h4>Recently Added Products</h4>
          <?php if ($cart_count > 0): ?>
            <?php foreach ($cart_preview as $item): ?>
              <a href="cart.php" class="cart_preview_item">
                <span><?php echo htmlspecialchars($item['name']); ?> × <?php echo (int)$item['quantity']; ?></span>
                <p>₱<?php echo number_format($item['price'], 2); ?></p>
              </a>
            <?php endforeach; ?>
            <div class="cart_preview_footer">
              <span><?php echo $cart_count; ?> item<?php echo $cart_count > 1 ? 's' : ''; ?> in cart</span>
              <a href="cart.php">View My Cart</a>
            </div>
          <?php else: ?>
            <p>No items in your cart yet.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <a href="https://www.instagram.com/cafe_solido_/?utm_source=ig_web_button_share_sheet&stkn=ZDNlZDc0MzIxNw==" target="_blank" title="Instagram">
      <button class="nav_right_iconbtn"><img src="icons/instagram.png" alt="Instagram Icon"></button>
    </a>

    <a href="logout.php" title="Log Out"><button class="nav_right_iconbtn"><img src="icons/logout.png" alt="Log Out Icon"></button></a>
  </div>
</header>

<section class="dashboard_admin" id="dashboard_manager">
<div class="dashboards">

  <div class="dashboard-grid">
    <div class="dashboard-card"><span><div class="icons"><img src="icons/sales.png" alt="Sales Icon">Total Sales</div></span><h3>₱<?php echo number_format($total_sales, 2); ?></h3><p>Paid orders</p></div>
    <div class="dashboard-card"><span><div class="icons"><img src="icons/stocks.png" alt="Stocks Icon">Total Stocks</div></span><h3><?php echo number_format($total_stocks); ?></h3><p>Available product stocks</p></div>
    <div class="dashboard-card"><span><div class="icons"><img src="icons/custoemr.png" alt="Accounts Icon">Customer Accounts</div></span><h3><?php echo number_format($total_customers); ?></h3><p>Registered customers</p></div>
  </div>

  <?php if ($show_stock_saved): ?><div class="notice notice-ok">Stock updated.</div><?php endif; ?>

  <div class="panel2" id="inventory">
    <h3>Manage Inventory / Stock</h3>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Name</th><th>Category</th><th>Current Stock</th><th>Update Stock</th></tr></thead>
        <tbody>
          <?php if ($dashboard_products && $dashboard_products->num_rows > 0): ?>
            <?php $dashboard_products->data_seek(0); while ($prod = $dashboard_products->fetch_assoc()): ?>
              <tr>
                <td><?php echo htmlspecialchars($prod['product_name']); ?></td>
                <td><?php echo htmlspecialchars($prod['category_name'] ?? '-'); ?></td>
                <td><?php echo (int)$prod['stock']; ?></td>
                <td>
                  <form method="POST" class="stock-form">
                    <input type="hidden" name="action" value="update_stock">
                    <input type="hidden" name="product_id" value="<?php echo (int)$prod['product_id']; ?>">
                    <input type="number" min="0" name="stock" class="form-input" value="<?php echo (int)$prod['stock']; ?>" required>
                    <button type="submit" class="save"><img src="icons/save.png" alt="Save Icon"></button>
                  </form>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="4" class="empty">No products found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel3" id="sales">
    <h3>Sales Reports</h3>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Receipt</th><th>Customer</th><th>Payment</th><th>Amount</th><th>Date</th><th></th></tr></thead>
        <tbody>
          <?php if ($recent_sales && $recent_sales->num_rows > 0): ?>
            <?php while ($sale = $recent_sales->fetch_assoc()): ?>
              <tr>
                <td><?php echo htmlspecialchars($sale['receipt_number']); ?></td>
                <td><?php echo htmlspecialchars($sale['customer_name']); ?></td>
                <td><?php echo htmlspecialchars($sale['payment_method']); ?></td>
                <td>₱<?php echo number_format((float)$sale['total_amount'], 2); ?></td>
                <td><?php echo htmlspecialchars($sale['created_at']); ?></td>
                <td><a href="receipt.php?order=<?php echo (int)$sale['order_id']; ?>" class="view"><img src="icons/view.png" alt="View Icon"></a></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="6" class="empty">No paid sales yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
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
</script>

</body>
</html>