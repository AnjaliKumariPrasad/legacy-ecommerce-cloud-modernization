# Bilal Store — PHP E-commerce + Admin Panel

![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5-7952B3?logo=bootstrap&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-green.svg)

A small e-commerce site with a public storefront and a self-contained admin
panel, written in plain PHP 7.4+ on top of MySQL. No Composer, no framework,
no JS build step. Drop the folder into `htdocs`, run the installer, and you
have a working shop.

The codebase is deliberately flat — every page is a single PHP file you can
read top-to-bottom, which makes it easy to fork, customize, or use as a
learning reference. Around 5.2k lines of PHP and 1.2k lines of CSS at the
time of writing.

## Screenshots

Drop your screenshots into a `docs/screenshots/` folder and reference them
here. Suggested set:

```
docs/screenshots/
├── home.png
├── shop.png
├── product.png
├── cart.png
├── checkout.png
├── track-order.png
├── admin-dashboard.png
├── admin-orders.png
└── admin-products.png
```

Then add to this README:

```markdown
![Home](docs/screenshots/home.png)
![Admin dashboard](docs/screenshots/admin-dashboard.png)
```

## What's inside

**Public storefront** (root of the project)

- Landing page with hero, featured/latest products, categories, about,
  CTA banner, newsletter signup
- Product list with search, category filter, sort, pagination
- Product detail with related products
- Session-based shopping cart with quantity management and per-row removal
- Checkout that creates an order in a single DB transaction (also decrements
  stock atomically)
- Order confirmation page
- Order tracking page — lookup by order number + email

**Admin panel** (everything under `/admin/`)

- Login + role-based access (`admin`, `editor`)
- Dashboard: order stats, revenue, pending count, low-stock alerts,
  recent orders, recent activity, Chart.js 7-day trend
- Orders: filter by status / date / search, full detail view, inline
  status updates
- Products: image upload, SKU, regular + sale price, stock tracking,
  featured flag, status (active / draft / inactive)
- Categories: CRUD with auto-generated slugs
- Settings: currency, items per page, shipping fee, contact email,
  about copy
- Activity log
- Profile + password change
- Light/dark theme toggle (persisted in `localStorage`)

There's no payment gateway integration — checkout supports Cash on Delivery
and Bank Transfer. Adding Stripe / PayPal is a few lines (see *Extending*
below).

## Stack

| Layer    | Used                                                       |
| -------- | ---------------------------------------------------------- |
| Backend  | PHP 7.4+, PDO                                              |
| Database | MySQL 5.7+ / MariaDB 10.x                                  |
| Frontend | Bootstrap 5.3, Bootstrap Icons, Chart.js (all via CDN)     |
| Server   | Anything that runs PHP. Tested on XAMPP / Apache 2.4.      |

No Node, no Composer, no migrations framework. The "migrations" are a pair
of `.sql` files and two browser-runnable scripts (`install.php`,
`migrate.php`).

## Quick start

For XAMPP on macOS / Linux / Windows:

1. Clone the repo into your `htdocs` folder. The folder name becomes the
   URL prefix:

   ```
   /Applications/XAMPP/xamppfiles/htdocs/bilal-store/
   ```

2. Start Apache and MySQL from the XAMPP control panel.

3. If your MySQL `root` user has a password, edit `config/config.php`:

   ```php
   define('DB_HOST', '127.0.0.1');
   define('DB_PORT', '3306');
   define('DB_NAME', 'php_admin_panel');
   define('DB_USER', 'root');
   define('DB_PASS', 'your-password');   // empty string is fine for fresh XAMPP
   ```

4. Open the installer in a browser:

   ```
   http://localhost/bilal-store/admin/install.php
   ```

   Fill in admin name / email / password and submit. The installer creates
   the database, runs `sql/schema.sql`, and creates your admin user.

5. **Delete `admin/install.php`.** It's a one-time bootstrap script and
   shouldn't sit in production.

6. Run the e-commerce migration:

   ```
   http://localhost/bilal-store/admin/migrate.php
   ```

   Click *Apply migration*. This adds the `products`, `categories`,
   `orders`, and `order_items` tables. Delete the file when done.

7. You're up:

   - Storefront: `http://localhost/bilal-store/`
   - Admin: `http://localhost/bilal-store/admin/login.php`

If you skip step 6 the dashboard will show a warning and still load — it
just won't have any e-commerce data to show.

## Project layout

```
bilal-store/
├── .htaccess
├── README.md
├── CONTRIBUTING.md
├── LICENSE
│
├── index.php                    # Storefront landing
├── shop.php                     # Product list w/ filters
├── product.php                  # Product detail
├── category.php                 # Category page
├── cart.php                     # Shopping cart
├── checkout.php                 # Checkout form + order create
├── order_confirmation.php       # Thank-you page
├── track.php                    # Order tracking lookup
├── _product_card.php            # Reusable card partial
│
├── admin/
│   ├── index.php                # Redirects to login or dashboard
│   ├── login.php
│   ├── logout.php
│   ├── dashboard.php
│   ├── orders.php
│   ├── order_view.php
│   ├── products.php
│   ├── product_form.php
│   ├── categories.php
│   ├── category_form.php
│   ├── activity.php
│   ├── settings.php
│   ├── profile.php
│   ├── install.php              # Delete after first run
│   ├── migrate.php              # Delete after running migrations
│   └── includes/
│       ├── auth.php             # Login guard + role helpers
│       ├── header.php           # Admin shell (topbar + sidebar)
│       ├── sidebar.php
│       └── footer.php
│
├── includes/
│   ├── functions.php            # Shared: e(), price(), redirect(), CSRF, …
│   ├── shop_bootstrap.php       # Storefront helpers + cart functions
│   ├── shop_header.php
│   └── shop_footer.php
│
├── config/
│   ├── config.php               # DB creds + app constants
│   └── database.php             # PDO connection
│
├── sql/
│   ├── schema.sql               # Initial schema
│   └── migrations.sql           # Adds e-commerce tables (idempotent)
│
└── assets/
    ├── css/
    │   ├── style.css            # Admin styles
    │   └── shop.css             # Storefront styles
    ├── js/script.js             # Sidebar toggle + theme toggle
    └── uploads/                 # User uploads (avatars, product images)
        └── products/
```

## How it's wired

There's no router. Every URL maps directly to a `.php` file. Each page
generally follows the same pattern:

1. `require_once __DIR__ . '/config/config.php';`
   (this calls `session_start()` and connects to the DB via
   `config/database.php`)
2. PHP at the top — handle POST, load data
3. `include 'includes/.../header.php';`
4. The HTML markup
5. `include 'includes/.../footer.php';`

Storefront pages pull `includes/shop_bootstrap.php`, which sets up the
shop helpers (`shop_url()`, `cart_load()`, `product_effective_price()`,
`generate_order_number()`, …). Admin pages pull
`admin/includes/auth.php`, which calls `require_login()` /
`require_role(...)` and exposes `current_user()`.

**CSRF.** Every POST form embeds `<?= csrf_field() ?>` (a hidden `_csrf`
input). Every POST handler calls `require_csrf()` before doing anything.
The token regenerates on login.

**URLs.** Two helpers in `includes/functions.php`:

- `url('shop.php')` builds a URL relative to the project root.
- `admin_url('orders.php')` builds a URL relative to `/admin/`.

Same split for `redirect()` vs `admin_redirect()`. Use whichever matches
the page you're linking *to*.

**`BASE_URL` auto-detection.** `config/config.php` figures out the
project's URL prefix from `$_SERVER['SCRIPT_NAME']`, including when the
request is for `/admin/...`. That means the same config works whether the
project lives at `/bilal-store/` or at the document root.

## Settings

Settings live in a `settings` table as key/value rows and are edited from
admin → Settings. Defaults shipped:

| Key               | Purpose                                            |
| ----------------- | -------------------------------------------------- |
| `site_name`       | Navbar brand, page titles, transactional messages  |
| `site_about`      | Footer description, default meta description       |
| `site_email`      | Public contact email                               |
| `currency_code`   | ISO code (`USD`, `PKR`, …)                         |
| `currency_symbol` | What gets prefixed to prices (`$`, `Rs`, …)        |
| `shipping_fee`    | Flat fee added at checkout. Set `0.00` for free.   |
| `items_per_page`  | Pagination size for product/category/order lists   |

Read with `setting('shipping_fee', '0')`. Write through the settings page,
or directly via SQL.

## Default credentials

The first admin is whatever you entered into `install.php`. There's no
second admin and no UI to add more — we removed user management to keep
the surface area small for a single-merchant shop.

To reset a forgotten password:

```php
<?php
require __DIR__ . '/config/config.php';
$hash = password_hash('newpassword', PASSWORD_BCRYPT);
db()->prepare('UPDATE users SET password = ? WHERE email = ?')
    ->execute([$hash, 'admin@example.com']);
```

Or via SQL with a hash you've generated separately:

```sql
UPDATE users
SET password = '$2y$10$...replace-with-real-hash...'
WHERE email = 'admin@example.com';
```

## Extending the project

A few common things you might want to do:

**Add an admin page.** Create `admin/your_page.php`:

```php
<?php
require_once __DIR__ . '/includes/auth.php';
require_role('admin');           // or 'admin', 'editor'

$pageTitle = 'Your page';

// load data, handle POST, etc.

include __DIR__ . '/includes/header.php';
?>
<!-- your markup -->
<?php include __DIR__ . '/includes/footer.php'; ?>
```

Then add a link in `admin/includes/sidebar.php`.

**Add a storefront page.** Same idea but pull
`includes/shop_bootstrap.php` and the `shop_header.php` /
`shop_footer.php` includes. No auth call needed.

**Add a database table.** Append a `CREATE TABLE IF NOT EXISTS` block to
`sql/migrations.sql` (always use `IF NOT EXISTS` so re-running is safe),
then run `admin/migrate.php` from the browser.

**Plug in a real payment gateway.** The order is created in `checkout.php`
inside a transaction:

```php
$pdo->beginTransaction();
// INSERT INTO orders ...
// INSERT INTO order_items ...
// UPDATE products SET stock = stock - X ...
$pdo->commit();
```

Add your gateway call between the `INSERT`s and the `commit`. If the
gateway returns an error, throw and the rollback handles the rest.

**i18n.** Strings are inline. There's no gettext or translation table. If
you need multiple languages, the cleanest path is to wrap user-facing
text in a `t('...')` helper backed by a static array per locale.

## Common gotchas

**"Could not save image" on product upload.** On macOS XAMPP, Apache runs
as the `daemon` user, but `assets/uploads/` is probably owned by your
local user. Loosen permissions:

```bash
chmod -R 777 assets/uploads
```

We tried `755` first and it bit us, hence the wider permission.

**Migration won't run.** The script splits `sql/migrations.sql` on
semicolons and runs each statement separately. If it fails partway
through and you've fixed the SQL, re-run it — every statement uses
`IF NOT EXISTS` or `ON DUPLICATE KEY UPDATE`.

**`/admin/` returns 403.** Make sure `admin/index.php` exists. It's the
directory's entry point and redirects to `login.php` or `dashboard.php`
based on session state.

**Sessions expire mid-session.** `config/config.php` sets cookie params
and `session.gc_maxlifetime` to roughly two hours. Bump those values
higher if your shop has long browse times.

**Styling looks off.** Hard-refresh (Cmd-Shift-R / Ctrl-F5). The two CSS
files are not versioned and your browser caches them aggressively.

## Going to production

The project ships set up for local development. Before deploying:

1. Switch to a dedicated MySQL user (not `root`) with only the privileges
   this database actually needs.
2. In `config/config.php`, set `APP_ENV` to `'production'`. That hides
   PHP error output from visitors.
3. Serve over HTTPS. There are no HSTS headers built in — add them at
   the web-server level.
4. Confirm `install.php` and `migrate.php` are deleted from `admin/`.
5. If your host conflates document root with writable storage, move
   `assets/uploads/` to a writable, non-executable path and update
   `UPLOADS_PATH` in `config/config.php`.

## Contributing

PRs welcome. See [CONTRIBUTING.md](CONTRIBUTING.md) for the full guide.
TL;DR:

- One feature/fix per PR.
- Match existing style (4-space indent, single-quoted strings,
  `snake_case` for SQL columns).
- Test against PHP 7.4 *and* PHP 8.x.
- Don't add a build step or a framework dependency without opening an
  issue first.

Bug reports go in GitHub Issues with PHP version, MySQL version, browser,
and steps to reproduce.

## License

[MIT](LICENSE). Copy, modify, sell, sublicense — just keep the copyright
notice.
