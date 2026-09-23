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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'checkout') {

        if (!$is_logged_in) {
            header("Location: login.php");
            exit;
        }

        if (empty($_SESSION['cart'])) {
            header("Location: cart.php");
            exit;
        }

        $payment_method = $_POST['payment_method'] ?? 'Cash';

        if (!in_array($payment_method, ['Cash', 'GCash'], true)) {
            $payment_method = 'Cash';
        }

        $conn->begin_transaction();

        try {

            $verified_items = [];
            $total = 0;

            foreach ($_SESSION['cart'] as $item) {

                $product_id = (int)$item['product_id'];
                $quantity = max(1, (int)$item['quantity']);

                $stockStmt = $conn->prepare("SELECT product_id, product_name, price, stock FROM products WHERE product_id = ? FOR UPDATE");
                $stockStmt->bind_param("i", $product_id);
                $stockStmt->execute();
                $stockResult = $stockStmt->get_result();

                if ($stockResult->num_rows !== 1) {
                    throw new Exception("Product not found.");
                }

                $product = $stockResult->fetch_assoc();
                $stockStmt->close();

                if ((int)$product['stock'] < $quantity) {
                    throw new Exception("Insufficient stock.");
                }

                $price = (float)$product['price'];
                $subtotal = $price * $quantity;

                $verified_items[] = [
                    'product_id' => $product_id,
                    'product_name' => $product['product_name'],
                    'price' => $price,
                    'quantity' => $quantity,
                    'subtotal' => $subtotal
                ];

                $total += $subtotal;
            }

            $stmt = $conn->prepare("INSERT INTO orders (customer_id, customer_name, total_amount, status, payment_method, payment_amount, change_amount) VALUES (?, ?, ?, 'Pending', ?, 0, 0)");
            $stmt->bind_param("isds", $user_id, $user_name, $total, $payment_method);

            if (!$stmt->execute()) {
                throw new Exception("Order creation failed.");
            }

            $order_id = $conn->insert_id;
            $receipt_number = 'CS-' . date('Ymd') . '-' . str_pad($order_id, 5, '0', STR_PAD_LEFT);

            $receiptStmt = $conn->prepare("UPDATE orders SET receipt_number = ? WHERE order_id = ?");
            $receiptStmt->bind_param("si", $receipt_number, $order_id);
            $receiptStmt->execute();

            $itemStmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
            $stockUpdateStmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE product_id = ? AND stock >= ?");

            foreach ($verified_items as $item) {

                $product_id = $item['product_id'];
                $product_name = $item['product_name'];
                $price = $item['price'];
                $quantity = $item['quantity'];
                $subtotal = $item['subtotal'];

                $itemStmt->bind_param("iisdid", $order_id, $product_id, $product_name, $price, $quantity, $subtotal);

                if (!$itemStmt->execute()) {
                    throw new Exception("Order item failed.");
                }

                $stockUpdateStmt->bind_param("iii", $quantity, $product_id, $quantity);

                if (!$stockUpdateStmt->execute() || $stockUpdateStmt->affected_rows !== 1) {
                    throw new Exception("Insufficient stock.");
                }
            }

            $conn->commit();

            $_SESSION['cart'] = [];

            header("Location: receipt.php?order=" . $order_id);
            exit;

        } catch (Exception $e) {

            $conn->rollback();
            header("Location: cart.php?error=checkout");
            exit;
        }
    }
}

$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;

$categories = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_id ASC");
if (!$categories) die("Category error: " . $conn->error);

if ($category_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE category_id = ? ORDER BY product_id ASC");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $products = $stmt->get_result();
} else {
    $products = $conn->query("SELECT * FROM products ORDER BY product_id ASC");
}
if (!$products) die("Product error: " . $conn->error);

$featured = $conn->query("SELECT * FROM featured WHERE id = 1")->fetch_assoc();
$place_images = $conn->query("SELECT * FROM place_carousel ORDER BY sort_order ASC, id ASC");

$cart_items = $_SESSION['cart'] ?? [];
$cart_count = count($cart_items);
$cart_preview = array_slice(array_reverse($cart_items), 0, 5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cafe Solido!</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<header>
  <div class="nav-left">
    <a href="index.php"><img src="logo/logoNAME.png" alt="Cafe Solido Logo" class="logo"></a>
  </div>

  <div class="nav-center">
    <ul class="nav1">
      <li><a class="nav-link active" href="#home">Home</a></li>
      <li><a class="nav-link" href="#menu">Menu</a></li>
      <li><a class="nav-link" href="#contacts">Contacts</a></li>
    </ul>
  </div>

  <div class="nav_right">
    <?php if ($is_logged_in): ?>
      <span class="nav_right_username">Hi, <?php echo htmlspecialchars($user_name); ?></span>
    <?php else: ?>
      <a href="login.php"><button class="nav_right_signbtn">SIGN IN</button></a>
    <?php endif; ?>

    <?php if ($is_logged_in && $role !== 'Customer'): ?>
      <?php if ($role === 'Cashier'): ?>
        <a href="index_cashier.php"><button class="nav_right_signbtn">ORDERS</button></a>
      <?php elseif ($role === 'Manager'): ?>
        <a href="index_manager.php"><button class="nav_right_signbtn">DASHBOARD</button></a>
      <?php elseif ($role === 'Admin'): ?>
        <a href="index_admin.php"><button class="nav_right_signbtn">DASHBOARD</button></a>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($is_logged_in): ?>
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
    <?php else: ?>
      <a class="contact1" href="login.php"><button class="nav_right_iconbtn"><img src="icons/cart.png" alt="Cart Icon"></button></a>
    <?php endif; ?>

    <a href="https://www.instagram.com/cafe_solido_/?utm_source=ig_web_button_share_sheet&stkn=ZDNlZDc0MzIxNw==" target="_blank" title="Instagram">
      <button class="nav_right_iconbtn"><img src="icons/instagram.png" alt="Instagram Icon"></button>
    </a>

    <?php if ($is_logged_in): ?>
      <a href="logout.php" title="Log Out"><button class="nav_right_iconbtn"><img src="icons/logout.png" alt="Log Out Icon"></button></a>
    <?php endif; ?>
  </div>
</header>

<div class="card" id="home"<?php if (!empty($featured['image'])): ?> style="background-image:url('<?php echo htmlspecialchars($featured['image']); ?>');"<?php endif; ?>>
  <div class="Featured"><?php echo htmlspecialchars($featured['title'] ?? 'Strawberry Milk'); ?></div>
  <div class="desc"><?php echo htmlspecialchars($featured['description'] ?? 'with our newest dessert, Beef Fuego Taco'); ?></div>
</div>

<section class="place" id="place">
  <div class="place-carousel">
    <button class="place-arrow prev" type="button" aria-label="Scroll left">&#10094;</button>
    <div class="place-container" id="placeContainer">
      <?php if ($place_images && $place_images->num_rows > 0): ?>
        <?php while ($place_img = $place_images->fetch_assoc()): ?>
          <div class="place-card"><img src="<?php echo htmlspecialchars($place_img['image']); ?>" alt="<?php echo htmlspecialchars($place_img['alt_text'] ?: 'Place image'); ?>" loading="lazy" decoding="async"></div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="place-card"><img src="place carousel/MATCHA.png" alt="Matcha drink image" loading="lazy" decoding="async"></div>
      <?php endif; ?>
    </div>
    <button class="place-arrow next" type="button" aria-label="Scroll right">&#10095;</button>
  </div>
</section>

<section class="menu" id="menu">
  <div class="Menu">
    <div class="category">
      <ul id="categoryList">
        <li><a href="#" class="category-link active" data-category="0">All</a></li>
        <?php $categories->data_seek(0); while ($category = $categories->fetch_assoc()): ?>
          <li>
            <a href="#" class="category-link" data-category="<?php echo (int)$category['category_id']; ?>">
              <?php echo htmlspecialchars($category['category_name']); ?>
            </a>
          </li>
        <?php endwhile; ?>
      </ul>
    </div>

    <div class="product_view">
      <div class="Drinks" id="productList">
        <?php if ($products->num_rows > 0): ?>
          <?php while ($product = $products->fetch_assoc()): ?>
            <div class="product_card"
                 data-product-id="<?php echo (int)$product['product_id']; ?>"
                 data-product-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                 data-price="<?php echo $product['price']; ?>">
              <?php if (!empty($product['product_image'])): ?>
                <img src="<?php echo htmlspecialchars($product['product_image']); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>">
              <?php endif; ?>
              <h3><?php echo htmlspecialchars($product['product_name']); ?></h3>
              <div class="product_price"><p>₱<?php echo number_format($product['price'], 2); ?></p></div>
            </div>
          <?php endwhile; ?>
        <?php else: ?>
          <p>No products available in this category.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<section class="about" id="about">
  <div class="about-container">
    <div class="about-text">
      <h2>About Us</h2>
      <p>Welcome to Cafe Solido, your cozy corner for delightful drinks and tasty treats. 
        We pride ourselves on serving high-quality beverages and snacks in a warm and inviting atmosphere. 
        Whether you're here for a quick coffee or a relaxing meal, we aim to make every visit special.</p>
    </div>
  </div>
</section>

<footer class="footer" id="contacts">
  <div class="footer-container">
    <div class="footer-section">
      <h3>Contact Us</h3>
      <p>Email: strongcoffee@gmail.com</p>
      <p>Location: Pangasinan, San Nicolas</p>
    </div>
  </div>
  <div class="footer-bottom">
    <p>© 2026 Cafe Solido. All Rights Reserved.</p>
  </div>
</footer>

<div class="qty_modal_overlay" id="qtyModalOverlay">
  <div class="qty_modal_box">
    <button type="button" class="qty_modal_close" id="qtyCloseBtn" aria-label="Close">&times;</button>
    <div class="qty_modal_body">
      <h3 id="qtyModalProductName">Add to Cart</h3>
      <div class="qty_modal_price" id="qtyModalPrice">₱0.00</div>
      <div class="qty_modal_divider"></div>
      <div class="qty_modal_controls">
        <span class="qty_modal_label">Quantity</span>
        <div class="qty_modal_stepper">
          <button type="button" class="qty_btn" id="qtyMinusBtn">&minus;</button>
          <input type="number" id="qtyModalInput" value="1" min="1">
          <button type="button" class="qty_btn" id="qtyPlusBtn">+</button>
        </div>
      </div>
      <div class="qty_modal_actions">
        <button type="button" class="qty_cancel_btn" id="qtyCancelBtn">CANCEL</button>
        <button type="button" class="qty_confirm_btn" id="qtyConfirmBtn">ADD TO CART</button>
      </div>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('.nav1 .nav-link').forEach(link => {
  link.addEventListener('click', () => {
    document.querySelectorAll('.nav1 .nav-link').forEach(item => item.classList.remove('active'));
    link.classList.add('active');
  });
});

document.querySelectorAll('.category-link').forEach(link => {
  link.addEventListener('click', function (e) {
    e.preventDefault();
    const categoryId = this.dataset.category;
    const productList = document.getElementById('productList');
    if (!productList) return;

    document.querySelectorAll('.category-link').forEach(l => l.classList.remove('active'));
    this.classList.add('active');
    productList.classList.add('fade-out');

    setTimeout(() => {
      fetch('get_products.php?category=' + categoryId)
        .then(response => response.text())
        .then(html => {
          productList.innerHTML = html;
          productList.classList.remove('fade-out');
          bindAddToCartCards();
        })
        .catch(err => {
          console.error('Failed to load products:', err);
          productList.classList.remove('fade-out');
        });
    }, 500);
  });
});

const placeContainer = document.getElementById('placeContainer');
const prevButton = document.querySelector('.place-arrow.prev');
const nextButton = document.querySelector('.place-arrow.next');

if (placeContainer && prevButton && nextButton) {
  const getScrollAmount = () => {
    const card = placeContainer.querySelector('.place-card');
    if (!card) return 260;
    const gap = parseInt(getComputedStyle(placeContainer).columnGap || getComputedStyle(placeContainer).gap || '20', 10);
    return card.getBoundingClientRect().width + gap;
  };

  prevButton.addEventListener('click', () => {
    placeContainer.scrollBy({ left: -getScrollAmount(), behavior: 'smooth' });
  });

  nextButton.addEventListener('click', () => {
    placeContainer.scrollBy({ left: getScrollAmount(), behavior: 'smooth' });
  });
}

let currentCartData = null;

function bindAddToCartCards() {
  document.querySelectorAll('.product_card').forEach(card => {
    card.addEventListener('click', function () {
      openQtyModal({
        product_id: card.dataset.productId,
        product_name: card.dataset.productName,
        price: card.dataset.price
      });
    });
  });
}

function openQtyModal(data) {
  currentCartData = data;
  document.getElementById('qtyModalProductName').textContent = data.product_name;
  document.getElementById('qtyModalPrice').textContent = '₱' + parseFloat(data.price).toFixed(2);
  document.getElementById('qtyModalInput').value = 1;
  document.getElementById('qtyModalOverlay').classList.add('active');
}

function closeQtyModal() {
  currentCartData = null;
  document.getElementById('qtyModalOverlay').classList.remove('active');
}

const qtyMinusBtn = document.getElementById('qtyMinusBtn');
if (qtyMinusBtn) {
  qtyMinusBtn.addEventListener('click', () => {
    const input = document.getElementById('qtyModalInput');
    if (parseInt(input.value) > 1) input.value = parseInt(input.value) - 1;
  });
}

const qtyPlusBtn = document.getElementById('qtyPlusBtn');
if (qtyPlusBtn) {
  qtyPlusBtn.addEventListener('click', () => {
    const input = document.getElementById('qtyModalInput');
    input.value = parseInt(input.value) + 1;
  });
}

const qtyCancelBtn = document.getElementById('qtyCancelBtn');
if (qtyCancelBtn) qtyCancelBtn.addEventListener('click', closeQtyModal);

const qtyCloseBtn = document.getElementById('qtyCloseBtn');
if (qtyCloseBtn) qtyCloseBtn.addEventListener('click', closeQtyModal);

const qtyModalOverlay = document.getElementById('qtyModalOverlay');
if (qtyModalOverlay) {
  qtyModalOverlay.addEventListener('click', function (e) {
    if (e.target === this) closeQtyModal();
  });
}

const qtyConfirmBtn = document.getElementById('qtyConfirmBtn');
if (qtyConfirmBtn) {
  qtyConfirmBtn.addEventListener('click', () => {
    if (!currentCartData) return;

    const quantity = parseInt(document.getElementById('qtyModalInput').value) || 1;
    const formData = new FormData();
    formData.set('product_id', currentCartData.product_id);
    formData.set('product_name', currentCartData.product_name);
    formData.set('price', currentCartData.price);
    formData.set('quantity', quantity);

    fetch('add_to_cart.php', { method: 'POST', body: formData })
      .then(response => response.json())
      .then(data => {
        if (!data.success) {
          if (data.message === 'not_logged_in') {
            alert('Please sign in first before adding items to your cart.');
            window.location.href = 'login.php';
          }
          return;
        }
        updateCartUI(data.cart_count, data.cart);
        closeQtyModal();
      })
      .catch(err => console.error('Failed to add to cart:', err));
  });
}

function updateCartUI(count, items) {
  const badge = document.querySelector('.cart_count_badge');
  const iconBtn = document.querySelector('.nav_right_iconbtn');

  if (count > 0) {
    if (badge) {
      badge.textContent = count;
    } else if (iconBtn) {
      const newBadge = document.createElement('span');
      newBadge.className = 'cart_count_badge';
      newBadge.textContent = count;
      iconBtn.appendChild(newBadge);
    }
  }

  const dropdownInner = document.querySelector('.nav_right_cartdropdown_inner');
  if (!dropdownInner) return;

  let html = '<h4>Recently Added Products</h4>';

  if (items.length > 0) {
    items.forEach(item => {
      html += `<a href="cart.php" class="cart_preview_item"><span>${item.name} × ${item.quantity}</span><p>₱${parseFloat(item.price).toFixed(2)}</p></a>`;
    });
    html += `<div class="cart_preview_footer"><span>${count} item${count > 1 ? 's' : ''} in cart</span><a href="cart.php">View My Cart</a></div>`;
  } else {
    html += '<p>No items in your cart yet.</p>';
  }

  dropdownInner.innerHTML = html;
}

bindAddToCartCards();
</script>

</body>
</html>
