# DB Lite Admin (Laravel)

A secure lightweight phpMyAdmin-style web app built with:

- Backend: Laravel 13 (PHP 8.3+)
- Frontend: Blade (HTML5), Bootstrap 5, Vanilla JavaScript
- Database: MySQL (supports multiple saved target DB connections)

## Core Features

- Session-based login (`email + password`)
- Protected routes after login
- Save multiple external MySQL connections
- Saved connection passwords are encrypted at rest
- Query console for `SELECT`, `INSERT/UPDATE/DELETE`, `CREATE/ALTER/DROP`
- Sidebar database explorer (database > tables > columns)
- Table data preview (pagination)
- Table operations:
  - Create table
  - Drop table with confirmation
  - Add/remove columns
- Query history
- Dark mode toggle
- Export table to CSV

## Security Features

- Laravel session authentication and CSRF protection
- Input validation for all API endpoints
- SQL identifier validation for table/column operations
- Optional dangerous-query guard in query console
- Encrypted DB passwords using Laravel encrypted cast

## Project Structure

- `frontend/` (frontend mapping notes)
- `backend/` (backend mapping notes)
- `routes/` (Laravel routes)
- `controllers/` (mapping notes to Laravel controllers)
- `models/` (mapping notes to Laravel models)
- `app/Http/Controllers/` (actual controller code)
- `app/Models/` (actual model code)
- `app/Services/` (dynamic DB connection service)
- `resources/views/` (Blade UI)
- `public/js/` and `public/css/` (Vanilla JS + styling)

## API Endpoints

- `POST /login`
- `POST /add-database`
- `GET /databases`
- `POST /connect`
- `POST /query`
- `GET /tables`
- `GET /columns`

Extra:

- `GET /query-history`
- `GET /table-data`
- `POST /tables/create`
- `DELETE /tables/{table}`
- `POST /tables/{table}/columns`
- `DELETE /tables/{table}/columns/{column}`
- `GET /tables/{table}/export`

## Local Setup (Step by Step)

1. Install requirements:
   - PHP 8.3+
   - Composer
   - MySQL 8+
2. Open the project folder:
   ```bash
   cd db-lite-admin
   ```
3. Install dependencies:
   ```bash
   composer install
   ```
4. Create environment file:
   ```bash
   copy .env.example .env
   ```
5. Set your app database in `.env` (this is the Laravel app DB, not target DBs):
   ```env
   APP_NAME="DB Lite Admin"
   APP_URL=http://127.0.0.1:8000

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=db_lite_admin
   DB_USERNAME=root
   DB_PASSWORD=
   ```
6. Generate key and run migrations + seed:
   ```bash
   php artisan key:generate
   php artisan migrate --seed
   ```
7. Start the app:
   ```bash
   php artisan serve
   ```
8. Open:
   - `http://127.0.0.1:8000/login`

## Default Login

- Email: `admin@example.com`
- Password: `password123`

Change this after first login in production environments.

## How External DB Connections Work

- You login to this app first.
- From sidebar, save target MySQL credentials.
- Click `Connect` for a saved connection.
- App stores active connection id in session and uses it for explorer/query/table operations.

## VPS Deployment Notes

- Set web server document root to Laravel `public/`
- Set `APP_ENV=production` and `APP_DEBUG=false`
- Use HTTPS and strong session/cookie settings
- Run:
  - `php artisan config:cache`
  - `php artisan route:cache`
  - `php artisan migrate --force`

## Testing

```bash
php artisan test
```
