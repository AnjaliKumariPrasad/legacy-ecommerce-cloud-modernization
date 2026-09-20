</main><!-- /.site-main -->

<footer class="site-footer">
  <div class="container">
    <div class="row g-4">
      <div class="col-md-4">
        <h5 class="footer-brand">
          <i class="bi bi-bag-fill"></i>
          <?= e(setting('site_name', APP_NAME)) ?>
        </h5>
        <p class="mb-0">
          <?= e(setting('site_about', 'Quality products, delivered to your door.')) ?>
        </p>
      </div>

      <div class="col-md-2">
        <h6>Shop</h6>
        <ul class="list-unstyled">
          <li><a href="<?= e(shop_url()) ?>">Home</a></li>
          <li><a href="<?= e(shop_url('shop.php')) ?>">All products</a></li>
          <li><a href="<?= e(shop_url('shop.php?featured=1')) ?>">Featured</a></li>
          <li><a href="<?= e(shop_url('shop.php?sort=newest')) ?>">New arrivals</a></li>
          <li><a href="<?= e(shop_url('cart.php')) ?>">Cart</a></li>
          <li><a href="<?= e(shop_url('track.php')) ?>">Track order</a></li>
        </ul>
      </div>

      <div class="col-md-3">
        <h6>Categories</h6>
        <ul class="list-unstyled">
          <?php foreach (array_slice(shop_active_categories(), 0, 6) as $c): ?>
            <li>
              <a href="<?= e(shop_url('category.php?slug=' . urlencode($c['slug']))) ?>">
                <?= e($c['name']) ?>
              </a>
            </li>
          <?php endforeach; ?>
          <?php if (!shop_active_categories()): ?>
            <li class="text-muted small">No categories yet.</li>
          <?php endif; ?>
        </ul>
      </div>

      <div class="col-md-3">
        <h6>Get in touch</h6>
        <ul class="list-unstyled">
          <li><i class="bi bi-envelope"></i> <?= e(setting('site_email', 'hello@bilalstore.com')) ?></li>
          <li><i class="bi bi-geo-alt"></i> Online store</li>
          <li><i class="bi bi-clock"></i> 24/7 customer support</li>
        </ul>
      </div>
    </div>
    <hr>
    <div class="text-center copyright small">
      &copy; <?= date('Y') ?> <?= e(setting('site_name', APP_NAME)) ?>. All rights reserved.
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
