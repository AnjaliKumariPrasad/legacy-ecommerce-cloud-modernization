<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    admin_redirect('dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $email    = trim($_POST['email']    ?? '');
    $password = (string)($_POST['password'] ?? '');

    if (!$email || !$password) {
        $errors[] = 'Email and password are required.';
    } elseif (!attempt_login($email, $password)) {
        $errors[] = 'Invalid credentials, or your account is disabled.';
    } else {
        flash('success', 'Welcome back, ' . current_user()['name'] . '!');
        admin_redirect('dashboard.php');
    }
    set_old(['email' => $email]);
}

$siteName = setting('site_name', APP_NAME);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login · <?= e($siteName) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<div class="auth-wrapper">
  <div class="card auth-card">
    <div class="card-body">

      <div class="auth-brand">
        <div class="brand-icon"><i class="bi bi-shield-lock-fill"></i></div>
        <h4 class="mb-0"><?= e($siteName) ?></h4>
        <p class="text-muted small mb-0">Sign in to your account</p>
      </div>

      <?php foreach (get_flashes() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?> py-2"><?= e($f['message']) ?></div>
      <?php endforeach; ?>

      <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger py-2"><?= e($err) ?></div>
      <?php endforeach; ?>

      <form method="post" autocomplete="off" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label">Email address</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" name="email" class="form-control"
                   value="<?= old('email') ?>" required autofocus>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-key"></i></span>
            <input type="password" name="password" class="form-control" required>
          </div>
        </div>
        <button class="btn btn-primary w-100">
          <i class="bi bi-box-arrow-in-right"></i> Sign in
        </button>
      </form>

    </div>
  </div>
</div>
<?php clear_old(); ?>
</body>
</html>
