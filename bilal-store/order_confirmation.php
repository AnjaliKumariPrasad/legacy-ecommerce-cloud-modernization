<?php
require_once __DIR__ . '/includes/shop_bootstrap.php';

$pageTitle = 'Order confirmation';

$orderNumber = trim((string)($_GET['o'] ?? ''));

$order = null;
$items = [];

if ($orderNumber !== '') {
    $stmt = db()->prepare("SELECT * FROM orders WHERE order_number = :n LIMIT 1");
    $stmt->execute([':n' => $orderNumber]);
    $order = $stmt->fetch();

    if ($order) {
        $stmt = db()->prepare("SELECT * FROM order_items WHERE order_id = :id");
        $stmt->execute([':id' => $order['id']]);
        $items = $stmt->fetchAll();
    }
}

include __DIR__ . '/includes/shop_header.php';
?>

<section class="section">
  <div class="container" style="max-width: 800px;">

    <?php if (!$order): ?>
      <div class="empty-state">
        <i class="bi bi-question-circle"></i>
        <h3>Order not found</h3>
        <p>We couldn't find an order with that reference number.</p>
        <a href="<?= e(shop_url('shop.php')) ?>" class="btn btn-primary">Back to shop</a>
      </div>
    <?php else: ?>

      <div class="text-center mb-4">
        <div class="display-1 text-success"><i class="bi bi-check-circle-fill"></i></div>
        <h2 class="mt-2">Thank you, <?= e($order['customer_name']) ?>!</h2>
        <p class="lead text-muted">Your order has been placed successfully.</p>
        <div class="badge bg-primary fs-6 px-3 py-2">
          Order #<?= e($order['order_number']) ?>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <h6 class="text-muted text-uppercase small">Contact</h6>
              <div><?= e($order['customer_name']) ?></div>
              <div><?= e($order['customer_email']) ?></div>
              <div><?= e($order['customer_phone']) ?></div>
            </div>
            <div class="col-md-6">
              <h6 class="text-muted text-uppercase small">Ship to</h6>
              <div><?= nl2br(e($order['shipping_address'])) ?></div>
              <div>
                <?= e(trim(($order['shipping_city'] ?? '') . ' ' . ($order['shipping_zip'] ?? ''))) ?>
              </div>
              <div><?= e($order['shipping_country']) ?></div>
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
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
                  <td class="text-end"><?= e(price($i['unit_price'])) ?></td>
                  <td class="text-end"><?= e(price($i['line_total'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="3" class="text-end">Subtotal</td>
                <td class="text-end"><?= e(price($order['subtotal'])) ?></td>
              </tr>
              <tr>
                <td colspan="3" class="text-end">Shipping</td>
                <td class="text-end">
                  <?= ((float)$order['shipping_fee'] > 0)
                        ? e(price($order['shipping_fee']))
                        : '<span class="text-success">Free</span>' ?>
                </td>
              </tr>
              <tr class="fw-bold fs-5">
                <td colspan="3" class="text-end">Total</td>
                <td class="text-end"><?= e(price($order['total'])) ?></td>
              </tr>
            </tfoot>
          </table>
          </div>
        </div>
      </div>

      <div class="alert alert-info">
        <i class="bi bi-info-circle"></i>
        We've recorded your order. You'll receive a confirmation at
        <strong><?= e($order['customer_email']) ?></strong> shortly.
        <?php if ($order['payment_method'] === 'bank_transfer'): ?>
          Bank transfer instructions will be sent in a separate email.
        <?php endif; ?>
      </div>

      <div class="text-center d-flex flex-wrap justify-content-center gap-2">
        <a href="<?= e(shop_url('track.php?order=' . urlencode($order['order_number'])
                                . '&email=' . urlencode($order['customer_email']))) ?>"
           class="btn btn-outline-primary">
          <i class="bi bi-geo-alt"></i> Track this order
        </a>
        <a href="<?= e(shop_url('shop.php')) ?>" class="btn btn-primary">
          <i class="bi bi-bag"></i> Continue shopping
        </a>
      </div>

    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/shop_footer.php'; ?>
