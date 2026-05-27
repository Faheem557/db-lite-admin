# DB Lite Admin Laravel Package

A Laravel package that provides a database administration UI with Blade views, authentication, and a navigation link for easy integration.

## What this repository provides

- Package source under `packages/db-lite-admin/src`
- Blade package views under `packages/db-lite-admin/resources/views`
- Package routes under `packages/db-lite-admin/src/routes/web.php`
- Package migrations under `packages/db-lite-admin/database/migrations`
- A service provider at `packages/db-lite-admin/src/DbLiteAdminServiceProvider.php`
- A package nav partial at `packages/db-lite-admin/resources/views/nav/link.blade.php`

## Install via Composer

This package is ready for Packagist-style installation once the repository is registered there.

After Packagist registration, install it with:

```bash
composer require faheem557/db-lite-admin:dev-main
```

If you publish a tagged release like `v1.0.0`, you can install it with:

```bash
composer require faheem557/db-lite-admin:^1.0
```

If the package is not yet on Packagist, you must register the GitHub repository on Packagist first.

## Use in Blade or Livewire

In your host app layout, add the package nav link:

```blade
<ul class="navbar-nav">
    @include('db-lite-admin::nav.link')
</ul>
```

This will render a link to the package dashboard at:

- `/db-lite-admin/dashboard`

## Publish views and config (optional)

```bash
php artisan vendor:publish --provider="DbLiteAdmin\DbLiteAdminServiceProvider" --tag="db-lite-admin-views"
php artisan vendor:publish --provider="DbLiteAdmin\DbLiteAdminServiceProvider" --tag="db-lite-admin-config"
```

## Route prefix

The package route prefix is configurable in `.env`:

```env
DB_LITE_ADMIN_ROUTE_PREFIX=db-lite-admin
```

## Notes

- The package is bootstrapped via `DbLiteAdmin\DbLiteAdminServiceProvider`
- The GitHub repo root is now package root for composer installation
- Use `dev-main` until a tagged release is created
