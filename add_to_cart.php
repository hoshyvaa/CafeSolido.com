<?php
session_start();
include "db.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'not_logged_in'
    ]);
    exit;
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $product_name = isset($_POST['product_name']) ? $_POST['product_name'] : '';
    $price = isset($_POST['price']) ? (float)$_POST['price'] : 0;
    $quantity = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;

    $stmt = $conn->prepare("SELECT stock FROM products WHERE product_id = ? LIMIT 1");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($product_id > 0 && $product_name != '' && $price >= 0 && $result->num_rows === 1) {

        $stock = (int)$result->fetch_assoc()['stock'];

        if ($stock > 0) {

            $found = false;

            foreach ($_SESSION['cart'] as &$item) {
                if ($item['product_id'] == $product_id) {
                    $new_quantity = $item['quantity'] + $quantity;
                    $item['quantity'] = min($new_quantity, $stock);
                    $found = true;
                    break;
                }
            }
            unset($item);

            if (!$found) {
                $_SESSION['cart'][] = [
                    'product_id' => $product_id,
                    'name' => $product_name,
                    'price' => $price,
                    'quantity' => min($quantity, $stock)
                ];
            }
        }
    }
}

echo json_encode([
    'success' => true,
    'cart_count' => count($_SESSION['cart']),
    'cart' => array_slice(array_reverse($_SESSION['cart']), 0, 5)
]);
exit;
?>
