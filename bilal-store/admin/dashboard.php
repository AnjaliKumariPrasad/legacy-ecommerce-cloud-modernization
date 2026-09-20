<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'Dashboard';

// E-commerce stats (gracefully handle missing tables — pre-migration).
$ecommerceEnabled = false;
$totalProducts    = 0;
$activeProducts   = 0;
$outOfStock       = 0;
$totalCategories  = 0;
$featuredCount    = 0;
$inventoryValue   = 0.0;
$lowStock         = [];
$recentProducts   = [];
$labels = $values = [];

// Order stats
$totalOrders     = 0;
$pendingOrders   = 0;
$totalRevenue    = 0.0;
$todayRevenue    = 0.0;
$recentOrders    = [];
$ordersEnabled   = false;

try {
    $totalProducts    = (int) db()->query('SELECT COUNT(*) FROM products')->fetchColumn();
    $activeProducts   = (int) db()->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn();
    $outOfStock       = (int) db()->query('SELECT COUNT(*) FROM products WHERE stock <= 0')->fetchColumn();
    $totalCategories  = (int) db()->query('SELECT COUNT(*) FROM categories')->fetchColumn();
    $featuredCount    = (int) db()->query('SELECT COUNT(*) FROM products WHERE featured = 1')->fetchColumn();
    $inventoryValue   = (float) db()->query(
        'SELECT COALESCE(SUM(stock * COALESCE(sale_price, price)), 0) FROM products'
    )->fetchColumn();

    $lowStock = db()->query(
        'SELECT id, name, stock, image FROM products
         WHERE stock > 0 AND stock < 10 ORDER BY stock ASC LIMIT 5'
    )->fetchAll();

    $recentProducts = db()->query(
        'SELECT p.id, p.name, p.price, p.sale_price, p.stock, p.image, p.created_at,
                c.name AS category_name
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         ORDER BY p.created_at DESC LIMIT 5'
    )->fetchAll();

    // Chart: products added per day (last 7 days)
    $rows = db()->query(
        "SELECT DATE(created_at) AS d, COUNT(*) AS c
         FROM products
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
         GROUP BY DATE(created_at)"
    )->fetchAll();
    $byDate = [];
    foreach ($rows as $r) $byDate[$r['d']] = (int)$r['c'];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i day"));
        $labels[] = date('M j', strtotime($d));
        $values[] = $byDate[$d] ?? 0;
    }

    $ecommerceEnabled = true;
} catch (Throwable $e) {
    // Migration not applied yet — show a hint instead of stats.
}

try {
    $totalOrders   = (int) db()->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    $pendingOrders = (int) db()->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
    $totalRevenue  = (float) db()->query(
        "SELECT COALESCE(SUM(total), 0) FROM orders WHERE status NOT IN ('cancelled')"
    )->fetchColumn();
    $todayRevenue  = (float) db()->query(
        "SELECT COALESCE(SUM(total), 0) FROM orders
         WHERE status NOT IN ('cancelled') AND DATE(created_at) = CURDATE()"
    )->fetchColumn();
    $recentOrders  = db()->query(
        "SELECT id, order_number, customer_name, total, status, created_at
         FROM orders ORDER BY created_at DESC LIMIT 6"
    )->fetchAll();
    $ordersEnabled = true;
} catch (Throwable $e) {
    // Orders table missing — silently skip.
}

// Recent CRUD activity (filtered to e-commerce events).
$recentActivity = db()->query(
    "SELECT a.*, u.name AS user_name
     FROM activity_log a
     LEFT JOIN users u ON u.id = a.user_id
     WHERE a.action LIKE 'product.%' OR a.action LIKE 'category.%'
     ORDER BY a.created_at DESC LIMIT 8"
)->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<?php if (!$ecommerceEnabled): ?>
  <div class="alert alert-warning">
    The e-commerce tables don't exist yet. Run
    <a href="<?= e(admin_url('migrate.php')) ?>" class="alert-link">migrate.php</a>
    to enable the products module.
  </div>
<?php endif; ?>

<!-- E-commerce stats -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <a href="<?= e(admin_url('products.php')) ?>" class="text-decoration-none text-reset">
      <div class="card stat-card">
        <div class="stat-icon bg-indigo"><i class="bi bi-box-seam"></i></div>
        <div>
          <p class="stat-label">Products</p>
          <p class="stat-value"><?= number_format($totalProducts) ?></p>
        </div>
      </div>
    </a>
  </div>
  <div class="col-sm-6 col-xl-3">
    <a href="<?= e(admin_url('products.php?status=active')) ?>" class="text-decoration-none text-reset">
      <div class="card stat-card">
        <div class="stat-icon bg-emerald"><i class="bi bi-check-circle"></i></div>
        <div>
          <p class="stat-label">Active products</p>
          <p class="stat-value"><?= number_format($activeProducts) ?></p>
        </div>
      </div>
    </a>
  </div>
  <div class="col-sm-6 col-xl-3">
    <a href="<?= e(admin_url('categories.php')) ?>" class="text-decoration-none text-reset">
      <div class="card stat-card">
        <div class="stat-icon bg-amber"><i class="bi bi-tags"></i></div>
        <div>
          <p class="stat-label">Categories</p>
          <p class="stat-value"><?= number_format($totalCategories) ?></p>
        </div>
      </div>
    </a>
  </div>
  <div class="col-sm-6 col-xl-3">
    <a href="<?= e(admin_url('products.php?status=active')) ?>" class="text-decoration-none text-reset">
      <div class="card stat-card">
        <div class="stat-icon bg-rose"><i class="bi bi-exclamation-triangle"></i></div>
        <div>
          <p class="stat-label">Out of stock</p>
          <p class="stat-value"><?= number_format($outOfStock) ?></p>
        </div>
      </div>
    </a>
  </div>
</div>

<?php if ($ordersEnabled): ?>
<!-- Order stats -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <a href="<?= e(admin_url('orders.php')) ?>" class="text-decoration-none text-reset">
      <div class="card stat-card">
        <div class="stat-icon bg-indigo"><i class="bi bi-receipt"></i></div>
        <div>
          <p class="stat-label">Total orders</p>
          <p class="stat-value"><?= number_format($totalOrders) ?></p>
        </div>
      </div>
    </a>
  </div>
  <div class="col-sm-6 col-xl-3">
    <a href="<?= e(admin_url('orders.php?status=pending')) ?>" class="text-decoration-none text-reset">
      <div class="card stat-card">
        <div class="stat-icon bg-amber"><i class="bi bi-hourglass-split"></i></div>
        <div>
          <p class="stat-label">Pending orders</p>
          <p class="stat-value"><?= number_format($pendingOrders) ?></p>
        </div>
      </div>
    </a>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card stat-card">
      <div class="stat-icon bg-emerald"><i class="bi bi-cash-stack"></i></div>
      <div>
        <p class="stat-label">Total revenue</p>
        <p class="stat-value"><?= e(price($totalRevenue)) ?></p>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card stat-card">
      <div class="stat-icon bg-rose"><i class="bi bi-graph-up-arrow"></i></div>
      <div>
        <p class="stat-label">Revenue today</p>
        <p class="stat-value"><?= e(price($todayRevenue)) ?></p>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Secondary stats -->
<div class="row g-3 mb-4">
  <div class="col-sm-6">
    <div class="card stat-card">
      <div class="stat-icon bg-amber"><i class="bi bi-star-fill"></i></div>
      <div>
        <p class="stat-label">Featured products</p>
        <p class="stat-value"><?= number_format($featuredCount) ?></p>
      </div>
    </div>
  </div>
  <div class="col-sm-6">
    <div class="card stat-card">
      <div class="stat-icon bg-emerald"><i class="bi bi-cash-stack"></i></div>
      <div>
        <p class="stat-label">Inventory value</p>
        <p class="stat-value"><?= e(price($inventoryValue)) ?></p>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- Chart -->
  <div class="col-xl-8">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h5 class="card-title mb-0">Products added (last 7 days)</h5>
          <span class="badge text-bg-light"><?= array_sum($values) ?> total</span>
        </div>
        <canvas id="productsChart" height="110"></canvas>
      </div>
    </div>
  </div>

  <!-- Side widgets -->
  <div class="col-xl-4">
    <?php if ($lowStock): ?>
      <div class="card mb-3">
        <div class="card-body">
          <h5 class="card-title">
            <i class="bi bi-exclamation-triangle text-warning"></i>
            Low stock alerts
          </h5>
          <ul class="list-unstyled mb-0">
            <?php foreach ($lowStock as $p): ?>
              <li class="d-flex align-items-center gap-3 py-2 border-bottom">
                <img src="<?= e(product_image_url($p['image'])) ?>"
                     class="rounded" width="36" height="36" style="object-fit:cover" alt="">
                <div class="flex-grow-1 min-w-0">
                  <a href="<?= e(admin_url('product_form.php?id=' . (int)$p['id'])) ?>"
                     class="fw-semibold text-truncate text-decoration-none">
                    <?= e($p['name']) ?>
                  </a>
                </div>
                <span class="badge text-bg-warning"><?= (int)$p['stock'] ?> left</span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    <?php endif; ?>

    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h5 class="card-title mb-0">Recent products</h5>
          <a href="<?= e(admin_url('products.php')) ?>" class="btn btn-sm btn-light">View all</a>
        </div>
        <ul class="list-unstyled mb-0">
          <?php foreach ($recentProducts as $p): ?>
            <li class="d-flex align-items-center gap-3 py-2 border-bottom">
              <img src="<?= e(product_image_url($p['image'])) ?>"
                   class="rounded" width="40" height="40" style="object-fit:cover" alt="">
              <div class="flex-grow-1 min-w-0">
                <a href="<?= e(admin_url('product_form.php?id=' . (int)$p['id'])) ?>"
                   class="fw-semibold text-truncate text-decoration-none d-block">
                  <?= e($p['name']) ?>
                </a>
                <small class="text-muted"><?= e($p['category_name'] ?: 'Uncategorized') ?></small>
              </div>
              <div class="text-end">
                <div class="fw-semibold small">
                  <?php $effective = $p['sale_price'] !== null && (float)$p['sale_price'] > 0
                                      ? (float)$p['sale_price'] : (float)$p['price']; ?>
                  <?= e(price($effective)) ?>
                </div>
                <small class="text-muted"><?= (int)$p['stock'] ?> in stock</small>
              </div>
            </li>
          <?php endforeach; ?>
          <?php if (!$recentProducts): ?>
            <li class="text-muted small py-3">
              No products yet.
              <a href="<?= e(admin_url('product_form.php')) ?>">Add your first one</a>.
            </li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </div>

  <?php if ($ordersEnabled): ?>
  <!-- Recent orders -->
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h5 class="card-title mb-0"><i class="bi bi-receipt"></i> Recent orders</h5>
          <a href="<?= e(admin_url('orders.php')) ?>" class="btn btn-sm btn-light">View all</a>
        </div>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Order #</th><th>Customer</th><th>Total</th><th>Status</th><th>Placed</th><th></th>
              </tr>
            </thead>
            <tbody>
              <?php
              $sb = [
                  'pending'    => 'text-bg-warning',
                  'processing' => 'text-bg-info',
                  'shipped'    => 'text-bg-primary',
                  'completed'  => 'text-bg-success',
                  'cancelled'  => 'text-bg-secondary',
              ];
              ?>
              <?php foreach ($recentOrders as $o): ?>
                <tr>
                  <td>
                    <a href="<?= e(admin_url('order_view.php?id=' . (int)$o['id'])) ?>"
                       class="fw-semibold"><?= e($o['order_number']) ?></a>
                  </td>
                  <td><?= e($o['customer_name']) ?></td>
                  <td class="fw-semibold"><?= e(price((float)$o['total'])) ?></td>
                  <td>
                    <span class="badge <?= e($sb[$o['status']] ?? 'text-bg-secondary') ?>">
                      <?= e(ucfirst($o['status'])) ?>
                    </span>
                  </td>
                  <td class="text-muted">
                    <small><?= e(date('M j, g:i a', strtotime($o['created_at']))) ?></small>
                  </td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-light"
                       href="<?= e(admin_url('order_view.php?id=' . (int)$o['id'])) ?>">
                      <i class="bi bi-eye"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$recentOrders): ?>
                <tr><td colspan="6" class="text-center text-muted py-3">
                  No orders yet. Once a customer places an order from the storefront,
                  it will appear here.
                </td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Recent CRUD activity (e-commerce only) -->
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h5 class="card-title mb-0">Recent product &amp; category activity</h5>
          <a href="<?= e(admin_url('activity.php')) ?>" class="btn btn-sm btn-light">View all</a>
        </div>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>By</th><th>Action</th><th>Description</th><th>When</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentActivity as $a): ?>
                <tr>
                  <td><?= e($a['user_name'] ?? '—') ?></td>
                  <td><span class="badge text-bg-secondary"><?= e($a['action']) ?></span></td>
                  <td><?= e($a['description']) ?></td>
                  <td class="text-muted"><?= e(format_date($a['created_at'])) ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$recentActivity): ?>
                <tr><td colspan="4" class="text-center text-muted py-3">
                  No product activity yet.
                </td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('productsChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [{
                label: 'Products',
                data:  <?= json_encode($values) ?>,
                borderColor: '#4f46e5',
                backgroundColor: 'rgba(79,70,229,.12)',
                fill: true, tension: .35, borderWidth: 2,
                pointBackgroundColor: '#4f46e5'
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
