<?php
require_once __DIR__ . '/includes/shop_bootstrap.php';

$pageTitle = 'Checkout';

$cart = cart_load();

if (empty($cart['items'])) {
    flash('warning', 'Your cart is empty. Add some products before checking out.');
    redirect('cart.php');
}

$shippingFee = (float) setting('shipping_fee', '0');
$grandTotal  = $cart['subtotal'] + $shippingFee;

$errors = [];
$old    = [
    'customer_name'    => '',
    'customer_email'   => '',
    'customer_phone'   => '',
    'shipping_address' => '',
    'shipping_city'    => '',
    'shipping_zip'     => '',
    'shipping_country' => '',
    'payment_method'   => 'cod',
    'notes'            => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    foreach (array_keys($old) as $k) $old[$k] = trim((string)($_POST[$k] ?? ''));

    if ($old['customer_name'] === '')        $errors[] = 'Full name is required.';
    if (!filter_var($old['customer_email'], FILTER_VALIDATE_EMAIL))
                                              $errors[] = 'Valid email is required.';
    if ($old['customer_phone'] === '')        $errors[] = 'Phone number is required.';
    if ($old['shipping_address'] === '')      $errors[] = 'Shipping address is required.';
    if ($old['shipping_city'] === '')         $errors[] = 'City is required.';

    $allowedPayments = ['cod', 'bank_transfer'];
    if (!in_array($old['payment_method'], $allowedPayments, true)) {
        $errors[] = 'Please choose a valid payment method.';
    }

    // Re-verify stock right before order placement.
    if (!$errors) {
        foreach ($cart['items'] as $i) {
            if ((int)$i['qty'] > (int)$i['product']['stock']) {
                $errors[] = 'Not enough stock for "' . $i['product']['name']
                          . '" (only ' . (int)$i['product']['stock'] . ' left).';
            }
        }
    }

    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();

            $orderNumber = generate_order_number();

            $stmt = $pdo->prepare(
                "INSERT INTO orders
                  (order_number, customer_name, customer_email, customer_phone,
                   shipping_address, shipping_city, shipping_zip, shipping_country,
                   subtotal, shipping_fee, total, payment_method, notes, status)
                 VALUES
                  (:order_number, :name, :email, :phone,
                   :address, :city, :zip, :country,
                   :subtotal, :shipping_fee, :total, :payment, :notes, 'pending')"
            );
            $stmt->execute([
                ':order_number'  => $orderNumber,
                ':name'          => $old['customer_name'],
                ':email'         => $old['customer_email'],
                ':phone'         => $old['customer_phone'],
                ':address'       => $old['shipping_address'],
                ':city'          => $old['shipping_city'],
                ':zip'           => $old['shipping_zip'],
                ':country'       => $old['shipping_country'],
                ':subtotal'      => $cart['subtotal'],
                ':shipping_fee'  => $shippingFee,
                ':total'         => $grandTotal,
                ':payment'       => $old['payment_method'],
                ':notes'         => $old['notes'],
            ]);
            $orderId = (int)$pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                "INSERT INTO order_items
                  (order_id, product_id, product_name, product_sku, product_image,
                   unit_price, quantity, line_total)
                 VALUES
                  (:order_id, :product_id, :name, :sku, :image,
                   :unit_price, :qty, :line_total)"
            );
            $stockStmt = $pdo->prepare(
                "UPDATE products SET stock = GREATEST(stock - :q, 0) WHERE id = :id"
            );

            foreach ($cart['items'] as $i) {
                $p = $i['product'];
                $itemStmt->execute([
                    ':order_id'   => $orderId,
                    ':product_id' => (int)$p['id'],
                    ':name'       => $p['name'],
                    ':sku'        => $p['sku'] ?: null,
                    ':image'      => $p['image'] ?: null,
                    ':unit_price' => $i['unit_price'],
                    ':qty'        => $i['qty'],
                    ':line_total' => $i['line_total'],
                ]);
                $stockStmt->execute([
                    ':q'  => $i['qty'],
                    ':id' => (int)$p['id'],
                ]);
            }

            $pdo->commit();

            cart_clear();
            redirect('order_confirmation.php?o=' . urlencode($orderNumber));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Could not place your order: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/includes/shop_header.php';
?>

<section class="section">
  <div class="container">

    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= e(shop_url()) ?>">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= e(shop_url('cart.php')) ?>">Cart</a></li>
        <li class="breadcrumb-item active">Checkout</li>
      </ol>
    </nav>

    <div class="section-header">
      <h2>Checkout</h2>
      <p>Just a few details and your order is placed.</p>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <strong>Please fix the following:</strong>
        <ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <form method="post" novalidate>
      <?= csrf_field() ?>

      <div class="row g-4">

        <div class="col-lg-7">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Contact information</h5>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Full name *</label>
                  <input name="customer_name" class="form-control"
                         value="<?= e($old['customer_name']) ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Email *</label>
                  <input name="customer_email" type="email" class="form-control"
                         value="<?= e($old['customer_email']) ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Phone *</label>
                  <input name="customer_phone" class="form-control"
                         value="<?= e($old['customer_phone']) ?>" required>
                </div>
              </div>
            </div>
          </div>

          <div class="card mt-4">
            <div class="card-body">
              <h5 class="card-title">Shipping address</h5>
              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label">Street address *</label>
                  <textarea name="shipping_address" class="form-control" rows="2" required><?= e($old['shipping_address']) ?></textarea>
                </div>
                <div class="col-md-6">
                  <label class="form-label">City *</label>
                  <input name="shipping_city" class="form-control"
                         value="<?= e($old['shipping_city']) ?>" required>
                </div>
                <div class="col-md-3">
                  <label class="form-label">ZIP / Postal</label>
                  <input name="shipping_zip" class="form-control"
                         value="<?= e($old['shipping_zip']) ?>">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Country</label>
                  <input name="shipping_country" class="form-control"
                         value="<?= e($old['shipping_country']) ?>">
                </div>
              </div>
            </div>
          </div>

          <div class="card mt-4">
            <div class="card-body">
              <h5 class="card-title">Payment method</h5>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="payment_method"
                       id="pm_cod" value="cod"
                       <?= $old['payment_method'] === 'cod' ? 'checked' : '' ?>>
                <label class="form-check-label" for="pm_cod">
                  <strong>Cash on Delivery</strong>
                  <div class="small text-muted">Pay with cash when the order arrives.</div>
                </label>
              </div>
              <div class="form-check mt-2">
                <input class="form-check-input" type="radio" name="payment_method"
                       id="pm_bank" value="bank_transfer"
                       <?= $old['payment_method'] === 'bank_transfer' ? 'checked' : '' ?>>
                <label class="form-check-label" for="pm_bank">
                  <strong>Bank Transfer</strong>
                  <div class="small text-muted">We'll email transfer instructions after you place the order.</div>
                </label>
              </div>
            </div>
          </div>

          <div class="card mt-4">
            <div class="card-body">
              <label class="form-label fw-semibold">Order notes (optional)</label>
              <textarea name="notes" class="form-control" rows="3"
                        placeholder="Anything we should know about delivery?"><?= e($old['notes']) ?></textarea>
            </div>
          </div>
        </div>

        <div class="col-lg-5">
          <div class="card checkout-summary">
            <div class="card-body">
              <h5 class="card-title">Your order</h5>
              <hr>
              <ul class="list-unstyled mb-3">
                <?php foreach ($cart['items'] as $i): ?>
                  <li class="d-flex justify-content-between mb-2">
                    <span class="text-truncate me-2" style="max-width:70%">
                      <?= e($i['product']['name']) ?>
                      <span class="text-muted small">× <?= (int)$i['qty'] ?></span>
                    </span>
                    <span class="fw-semibold"><?= e(price($i['line_total'])) ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
              <hr>
              <div class="d-flex justify-content-between mb-2">
                <span>Subtotal</span>
                <strong><?= e(price($cart['subtotal'])) ?></strong>
              </div>
              <div class="d-flex justify-content-between mb-2">
                <span>Shipping</span>
                <strong>
                  <?= $shippingFee > 0 ? e(price($shippingFee)) : '<span class="text-success">Free</span>' ?>
                </strong>
              </div>
              <hr>
              <div class="d-flex justify-content-between fs-5 mb-3">
                <span>Total</span>
                <strong><?= e(price($grandTotal)) ?></strong>
              </div>
              <button class="btn btn-primary w-100 btn-lg">
                <i class="bi bi-check2-circle"></i> Place order
              </button>
              <a href="<?= e(shop_url('cart.php')) ?>"
                 class="btn btn-link w-100 mt-2">Back to cart</a>
            </div>
          </div>
        </div>

      </div>
    </form>

  </div>
</section>

<?php include __DIR__ . '/includes/shop_footer.php'; ?>
