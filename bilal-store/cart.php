<?php
require_once __DIR__ . '/includes/shop_bootstrap.php';

$pageTitle = 'Shopping Cart';

// ----- Handle POST actions -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $pid = (int)($_POST['product_id'] ?? 0);
        $qty = (int)($_POST['qty']        ?? 1);

        // Verify product exists & is active.
        if ($pid > 0) {
            $stmt = db()->prepare(
                "SELECT id, name, stock FROM products
                 WHERE id = :id AND status = 'active' LIMIT 1"
            );
            $stmt->execute([':id' => $pid]);
            $p = $stmt->fetch();
            if ($p) {
                if ((int)$p['stock'] <= 0) {
                    flash('warning', $p['name'] . ' is currently out of stock.');
                } else {
                    $newQty = cart_add($pid, $qty);
                    if ($newQty > (int)$p['stock']) {
                        cart_set($pid, (int)$p['stock']);
                        flash('warning', 'Only ' . (int)$p['stock'] . ' of '
                            . $p['name'] . ' are available.');
                    } else {
                        flash('success', 'Added "' . $p['name'] . '" to your cart.');
                    }
                }
            }
        }
        // After adding, allow returning to one of a small allow-list of pages.
        // 'checkout.php' is used by the "Buy now" button on the product page.
        $return  = $_POST['return_to'] ?? 'cart.php';
        $allowed = ['cart.php', 'checkout.php', 'shop.php', 'index.php'];
        redirect(in_array($return, $allowed, true) ? $return : 'cart.php');
    }

    if ($action === 'update') {
        // A row "remove" button submits remove_id alongside the form.
        if (!empty($_POST['remove_id'])) {
            cart_remove((int)$_POST['remove_id']);
            flash('success', 'Item removed from cart.');
        } else {
            $items = $_POST['qty'] ?? [];
            if (is_array($items)) {
                foreach ($items as $pid => $qty) {
                    cart_set((int)$pid, (int)$qty);
                }
            }
            flash('success', 'Cart updated.');
        }
        redirect('cart.php');
    }

    if ($action === 'remove') {
        $pid = (int)($_POST['product_id'] ?? 0);
        if ($pid) cart_remove($pid);
        flash('success', 'Item removed from cart.');
        redirect('cart.php');
    }

    if ($action === 'clear') {
        cart_clear();
        flash('info', 'Cart cleared.');
        redirect('cart.php');
    }
}

$cart = cart_load();

include __DIR__ . '/includes/shop_header.php';
?>

<section class="section">
  <div class="container">

    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= e(shop_url()) ?>">Home</a></li>
        <li class="breadcrumb-item active">Cart</li>
      </ol>
    </nav>

    <div class="section-header">
      <h2>Your cart</h2>
      <p><?= count($cart['items']) ?> item<?= count($cart['items']) === 1 ? '' : 's' ?></p>
    </div>

    <?php foreach (get_flashes() as $f): ?>
      <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show">
        <?= e($f['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endforeach; ?>

    <?php if (!$cart['items']): ?>
      <div class="empty-state">
        <i class="bi bi-cart-x"></i>
        <h3>Your cart is empty</h3>
        <p>Looks like you haven't added anything yet.</p>
        <a href="<?= e(shop_url('shop.php')) ?>" class="btn btn-primary">
          <i class="bi bi-bag"></i> Continue shopping
        </a>
      </div>
    <?php else: ?>
      <div class="row g-4">
        <div class="col-lg-8">

          <form method="post" id="cartForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update">

            <div class="card">
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table align-middle mb-0 cart-table">
                    <thead>
                      <tr>
                        <th colspan="2">Product</th>
                        <th>Price</th>
                        <th style="width:120px">Qty</th>
                        <th class="text-end">Total</th>
                        <th style="width:46px"></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($cart['items'] as $i): $p = $i['product']; ?>
                        <tr>
                          <td style="width:84px">
                            <img src="<?= e(product_image_url($p['image'])) ?>"
                                 class="rounded" width="64" height="64"
                                 style="object-fit:cover" alt="">
                          </td>
                          <td>
                            <a href="<?= e(shop_url('product.php?slug=' . urlencode($p['slug']))) ?>"
                               class="fw-semibold text-reset text-decoration-none">
                              <?= e($p['name']) ?>
                            </a>
                            <?php if (!empty($p['sku'])): ?>
                              <div class="small text-muted">SKU: <?= e($p['sku']) ?></div>
                            <?php endif; ?>
                          </td>
                          <td><?= e(price($i['unit_price'])) ?></td>
                          <td>
                            <input type="number" name="qty[<?= (int)$p['id'] ?>]"
                                   class="form-control form-control-sm qty-input"
                                   value="<?= (int)$i['qty'] ?>"
                                   min="0" max="<?= (int)$p['stock'] ?>">
                          </td>
                          <td class="text-end fw-semibold"><?= e(price($i['line_total'])) ?></td>
                          <td class="text-end">
                            <button class="btn btn-sm btn-outline-danger"
                                    name="remove_id" value="<?= (int)$p['id'] ?>"
                                    title="Remove" formnovalidate
                                    onclick="return confirm('Remove this item from cart?');">
                              <i class="bi bi-x-lg"></i>
                            </button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <div class="d-flex gap-2 mt-3 flex-wrap">
              <button class="btn btn-outline-primary">
                <i class="bi bi-arrow-clockwise"></i> Update cart
              </button>
              <a href="<?= e(shop_url('shop.php')) ?>" class="btn btn-outline-secondary">
                <i class="bi bi-bag"></i> Continue shopping
              </a>
            </div>
          </form>

          <form method="post" class="mt-2">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="clear">
            <button class="btn btn-link text-danger px-0"
                    onclick="return confirm('Clear all items from cart?');">
              <i class="bi bi-trash"></i> Clear cart
            </button>
          </form>
        </div>

        <div class="col-lg-4">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Order summary</h5>
              <hr>
              <div class="d-flex justify-content-between mb-2">
                <span>Subtotal</span>
                <strong><?= e(price($cart['subtotal'])) ?></strong>
              </div>
              <?php $shipping = (float) setting('shipping_fee', '0'); ?>
              <div class="d-flex justify-content-between mb-2">
                <span>Shipping</span>
                <strong>
                  <?= $shipping > 0 ? e(price($shipping)) : '<span class="text-success">Free</span>' ?>
                </strong>
              </div>
              <hr>
              <div class="d-flex justify-content-between fs-5">
                <span>Total</span>
                <strong><?= e(price($cart['subtotal'] + $shipping)) ?></strong>
              </div>

              <a href="<?= e(shop_url('checkout.php')) ?>" class="btn btn-primary w-100 mt-3">
                <i class="bi bi-lock-fill"></i> Proceed to checkout
              </a>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/shop_footer.php'; ?>
