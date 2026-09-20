<?php
require_once __DIR__ . '/shop_bootstrap.php';

$pageTitle = $pageTitle ?? '';
$siteName  = setting('site_name', APP_NAME);
$fullTitle = $pageTitle !== '' ? "$pageTitle · $siteName" : $siteName;
$cats      = shop_active_categories();

// Highlight current nav link.
$current = basename($_SERVER['PHP_SELF'] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($fullTitle) ?></title>
<meta name="description" content="<?= e(setting('site_about', 'Shop the latest products.')) ?>">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('assets/css/shop.css')) ?>">
</head>
<body>

<header class="site-header">
  <div class="container">
    <nav class="navbar navbar-expand-lg">
      <a href="<?= e(shop_url()) ?>" class="navbar-brand">
        <span class="brand-icon"><i class="bi bi-bag-fill"></i></span>
        <span class="brand-text"><?= e($siteName) ?></span>
      </a>

      <!-- Always-visible cart + mobile toggle (right side on small screens) -->
      <div class="d-flex align-items-center gap-2 order-lg-last">
        <a href="<?= e(shop_url('cart.php')) ?>" class="cart-link" title="Cart">
          <i class="bi bi-bag"></i>
          <?php $cnt = cart_count(); if ($cnt > 0): ?>
            <span class="cart-count"><?= $cnt ?></span>
          <?php endif; ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#mainNav" aria-controls="mainNav"
                aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
      </div>

      <div class="collapse navbar-collapse" id="mainNav">
        <ul class="navbar-nav mx-auto">
          <li class="nav-item">
            <a class="nav-link <?= $current === 'index.php' ? 'active' : '' ?>"
               href="<?= e(shop_url()) ?>">Home</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= $current === 'shop.php' ? 'active' : '' ?>"
               href="<?= e(shop_url('shop.php')) ?>">Shop</a>
          </li>
          <?php if ($cats): ?>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                Categories
              </a>
              <ul class="dropdown-menu">
                <?php foreach ($cats as $c): ?>
                  <li>
                    <a class="dropdown-item"
                       href="<?= e(shop_url('category.php?slug=' . urlencode($c['slug']))) ?>">
                      <?= e($c['name']) ?>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            </li>
          <?php endif; ?>
          <li class="nav-item">
            <a class="nav-link" href="<?= e(shop_url('index.php#about')) ?>">About</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= $current === 'track.php' ? 'active' : '' ?>"
               href="<?= e(shop_url('track.php')) ?>">Track order</a>
          </li>
        </ul>

        <form class="d-flex header-search" method="get" action="<?= e(shop_url('shop.php')) ?>">
          <div class="search-box">
            <i class="bi bi-search"></i>
            <input type="text" name="q" class="form-control"
                   placeholder="Search…"
                   value="<?= e($_GET['q'] ?? '') ?>">
          </div>
        </form>
      </div>
    </nav>
  </div>
</header>

<main class="site-main">
