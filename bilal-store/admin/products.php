<?php
require_once __DIR__ . '/includes/auth.php';
require_role('admin', 'editor');

$pageTitle = 'Products';

// ----- Delete -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    require_csrf();
    require_role('admin');

    $id   = (int)($_POST['id'] ?? 0);
    $stmt = db()->prepare('SELECT name, image FROM products WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if ($p = $stmt->fetch()) {
        if ($p['image'] && file_exists(UPLOADS_PATH . '/products/' . $p['image'])) {
            @unlink(UPLOADS_PATH . '/products/' . $p['image']);
        }
        db()->prepare('DELETE FROM products WHERE id = :id')->execute([':id' => $id]);
        log_activity('product.delete', 'Deleted product ' . $p['name']);
        flash('success', 'Product deleted.');
    } else {
        flash('warning', 'Product not found.');
    }
    admin_redirect('products.php');
}

// ----- Filters & pagination -----
$search   = trim((string)($_GET['q']        ?? ''));
$catId    = (int)         ($_GET['category'] ?? 0);
$status   = trim((string)($_GET['status']   ?? ''));
$featured = trim((string)($_GET['featured'] ?? ''));
$perPage  = max(5, (int) setting('items_per_page', '10'));
$page     = max(1, (int) ($_GET['page'] ?? 1));
$offset   = ($page - 1) * $perPage;

$where = []; $params = [];
if ($search !== '') {
    $where[] = '(p.name LIKE :q1 OR p.sku LIKE :q2)';
    $params[':q1'] = "%$search%";
    $params[':q2'] = "%$search%";
}
if ($catId > 0) {
    $where[] = 'p.category_id = :cid';
    $params[':cid'] = $catId;
}
if (in_array($status, ['active', 'inactive', 'draft'], true)) {
    $where[] = 'p.status = :st';
    $params[':st'] = $status;
}
if ($featured === '1') {
    $where[] = 'p.featured = 1';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = db()->prepare("SELECT COUNT(*) FROM products p $whereSql");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));

$stmt = db()->prepare(
    "SELECT p.*, c.name AS category_name
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     $whereSql
     ORDER BY p.created_at DESC
     LIMIT :lim OFFSET :off"
);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset,  PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$categories = db()->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end" method="get">
      <div class="col-md-3">
        <label class="form-label small text-muted">Search</label>
        <input type="text" name="q" class="form-control"
               value="<?= e($search) ?>" placeholder="Name or SKU">
      </div>
      <div class="col-md-3">
        <label class="form-label small text-muted">Category</label>
        <select name="category" class="form-select">
          <option value="0">All categories</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $catId === (int)$c['id'] ? 'selected' : '' ?>>
              <?= e($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small text-muted">Status</label>
        <select name="status" class="form-select">
          <option value="">All</option>
          <?php foreach (['active', 'inactive', 'draft'] as $s): ?>
            <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small text-muted">Featured</label>
        <select name="featured" class="form-select">
          <option value="">Any</option>
          <option value="1" <?= $featured === '1' ? 'selected' : '' ?>>Featured</option>
        </select>
      </div>
      <div class="col-md-2 d-grid">
        <button class="btn btn-primary"><i class="bi bi-funnel"></i> Filter</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="card-title mb-0"><?= number_format($total) ?> product<?= $total === 1 ? '' : 's' ?></h5>
      <a class="btn btn-primary" href="<?= e(admin_url('product_form.php')) ?>">
        <i class="bi bi-plus-lg"></i> Add product
      </a>
    </div>

    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th style="width:60px"></th>
            <th>Product</th>
            <th>SKU</th>
            <th>Category</th>
            <th>Price</th>
            <th>Stock</th>
            <th>Status</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $p): ?>
            <tr>
              <td>
                <img src="<?= e(product_image_url($p['image'])) ?>"
                     class="rounded" width="44" height="44" style="object-fit:cover" alt="">
              </td>
              <td>
                <div class="fw-semibold">
                  <?= e($p['name']) ?>
                  <?php if ((int)$p['featured'] === 1): ?>
                    <i class="bi bi-star-fill text-warning ms-1" title="Featured"></i>
                  <?php endif; ?>
                </div>
                <small class="text-muted"><code><?= e($p['slug']) ?></code></small>
              </td>
              <td><code><?= e($p['sku'] ?: '—') ?></code></td>
              <td><?= e($p['category_name'] ?: '—') ?></td>
              <td>
                <?php if ($p['sale_price'] !== null && (float)$p['sale_price'] > 0): ?>
                  <span class="fw-semibold text-success"><?= e(price((float)$p['sale_price'])) ?></span>
                  <small class="text-muted text-decoration-line-through"><?= e(price((float)$p['price'])) ?></small>
                <?php else: ?>
                  <span class="fw-semibold"><?= e(price((float)$p['price'])) ?></span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ((int)$p['stock'] <= 0): ?>
                  <span class="badge text-bg-danger">Out</span>
                <?php elseif ((int)$p['stock'] < 10): ?>
                  <span class="badge text-bg-warning"><?= (int)$p['stock'] ?></span>
                <?php else: ?>
                  <span class="badge text-bg-success"><?= (int)$p['stock'] ?></span>
                <?php endif; ?>
              </td>
              <td><span class="badge status-<?= e($p['status'] === 'active' ? 'active' : 'inactive') ?>">
                <?= e($p['status']) ?></span></td>
              <td class="text-end">
                <a class="btn btn-sm btn-light"
                   href="<?= e(admin_url('product_form.php?id=' . (int)$p['id'])) ?>" title="Edit">
                  <i class="bi bi-pencil"></i>
                </a>
                <?php if (has_role('admin')): ?>
                  <form method="post" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"
                            data-confirm="Delete this product? This cannot be undone."
                            title="Delete">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">No products match your filters.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($pages > 1): $qs = $_GET; ?>
      <nav class="mt-3"><ul class="pagination justify-content-end mb-0">
        <?php for ($pn = 1; $pn <= $pages; $pn++): $qs['page'] = $pn; ?>
          <li class="page-item <?= $pn === $page ? 'active' : '' ?>">
            <a class="page-link" href="?<?= e(http_build_query($qs)) ?>"><?= $pn ?></a>
          </li>
        <?php endfor; ?>
      </ul></nav>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
