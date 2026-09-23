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

if (!$is_logged_in) {
    header("Location: login.php");
    exit;
}

$receipt_order = null;
$receipt_items = [];

$receipt_order_id = (int)($_GET['order'] ?? 0);

if ($receipt_order_id > 0) {

    if ($role === 'Customer') {
        $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? AND customer_id = ? LIMIT 1");
        $stmt->bind_param("ii", $receipt_order_id, $user_id);
    } else {
        $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? LIMIT 1");
        $stmt->bind_param("i", $receipt_order_id);
    }

    $stmt->execute();
    $receiptResult = $stmt->get_result();

    if ($receiptResult->num_rows === 1) {

        $receipt_order = $receiptResult->fetch_assoc();

        $itemStmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY order_item_id ASC");
        $itemStmt->bind_param("i", $receipt_order_id);
        $itemStmt->execute();
        $receiptItemsResult = $itemStmt->get_result();

        while ($row = $receiptItemsResult->fetch_assoc()) {
            $receipt_items[] = $row;
        }
    }
}

$cart_items = $_SESSION['cart'] ?? [];
$cart_count = count($cart_items);
$cart_preview = array_slice(array_reverse($cart_items), 0, 5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Receipt - Cafe Solido</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<header>
  <div class="nav-left">
    <a href="index.php"><img src="logo/logoNAME.png" alt="Cafe Solido Logo" class="logo"></a>
  </div>

  <div class="nav_right">
    <span class="nav_right_username">Hi, <?php echo htmlspecialchars($user_name); ?></span>

    <a href="https://www.instagram.com/cafe_solido_/?utm_source=ig_web_button_share_sheet&stkn=ZDNlZDc0MzIxNw==" target="_blank" title="Instagram">
      <button class="nav_right_iconbtn"><img src="icons/instagram.png" alt="Instagram Icon"></button>
    </a>

    <a href="logout.php" title="Log Out"><button class="nav_right_iconbtn"><img src="icons/logout.png" alt="Log Out Icon"></button></a>
  </div>
</header>

<section class="dashboard" id="dashboard">

  <?php if ($receipt_order): ?>
    <div class="panel receipt-box">
      <div class="receipt-header">
        <img src="logo/cup_logo.png">
        <h2>Cafe Solido</h2>
        <p><?php echo htmlspecialchars($receipt_order['receipt_number']); ?></p>
        <p><?php echo htmlspecialchars($receipt_order['created_at']); ?></p>
      </div>
      <div class="receipt-row"><span>Customer</span><span><?php echo htmlspecialchars($receipt_order['customer_name']); ?></span></div>
      <div class="receipt-row"><span>Status</span><span><?php echo htmlspecialchars($receipt_order['status']); ?></span></div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Product</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
          <tbody>
            <?php foreach ($receipt_items as $item): ?>
              <tr>
                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                <td>₱<?php echo number_format((float)$item['price'], 2); ?></td>
                <td><?php echo (int)$item['quantity']; ?></td>
                <td>₱<?php echo number_format((float)$item['subtotal'], 2); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="receipt-total">Total: ₱<?php echo number_format((float)$receipt_order['total_amount'], 2); ?></div>
      <?php if ($receipt_order['status'] === 'Paid'): ?>
        <div class="receipt-row"><span>Payment Method</span><span><?php echo htmlspecialchars($receipt_order['payment_method']); ?></span></div>
        <div class="receipt-row"><span>Amount Given</span><span>₱<?php echo number_format((float)$receipt_order['payment_amount'], 2); ?></span></div>
        <div class="receipt-row"><span>Change</span><span>₱<?php echo number_format((float)$receipt_order['change_amount'], 2); ?></span></div>
      <?php endif; ?>
      <div class="receipt-actions">
        <?php if ($role !== 'Customer'): ?>
          <?php if ($role === 'Cashier'): ?>
            <a href="index_cashier.php" class="green-btn">ORDERS</a>
          <?php elseif ($role === 'Manager'): ?>
            <a href="index_manager.php" class="green-btn">DASHBOARD</a>
          <?php elseif ($role === 'Admin'): ?>
            <a href="index_admin.php" class="green-btn">DASHBOARD</a>
          <?php endif; ?>
        <?php endif; ?>
        <a href="javascript:window.print()" class="green-btn">PRINT RECEIPT</a>
      </div>
    </div>
  <?php else: ?>
    <div class="empty">Receipt not found.</div>
  <?php endif; ?>

</section>

</body>
</html>
