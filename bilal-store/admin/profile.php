<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'My Profile';
$me        = current_user();
$errors    = [];
$pwErrors  = [];

// Load full record.
$stmt = db()->prepare('SELECT * FROM users WHERE id = :id');
$stmt->execute([':id' => $me['id']]);
$user = $stmt->fetch();

// ----- Profile update -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'profile') {
    require_csrf();

    $name  = trim($_POST['name']  ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $bio   = trim($_POST['bio']   ?? '');

    if ($name === '')                                  $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))    $errors[] = 'Valid email required.';

    if (!$errors) {
        $stmt = db()->prepare('SELECT id FROM users WHERE email = :e AND id <> :id');
        $stmt->execute([':e' => $email, ':id' => $user['id']]);
        if ($stmt->fetch()) $errors[] = 'That email is already in use.';
    }

    // Avatar upload (optional).
    $avatarFile = $user['avatar'];
    $uploadErr  = $_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE;
    $hasUpload  = !empty($_FILES['avatar']['name']) && $uploadErr !== UPLOAD_ERR_NO_FILE;

    if ($hasUpload) {
        if ($uploadErr !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload failed: ' . upload_error_message($uploadErr);
        } else {
            $tmp  = $_FILES['avatar']['tmp_name'];
            $size = (int) $_FILES['avatar']['size'];
            $mime = function_exists('mime_content_type') ? mime_content_type($tmp) : null;
            $ok   = ['image/jpeg' => 'jpg', 'image/png' => 'png',
                     'image/gif'  => 'gif', 'image/webp' => 'webp'];

            if (!isset($ok[$mime])) {
                $errors[] = 'Avatar must be JPG, PNG, GIF or WEBP (got: ' . e((string)$mime) . ').';
            } elseif ($size > 2 * 1024 * 1024) {
                $errors[] = 'Avatar must be smaller than 2 MB.';
            } else {
                if (!is_dir(UPLOADS_PATH) && !@mkdir(UPLOADS_PATH, 0775, true)) {
                    $errors[] = 'Upload folder could not be created: ' . e(UPLOADS_PATH);
                } elseif (!is_writable(UPLOADS_PATH)) {
                    $errors[] = 'Upload folder is not writable by the web server: '
                              . e(UPLOADS_PATH) . ' — try: chmod 775 (or 777) on that folder.';
                } else {
                    $newName = 'avatar_' . $user['id'] . '_' . time() . '.' . $ok[$mime];
                    if (move_uploaded_file($tmp, UPLOADS_PATH . '/' . $newName)) {
                        if ($user['avatar'] && file_exists(UPLOADS_PATH . '/' . $user['avatar'])) {
                            @unlink(UPLOADS_PATH . '/' . $user['avatar']);
                        }
                        $avatarFile = $newName;
                    } else {
                        $errors[] = 'Could not save avatar. Last PHP error: '
                                  . e(error_get_last()['message'] ?? 'unknown');
                    }
                }
            }
        }
    }

    if (!$errors) {
        db()->prepare(
            'UPDATE users SET name = :n, email = :e, phone = :ph, bio = :b, avatar = :av
             WHERE id = :id'
        )->execute([
            ':n'  => $name, ':e' => $email, ':ph' => $phone,
            ':b'  => $bio,  ':av' => $avatarFile,
            ':id' => $user['id'],
        ]);
        log_activity('profile.update', 'Updated own profile');
        refresh_user_session();
        flash('success', 'Profile updated.');
        admin_redirect('profile.php');
    }

    $user = array_merge($user, compact('name', 'email', 'phone', 'bio'));
}

// ----- Password change -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'password') {
    require_csrf();

    $current = (string)($_POST['current_password'] ?? '');
    $new     = (string)($_POST['new_password']     ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if (!password_verify($current, $user['password'])) {
        $pwErrors[] = 'Current password is incorrect.';
    }
    if (strlen($new) < 6)        $pwErrors[] = 'New password must be at least 6 characters.';
    if ($new !== $confirm)       $pwErrors[] = 'New passwords do not match.';

    if (!$pwErrors) {
        db()->prepare('UPDATE users SET password = :p WHERE id = :id')->execute([
            ':p'  => password_hash($new, PASSWORD_BCRYPT),
            ':id' => $user['id'],
        ]);
        log_activity('password.change', 'Changed own password');
        flash('success', 'Password updated.');
        admin_redirect('profile.php');
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="row g-3">
  <!-- Profile card -->
  <div class="col-lg-4">
    <div class="card">
      <div class="card-body text-center">
        <img src="<?= e(avatar_url($user['avatar'], $user['name'])) ?>"
             class="rounded-circle mb-3" width="120" height="120" alt="">
        <h5 class="mb-0"><?= e($user['name']) ?></h5>
        <p class="text-muted small mb-2"><?= e($user['email']) ?></p>
        <span class="badge role-<?= e($user['role']) ?>"><?= e($user['role']) ?></span>
        <span class="badge status-<?= e($user['status']) ?>"><?= e($user['status']) ?></span>
        <hr>
        <div class="text-start small">
          <div><strong>Phone:</strong> <?= e($user['phone'] ?: '—') ?></div>
          <div><strong>Joined:</strong> <?= e(format_date($user['created_at'])) ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Forms -->
  <div class="col-lg-8">
    <div class="card mb-3">
      <div class="card-body">
        <h5 class="card-title">Profile information</h5>

        <?php foreach ($errors as $err): ?>
          <div class="alert alert-danger py-2"><?= e($err) ?></div>
        <?php endforeach; ?>

        <form method="post" enctype="multipart/form-data" autocomplete="off">
          <?= csrf_field() ?>
          <input type="hidden" name="form" value="profile">

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Name</label>
              <input type="text" name="name" class="form-control"
                     value="<?= e($user['name']) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control"
                     value="<?= e($user['email']) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone</label>
              <input type="text" name="phone" class="form-control"
                     value="<?= e($user['phone'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Avatar (JPG/PNG, ≤ 2 MB)</label>
              <input type="file" name="avatar" class="form-control" accept="image/*">
            </div>
            <div class="col-12">
              <label class="form-label">Bio</label>
              <textarea name="bio" class="form-control" rows="3"><?= e($user['bio'] ?? '') ?></textarea>
            </div>
          </div>

          <div class="mt-3">
            <button class="btn btn-primary"><i class="bi bi-check2"></i> Save changes</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <h5 class="card-title">Change password</h5>

        <?php foreach ($pwErrors as $err): ?>
          <div class="alert alert-danger py-2"><?= e($err) ?></div>
        <?php endforeach; ?>

        <form method="post" autocomplete="off">
          <?= csrf_field() ?>
          <input type="hidden" name="form" value="password">

          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Current password</label>
              <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">New password</label>
              <input type="password" name="new_password" class="form-control" minlength="6" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Confirm new password</label>
              <input type="password" name="confirm_password" class="form-control" minlength="6" required>
            </div>
          </div>

          <div class="mt-3">
            <button class="btn btn-primary"><i class="bi bi-shield-lock"></i> Update password</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
