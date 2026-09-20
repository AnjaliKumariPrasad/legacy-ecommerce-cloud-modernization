<?php
require_once __DIR__ . '/includes/shop_bootstrap.php';

$pageTitle = 'Track your order';

// Pre-fill from query string (e.g. when linking from confirmation page).
$orderNumber = trim((string)($_GET['order'] ?? $_POST['order'] ?? ''));
$email       = trim((string)($_GET['email'] ?? $_POST['email'] ?? ''));

$order  = null;
$items  = [];
$errors = [];
$searched = false;

if ($orderNumber !== '' || $email !== '') {
    $searched = true;

    if ($orderNumber === '') {
        $errors[] = 'Please enter your order number.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter the email you used at checkout.';
    }

    if (!$errors) {
        $stmt = db()->prepare(
            "SELECT * FROM orders
             WHERE order_number = :n AND customer_email = :e
             LIMIT 1"
        );
        $stmt->execute([':n' => $orderNumber, ':e' => $email]);
        $order = $stmt->fetch();

        if (!$order) {
            $errors[] = "We couldn't find an order matching that number and email. "
                      . "Please double-check both and try again.";
        } else {
            $stmt = db()->prepare("SELECT * FROM order_items WHERE order_id = :id");
            $stmt->execute([':id' => $order['id']]);
            $items = $stmt->fetchAll();
        }
    }
}

/**
 * Status timeline (in order). 'cancelled' is shown separately as a red endpoint.
 */
$timeline = [
    'pending'    => ['label' => 'Order placed',    'icon' => 'bi-receipt'],
    'processing' => ['label' => 'Processing',      'icon' => 'bi-box-seam'],
    'shipped'    => ['label' => 'Shipped',         'icon' => 'bi-truck'],
    'completed'  => ['label' => 'Delivered',       'icon' => 'bi-check2-circle'],
];

$statusBadge = [
    'pending'    => 'bg-warning text-dark',
    'processing' => 'bg-info text-white',
    'shipped'    => 'bg-primary text-white',
    'completed'  => 'bg-success text-white',
    'cancelled'  => 'bg-secondary text-white',
];

include __DIR__ . '/includes/shop_header.php';
?>

<section class="section">
  <div class="container" style="max-width: 900px;">

    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= e(shop_url()) ?>">Home</a></li>
        <li class="breadcrumb-item active">Track order</li>
      </ol>
    </nav>

    <div class="section-header text-center">
      <h2><i class="bi bi-geo-alt"></i> Track your order</h2>
      <p>Enter your order number and email to see the latest status.</p>
    </div>

    <div class="card mb-4">
      <div class="card-body">
        <form method="get" class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Order number</label>
            <input type="text" name="order" class="form-control form-control-lg"
                   value="<?= e($orderNumber) ?>"
                   placeholder="e.g. ORD-202605-0001" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Email used at checkout</label>
            <input type="email" name="email" class="form-control form-control-lg"
                   value="<?= e($email) ?>"
                   placeholder="you@example.com" required>
          </div>
          <div class="col-12 d-grid">
            <button class="btn btn-primary btn-lg">
              <i class="bi bi-search"></i> Track order
            </button>
          </div>
        </form>
      </div>
    </div>

    <?php foreach ($errors as $err): ?>
      <div class="alert alert-danger"><?= e($err) ?></div>
    <?php endforeach; ?>

    <?php if ($order): ?>
      <?php
        $isCancelled = $order['status'] === 'cancelled';
        // Map current status to step index (0..3) for the visual timeline.
        $stepIndex = array_search($order['status'], array_keys($timeline), true);
        if ($stepIndex === false) $stepIndex = -1;
      ?>

      <div class="card mb-4">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
            <div>
              <div class="text-muted small">Order number</div>
              <div class="h4 mb-0"><?= e($order['order_number']) ?></div>
            </div>
            <div>
              <span class="badge <?= e($statusBadge[$order['status']] ?? 'bg-secondary') ?> fs-6 px-3 py-2">
                <?= e(ucfirst($order['status'])) ?>
              </span>
            </div>
          </div>

          <hr>

          <?php if ($isCancelled): ?>
            <div class="alert alert-secondary mb-0">
              <i class="bi bi-x-circle"></i>
              This order has been <strong>cancelled</strong>. If this is unexpected,
              please contact us with your order number.
            </div>
          <?php else: ?>
            <div class="status-timeline">
              <?php $i = 0; foreach ($timeline as $key => $step):
                $isDone    = $i <= $stepIndex;
                $isCurrent = $i === $stepIndex;
              ?>
                <div class="timeline-step <?= $isDone ? 'is-done' : '' ?> <?= $isCurrent ? 'is-current' : '' ?>">
                  <div class="timeline-bullet"><i class="bi <?= e($step['icon']) ?>"></i></div>
                  <div class="timeline-label"><?= e($step['label']) ?></div>
                </div>
                <?php if ($i < count($timeline) - 1): ?>
                  <div class="timeline-line <?= $i < $stepIndex ? 'is-done' : '' ?>"></div>
                <?php endif; ?>
              <?php $i++; endforeach; ?>
            </div>

            <div class="text-muted small mt-3">
              <?php
                $msg = [
                  'pending'    => 'We have received your order and will start processing it soon.',
                  'processing' => "We're preparing your order for shipment.",
                  'shipped'    => 'Your order is on its way!',
                  'completed'  => 'Your order has been delivered. Thank you for shopping with us!',
                ][$order['status']] ?? '';
              ?>
              <i class="bi bi-info-circle"></i> <?= e($msg) ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <div class="card h-100">
            <div class="card-body">
              <h6 class="text-muted text-uppercase small">Ship to</h6>
              <div class="fw-semibold"><?= e($order['customer_name']) ?></div>
              <div><?= nl2br(e($order['shipping_address'])) ?></div>
              <div>
                <?= e(trim(($order['shipping_city'] ?? '') . ' ' . ($order['shipping_zip'] ?? ''))) ?>
              </div>
              <div><?= e($order['shipping_country']) ?></div>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="card h-100">
            <div class="card-body">
              <h6 class="text-muted text-uppercase small">Order details</h6>
              <div>
                <strong>Placed:</strong>
                <?= e(date('M j, Y g:i a', strtotime($order['created_at']))) ?>
              </div>
              <div>
                <strong>Last updated:</strong>
                <?= e(date('M j, Y g:i a', strtotime($order['updated_at']))) ?>
              </div>
              <div>
                <strong>Payment:</strong>
                <?= e($order['payment_method'] === 'cod' ? 'Cash on Delivery' : 'Bank Transfer') ?>
              </div>
              <div>
                <strong>Total:</strong>
                <span class="fw-bold"><?= e(price((float)$order['total'])) ?></span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-body p-0">
          <div class="table-responsive">
          <table class="table mb-0">
            <thead>
              <tr>
                <th>Product</th>
                <th class="text-center">Qty</th>
                <th class="text-end">Price</th>
                <th class="text-end">Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $i): ?>
                <tr>
                  <td>
                    <?= e($i['product_name']) ?>
                    <?php if (!empty($i['product_sku'])): ?>
                      <div class="small text-muted">SKU: <?= e($i['product_sku']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="text-center"><?= (int)$i['quantity'] ?></td>
                  <td class="text-end"><?= e(price((float)$i['unit_price'])) ?></td>
                  <td class="text-end"><?= e(price((float)$i['line_total'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="3" class="text-end">Subtotal</td>
                <td class="text-end"><?= e(price((float)$order['subtotal'])) ?></td>
              </tr>
              <tr>
                <td colspan="3" class="text-end">Shipping</td>
                <td class="text-end">
                  <?= ((float)$order['shipping_fee'] > 0)
                        ? e(price((float)$order['shipping_fee']))
                        : '<span class="text-success">Free</span>' ?>
                </td>
              </tr>
              <tr class="fw-bold fs-5">
                <td colspan="3" class="text-end">Total</td>
                <td class="text-end"><?= e(price((float)$order['total'])) ?></td>
              </tr>
            </tfoot>
          </table>
          </div>
        </div>
      </div>

    <?php elseif ($searched && !$errors): ?>
      <div class="empty-state">
        <i class="bi bi-question-circle"></i>
        <h3>Order not found</h3>
      </div>
    <?php endif; ?>

  </div>
</section>

<?php include __DIR__ . '/includes/shop_footer.php'; ?>
