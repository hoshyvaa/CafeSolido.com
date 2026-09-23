<?php
include "db.php";

$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;

if ($category_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE category_id = ? ORDER BY product_id ASC");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $products = $stmt->get_result();
} else {
    $products = $conn->query("SELECT * FROM products ORDER BY product_id ASC");
}
if (!$products) die("Product error: " . $conn->error);

if ($products->num_rows > 0):
    while ($product = $products->fetch_assoc()):
?>
    <div class="product_card"
         data-product-id="<?php echo (int)$product['product_id']; ?>"
         data-product-name="<?php echo htmlspecialchars($product['product_name']); ?>"
         data-price="<?php echo $product['price']; ?>">
        <?php if (!empty($product['product_image'])): ?>
            <img src="<?php echo htmlspecialchars($product['product_image']); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>">
        <?php endif; ?>
        <h3><?php echo htmlspecialchars($product['product_name']); ?></h3>
        <p>₱<?php echo number_format($product['price'], 2); ?></p>
    </div>
<?php
    endwhile;
else:
?>
    <p>No products available in this category.</p>
<?php endif; ?>
