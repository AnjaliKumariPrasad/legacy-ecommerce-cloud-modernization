<?php
require_once __DIR__ . '/includes/auth.php';
require_role('admin');

$pageTitle = 'Settings';

$keys = ['site_name', 'site_email', 'site_about', 'items_per_page',
         'currency_code', 'currency_symbol'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    foreach ($keys as $k) {
        $v = trim((string)($_POST[$k] ?? ''));
        if ($k === 'items_per_page') {
            $v = (string) max(5, min(100, (int) $v));
        }
        update_setting($k, $v);
    }
    log_activity('settings.update', 'Updated site settings');
    flash('success', 'Settings saved.');
    admin_redirect('settings.php');
}

$values = [];
foreach ($keys as $k) $values[$k] = setting($k, '');

include __DIR__ . '/includes/header.php';
?>

<div class="row">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-body">
        <h5 class="card-title">General settings</h5>
        <p class="text-muted small">These values are stored in the <code>settings</code> table.</p>

        <form method="post" autocomplete="off">
          <?= csrf_field() ?>

          <div class="mb-3">
            <label class="form-label">Site name</label>
            <input type="text" name="site_name" class="form-control"
                   value="<?= e($values['site_name']) ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Contact email</label>
            <input type="email" name="site_email" class="form-control"
                   value="<?= e($values['site_email']) ?>">
          </div>

          <div class="mb-3">
            <label class="form-label">About</label>
            <textarea name="site_about" class="form-control" rows="3"><?= e($values['site_about']) ?></textarea>
          </div>

          <div class="mb-3">
            <label class="form-label">Items per page (lists)</label>
            <input type="number" min="5" max="100" name="items_per_page"
                   class="form-control" value="<?= e($values['items_per_page'] ?: '10') ?>">
            <div class="form-text">Used by user list, activity log, etc.</div>
          </div>

          <hr>
          <h6 class="mb-3"><i class="bi bi-currency-exchange"></i> E-commerce</h6>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label">Currency code</label>
              <input type="text" name="currency_code" maxlength="5"
                     class="form-control" value="<?= e($values['currency_code'] ?: 'USD') ?>">
              <div class="form-text">e.g. USD, EUR, GBP, PKR.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Currency symbol</label>
              <input type="text" name="currency_symbol" maxlength="5"
                     class="form-control" value="<?= e($values['currency_symbol'] ?: '$') ?>">
              <div class="form-text">e.g. $, €, £, ₨.</div>
            </div>
          </div>

          <button class="btn btn-primary"><i class="bi bi-check2"></i> Save settings</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card">
      <div class="card-body">
        <h6 class="card-title">Tips</h6>
        <ul class="small mb-0 ps-3">
          <li>The site name and logo appear in the sidebar and login screen.</li>
          <li>To change DB credentials, edit <code>config/config.php</code>.</li>
          <li>Delete <code>install.php</code> after first install.</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
