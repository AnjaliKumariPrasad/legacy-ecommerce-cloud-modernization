<?php
require_once __DIR__ . '/includes/shop_bootstrap.php';

$slug = trim((string)($_GET['slug'] ?? ''));

if ($slug === '') {
    header('Location: ' . shop_url('shop.php'));
    exit;
}

try {
    $stmt = db()->prepare(
        "SELECT p.*, c.name AS category_name, c.slug AS category_slug
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.slug = :slug AND p.status = 'active'
         LIMIT 1"
    );
    $stmt->execute([':slug' => $slug]);
    $product = $stmt->fetch();
} catch (Throwable $e) {
    $product = false;
}

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Product not found';
    include __DIR__ . '/includes/shop_header.php';
    ?>
    <section class="section">
      <div class="container">
        <div class="empty-state">
          <i class="bi bi-question-circle"></i>
          <h3>Product not found</h3>
          <p>The product you're looking for doesn't exist or is no longer available.</p>
          <a href="<?= e(shop_url('shop.php')) ?>" class="btn btn-primary">
            Browse all products
          </a>
        </div>
      </div>
    </section>
    <?php
    include __DIR__ . '/includes/shop_footer.php';
    exit;
}

$pageTitle = $product['name'];

$onSale     = product_is_on_sale($product);
$discount   = $onSale ? product_discount_percent($product) : 0;
$inStock    = (int)$product['stock'] > 0;

// Related products from same category
$related = [];
if (!empty($product['category_id'])) {
    try {
        $stmt = db()->prepare(
            "SELECT p.*, c.name AS category_name, c.slug AS category_slug
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.category_id = :cid
               AND p.id <> :pid
               AND p.status = 'active'
             ORDER BY p.created_at DESC
             LIMIT 4"
        );
        $stmt->execute([':cid' => $product['category_id'], ':pid' => $product['id']]);
        $related = $stmt->fetchAll();
    } catch (Throwable $e) {}
}

include __DIR__ . '/includes/shop_header.php';
?>

<section class="product-detail">
  <div class="container">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= e(shop_url()) ?>">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= e(shop_url('shop.php')) ?>">Shop</a></li>
        <?php if (!empty($product['category_slug'])): ?>
          <li class="breadcrumb-item">
            <a href="<?= e(shop_url('category.php?slug=' . urlencode($product['category_slug']))) ?>">
              <?= e($product['category_name']) ?>
            </a>
          </li>
        <?php endif; ?>
        <li class="breadcrumb-item active"><?= e($product['name']) ?></li>
      </ol>
    </nav>

    <div class="row g-4">
      <div class="col-md-6">
        <div class="product-image">
          <img src="<?= e(product_image_url($product['image'])) ?>"
               alt="<?= e($product['name']) ?>">
        </div>
      </div>

      <div class="col-md-6">
        <?php if (!empty($product['category_name'])): ?>
          <div class="product-cat text-uppercase small text-muted mb-2">
            <a href="<?= e(shop_url('category.php?slug=' . urlencode($product['category_slug']))) ?>"
               class="text-muted">
              <?= e($product['category_name']) ?>
            </a>
          </div>
        <?php endif; ?>

        <h1><?= e($product['name']) ?></h1>

        <?php if (!empty($product['short_description'])): ?>
          <p class="lead text-muted"><?= e($product['short_description']) ?></p>
        <?php endif; ?>

        <div class="price-block my-3">
          <?php if ($onSale): ?>
            <span><?= e(price((float)$product['sale_price'])) ?></span>
            <span class="old"><?= e(price((float)$product['price'])) ?></span>
            <span class="badge bg-danger fs-6 align-self-center">-<?= $discount ?>%</span>
          <?php else: ?>
            <span><?= e(price((float)$product['price'])) ?></span>
          <?php endif; ?>
        </div>

        <div class="stock-row">
          <?php if ($inStock): ?>
            <span class="in-stock"><i class="bi bi-check-circle-fill"></i> In stock</span>
            — <?= (int)$product['stock'] ?> available
          <?php else: ?>
            <span class="out-of-stock"><i class="bi bi-x-circle-fill"></i> Out of stock</span>
          <?php endif; ?>
          <?php if (!empty($product['sku'])): ?>
            · <span class="text-muted">SKU: <code><?= e($product['sku']) ?></code></span>
          <?php endif; ?>
        </div>

        <form method="post" action="<?= e(shop_url('cart.php')) ?>" class="my-4">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">

          <div class="row g-2 align-items-stretch">
            <div class="col-12 col-md-3">
              <input type="number" name="qty" value="1" min="1"
                     max="<?= (int)$product['stock'] ?>"
                     class="form-control form-control-lg"
                     <?= $inStock ? '' : 'disabled' ?>>
            </div>
            <div class="col-12 col-md">
              <button class="btn btn-outline-primary btn-lg w-100"
                      name="return_to" value="cart.php"
                      <?= $inStock ? '' : 'disabled' ?>>
                <i class="bi bi-bag-plus"></i>
                <?= $inStock ? 'Add to cart' : 'Sold out' ?>
              </button>
            </div>
            <?php if ($inStock): ?>
              <div class="col-12 col-md">
                <button class="btn btn-primary btn-lg w-100"
                        name="return_to" value="checkout.php">
                  <i class="bi bi-lightning-charge-fill"></i> Buy now
                </button>
              </div>
            <?php endif; ?>
          </div>
        </form>

        <?php if (!empty($product['description'])): ?>
          <hr>
          <h5>Description</h5>
          <div class="text-muted" style="white-space: pre-line"><?= e($product['description']) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($related): ?>
      <hr class="my-5">
      <div class="section-header">
        <h2>You might also like</h2>
        <p>Other products in <?= e($product['category_name']) ?></p>
      </div>

      <div class="row g-3">
        <?php foreach ($related as $p): ?>
          <div class="col-6 col-md-3">
            <?php include __DIR__ . '/_product_card.php'; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>
</section>

<?php include __DIR__ . '/includes/shop_footer.php'; ?>
