# NIR360

PHP 8 + MySQL web app: Landing page and modal-based authentication (Create Account, Log In, OTP). RBAC (civilian, authority, admin), profile setup, government ID upload, OCR placeholder, admin verification fallback.

## Requirements

- PHP 8+
- MySQL 5.7+ / 8
- Apache with mod_rewrite (or equivalent)

## Setup

1. **Database**
   - Create DB and tables: `mysql -u root -p < sql/schema.sql`
   - Or run `sql/schema.sql` in your MySQL client.

2. **Config**
   - Copy config or set env: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
   - Optional: `APP_ENV=local` (enables dev OTP in response and logs), `APP_DEBUG=1`

3. **Document root**
   - Point document root to `public/` (e.g. `DocumentRoot /path/to/nir360/public`).
   - Or open `http://localhost/path/to/nir360/public/` and ensure `.htaccess` is allowed (AllowOverride All).

4. **Rewrite**
   - `.htaccess` in `public/` sends all non-file requests to `index.php`. Adjust `RewriteBase` if your app is in a subpath (e.g. `/nir360/public/`).

## Run

- Open landing: `GET /` (or `http://localhost/nir360/public/`).
- Create Account → OTP modal (DEV: OTP in response when `APP_ENV=local`).
- Log In → redirect to `/civilian/dashboard`, `/authority/dashboard`, or `/admin/dashboard` by role.

## Default roles

- New registrations get role `civilian`. Change role in DB to `authority` or `admin` to test dashboards.

## Requirement-to-code map

See **REQUIREMENTS.md** for the full requirement-to-file/function checklist.
