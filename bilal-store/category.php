<?php
require_once __DIR__ . '/includes/shop_bootstrap.php';

$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '') {
    header('Location: ' . shop_url('shop.php'));
    exit;
}

try {
    $stmt = db()->prepare(
        "SELECT * FROM categories WHERE slug = :slug AND status = 'active' LIMIT 1"
    );
    $stmt->execute([':slug' => $slug]);
    $category = $stmt->fetch();
} catch (Throwable $e) {
    $category = false;
}

if (!$category) {
    http_response_code(404);
    $pageTitle = 'Category not found';
    include __DIR__ . '/includes/shop_header.php';
    ?>
    <section class="section">
      <div class="container">
        <div class="empty-state">
          <i class="bi bi-question-circle"></i>
          <h3>Category not found</h3>
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

$pageTitle = $category['name'];

$sort     = trim((string)($_GET['sort'] ?? 'newest'));
$perPage  = 12;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $perPage;

$orderSql = match ($sort) {
    'price_asc'  => 'COALESCE(sale_price, price) ASC',
    'price_desc' => 'COALESCE(sale_price, price) DESC',
    'name_asc'   => 'name ASC',
    'name_desc'  => 'name DESC',
    default      => 'created_at DESC',
};

try {
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM products
         WHERE category_id = :cid AND status = 'active'"
    );
    $stmt->execute([':cid' => $category['id']]);
    $total = (int) $stmt->fetchColumn();
    $pages = max(1, (int) ceil($total / $perPage));

    $stmt = db()->prepare(
        "SELECT * FROM products
         WHERE category_id = :cid AND status = 'active'
         ORDER BY $orderSql
         LIMIT :lim OFFSET :off"
    );
    $stmt->bindValue(':cid', $category['id'], PDO::PARAM_INT);
    $stmt->bindValue(':lim', $perPage,        PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset,         PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll();
    foreach ($products as &$p) {
        $p['category_name'] = $category['name'];
        $p['category_slug'] = $category['slug'];
    }
    unset($p);
} catch (Throwable $e) {
    $products = []; $total = 0; $pages = 1;
}

include __DIR__ . '/includes/shop_header.php';
?>

<section class="section">
  <div class="container">

    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= e(shop_url()) ?>">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= e(shop_url('shop.php')) ?>">Shop</a></li>
        <li class="breadcrumb-item active"><?= e($category['name']) ?></li>
      </ol>
    </nav>

    <div class="section-header">
      <h2><?= e($category['name']) ?></h2>
      <?php if (!empty($category['description'])): ?>
        <p><?= e($category['description']) ?></p>
      <?php endif; ?>
      <p class="small text-muted"><?= number_format($total) ?>
        product<?= $total === 1 ? '' : 's' ?> in this category</p>
    </div>

    <form class="filters-bar" method="get">
      <input type="hidden" name="slug" value="<?= e($slug) ?>">
      <div class="row g-2 align-items-end">
        <div class="col-md-4">
          <label class="form-label">Sort by</label>
          <select name="sort" class="form-select" onchange="this.form.submit()">
            <option value="newest"     <?= $sort === 'newest'     ? 'selected' : '' ?>>Newest first</option>
            <option value="price_asc"  <?= $sort === 'price_asc'  ? 'selected' : '' ?>>Price: low to high</option>
            <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: high to low</option>
            <option value="name_asc"   <?= $sort === 'name_asc'   ? 'selected' : '' ?>>Name: A → Z</option>
            <option value="name_desc"  <?= $sort === 'name_desc'  ? 'selected' : '' ?>>Name: Z → A</option>
          </select>
        </div>
      </div>
    </form>

    <?php if ($products): ?>
      <div class="row g-3">
        <?php foreach ($products as $p): ?>
          <div class="col-6 col-md-4 col-lg-3">
            <?php include __DIR__ . '/_product_card.php'; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if ($pages > 1): $qs = $_GET; ?>
        <nav class="mt-4">
          <ul class="pagination justify-content-center mb-0">
            <?php for ($pn = 1; $pn <= $pages; $pn++): $qs['page'] = $pn; ?>
              <li class="page-item <?= $pn === $page ? 'active' : '' ?>">
                <a class="page-link" href="?<?= e(http_build_query($qs)) ?>"><?= $pn ?></a>
              </li>
            <?php endfor; ?>
          </ul>
        </nav>
      <?php endif; ?>

    <?php else: ?>
      <div class="empty-state">
        <i class="bi bi-bag-x"></i>
        <h3>No products in this category yet</h3>
        <a href="<?= e(shop_url('shop.php')) ?>" class="btn btn-primary">
          Browse all products
        </a>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/shop_footer.php'; ?>
