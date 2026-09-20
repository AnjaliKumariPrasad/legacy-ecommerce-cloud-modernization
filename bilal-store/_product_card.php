<?php
/**
 * Reusable product card. Expects $p (product row) to be in scope.
 */
$onSale     = product_is_on_sale($p);
$discount   = $onSale ? product_discount_percent($p) : 0;
$outOfStock = (int)($p['stock'] ?? 0) <= 0;
?>
<div class="product-card">
  <a href="<?= e(shop_url('product.php?slug=' . urlencode($p['slug']))) ?>"
     class="product-img-wrap d-block">
    <img src="<?= e(product_image_url($p['image'] ?? null)) ?>"
         alt="<?= e($p['name']) ?>" loading="lazy">

    <?php if ($onSale): ?>
      <span class="badge-sale">-<?= $discount ?>%</span>
    <?php endif; ?>

    <?php if ((int)($p['featured'] ?? 0) === 1): ?>
      <span class="badge-feat"><i class="bi bi-star-fill"></i></span>
    <?php endif; ?>

    <?php if ($outOfStock): ?>
      <span class="badge-out">Sold out</span>
    <?php endif; ?>
  </a>

  <div class="product-body">
    <?php if (!empty($p['category_name'])): ?>
      <div class="product-cat"><?= e($p['category_name']) ?></div>
    <?php endif; ?>

    <h5 class="product-name">
      <a href="<?= e(shop_url('product.php?slug=' . urlencode($p['slug']))) ?>">
        <?= e($p['name']) ?>
      </a>
    </h5>

    <div class="product-price">
      <?php if ($onSale): ?>
        <?= e(price((float)$p['sale_price'])) ?>
        <span class="price-old"><?= e(price((float)$p['price'])) ?></span>
      <?php else: ?>
        <?= e(price((float)$p['price'])) ?>
      <?php endif; ?>
    </div>
  </div>
</div>
