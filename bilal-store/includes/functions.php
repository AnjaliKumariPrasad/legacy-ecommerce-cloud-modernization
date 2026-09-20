<?php
/**
 * Generic helper functions used throughout the panel.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/s3.php';

// ----------------------------------------------------------------
// Output / escaping
// ----------------------------------------------------------------
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . ltrim($path, '/');
}

function admin_url(string $path = ''): string
{
    return ADMIN_URL . ltrim($path, '/');
}

/**
 * Redirect to a path relative to the project root (BASE_URL).
 * For admin pages use admin_redirect() instead.
 */
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/** Redirect to a path relative to the admin panel (ADMIN_URL). */
function admin_redirect(string $path): void
{
    header('Location: ' . admin_url($path));
    exit;
}

// ----------------------------------------------------------------
// CSRF
// ----------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(?string $token): bool
{
    return !empty($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function require_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST'
        && !csrf_verify($_POST['_csrf'] ?? null)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }
}

// ----------------------------------------------------------------
// Flash messages
// ----------------------------------------------------------------
function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $messages;
}

// ----------------------------------------------------------------
// Validation
// ----------------------------------------------------------------
function input(string $key, $default = ''): string
{
    return trim((string)($_POST[$key] ?? $_GET[$key] ?? $default));
}

function old(string $key, $default = ''): string
{
    return e($_SESSION['_old'][$key] ?? $default);
}

function set_old(array $data): void
{
    $_SESSION['_old'] = $data;
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

// ----------------------------------------------------------------
// Settings (key/value table)
// ----------------------------------------------------------------
function setting(string $key, ?string $default = null): ?string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $rows = db()->query('SELECT key_name, value FROM settings')->fetchAll();
            foreach ($rows as $r) {
                $cache[$r['key_name']] = $r['value'];
            }
        } catch (Throwable $e) {
            // Settings table may not exist yet during install.
        }
    }
    return $cache[$key] ?? $default;
}

function update_setting(string $key, string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO settings (key_name, value) VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE value = VALUES(value)'
    );
    $stmt->execute([':k' => $key, ':v' => $value]);
}

// ----------------------------------------------------------------
// Activity log
// ----------------------------------------------------------------
function log_activity(string $action, string $description = ''): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO activity_log (user_id, action, description, ip_address)
             VALUES (:uid, :action, :desc, :ip)'
        );
        $stmt->execute([
            ':uid'    => $_SESSION['user']['id'] ?? null,
            ':action' => $action,
            ':desc'   => $description,
            ':ip'     => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $e) {
        // Silent — logging must never break the request.
    }
}

// ----------------------------------------------------------------
// Misc UI helpers
// ----------------------------------------------------------------
function active_if(bool $condition, string $class = 'active'): string
{
    return $condition ? $class : '';
}

function format_date(?string $datetime, string $format = 'M j, Y H:i'): string
{
    if (empty($datetime)) return '—';
    $ts = strtotime($datetime);
    return $ts ? date($format, $ts) : e($datetime);
}

function avatar_url(?string $avatar, string $name = ''): string
{
    if (!empty($avatar) && file_exists(UPLOADS_PATH . '/' . $avatar)) {
        return UPLOADS_URL . $avatar;
    }
    // Fallback: use ui-avatars.com (no key required).
    $initials = urlencode($name ?: 'User');
    return "https://ui-avatars.com/api/?name={$initials}&background=4f46e5&color=fff&size=128";
}

// ----------------------------------------------------------------
// E-commerce helpers
// ----------------------------------------------------------------

/** Convert a string to a URL-safe slug. */
function slugify(string $text): string
{
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT//IGNORE', $text) ?: $text;
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = strtolower(trim($text, '-'));
    $text = preg_replace('~-+~', '-', $text);
    return $text ?: 'n-a';
}

/** Make a slug unique within a table by appending -1, -2, ... if needed. */
function unique_slug(string $base, string $table, int $excludeId = 0): string
{
    $slug = $base;
    $i    = 1;
    $sql  = "SELECT id FROM `$table` WHERE slug = :s AND id <> :id LIMIT 1";
    while (true) {
        $stmt = db()->prepare($sql);
        $stmt->execute([':s' => $slug, ':id' => $excludeId]);
        if (!$stmt->fetch()) return $slug;
        $slug = $base . '-' . (++$i);
    }
}

/** Format a numeric price using the configured currency symbol. */
function price(?float $amount): string
{
    $symbol = setting('currency_symbol', '$');
    if ($amount === null) return '—';
    return $symbol . number_format((float)$amount, 2);
}

/**
 * Translate an uploaded-file error code into a human-readable message.
 * See https://www.php.net/manual/en/features.file-upload.errors.php
 */
function upload_error_message(int $code): string
{
    switch ($code) {
        case UPLOAD_ERR_OK:         return '';
        case UPLOAD_ERR_INI_SIZE:   return 'File is larger than the server allows (upload_max_filesize).';
        case UPLOAD_ERR_FORM_SIZE:  return 'File is larger than the form allows.';
        case UPLOAD_ERR_PARTIAL:    return 'File was only partially uploaded — please retry.';
        case UPLOAD_ERR_NO_FILE:    return 'No file was selected.';
        case UPLOAD_ERR_NO_TMP_DIR: return 'Server is missing a temporary upload folder.';
        case UPLOAD_ERR_CANT_WRITE: return 'Server could not write the file to disk.';
        case UPLOAD_ERR_EXTENSION:  return 'A PHP extension blocked the upload.';
    }
    return 'Unknown upload error.';
}

/** Return URL to product image (or a placeholder). */
function product_image_url(?string $image): string
{
    if (!empty($image) && file_exists(UPLOADS_PATH . '/products/' . $image)) {
        return UPLOADS_URL . 'products/' . $image;
    }
    // SVG placeholder.
    return 'data:image/svg+xml;utf8,' . rawurlencode(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">' .
        '<rect width="100" height="100" fill="#e5e7eb"/>' .
        '<text x="50" y="55" text-anchor="middle" fill="#9ca3af" ' .
        'font-family="sans-serif" font-size="12">No image</text></svg>'
    );
}
