<?php
require_once __DIR__ . '/includes/auth.php';
require_role('admin', 'editor');

$pageTitle = 'Categories';

// Delete handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    require_csrf();
    require_role('admin');

    $id = (int)($_POST['id'] ?? 0);
    $stmt = db()->prepare('SELECT name FROM categories WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if ($c = $stmt->fetch()) {
        db()->prepare('DELETE FROM categories WHERE id = :id')->execute([':id' => $id]);
        log_activity('category.delete', 'Deleted category ' . $c['name']);
        flash('success', 'Category deleted.');
    } else {
        flash('warning', 'Category not found.');
    }
    admin_redirect('categories.php');
}

// Filters & pagination
$search  = trim((string)($_GET['q']      ?? ''));
$status  = trim((string)($_GET['status'] ?? ''));
$perPage = max(5, (int) setting('items_per_page', '10'));
$page    = max(1, (int) ($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$where = []; $params = [];
if ($search !== '') { $where[] = 'name LIKE :q';      $params[':q']      = "%$search%"; }
if (in_array($status, ['active', 'inactive'], true)) {
    $where[] = 'status = :st'; $params[':st'] = $status;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = db()->prepare("SELECT COUNT(*) FROM categories $whereSql");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));

$stmt = db()->prepare(
    "SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
     FROM categories c $whereSql ORDER BY c.created_at DESC LIMIT :lim OFFSET :off"
);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset,  PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end" method="get">
      <div class="col-md-5">
        <label class="form-label small text-muted">Search</label>
        <input type="text" name="q" class="form-control" value="<?= e($search) ?>" placeholder="Category name">
      </div>
      <div class="col-md-3">
        <label class="form-label small text-muted">Status</label>
        <select name="status" class="form-select">
          <option value="">All</option>
          <option value="active"   <?= $status === 'active'   ? 'selected' : '' ?>>Active</option>
          <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
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
      <h5 class="card-title mb-0"><?= number_format($total) ?> categor<?= $total === 1 ? 'y' : 'ies' ?></h5>
      <a class="btn btn-primary" href="<?= e(admin_url('category_form.php')) ?>">
        <i class="bi bi-plus-lg"></i> Add category
      </a>
    </div>

    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>Name</th><th>Slug</th><th>Products</th>
            <th>Status</th><th>Created</th><th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $c): ?>
            <tr>
              <td><span class="fw-semibold"><?= e($c['name']) ?></span></td>
              <td><code><?= e($c['slug']) ?></code></td>
              <td><span class="badge text-bg-light"><?= (int)$c['product_count'] ?></span></td>
              <td><span class="badge status-<?= e($c['status']) ?>"><?= e($c['status']) ?></span></td>
              <td class="text-muted"><?= e(format_date($c['created_at'], 'M j, Y')) ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-light" href="<?= e(admin_url('category_form.php?id=' . (int)$c['id'])) ?>" title="Edit">
                  <i class="bi bi-pencil"></i>
                </a>
                <?php if (has_role('admin')): ?>
                  <form method="post" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"
                            data-confirm="Delete this category? Products in it will be unlinked."
                            title="Delete">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No categories yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($pages > 1): $qs = $_GET; ?>
      <nav class="mt-3"><ul class="pagination justify-content-end mb-0">
        <?php for ($p = 1; $p <= $pages; $p++): $qs['page'] = $p; ?>
          <li class="page-item <?= $p === $page ? 'active' : '' ?>">
            <a class="page-link" href="?<?= e(http_build_query($qs)) ?>"><?= $p ?></a>
          </li>
        <?php endfor; ?>
      </ul></nav>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
