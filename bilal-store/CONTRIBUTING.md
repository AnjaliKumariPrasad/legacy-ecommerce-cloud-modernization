# Contributing

Thanks for taking the time to look at this. The project is small enough
that a couple of conventions go a long way.

## Reporting bugs

Open a GitHub Issue with:

- PHP version (`php -v`)
- MySQL/MariaDB version
- Browser
- A copy of any error message (server log, browser console, or the
  on-screen "Something went wrong" page)
- Steps to reproduce, ideally starting from a fresh install

If the bug is security-sensitive (auth bypass, SQL injection, XSS, etc.)
please email the maintainer instead of opening a public issue.

## Suggesting features

Open an issue first. The project deliberately stays narrow — single
merchant, no payment gateway, no JS framework — so not every idea is a
fit. A short discussion saves everyone a wasted PR.

Things that are usually a yes:

- Bug fixes
- Accessibility improvements
- Better error messages
- Translations / i18n scaffolding
- A real payment gateway (as an optional adapter, not a hard dependency)

Things to discuss before building:

- Adding Composer or a build step
- Switching to a framework
- Multi-vendor / multi-tenant support
- A REST or GraphQL API

## Pull request checklist

Before opening a PR, please:

1. **One thing per PR.** "Add tax calculation" is fine. "Add tax + dark
   mode + refactor cart" should be three PRs.
2. **Test on PHP 7.4 *and* 8.x.** The codebase still targets 7.4.
3. **Run a quick lint sweep:**

   ```bash
   find . -name "*.php" -not -path "./vendor/*" \
     | xargs -n1 php -l
   ```

   No `Parse error` lines should come back.
4. **Check both pages render.** Open the storefront home and the admin
   dashboard. Loading without errors is a low bar but a real one.
5. **Update `README.md`** if you change a public URL, settings key,
   default credential, or folder layout.
6. **Update `sql/migrations.sql`** if you add or change a table. Use
   `CREATE TABLE IF NOT EXISTS` and `ALTER TABLE … IF NOT EXISTS`-style
   guards so re-running the migration stays safe.

## Code style

There's no `.editorconfig` or PHP-CS-Fixer config in the repo. Just
match what's already there:

- **Indent.** 4 spaces, no tabs.
- **Strings.** Single-quoted unless you need interpolation.
- **Arrays.** Short syntax `[]`.
- **Naming.** `snake_case` for variables, function names, and SQL
  columns. `CamelCase` for class names if you add any (currently there
  are none — the project is procedural).
- **PDO.** Always use named or positional placeholders. No string
  concatenation in queries, ever.
- **Output.** Always escape user-controlled data with `e()` (defined in
  `includes/functions.php`).
- **Forms.** Always include `<?= csrf_field() ?>` and call
  `require_csrf()` in the handler.
- **Don't bypass `redirect()` / `admin_redirect()`** by writing
  `header('Location: …')` by hand. The helpers prepend the right base
  URL and call `exit;` for you.

## File organization rule of thumb

- **Storefront pages** go in the project root (or a partial like
  `_product_card.php` if it's reusable).
- **Admin pages** go in `admin/`. Nothing in `admin/` should be
  reachable without `require_login()` or `require_role(...)`.
- **Shared helpers** go in `includes/functions.php`.
- **Storefront-only helpers** go in `includes/shop_bootstrap.php`.
- **Admin-only helpers** go in `admin/includes/auth.php` (or a new file
  in `admin/includes/`).

## Commit messages

Short imperative present-tense, like:

```
Fix stock decrement when checkout fails halfway
Add shipping fee setting + render on cart and checkout
Wrap order_view items table in .table-responsive
```

If the commit fixes an issue, reference it in the body:

```
Add per-row remove button on cart

The empty <td> in the cart table was supposed to hold a remove
button. Wired it up via name="remove_id" inside the update form.

Closes #14
```

## Local development tips

- The CSS files are not versioned. After editing `assets/css/*.css`,
  hard-refresh in the browser (Cmd-Shift-R / Ctrl-F5). Otherwise you'll
  spend ten minutes debugging a "bug" that's just the browser cache.
- `admin/install.php` and `admin/migrate.php` are intended to be deleted
  in production. Keep them around in your dev clone.
- The activity log is informative when something silently fails. Open
  admin → Activity log to see what the last few requests did.

## License

By contributing you agree your code will be released under the project's
[MIT license](LICENSE).
