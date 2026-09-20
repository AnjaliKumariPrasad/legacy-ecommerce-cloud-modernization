<?php
/**
 * Storefront bootstrap.
 * Reuses the admin panel's config/DB and adds a few shop-specific helpers.
 */
require_once __DIR__ . '/functions.php';

/**
 * Build a URL relative to the storefront (project root).
 * The shop now lives at the root, so shop_url() and url() are equivalent.
 * Kept as a separate helper for readability in shop pages.
 */
function shop_url(string $path = ''): string
{
    return BASE_URL . ltrim($path, '/');
}

/** Active category list for the navbar. */
function shop_active_categories(): array
{
    static $cache = null;
    if ($cache === null) {
        try {
            $cache = db()->query(
                "SELECT id, name, slug FROM categories
                 WHERE status = 'active'
                 ORDER BY name ASC"
            )->fetchAll();
        } catch (Throwable $e) {
            $cache = [];
        }
    }
    return $cache;
}

/** Effective price for a product (sale_price if set, otherwise price). */
function product_effective_price(array $p): float
{
    if (isset($p['sale_price']) && $p['sale_price'] !== null
        && (float)$p['sale_price'] > 0) {
        return (float)$p['sale_price'];
    }
    return (float)($p['price'] ?? 0);
}

/** True if the product is on sale. */
function product_is_on_sale(array $p): bool
{
    return isset($p['sale_price']) && $p['sale_price'] !== null
        && (float)$p['sale_price'] > 0
        && (float)$p['sale_price'] < (float)($p['price'] ?? 0);
}

/** % off for a sale product. */
function product_discount_percent(array $p): int
{
    if (!product_is_on_sale($p)) return 0;
    $price = (float)$p['price'];
    $sale  = (float)$p['sale_price'];
    return (int) round((($price - $sale) / $price) * 100);
}

// ----------------------------------------------------------------
// Shopping cart (session-based)
// Cart shape: $_SESSION['cart'] = [productId => quantity, ...]
// ----------------------------------------------------------------

/** Add (or increment) a product to the cart. Returns the new quantity. */
function cart_add(int $productId, int $qty = 1): int
{
    if ($qty < 1) $qty = 1;
    $_SESSION['cart'] = $_SESSION['cart'] ?? [];
    $current = (int)($_SESSION['cart'][$productId] ?? 0);
    $_SESSION['cart'][$productId] = $current + $qty;
    return $_SESSION['cart'][$productId];
}

/** Set the quantity of a cart item. 0 removes the item. */
function cart_set(int $productId, int $qty): void
{
    $_SESSION['cart'] = $_SESSION['cart'] ?? [];
    if ($qty <= 0) {
        unset($_SESSION['cart'][$productId]);
    } else {
        $_SESSION['cart'][$productId] = $qty;
    }
}

/** Remove a single item from the cart. */
function cart_remove(int $productId): void
{
    unset($_SESSION['cart'][$productId]);
}

/** Wipe the cart. */
function cart_clear(): void
{
    unset($_SESSION['cart']);
}

/** Return the raw cart array (productId => qty), or empty array. */
function cart_raw(): array
{
    return $_SESSION['cart'] ?? [];
}

/** Total number of items in the cart (sum of quantities). */
function cart_count(): int
{
    return array_sum(cart_raw());
}

/**
 * Hydrate the cart with current product data from DB.
 * Returns: [
 *   'items'    => [['product' => row, 'qty' => n, 'unit_price' => x, 'line_total' => y], ...],
 *   'subtotal' => float,
 * ]
 *
 * Stale items (deleted/inactive products) are silently dropped from the cart.
 */
function cart_load(): array
{
    $cart = cart_raw();
    if (!$cart) return ['items' => [], 'subtotal' => 0.0];

    $ids = array_map('intval', array_keys($cart));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    try {
        $stmt = db()->prepare(
            "SELECT * FROM products
             WHERE id IN ($placeholders) AND status = 'active'"
        );
        $stmt->execute($ids);
        $rows = $stmt->fetchAll();
    } catch (Throwable $e) {
        return ['items' => [], 'subtotal' => 0.0];
    }

    $items    = [];
    $subtotal = 0.0;
    $byId     = [];
    foreach ($rows as $r) $byId[(int)$r['id']] = $r;

    foreach ($cart as $pid => $qty) {
        $pid = (int)$pid;
        $qty = (int)$qty;
        if (!isset($byId[$pid]) || $qty < 1) {
            unset($_SESSION['cart'][$pid]);
            continue;
        }
        $product   = $byId[$pid];
        $unitPrice = product_effective_price($product);
        $lineTotal = $unitPrice * $qty;
        $subtotal += $lineTotal;
        $items[] = [
            'product'    => $product,
            'qty'        => $qty,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
        ];
    }

    return ['items' => $items, 'subtotal' => $subtotal];
}

/** Generate a unique order number, e.g. ORD-202605-0007. */
function generate_order_number(): string
{
    $prefix = 'ORD-' . date('Ym') . '-';
    $stmt   = db()->prepare(
        "SELECT order_number FROM orders
         WHERE order_number LIKE :p
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([':p' => $prefix . '%']);
    $last = $stmt->fetchColumn();
    $nextNum = 1;
    if ($last && preg_match('/-(\d+)$/', $last, $m)) {
        $nextNum = (int)$m[1] + 1;
    }
    return $prefix . str_pad((string)$nextNum, 4, '0', STR_PAD_LEFT);
}
