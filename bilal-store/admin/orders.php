<?php
require_once __DIR__ . '/includes/auth.php';
require_role('admin', 'editor');

$pageTitle = 'Orders';

// ----- Status update / delete -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        require_role('admin');
        $id = (int)($_POST['id'] ?? 0);
        $stmt = db()->prepare('SELECT order_number FROM orders WHERE id = :id');
        $stmt->execute([':id' => $id]);
        if ($o = $stmt->fetch()) {
            db()->prepare('DELETE FROM orders WHERE id = :id')->execute([':id' => $id]);
            log_activity('order.delete', 'Deleted order ' . $o['order_number']);
            flash('success', 'Order ' . $o['order_number'] . ' deleted.');
        } else {
            flash('warning', 'Order not found.');
        }
        admin_redirect('orders.php');
    }
}

// ----- Filters & pagination -----
$search  = trim((string)($_GET['q']      ?? ''));
$status  = trim((string)($_GET['status'] ?? ''));
$dateFrom = trim((string)($_GET['from']  ?? ''));
$dateTo   = trim((string)($_GET['to']    ?? ''));
$perPage = max(5, (int) setting('items_per_page', '10'));
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$allowedStatuses = ['pending', 'processing', 'shipped', 'completed', 'cancelled'];

$where = []; $params = [];
if ($search !== '') {
    $where[] = '(order_number LIKE :q1 OR customer_name LIKE :q2 OR customer_email LIKE :q3)';
    $params[':q1'] = "%$search%";
    $params[':q2'] = "%$search%";
    $params[':q3'] = "%$search%";
}
if (in_array($status, $allowedStatuses, true)) {
    $where[] = 'status = :st';
    $params[':st'] = $status;
}
if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $where[] = 'created_at >= :df';
    $params[':df'] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $where[] = 'created_at <= :dt';
    $params[':dt'] = $dateTo . ' 23:59:59';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = db()->prepare("SELECT COUNT(*) FROM orders $whereSql");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));

$stmt = db()->prepare(
    "SELECT o.*, (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS item_count
     FROM orders o
     $whereSql
     ORDER BY o.created_at DESC
     LIMIT :lim OFFSET :off"
);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset,  PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

// Headline metrics for the toolbar.
$stats = db()->query(
    "SELECT
       COUNT(*)                                     AS total_orders,
       SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_orders,
       SUM(CASE WHEN status NOT IN ('cancelled') THEN total ELSE 0 END) AS revenue
     FROM orders"
)->fetch();

$statusBadge = [
    'pending'    => 'text-bg-warning',
    'processing' => 'text-bg-info',
    'shipped'    => 'text-bg-primary',
    'completed'  => 'text-bg-success',
    'cancelled'  => 'text-bg-secondary',
];

include __DIR__ . '/includes/header.php';
?>

<div class="row g-3 mb-3">
  <div class="col-sm-6 col-md-4">
    <div class="card stat-card">
      <div class="stat-icon bg-indigo"><i class="bi bi-receipt"></i></div>
      <div>
        <p class="stat-label">Total orders</p>
        <p class="stat-value"><?= number_format((int)$stats['total_orders']) ?></p>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-md-4">
    <div class="card stat-card">
      <div class="stat-icon bg-amber"><i class="bi bi-hourglass-split"></i></div>
      <div>
        <p class="stat-label">Pending</p>
        <p class="stat-value"><?= number_format((int)$stats['pending_orders']) ?></p>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-4">
    <div class="card stat-card">
      <div class="stat-icon bg-emerald"><i class="bi bi-cash-stack"></i></div>
      <div>
        <p class="stat-label">Revenue (excl. cancelled)</p>
        <p class="stat-value"><?= e(price((float)$stats['revenue'])) ?></p>
      </div>
    </div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end" method="get">
      <div class="col-md-3">
        <label class="form-label small text-muted">Search</label>
        <input type="text" name="q" class="form-control"
               value="<?= e($search) ?>" placeholder="Order #, name or email">
      </div>
      <div class="col-md-2">
        <label class="form-label small text-muted">Status</label>
        <select name="status" class="form-select">
          <option value="">All</option>
          <?php foreach ($allowedStatuses as $s): ?>
            <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small text-muted">From</label>
        <input type="date" name="from" class="form-control" value="<?= e($dateFrom) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label small text-muted">To</label>
        <input type="date" name="to" class="form-control" value="<?= e($dateTo) ?>">
      </div>
      <div class="col-md-3 d-flex gap-2">
        <button class="btn btn-primary flex-grow-1"><i class="bi bi-funnel"></i> Filter</button>
        <a class="btn btn-outline-secondary" href="<?= e(admin_url('orders.php')) ?>">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <h5 class="card-title mb-3"><?= number_format($total) ?> order<?= $total === 1 ? '' : 's' ?></h5>

    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>Order #</th>
            <th>Customer</th>
            <th>Items</th>
            <th>Total</th>
            <th>Payment</th>
            <th>Status</th>
            <th>Placed</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $o): ?>
            <tr>
              <td>
                <a href="<?= e(admin_url('order_view.php?id=' . (int)$o['id'])) ?>"
                   class="fw-semibold"><?= e($o['order_number']) ?></a>
              </td>
              <td>
                <div><?= e($o['customer_name']) ?></div>
                <small class="text-muted"><?= e($o['customer_email']) ?></small>
              </td>
              <td><?= (int)$o['item_count'] ?></td>
              <td class="fw-semibold"><?= e(price((float)$o['total'])) ?></td>
              <td>
                <span class="badge text-bg-light">
                  <?= e($o['payment_method'] === 'cod' ? 'COD' : 'Bank') ?>
                </span>
              </td>
              <td>
                <span class="badge <?= e($statusBadge[$o['status']] ?? 'text-bg-secondary') ?>">
                  <?= e(ucfirst($o['status'])) ?>
                </span>
              </td>
              <td>
                <small><?= e(date('M j, Y g:i a', strtotime($o['created_at']))) ?></small>
              </td>
              <td class="text-end">
                <a class="btn btn-sm btn-light"
                   href="<?= e(admin_url('order_view.php?id=' . (int)$o['id'])) ?>"
                   title="View">
                  <i class="bi bi-eye"></i>
                </a>
                <?php if (has_role('admin')): ?>
                  <form method="post" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"
                            data-confirm="Delete this order? This cannot be undone."
                            title="Delete">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">No orders found.</td></tr>
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
