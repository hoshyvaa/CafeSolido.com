<?php

session_start();
include "db.php";
include "functions.php";

if (!isset($_SESSION['user_id'])) {
    echo "<script>
        alert('Please sign in first to view your cart.');
        window.location.href = 'login.php';
    </script>";
    exit;
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if (isset($_GET['remove'])) {
    $remove_id = (int)$_GET['remove'];

    foreach ($_SESSION['cart'] as $key => $item) {
        if ($item['product_id'] == $remove_id) {
            unset($_SESSION['cart'][$key]);
        }
    }

    $_SESSION['cart'] = array_values($_SESSION['cart']);
    header("Location: cart.php");
    exit;
}

if (isset($_GET['clear'])) {
    $_SESSION['cart'] = [];
    header("Location: cart.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {
    foreach ($_SESSION['cart'] as $key => $item) {
        $product_id = $item['product_id'];
        if (isset($_POST['quantity'][$product_id])) {
            $new_quantity = (int)$_POST['quantity'][$product_id];
            if ($new_quantity < 1) {
                $new_quantity = 1;
            }
            $_SESSION['cart'][$key]['quantity'] = $new_quantity;
        }
    }
    header("Location: cart.php");
    exit;
}

$checkout_error = isset($_GET['error']);

$cart_items = $_SESSION['cart'];
$cart_count = count($cart_items);
$cart_preview = array_slice(array_reverse($cart_items), 0, 5);
$user_name = $_SESSION['name'] ?? 'Account';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cart - Cafe Solido</title>
    <link rel="stylesheet" href="style.css">

    <style>

        .cart-title {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 32px;
            margin-bottom: 25px;
        }

        .cart-title img {
            width: 32px;
            height: 32px;
            object-fit: contain;
        }
        .cart-container {
            width: 90%;
            max-width: 1000px;
            margin: 120px auto 50px;
        }

        .cart-table-wrap {
            background: #ffffff;
            padding: 25px 30px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.10);
        }

        .cart-table {
            width: 100%;
            border-collapse: collapse;
        }

        .cart-table th {
            text-align: left;
            color: #00754a;
            font-size: 14px;
            font-weight: 600;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .cart-table td {
            padding: 18px 0;
            border-bottom: 1px solid #eee;
            color: #1f2937;
            vertical-align: middle;
        }

        .cart-table tr:last-child td {
            border-bottom: none;
        }

        .qty-input {
            width: 60px;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            text-align: center;
        }

        .remove-btn {
            background: #fde2e2;
            border: none;
            width: 36px;
            height: 36px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border-radius: 8px;
            transition: 0.3s;
        }

        .remove-btn img {
            width: 16px;
            height: 16px;
            object-fit: contain;
        }

        .remove-btn:hover {
            background: #fbcaca;
        }

        .update-btn {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 20px;
            background: #00754a;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
        }

        .cart-summary {
            margin-top: 25px;
            padding: 25px 30px;
            background: #ffffff;
            border-radius: 12px;
        }

        .total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 24px;
            font-weight: bold;
            color: #00754a;
        }

        .payment-label {
            margin-top: 20px;
            margin-bottom: 8px;
            font-size: 14px;
            color: #a5713d;
        }

        .payment-select {
            width: 100%;
            padding: 12px 14px;
            border-radius: 8px;
            border: 1px solid #ddd;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            background: white;
        }

        .checkout-btn {
            display: block;
            width: 100%;
            margin-top: 20px;
            padding: 16px;
            text-decoration: none;
            border-radius: 10px;
            color: white;
            border: none;
            font-size: 15px;
            font-weight: 600;
            text-transform: uppercase;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            background: #00754a;
        }

        .shop-btn {
            display: inline-block;
            padding: 12px 18px;
            text-decoration: none;
            border-radius: 8px;
            color: white;
            border: none;
            font-size: 14px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            background: #333;
        }

        .checkout-error {
            background: #ffebee;
            color: #c62828;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .empty-cart {
            text-align: center;
            padding: 50px;
        }
    </style>
</head>

<body>

<header>
    <div class="nav-left">
        <a href="index.php"><img src="logoNAME.png" alt="Cafe Solido Logo" class="logo"></a>
    </div>

    <div class="nav-center">
        <ul class="nav1">
            <li><a class="nav-link" href="index.php#home">Home</a></li>
            <li><a class="nav-link" href="index.php#menu">Menu</a></li>
            <li><a class="nav-link" href="index.php#contacts">Contacts</a></li>
        </ul>
    </div>

    <div class="nav_right">
        <?php if (isset($_SESSION['user_id'])): ?>
            <span class="nav_right_username">Hi, <?php echo htmlspecialchars($user_name); ?></span>
        <?php else: ?>
            <a href="login.php"><button class="nav_right_signbtn">SIGN IN</button></a>
        <?php endif; ?>

        <?php if (isset($_SESSION['user_id'])): ?>
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

        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="logout.php" title="Log Out"><button class="nav_right_iconbtn"><img src="icons/logout.png" alt="Log Out Icon"></button></a>
        <?php endif; ?>
    </div>
</header>

<div class="cart-container">

    <h1 class="cart-title"><img src="icons/cart.png" alt="Cart Icon"> My Cart</h1>

    <?php if ($checkout_error): ?>
        <div class="checkout-error">Checkout failed. One or more items may be out of stock — please review your cart and try again.</div>
    <?php endif; ?>

    <?php if (empty($_SESSION['cart'])): ?>

        <div class="empty-cart">
            <h2>Your cart is empty</h2>
            <p>Add some products from our menu.</p>
            <br>
            <a href="index.php" class="shop-btn">← Continue Shopping</a>
        </div>

    <?php else: ?>

        <?php $total = 0; ?>

        <form method="POST" action="cart.php">
            <input type="hidden" name="action" value="update">

            <div class="cart-table-wrap">
                <table class="cart-table">
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Subtotal</th>
                        <th>Action</th>
                    </tr>

                    <?php foreach ($_SESSION['cart'] as $item): ?>

                        <?php
                        $quantity = isset($item['quantity']) ? $item['quantity'] : 1;
                        $subtotal = $item['price'] * $quantity;
                        $total += $subtotal;
                        ?>

                        <tr>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td>₱<?php echo number_format($item['price'], 2); ?></td>
                            <td>
                                <input type="number" min="1" class="qty-input" name="quantity[<?php echo (int)$item['product_id']; ?>]" value="<?php echo (int)$quantity; ?>">
                            </td>
                            <td>₱<?php echo number_format($subtotal, 2); ?></td>
                            <td>
                                <a href="cart.php?remove=<?php echo (int)$item['product_id']; ?>" title="Remove">
                                    <button type="button" class="remove-btn"><img src="icons/trash.png" alt="Remove Icon"></button>
                                </a>
                            </td>
                        </tr>

                    <?php endforeach; ?>
                </table>

                <button type="submit" class="update-btn">Update Order</button>
            </div>
        </form>

        <div class="cart-summary">
            <div class="total">
                <span>Total</span>
                <span>₱<?php echo number_format($total, 2); ?></span>
            </div>

            <form method="POST" action="index.php">
                <input type="hidden" name="action" value="checkout">
                <p class="payment-label">Payment Method</p>
                <select name="payment_method" class="payment-select">
                    <option value="Cash">Cash</option>
                    <option value="GCash">GCash</option>
                </select>
                <button type="submit" class="checkout-btn">Place Order</button>
            </form>
        </div>

    <?php endif; ?>

</div>

</body>
</html>