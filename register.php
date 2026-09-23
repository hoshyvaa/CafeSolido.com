<?php
session_start();
include "db.php";

$message = "";

if (isset($_POST['register'])) {
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $role = "Customer";

    $check = $conn->prepare("SELECT id FROM accounts WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        $message = "Email already exists.";
    } else {
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
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register - Cafe Solido</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div style="width:400px;margin:80px auto;text-align:center;">

    <h1>Register Account</h1>

    <?php if ($message != ""): ?>
        <p><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <form method="POST">

        <input type="text" name="fullname" placeholder="Full Name" required>
        <br><br>

        <input type="email" name="email" placeholder="Email" required>
        <br><br>

        <input type="password" name="password" placeholder="Password" required>
        <br><br>

        <button type="submit" name="register">
            Register
        </button>

    </form>

    <p>
        Already have an account?
        <a href="login.php">Sign In</a>
    </p>

    <p>
        <a href="index.php">Back to Home</a>
    </p>

</div>

</body>
</html>
