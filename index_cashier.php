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

if (!$is_logged_in || !canDo($role, 'process_orders')) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'cashier_search') {

        $receipt = trim($_POST['receipt_number'] ?? '');

        if ($receipt !== '') {
            header("Location: index_cashier.php?receipt=" . urlencode($receipt));
        } else {
            header("Location: index_cashier.php");
        }

        exit;
    }

    if ($action === 'cashier_add_product') {

        $order_id = (int)($_POST['order_id'] ?? 0);
        $product_id = (int)($_POST['product_id'] ?? 0);
        $add_quantity = (int)($_POST['add_quantity'] ?? 1);

        if ($order_id <= 0 || $product_id <= 0 || $add_quantity <= 0) {
            header("Location: index_cashier.php?order=" . $order_id . "&add_error=1");
            exit;
        }

        $conn->begin_transaction();

        try {

            $orderStmt = $conn->prepare("SELECT status FROM orders WHERE order_id = ? LIMIT 1 FOR UPDATE");
            $orderStmt->bind_param("i", $order_id);
            $orderStmt->execute();
            $orderResult = $orderStmt->get_result();

            if ($orderResult->num_rows !== 1) {
                throw new Exception("Order not found.");
            }

            $orderData = $orderResult->fetch_assoc();

            if ($orderData['status'] !== 'Pending') {
                throw new Exception("Only pending orders can be edited.");
            }

            $productStmt = $conn->prepare("SELECT product_name, price, stock FROM products WHERE product_id = ? LIMIT 1 FOR UPDATE");
            $productStmt->bind_param("i", $product_id);
            $productStmt->execute();
            $productResult = $productStmt->get_result();

            if ($productResult->num_rows !== 1) {
                throw new Exception("Product not found.");
            }

            $product = $productResult->fetch_assoc();

            $reduceStock = $conn->prepare("UPDATE products SET stock = stock - ? WHERE product_id = ? AND stock >= ?");
            $reduceStock->bind_param("iii", $add_quantity, $product_id, $add_quantity);
            $reduceStock->execute();

            if ($reduceStock->affected_rows !== 1) {
                throw new Exception("Not enough stock for this product.");
            }

            $existingItemStmt = $conn->prepare("SELECT order_item_id, quantity FROM order_items WHERE order_id = ? AND product_id = ? LIMIT 1 FOR UPDATE");
            $existingItemStmt->bind_param("ii", $order_id, $product_id);
            $existingItemStmt->execute();
            $existingItemResult = $existingItemStmt->get_result();

            if ($existingItemResult->num_rows === 1) {

                $existingItem = $existingItemResult->fetch_assoc();
                $new_quantity = (int)$existingItem['quantity'] + $add_quantity;
                $existing_item_id = (int)$existingItem['order_item_id'];

                $updateItem = $conn->prepare("UPDATE order_items SET quantity = ?, subtotal = price * ? WHERE order_item_id = ?");
                $updateItem->bind_param("iii", $new_quantity, $new_quantity, $existing_item_id);
                $updateItem->execute();

            } else {

                $price = (float)$product['price'];
                $subtotal = $price * $add_quantity;
                $product_name = $product['product_name'];

                $insertItem = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
                $insertItem->bind_param("iisdid", $order_id, $product_id, $product_name, $price, $add_quantity, $subtotal);
                $insertItem->execute();
            }

            $totalStmt = $conn->prepare("SELECT COALESCE(SUM(subtotal), 0) AS total FROM order_items WHERE order_id = ?");
            $totalStmt->bind_param("i", $order_id);
            $totalStmt->execute();
            $newTotal = (float)$totalStmt->get_result()->fetch_assoc()['total'];

            $orderUpdate = $conn->prepare("UPDATE orders SET total_amount = ? WHERE order_id = ?");
            $orderUpdate->bind_param("di", $newTotal, $order_id);
            $orderUpdate->execute();

            $conn->commit();

            header("Location: index_cashier.php?order=" . $order_id . "&added=1");
            exit;

        } catch (Exception $e) {

            $conn->rollback();
            header("Location: index_cashier.php?order=" . $order_id . "&add_error=1");
            exit;
        }
    }

    if ($action === 'cashier_update_order') {

        $order_id = (int)($_POST['order_id'] ?? 0);

        if ($order_id <= 0) {
            header("Location: index_cashier.php");
            exit;
        }

        $conn->begin_transaction();

        try {

            $orderStmt = $conn->prepare("SELECT status FROM orders WHERE order_id = ? LIMIT 1 FOR UPDATE");
            $orderStmt->bind_param("i", $order_id);
            $orderStmt->execute();
            $orderResult = $orderStmt->get_result();

            if ($orderResult->num_rows !== 1) {
                throw new Exception("Order not found.");
            }

            $orderData = $orderResult->fetch_assoc();

            if ($orderData['status'] === 'Paid') {
                throw new Exception("Paid orders cannot be edited.");
            }

            $existingStmt = $conn->prepare("SELECT order_item_id, product_id, quantity FROM order_items WHERE order_id = ? FOR UPDATE");
            $existingStmt->bind_param("i", $order_id);
            $existingStmt->execute();
            $existingResult = $existingStmt->get_result();

            $existingItems = [];

            while ($row = $existingResult->fetch_assoc()) {
                $existingItems[(int)$row['order_item_id']] = $row;
            }

            $submitted = $_POST['quantities'] ?? [];

            foreach ($existingItems as $item_id => $oldItem) {

                $old_quantity = (int)$oldItem['quantity'];
                $new_quantity = isset($submitted[$item_id]) ? (int)$submitted[$item_id] : $old_quantity;
                $product_id = (int)$oldItem['product_id'];

                if ($new_quantity <= 0) {

                    $returnStock = $conn->prepare("UPDATE products SET stock = stock + ? WHERE product_id = ?");
                    $returnStock->bind_param("ii", $old_quantity, $product_id);
                    $returnStock->execute();

                    $deleteStmt = $conn->prepare("DELETE FROM order_items WHERE order_item_id = ? AND order_id = ?");
                    $deleteStmt->bind_param("ii", $item_id, $order_id);
                    $deleteStmt->execute();

                } elseif ($new_quantity !== $old_quantity) {

                    $difference = $new_quantity - $old_quantity;

                    if ($difference > 0) {

                        $reduceStock = $conn->prepare("UPDATE products SET stock = stock - ? WHERE product_id = ? AND stock >= ?");
                        $reduceStock->bind_param("iii", $difference, $product_id, $difference);
                        $reduceStock->execute();

                        if ($reduceStock->affected_rows !== 1) {
                            throw new Exception("Not enough stock for increased quantity.");
                        }

                    } else {

                        $returnQuantity = abs($difference);

                        $returnStock = $conn->prepare("UPDATE products SET stock = stock + ? WHERE product_id = ?");
                        $returnStock->bind_param("ii", $returnQuantity, $product_id);
                        $returnStock->execute();
                    }

                    $updateStmt = $conn->prepare("UPDATE order_items SET quantity = ?, subtotal = price * ? WHERE order_item_id = ? AND order_id = ?");
                    $updateStmt->bind_param("iiii", $new_quantity, $new_quantity, $item_id, $order_id);
                    $updateStmt->execute();
                }
            }

            $totalStmt = $conn->prepare("SELECT COALESCE(SUM(subtotal), 0) AS total FROM order_items WHERE order_id = ?");
            $totalStmt->bind_param("i", $order_id);
            $totalStmt->execute();
            $newTotal = (float)$totalStmt->get_result()->fetch_assoc()['total'];

            if ($newTotal <= 0) {

                $cancelStmt = $conn->prepare("UPDATE orders SET total_amount = 0, status = 'Cancelled' WHERE order_id = ?");
                $cancelStmt->bind_param("i", $order_id);
                $cancelStmt->execute();

            } else {

                $orderUpdate = $conn->prepare("UPDATE orders SET total_amount = ? WHERE order_id = ?");
                $orderUpdate->bind_param("di", $newTotal, $order_id);
                $orderUpdate->execute();
            }

            $conn->commit();

            header("Location: index_cashier.php?order=" . $order_id . "&updated=1");
            exit;

        } catch (Exception $e) {

            $conn->rollback();
            header("Location: index_cashier.php?order=" . $order_id . "&update_error=1");
            exit;
        }
    }

    if ($action === 'cashier_payment') {

        $order_id = (int)($_POST['order_id'] ?? 0);
        $payment_method = $_POST['payment_method'] ?? 'Cash';
        $payment_amount = (float)($_POST['payment_amount'] ?? 0);

        if (!in_array($payment_method, ['Cash', 'GCash'], true)) {
            $payment_method = 'Cash';
        }

        $stmt = $conn->prepare("SELECT total_amount, status FROM orders WHERE order_id = ? LIMIT 1");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {

            $order = $result->fetch_assoc();
            $total = (float)$order['total_amount'];

            if ($order['status'] === 'Paid') {
                header("Location: receipt.php?order=" . $order_id);
                exit;
            }

            if ($payment_method === 'GCash') {
                $payment_amount = $total;
            }

            if ($payment_amount >= $total && $total > 0) {

                $change = $payment_amount - $total;

                $update = $conn->prepare("UPDATE orders SET payment_method = ?, payment_amount = ?, change_amount = ?, status = 'Paid' WHERE order_id = ?");
                $update->bind_param("sddi", $payment_method, $payment_amount, $change, $order_id);

                if ($update->execute()) {
                    header("Location: receipt.php?order=" . $order_id);
                    exit;
                }
            }
        }

        header("Location: index_cashier.php?order=" . $order_id . "&payment_error=1");
        exit;
    }
}

$cart_items = $_SESSION['cart'] ?? [];
$cart_count = count($cart_items);
$cart_preview = array_slice(array_reverse($cart_items), 0, 5);

$pending_orders = $conn->query("
    SELECT order_id, customer_name, total_amount, status, payment_method, receipt_number, created_at
    FROM orders
    WHERE status = 'Pending'
    ORDER BY order_id DESC
    LIMIT 20
");

$recent_sales = $conn->query("
    SELECT order_id, customer_name, total_amount, payment_method, payment_amount, change_amount, receipt_number, created_at
    FROM orders
    WHERE status = 'Paid'
    ORDER BY order_id DESC
    LIMIT 20
");

$cashier_order = null;
$cashier_items = [];

$receipt_search = trim($_GET['receipt'] ?? '');
$order_search = (int)($_GET['order'] ?? 0);

if ($receipt_search !== '') {

    $stmt = $conn->prepare("SELECT * FROM orders WHERE receipt_number = ? LIMIT 1");
    $stmt->bind_param("s", $receipt_search);
    $stmt->execute();
    $cashierResult = $stmt->get_result();

    if ($cashierResult->num_rows === 1) {
        $cashier_order = $cashierResult->fetch_assoc();
    }

} elseif ($order_search > 0) {

    $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? LIMIT 1");
    $stmt->bind_param("i", $order_search);
    $stmt->execute();
    $cashierResult = $stmt->get_result();

    if ($cashierResult->num_rows === 1) {
        $cashier_order = $cashierResult->fetch_assoc();
    }
}

if ($cashier_order) {

    $stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY order_item_id ASC");
    $stmt->bind_param("i", $cashier_order['order_id']);
    $stmt->execute();
    $cashierItemsResult = $stmt->get_result();

    while ($row = $cashierItemsResult->fetch_assoc()) {
        $cashier_items[] = $row;
    }
}

// Products available to add to a pending order, grouped by category.
$available_products = [];

if ($cashier_order && $cashier_order['status'] === 'Pending') {

    $productsResult = $conn->query("
        SELECT p.product_id, p.product_name, p.price, p.stock, c.category_name
        FROM products p
        JOIN categories c ON p.category_id = c.category_id
        ORDER BY c.category_name ASC, p.product_name ASC
    ");

    while ($row = $productsResult->fetch_assoc()) {
        $available_products[$row['category_name']][] = $row;
    }
}

$show_updated = isset($_GET['updated']);
$show_update_error = isset($_GET['update_error']);
$show_payment_error = isset($_GET['payment_error']);
$show_added = isset($_GET['added']);
$show_add_error = isset($_GET['add_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cashier - Cafe Solido</title>
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

<section class="dashboard_cashier" id="dashboard_cashier">

  <div class="panel1">
  <?php if ($show_updated): ?><div class="notice notice-ok">Order updated.</div><?php endif; ?>
  <?php if ($show_update_error): ?><div class="notice notice-error">Could not update the order.</div><?php endif; ?>
  <?php if ($show_payment_error): ?><div class="notice notice-error">Payment amount is not enough to cover the total.</div><?php endif; ?>
  <?php if ($show_added): ?><div class="notice notice-ok">Product added to the order.</div><?php endif; ?>
  <?php if ($show_add_error): ?><div class="notice notice-error">Could not add that product (check stock and try again).</div><?php endif; ?>
    <h3>Search Order ID</h3>
    <form method="POST" class="inline-form">
      <input type="hidden" name="action" value="cashier_search">
      <input type="text" name="receipt_number" placeholder="Enter Receipt Number e.g. CS-20260921-00001" value="<?php echo htmlspecialchars($_GET['receipt'] ?? ''); ?>">
      <button type="submit" class="green-btn">SEARCH</button>
    </form>

    <?php if ($cashier_order): ?>
      <div class="order-info-grid">
        <div class="order-info-item"><span>Receipt Number</span><strong><?php echo htmlspecialchars($cashier_order['receipt_number']); ?></strong></div>
        <div class="order-info-item"><span>Customer</span><strong><?php echo htmlspecialchars($cashier_order['customer_name']); ?></strong></div>
        <div class="order-info-item"><span>Total</span><strong>₱<?php echo number_format((float)$cashier_order['total_amount'], 2); ?></strong></div>
        <div class="order-info-item">
          <span>Status</span>
          <strong>
            <?php if ($cashier_order['status'] === 'Paid'): ?>
              <span class="status-badge status-paid">Paid</span>
            <?php elseif ($cashier_order['status'] === 'Cancelled'): ?>
              <span class="status-badge status-cancelled">Cancelled</span>
            <?php else: ?>
              <span class="status-badge">Pending</span>
            <?php endif; ?>
          </strong>
        </div>
      </div>

      <form method="POST">
        <input type="hidden" name="action" value="cashier_update_order">
        <input type="hidden" name="order_id" value="<?php echo (int)$cashier_order['order_id']; ?>">
        <div class="table-wrap">
          <table>
            <thead><tr><th>Product</th><th>Price</th><th>Quantity</th><th>Subtotal</th></tr></thead>
            <tbody>
              <?php if (!empty($cashier_items)): ?>
                <?php foreach ($cashier_items as $item): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                    <td>₱<?php echo number_format((float)$item['price'], 2); ?></td>
                    <td>
                      <?php if ($cashier_order['status'] === 'Pending'): ?>
                        <input class="quantity-input" type="number" min="0" name="quantities[<?php echo (int)$item['order_item_id']; ?>]" value="<?php echo (int)$item['quantity']; ?>">
                      <?php else: ?>
                        <?php echo (int)$item['quantity']; ?>
                      <?php endif; ?>
                    </td>
                    <td>₱<?php echo number_format((float)$item['subtotal'], 2); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="4" class="empty">No items in this order.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php if ($cashier_order['status'] === 'Pending' && !empty($cashier_items)): ?>
          <button type="submit" class="green-btn" style="margin-top:16px;">UPDATE CUSTOMER ORDER</button>
        <?php endif; ?>
      </form>

      <?php if ($cashier_order['status'] === 'Pending'): ?>
        <div class="add-product-box payment-box">
          <h4>Add Product to Order</h4>
          <form method="POST">
            <input type="hidden" name="action" value="cashier_add_product">
            <input type="hidden" name="order_id" value="<?php echo (int)$cashier_order['order_id']; ?>">

            <div class="payment-group">
              <label>Product</label>
              <select name="product_id" required>
                <option value="">Select a product</option>
                <?php foreach ($available_products as $category_name => $products): ?>
                  <optgroup label="<?php echo htmlspecialchars($category_name); ?>">
                    <?php foreach ($products as $product): ?>
                      <option value="<?php echo (int)$product['product_id']; ?>" <?php echo $product['stock'] <= 0 ? 'disabled' : ''; ?>>
                        <?php echo htmlspecialchars($product['product_name']); ?>
                        — ₱<?php echo number_format((float)$product['price'], 2); ?>
                        (<?php echo $product['stock'] > 0 ? (int)$product['stock'] . ' in stock' : 'out of stock'; ?>)
                      </option>
                    <?php endforeach; ?>
                  </optgroup>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="payment-group">
              <label>Quantity</label>
              <input type="number" name="add_quantity" min="1" value="1">
            </div>

            <button type="submit" class="green-btn" style="width:100%;margin-top:12px;">ADD PRODUCT</button>
          </form>
        </div>
      <?php endif; ?>

      <?php if ($cashier_order['status'] === 'Pending' && !empty($cashier_items)): ?>
        <div class="payment-box">
          <h4>Payment</h4>
          <form method="POST">
            <input type="hidden" name="action" value="cashier_payment">
            <input type="hidden" name="order_id" value="<?php echo (int)$cashier_order['order_id']; ?>">
            <div class="payment-group">
              <label>Payment Method</label>
              <select name="payment_method" id="cashierPaymentMethod" onchange="toggleCashPayment()">
                <option value="Cash">Cash</option>
                <option value="GCash">GCash</option>
              </select>
            </div>
            <div class="payment-group" id="cashAmountGroup">
              <label>Cash Given</label>
              <input type="number" step="0.01" min="0" name="payment_amount" id="cashGiven" placeholder="Enter cash amount" oninput="calculateChange(<?php echo (float)$cashier_order['total_amount']; ?>)">
            </div>
            <div id="changeDisplay" class="change-display"></div>
            <button type="submit" class="green-btn" style="width:100%;margin-top:12px;">COMPLETE PAYMENT & GENERATE RECEIPT</button>
          </form>
        </div>
      <?php endif; ?>

      <?php if ($cashier_order['status'] === 'Paid'): ?>
        <div class="notice notice-ok" style="margin-top:16px;">This order is already paid.</div>
        <a href="receipt.php?order=<?php echo (int)$cashier_order['order_id']; ?>" class="small-btn">VIEW RECEIPT</a>
      <?php endif; ?>

    <?php else: ?>
      <div class="empty">Enter a receipt number to display the customer's order.</div>
    <?php endif; ?>
  </div>

  <?php if ($pending_orders && $pending_orders->num_rows > 0): ?>
    <div class="panel2">
      <h3>Pending Orders</h3>
      <div class="pending-list">
        <?php while ($pending = $pending_orders->fetch_assoc()): ?>
          <div class="pending-item">
            <div>
              <strong><?php echo htmlspecialchars($pending['receipt_number']); ?></strong><br>
              <span><?php echo htmlspecialchars($pending['customer_name']); ?></span><br>
              <span>₱<?php echo number_format((float)$pending['total_amount'], 2); ?></span>
            </div>
            <a href="index_cashier.php?order=<?php echo (int)$pending['order_id']; ?>" class="small-btn">OPEN</a>
          </div>
        <?php endwhile; ?>
      </div>
    </div>
  <?php endif; ?>

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

</section>

<script>
function toggleCashPayment() {
  const method = document.getElementById('cashierPaymentMethod');
  const group = document.getElementById('cashAmountGroup');
  if (!method || !group) return;
  group.style.display = method.value === 'Cash' ? 'block' : 'none';
}

function calculateChange(total) {
  const given = parseFloat(document.getElementById('cashGiven').value) || 0;
  const display = document.getElementById('changeDisplay');
  if (!display) return;
  const change = given - total;
  display.style.display = 'block';
  display.textContent = change >= 0 ? 'Change: ₱' + change.toFixed(2) : 'Insufficient amount';
}
</script>

</body>
</html>