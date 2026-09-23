<?php
session_start();
include "db.php";

$message = "";
$showRegister = false;

if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM accounts WHERE email = ? AND password = ?");
    $stmt->bind_param("ss", $email, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];

        if ($user['role'] === 'Admin') {
            header("Location: index.php?view=admin");
        } elseif ($user['role'] === 'Cashier') {
            header("Location: index.php?view=cashier");
        } elseif ($user['role'] === 'Manager') {
            header("Location: index.php?view=manager");
        } else {
            header("Location: index.php");
        }
        exit;
    } else {
        $message = "Invalid email or password.";
    }
}

if (isset($_POST['register'])) {
    $fullname = $_POST['fullname'];
    $email = $_POST['reg_email'];
    $password = $_POST['reg_password'];

    $check = $conn->prepare("SELECT id FROM accounts WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        $message = "Email already exists.";
        $showRegister = true;
    } else {
        $role = "Customer";

        $stmt = $conn->prepare("INSERT INTO accounts (name, role, email, password, created_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP())");
        $stmt->bind_param("ssss", $fullname, $role, $email, $password);

        if ($stmt->execute()) {
            $_SESSION['user_id'] = $stmt->insert_id;
            $_SESSION['name'] = $fullname;
            $_SESSION['email'] = $email;
            $_SESSION['role'] = $role;

            header("Location: index.php");
            exit;
        } else {
            $message = "Registration failed.";
            $showRegister = true;
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
<title>Sign In - Cafe Solido</title>
<link rel="stylesheet" href="style.css">

<style>
body {
    margin: 0;
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    font-family: 'Poppins', sans-serif;
    padding-top: 90px;
}

.login-container {
    width: 400px;
    background: white;
    padding: 35px;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    text-align: center;
    box-sizing: border-box;
}

.login-container h1 {
    color: #00754a;
    margin-bottom: 10px;
    font-size: 30px;
}

.login-subtitle {
    color: #777;
    font-size: 14px;
    margin-top: 0;
    margin-bottom: 25px;
}

.message {
    background: #ffebee;
    color: #c62828;
    padding: 10px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 14px;
}

.login-container input {
    width: 100%;
    padding: 13px 15px;
    margin-bottom: 15px;
    border: 1px solid #ccc;
    border-radius: 10px;
    outline: none;
    font-size: 14px;
    box-sizing: border-box;
    transition: 0.3s;
}

.login-container input:focus {
    border-color: #388E3C;
    box-shadow: 0 0 5px rgba(56,142,60,0.3);
}

.login-btn {
    width: 100%;
    border: none;
    padding: 13px;
    border-radius: 25px;
    background: #388E3C;
    color: white;
    font-size: 15px;
    font-weight: bold;
    cursor: pointer;
    transition: 0.3s;
}

.login-btn:hover {
    background: #00754a;
    transform: translateY(-2px);
}

.switch-text {
    margin-top: 20px;
    color: #666;
    font-size: 14px;
}

.switch-text a {
    color: #00754a;
    font-weight: bold;
    text-decoration: none;
    cursor: pointer;
}

.switch-text a:hover {
    text-decoration: underline;
}

.back-home {
    display: inline-block;
    margin-top: 10px;
    color: #777;
    text-decoration: none;
    font-size: 14px;
}

.back-home:hover {
    color: #00754a;
}

.logo_signin img {
    height: 50px;
}
</style>
</head>

<body>
<header>
    <div class="nav-left">
        <a href="index.php"><img src="logo/logoNAME.png" alt="Cafe Solido Logo" class="logo"></a>
    </div>

    <div class="nav-center">
    <ul class="nav1">
      <li><a class="nav-link active" href="index.php#home">Home</a></li>
      <li><a class="nav-link" href="index.php#menu">Menu</a></li>
      <li><a class="nav-link" href="index.php#contacts">Contacts</a></li>
    </ul>
  </div>

    <div class="nav_right">
        <a href="login.php"><button class="nav_right_signbtn">SIGN IN</button></a>

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

<div class="login-container">

    <?php if ($message != ""): ?>
        <div class="message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div id="loginForm" style="<?php echo $showRegister ? 'display:none;' : 'display:block;'; ?>">
        <div class="logo_signin"><img src="logo/cup_logo.png" alt="Cup Logo"></div>
        <h1>Sign In</h1>
        <p class="login-subtitle">Welcome back to Cafe Solido!</p>

        <form method="POST">
            <input type="email" name="email" placeholder="Enter your email" required>
            <input type="password" name="password" placeholder="Enter your password" required>
            <button type="submit" name="login" class="login-btn">Sign In</button>
        </form>

        <p class="switch-text">
            Don't have an account?
            <a href="#" onclick="showRegister(); return false;">Register Account</a>
        </p>

        <a href="index.php" class="back-home">← Back to Home</a>
    </div>

    <div id="registerForm" style="<?php echo $showRegister ? 'display:block;' : 'display:none;'; ?>">
        <div class="logo_signin"><img src="logo/cup_logo.png" alt="Cup Logo"></div>
        <h1>Register Account</h1>
        <p class="login-subtitle">Create your Cafe Solido account</p>

        <form method="POST">
            <input type="text" name="fullname" placeholder="Enter your full name" required>
            <input type="email" name="reg_email" placeholder="Enter your email" required>
            <input type="password" name="reg_password" placeholder="Create a password" required>
            <button type="submit" name="register" class="login-btn">Create Account</button>
        </form>

        <p class="switch-text">
            Already have an account?
            <a href="#" onclick="showLogin(); return false;">Sign In</a>
        </p>

        <a href="index.php" class="back-home">← Back to Home</a>
    </div>

</div>

<script>
  document.querySelectorAll('.nav1 .nav-link').forEach(link => {
  link.addEventListener('click', () => {
    document.querySelectorAll('.nav1 .nav-link').forEach(item => item.classList.remove('active'));
    link.classList.add('active');
  });
});

function showRegister() {
    document.getElementById("loginForm").style.display = "none";
    document.getElementById("registerForm").style.display = "block";
}

function showLogin() {
    document.getElementById("registerForm").style.display = "none";
    document.getElementById("loginForm").style.display = "block";
}
</script>

</body>
</html>
