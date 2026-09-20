<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'Activity log';

$perPage = max(5, (int) setting('items_per_page', '10'));
$page    = max(1, (int) ($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$total = (int) db()->query('SELECT COUNT(*) FROM activity_log')->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));

$stmt = db()->prepare(
    'SELECT a.*, u.name AS user_name
     FROM activity_log a
     LEFT JOIN users u ON u.id = a.user_id
     ORDER BY a.created_at DESC
     LIMIT :lim OFFSET :off'
);
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset,  PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<div class="card">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="card-title mb-0">All activity</h5>
      <span class="text-muted small"><?= number_format($total) ?> events</span>
    </div>
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>User</th><th>Action</th><th>Description</th>
            <th>IP</th><th>When</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $a): ?>
            <tr>
              <td><?= e($a['user_name'] ?? '—') ?></td>
              <td><span class="badge text-bg-secondary"><?= e($a['action']) ?></span></td>
              <td><?= e($a['description']) ?></td>
              <td><code><?= e($a['ip_address'] ?? '') ?></code></td>
              <td class="text-muted"><?= e(format_date($a['created_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">No activity yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($pages > 1): ?>
      <nav class="mt-3"><ul class="pagination justify-content-end mb-0">
        <?php for ($p = 1; $p <= $pages; $p++): ?>
          <li class="page-item <?= $p === $page ? 'active' : '' ?>">
            <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
          </li>
        <?php endfor; ?>
      </ul></nav>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
