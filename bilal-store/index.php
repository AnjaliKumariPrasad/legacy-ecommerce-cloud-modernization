<?php
require_once __DIR__ . '/includes/shop_bootstrap.php';

$pageTitle = 'Home';
$siteName  = setting('site_name', 'Bilal Store');

// Featured + latest products + categories.
try {
    $featured = db()->query(
        "SELECT p.*, c.name AS category_name, c.slug AS category_slug
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.status = 'active' AND p.featured = 1
         ORDER BY p.created_at DESC LIMIT 8"
    )->fetchAll();

    $latest = db()->query(
        "SELECT p.*, c.name AS category_name, c.slug AS category_slug
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.status = 'active'
         ORDER BY p.created_at DESC LIMIT 8"
    )->fetchAll();

    $categories = db()->query(
        "SELECT c.id, c.name, c.slug,
                (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.status = 'active') AS product_count
         FROM categories c
         WHERE c.status = 'active'
         ORDER BY product_count DESC, c.name ASC
         LIMIT 6"
    )->fetchAll();

    $totalProducts   = (int) db()->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn();
    $totalCategories = (int) db()->query("SELECT COUNT(*) FROM categories WHERE status='active'")->fetchColumn();

    // Pick a hero spotlight product (first featured, fallback to first latest).
    $spotlight = $featured[0] ?? ($latest[0] ?? null);
} catch (Throwable $e) {
    $featured = $latest = $categories = [];
    $totalProducts = $totalCategories = 0;
    $spotlight = null;
}

include __DIR__ . '/includes/shop_header.php';
?>

<!-- =================== HERO =================== -->
<section class="hero">
  <div class="hero-decor decor-1"></div>
  <div class="hero-decor decor-2"></div>
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-6">
        <span class="hero-eyebrow">
          <i class="bi bi-lightning-charge-fill"></i> New season · Just dropped
        </span>
        <h1>
          Discover everyday essentials<br>
          you'll actually <span class="hero-accent">love</span>.
        </h1>
        <p class="lead">
          Welcome to <strong><?= e($siteName) ?></strong> — hand-picked products,
          fair prices, and lightning-fast delivery to your door.
        </p>
        <div class="hero-actions">
          <a href="<?= e(shop_url('shop.php')) ?>" class="btn btn-light btn-hero">
            <i class="bi bi-bag"></i> Shop now
          </a>
          <a href="<?= e(shop_url('shop.php?featured=1')) ?>" class="btn btn-outline-light btn-hero">
            <i class="bi bi-stars"></i> See featured
          </a>
        </div>
      </div>

      <div class="col-lg-6 d-none d-lg-block">
        <?php if ($spotlight): ?>
          <a href="<?= e(shop_url('product.php?slug=' . urlencode($spotlight['slug']))) ?>"
             class="hero-card">
            <div class="hero-card-img">
              <img src="<?= e(product_image_url($spotlight['image'])) ?>"
                   alt="<?= e($spotlight['name']) ?>">
            </div>
            <div class="hero-card-body">
              <span class="hero-card-tag">
                <i class="bi bi-star-fill"></i> Featured pick
              </span>
              <h4><?= e($spotlight['name']) ?></h4>
              <div class="hero-card-price">
                <?= e(price(product_effective_price($spotlight))) ?>
                <span>Shop the look <i class="bi bi-arrow-right"></i></span>
              </div>
            </div>
          </a>
        <?php else: ?>
          <div class="hero-collage">
            <div class="hero-blob blob-a"></div>
            <div class="hero-blob blob-b"></div>
            <div class="hero-blob blob-c"></div>
            <i class="bi bi-bag-heart-fill"></i>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<!-- =================== FEATURE STRIP =================== -->
<section class="feature-strip">
  <div class="container">
    <div class="row g-4">
      <div class="col-6 col-md-3">
        <div class="feature-item">
          <div class="feature-icon"><i class="bi bi-truck"></i></div>
          <h6>Fast delivery</h6>
          <small>Ships within 24 hours</small>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="feature-item">
          <div class="feature-icon"><i class="bi bi-shield-check"></i></div>
          <h6>Secure checkout</h6>
          <small>Your data is protected</small>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="feature-item">
          <div class="feature-icon"><i class="bi bi-arrow-repeat"></i></div>
          <h6>Easy returns</h6>
          <small>30-day return policy</small>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="feature-item">
          <div class="feature-icon"><i class="bi bi-headset"></i></div>
          <h6>24/7 support</h6>
          <small>We're here to help</small>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- =================== CATEGORIES =================== -->
<?php if ($categories): ?>
<section class="section">
  <div class="container">
    <div class="section-header text-center">
      <span class="section-eyebrow">Categories</span>
      <h2>Shop by category</h2>
      <p>Find exactly what you're looking for in our curated collections.</p>
    </div>

    <div class="row g-3">
      <?php
      $catIcons = ['bi-laptop', 'bi-bag', 'bi-house-door', 'bi-headphones',
                   'bi-camera', 'bi-watch', 'bi-controller', 'bi-cup-hot'];
      foreach ($categories as $i => $c):
          $alt = ($i % 5) + 1;
          $icon = $catIcons[$i % count($catIcons)];
      ?>
        <div class="col-6 col-md-4 col-lg-2">
          <a href="<?= e(shop_url('category.php?slug=' . urlencode($c['slug']))) ?>"
             class="category-card alt-<?= $alt ?>">
            <div class="cat-icon"><i class="bi <?= e($icon) ?>"></i></div>
            <h5><?= e($c['name']) ?></h5>
            <small><?= (int)$c['product_count'] ?> product<?= (int)$c['product_count'] === 1 ? '' : 's' ?></small>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- =================== FEATURED PRODUCTS =================== -->
<?php if ($featured): ?>
<section class="section section-soft">
  <div class="container">
    <div class="section-header d-flex justify-content-between align-items-end flex-wrap gap-3">
      <div>
        <span class="section-eyebrow">Top picks</span>
        <h2><i class="bi bi-star-fill text-warning"></i> Featured products</h2>
        <p>Our hand-picked selection of must-have items.</p>
      </div>
      <a href="<?= e(shop_url('shop.php?featured=1')) ?>" class="btn btn-outline-primary">
        View all <i class="bi bi-arrow-right"></i>
      </a>
    </div>

    <div class="row g-3">
      <?php foreach ($featured as $p): ?>
        <div class="col-6 col-md-4 col-lg-3">
          <?php include __DIR__ . '/_product_card.php'; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- =================== ABOUT US =================== -->
<section class="section about-section" id="about">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-6">
        <div class="about-image">
          <div class="about-image-grid">
            <div class="about-tile tile-1"><i class="bi bi-bag-heart-fill"></i></div>
            <div class="about-tile tile-2"><i class="bi bi-gem"></i></div>
            <div class="about-tile tile-3"><i class="bi bi-truck"></i></div>
            <div class="about-tile tile-4"><i class="bi bi-stars"></i></div>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <span class="section-eyebrow">About <?= e($siteName) ?></span>
        <h2>Quality you can feel.<br>Service you can trust.</h2>
        <p class="text-muted">
          At <strong><?= e($siteName) ?></strong>, we believe shopping should be
          simple, honest, and a little bit delightful. Every product on our shelves
          is picked by hand for its quality, value, and the way it fits into
          everyday life.
        </p>
        <p class="text-muted">
          From our family to yours — thanks for being part of the journey.
        </p>

        <div class="about-points">
          <div class="about-point">
            <div class="ap-icon"><i class="bi bi-check-lg"></i></div>
            <div>
              <strong>Hand-picked products</strong>
              <small>Every item personally vetted for quality.</small>
            </div>
          </div>
          <div class="about-point">
            <div class="ap-icon"><i class="bi bi-check-lg"></i></div>
            <div>
              <strong>Fair, transparent pricing</strong>
              <small>No hidden fees, ever — what you see is what you pay.</small>
            </div>
          </div>
          <div class="about-point">
            <div class="ap-icon"><i class="bi bi-check-lg"></i></div>
            <div>
              <strong>Real human support</strong>
              <small>A real person answers every question — 24/7.</small>
            </div>
          </div>
        </div>

        <a href="<?= e(shop_url('shop.php')) ?>" class="btn btn-primary mt-4">
          <i class="bi bi-bag"></i> Explore our shop
        </a>
      </div>
    </div>
  </div>
</section>

<!-- =================== NEW ARRIVALS =================== -->
<?php if ($latest): ?>
<section class="section section-soft">
  <div class="container">
    <div class="section-header d-flex justify-content-between align-items-end flex-wrap gap-3">
      <div>
        <span class="section-eyebrow">Just in</span>
        <h2>New arrivals</h2>
        <p>The latest additions to our catalog — fresh and ready to ship.</p>
      </div>
      <a href="<?= e(shop_url('shop.php?sort=newest')) ?>" class="btn btn-outline-primary">
        View all <i class="bi bi-arrow-right"></i>
      </a>
    </div>

    <div class="row g-3">
      <?php foreach ($latest as $p): ?>
        <div class="col-6 col-md-4 col-lg-3">
          <?php include __DIR__ . '/_product_card.php'; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if (!$featured && !$latest): ?>
  <section class="section">
    <div class="container">
      <div class="empty-state">
        <i class="bi bi-bag"></i>
        <h3>No products yet</h3>
        <p>Check back soon — new products are being added every week.</p>
      </div>
    </div>
  </section>
<?php endif; ?>

<!-- =================== BIG CTA BANNER =================== -->
<section class="cta-banner">
  <div class="container">
    <div class="cta-banner-inner">
      <div class="row align-items-center g-4">
        <div class="col-lg-8">
          <span class="cta-eyebrow">
            <i class="bi bi-tag-fill"></i> Limited time
          </span>
          <h2>Ready to find your next favorite thing?</h2>
          <p>Browse the entire <?= e($siteName) ?> catalog and discover something new today.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
          <a href="<?= e(shop_url('shop.php')) ?>" class="btn btn-light btn-lg">
            <i class="bi bi-bag"></i> Shop everything
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- =================== NEWSLETTER =================== -->
<section class="section newsletter-section">
  <div class="container">
    <div class="newsletter-card">
      <div class="newsletter-icon"><i class="bi bi-envelope-paper"></i></div>
      <h3>Stay in the loop</h3>
      <p>Get exclusive deals and product news — straight to your inbox.</p>
      <form class="newsletter-form"
            onsubmit="event.preventDefault(); this.reset(); this.querySelector('button').innerHTML='<i class=\'bi bi-check2\'></i> Subscribed!';">
        <input type="email" class="form-control form-control-lg"
               placeholder="you@example.com" required>
        <button class="btn btn-primary btn-lg">
          <i class="bi bi-send"></i> Subscribe
        </button>
      </form>
      <small class="text-muted d-block mt-3">
        <i class="bi bi-shield-lock"></i> We respect your privacy. Unsubscribe anytime.
      </small>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/shop_footer.php'; ?>
