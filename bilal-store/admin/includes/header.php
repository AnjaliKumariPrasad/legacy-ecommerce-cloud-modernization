<?php
/**
 * Top of every authenticated page.
 * Pages should set $pageTitle before including this file.
 */
require_once __DIR__ . '/auth.php';
require_login();

$pageTitle = $pageTitle ?? 'Dashboard';
$me        = current_user();
$siteName  = setting('site_name', APP_NAME);
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · <?= e($siteName) ?></title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<div class="app">

  <?php include __DIR__ . '/sidebar.php'; ?>
  <div class="sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>

  <main class="app-main">

    <!-- Top bar -->
    <nav class="topbar">
      <button class="btn btn-light btn-sm d-lg-none" id="sidebarToggle" type="button">
        <i class="bi bi-list"></i>
      </button>

      <h1 class="topbar-title"><?= e($pageTitle) ?></h1>

      <div class="ms-auto d-flex align-items-center gap-2">
        <button class="btn btn-light btn-sm" id="themeToggle" title="Toggle theme">
          <i class="bi bi-moon-stars"></i>
        </button>

        <div class="dropdown">
          <button class="btn btn-light btn-sm dropdown-toggle d-flex align-items-center gap-2"
                  data-bs-toggle="dropdown" aria-expanded="false">
            <img src="<?= e(avatar_url($me['avatar'] ?? null, $me['name'])) ?>"
                 class="rounded-circle" width="28" height="28" alt="">
            <span class="d-none d-sm-inline"><?= e($me['name']) ?></span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><h6 class="dropdown-header"><?= e($me['email']) ?></h6></li>
            <li><a class="dropdown-item" href="<?= e(admin_url('profile.php')) ?>">
              <i class="bi bi-person"></i> Profile</a></li>
            <?php if (has_role('admin')): ?>
              <li><a class="dropdown-item" href="<?= e(admin_url('settings.php')) ?>">
                <i class="bi bi-gear"></i> Settings</a></li>
            <?php endif; ?>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="<?= e(admin_url('logout.php')) ?>">
              <i class="bi bi-box-arrow-right"></i> Logout</a></li>
          </ul>
        </div>
      </div>
    </nav>

    <div class="app-content">
      <?php foreach (get_flashes() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
          <?= e($f['message']) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endforeach; ?>
