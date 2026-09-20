<?php
require_once __DIR__ . '/includes/auth.php';
require_role('admin', 'editor');

$id        = (int)($_GET['id'] ?? 0);
$pageTitle = $id ? 'Edit product' : 'Add product';
$errors    = [];

$product = [
    'id' => 0, 'category_id' => null, 'name' => '', 'slug' => '', 'sku' => '',
    'short_description' => '', 'description' => '',
    'price' => '0.00', 'sale_price' => '', 'stock' => '0',
    'image' => null, 'status' => 'active', 'featured' => 0,
];
if ($id) {
    $stmt = db()->prepare('SELECT * FROM products WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if (!($product = $stmt->fetch())) {
        flash('warning', 'Product not found.');
        admin_redirect('products.php');
    }
}

$categories = db()->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $name              = trim($_POST['name']              ?? '');
    $slug              = trim($_POST['slug']              ?? '');
    $sku               = trim($_POST['sku']               ?? '');
    $categoryId        = (int)($_POST['category_id']      ?? 0);
    $price             = (string)($_POST['price']         ?? '0');
    $salePrice         = trim((string)($_POST['sale_price'] ?? ''));
    $stock             = (int)($_POST['stock']            ?? 0);
    $shortDescription  = trim($_POST['short_description'] ?? '');
    $description       = trim($_POST['description']       ?? '');
    $status            = $_POST['status']                 ?? 'active';
    $featured          = isset($_POST['featured']) ? 1 : 0;

    // Validation
    if ($name === '')                              $errors[] = 'Name is required.';
    if (!is_numeric($price) || (float)$price < 0)  $errors[] = 'Price must be a non-negative number.';
    if ($salePrice !== '' && (!is_numeric($salePrice) || (float)$salePrice < 0)) {
        $errors[] = 'Sale price must be a non-negative number (or blank).';
    }
    if ($salePrice !== '' && is_numeric($salePrice)
        && is_numeric($price) && (float)$salePrice >= (float)$price) {
        $errors[] = 'Sale price must be less than the regular price.';
    }
    if (!in_array($status, ['active', 'inactive', 'draft'], true)) $status = 'active';

    // SKU uniqueness (only if provided)
    if ($sku !== '') {
        $stmt = db()->prepare('SELECT id FROM products WHERE sku = :s AND id <> :id');
        $stmt->execute([':s' => $sku, ':id' => $id]);
        if ($stmt->fetch()) $errors[] = 'That SKU is already used by another product.';
    }

    // Image upload (optional)
    $imageFile  = $product['image'] ?? null;
    $uploadErr  = $_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE;
    $hasUpload  = !empty($_FILES['image']['name']) && $uploadErr !== UPLOAD_ERR_NO_FILE;

    if ($hasUpload) {
        if ($uploadErr !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload failed: ' . upload_error_message($uploadErr);
        } else {
            $tmp  = $_FILES['image']['tmp_name'];
            $size = (int) $_FILES['image']['size'];
            $mime = function_exists('mime_content_type') ? mime_content_type($tmp) : null;
            $ok   = ['image/jpeg' => 'jpg', 'image/png' => 'png',
                     'image/gif'  => 'gif', 'image/webp' => 'webp'];

            if (!isset($ok[$mime])) {
                $errors[] = 'Image must be JPG, PNG, GIF or WEBP (got: ' . e((string)$mime) . ').';
            } elseif ($size > 4 * 1024 * 1024) {
                $errors[] = 'Image must be smaller than 4 MB.';
            } else {
                $dir = UPLOADS_PATH . '/products';
                if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
                    $errors[] = 'Upload folder does not exist and could not be created: '
                              . e($dir);
                } elseif (!is_writable($dir)) {
                    $errors[] = 'Upload folder is not writable by the web server: '
                              . e($dir) . ' — try: chmod 775 (or 777) on that folder.';
                } else {
                    $newName = 'p_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ok[$mime];
                    if (move_uploaded_file($tmp, $dir . '/' . $newName)) {
                        if ($imageFile && file_exists($dir . '/' . $imageFile)) {
                            @unlink($dir . '/' . $imageFile);
                        }
                        $imageFile = $newName;
                    } else {
                        $errors[] = 'Could not save the uploaded file. '
                                  . 'Last PHP error: '
                                  . e(error_get_last()['message'] ?? 'unknown');
                    }
                }
            }
        }
    }

    if (!$errors) {
        $finalSlug = $slug !== '' ? slugify($slug) : slugify($name);
        $finalSlug = unique_slug($finalSlug, 'products', $id);

        $data = [
            ':cid'   => $categoryId > 0 ? $categoryId : null,
            ':name'  => $name,
            ':slug'  => $finalSlug,
            ':sku'   => $sku !== '' ? $sku : null,
            ':sd'    => $shortDescription !== '' ? $shortDescription : null,
            ':d'     => $description       !== '' ? $description       : null,
            ':price' => number_format((float)$price, 2, '.', ''),
            ':sp'    => $salePrice !== '' ? number_format((float)$salePrice, 2, '.', '') : null,
            ':stock' => $stock,
            ':img'   => $imageFile,
            ':st'    => $status,
            ':feat'  => $featured,
        ];

        if ($id) {
            $data[':id'] = $id;
            db()->prepare(
                'UPDATE products SET category_id=:cid, name=:name, slug=:slug, sku=:sku,
                    short_description=:sd, description=:d, price=:price, sale_price=:sp,
                    stock=:stock, image=:img, status=:st, featured=:feat
                 WHERE id=:id'
            )->execute($data);
            log_activity('product.update', 'Updated product ' . $name);
            flash('success', 'Product updated.');
        } else {
            db()->prepare(
                'INSERT INTO products
                    (category_id, name, slug, sku, short_description, description,
                     price, sale_price, stock, image, status, featured)
                 VALUES
                    (:cid, :name, :slug, :sku, :sd, :d,
                     :price, :sp, :stock, :img, :st, :feat)'
            )->execute($data);
            log_activity('product.create', 'Created product ' . $name);
            flash('success', 'Product created.');
        }

        admin_redirect('products.php');
    }

    $product = array_merge($product, [
        'name' => $name, 'slug' => $slug, 'sku' => $sku,
        'category_id' => $categoryId ?: null,
        'short_description' => $shortDescription, 'description' => $description,
        'price' => $price, 'sale_price' => $salePrice, 'stock' => $stock,
        'status' => $status, 'featured' => $featured, 'image' => $imageFile,
    ]);
}

include __DIR__ . '/includes/header.php';
?>

<form method="post" enctype="multipart/form-data" autocomplete="off" novalidate>
  <?= csrf_field() ?>

  <?php foreach ($errors as $err): ?>
    <div class="alert alert-danger py-2"><?= e($err) ?></div>
  <?php endforeach; ?>

  <div class="row g-3">

    <!-- Main column -->
    <div class="col-lg-8">
      <div class="card mb-3">
        <div class="card-body">
          <h5 class="card-title mb-3">Product details</h5>

          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">Name <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control"
                     value="<?= e($product['name']) ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">SKU</label>
              <input type="text" name="sku" class="form-control"
                     value="<?= e($product['sku'] ?? '') ?>" placeholder="e.g. T-SHIRT-001">
            </div>
            <div class="col-md-8">
              <label class="form-label">Slug <small class="text-muted">(auto if blank)</small></label>
              <input type="text" name="slug" class="form-control"
                     value="<?= e($product['slug']) ?>" placeholder="auto-generated">
            </div>
            <div class="col-md-4">
              <label class="form-label">Category</label>
              <select name="category_id" class="form-select">
                <option value="0">— None —</option>
                <?php foreach ($categories as $c): ?>
                  <option value="<?= (int)$c['id'] ?>"
                          <?= (int)($product['category_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>>
                    <?= e($c['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Short description</label>
              <textarea name="short_description" class="form-control" rows="2"
                        maxlength="500"><?= e($product['short_description'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Full description</label>
              <textarea name="description" class="form-control" rows="6"><?= e($product['description'] ?? '') ?></textarea>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <h5 class="card-title mb-3">Pricing &amp; stock</h5>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Price (<?= e(setting('currency_symbol', '$')) ?>) <span class="text-danger">*</span></label>
              <input type="number" step="0.01" min="0" name="price" class="form-control"
                     value="<?= e($product['price']) ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Sale price <small class="text-muted">(optional)</small></label>
              <input type="number" step="0.01" min="0" name="sale_price" class="form-control"
                     value="<?= e($product['sale_price'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Stock quantity</label>
              <input type="number" step="1" min="0" name="stock" class="form-control"
                     value="<?= e((string)($product['stock'] ?? 0)) ?>">
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Sidebar -->
    <div class="col-lg-4">
      <div class="card mb-3">
        <div class="card-body">
          <h6 class="card-title">Status</h6>
          <select name="status" class="form-select mb-3">
            <?php foreach (['active', 'draft', 'inactive'] as $s): ?>
              <option value="<?= $s ?>" <?= ($product['status'] ?? 'active') === $s ? 'selected' : '' ?>>
                <?= ucfirst($s) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="form-check">
            <input type="checkbox" name="featured" id="featured"
                   class="form-check-input" value="1"
                   <?= ((int)($product['featured'] ?? 0) === 1) ? 'checked' : '' ?>>
            <label for="featured" class="form-check-label">
              <i class="bi bi-star-fill text-warning"></i> Featured product
            </label>
          </div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-body">
          <h6 class="card-title">Image</h6>
          <div class="text-center mb-2">
            <img src="<?= e(product_image_url($product['image'] ?? null)) ?>"
                 class="img-fluid rounded border" style="max-height:200px"
                 alt="Product image preview">
          </div>
          <input type="file" name="image" class="form-control" accept="image/*">
          <small class="text-muted">JPG / PNG / GIF / WEBP, ≤ 4 MB.</small>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <button class="btn btn-primary w-100 mb-2">
            <i class="bi bi-check2"></i>
            <?= $id ? 'Save changes' : 'Create product' ?>
          </button>
          <a href="<?= e(admin_url('products.php')) ?>" class="btn btn-light w-100 mb-2">Cancel</a>
          <?php if ($id && !empty($product['slug'])): ?>
            <a href="<?= e(url('product.php?slug=' . urlencode($product['slug']))) ?>"
               target="_blank" class="btn btn-outline-secondary w-100">
              <i class="bi bi-box-arrow-up-right"></i> View on store
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</form>

<?php include __DIR__ . '/includes/footer.php'; ?>
