# DB Lite Admin Laravel Package

This package provides a self-contained DB admin UI for Laravel applications.
It includes package routes, views, controllers, migrations, and a Blade nav link.

## Installation

1. Add the local package repository to your host application's `composer.json`:

```json
"repositories": [
  {
    "type": "path",
    "url": "./packages/db-lite-admin",
    "options": {
      "symlink": true
    }
  }
],
"require": {
  "db-lite-admin/db-lite-admin": "*"
}
```

2. Install the package:

```bash
composer require db-lite-admin/db-lite-admin:@dev
```

3. Publish package assets and config if needed:

```bash
php artisan vendor:publish --provider="DbLiteAdmin\DbLiteAdminServiceProvider" --tag="db-lite-admin-views"
php artisan vendor:publish --provider="DbLiteAdmin\DbLiteAdminServiceProvider" --tag="db-lite-admin-config"
```

4. Run the package migrations:

```bash
php artisan migrate
```

## Blade / Livewire Integration

To add a navigation link to the host app, include the package nav partial in your layout:

```blade
<ul class="navbar-nav">
    @include('db-lite-admin::nav.link')
</ul>
```

This renders a link to the DB Lite Admin dashboard route.

## Customization

You can customize the route prefix using `.env`:

```env
DB_LITE_ADMIN_ROUTE_PREFIX=db-lite-admin
```

After installation, visit `/db-lite-admin/dashboard` to access the package UI.
