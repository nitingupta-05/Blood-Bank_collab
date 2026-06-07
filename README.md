# RedPulse — Smart Blood Bank Management System

A modern, zero-dependency plain-PHP application for blood inventory management,
donor coordination, and emergency blood requests. Runs on any stock XAMPP
install — no Composer, no framework, no Node toolchain.

---

## Table of Contents

1. [Features](#features)
2. [Quick Start (5 minutes)](#quick-start-5-minutes)
3. [Test Login Credentials](#test-login-credentials)
4. [How It's Wired](#how-its-wired)
5. [Project Structure](#project-structure)
6. [User Roles](#user-roles)
7. [API Reference](#api-reference)
8. [Configuration](#configuration)
9. [Database Schema](#database-schema)
10. [Security Notes](#security-notes)
11. [Troubleshooting](#troubleshooting)

---

## Features

- **Authentication** — bcrypt + 30-minute session timeout + login lockout
  (5 attempts → 15-min lockout via `login_attempts` table).
- **Blood Inventory** — add / list / update-status / delete blood units, with
  automatic barcode generation (`BB + YYYYMMDD + 4-digit random`).
- **Smart Blood Search** — search by group + city, optionally sorted by
  Haversine distance from the requester's lat/lon. Returns matching banks
  *and* matching eligible donors in one call.
- **Emergencies** — public can post; the system notifies eligible donors in
  the same city and all admin/staff users. TTL is automatic (24h critical,
  36h moderate, 48h low). Expired rows are auto-marked on every request via
  `BackgroundJobs::runAll()`.
- **Bookings** — hospitals reserve blood with required-date and patient name;
  admins approve / reject and the hospital user is notified.
- **Donor Management** — eligibility auto-refreshes on every request
  (age / weight / 56-day cooldown rules).
- **Storage Monitoring** — temperature log per storage area, with
  out-of-range alerts to admins (allowed 2–6 °C).
- **Notifications** — in-app notifications always persisted to the
  `notifications` table; optionally also emailed via PHP's `mail()`. SMS is
  a no-op by default.
- **Reports & CSV export** — inventory / donors / emergencies / bookings
  with blood-group and status filters.
- **AI Insights** — donation trends + blood-group distribution pulled from
  Gemini (if `GEMINI_API_KEY` is set) with a 24-hour file cache. Falls back
  to clearly-labeled national-average estimates offline.
- **Audit Logging** — every write action appends a row to `audit_logs` with
  the user id, action, affected record, details, and IP.
- **SSE Notifications** — `public/sse/notifications.php` streams new
  notifications to the browser in real time.

---

## Quick Start (5 minutes)

### 1. Start XAMPP
Open XAMPP Control Panel → start **Apache** and **MySQL**.

### 2. Drop the project into `htdocs`
The project root must live at:
```
C:\xampp\htdocs\blood-bank-system\
```
If you cloned it elsewhere, set `APP_URL` in `config/app.php` accordingly.

### 3. Create the database
Open `http://localhost/phpmyadmin`, click **New**, create database:
```
blood_bank_system
```
Collation: `utf8mb4_unicode_ci`. (Empty database is fine — the seeder
populates everything.)

### 4. Seed the database
Two ways — pick one:

**(a) CLI (recommended):**
```bash
& "C:\xampp\php\php.exe" public/seed-data.php
```
> If your XAMPP PHP is on the PATH you can just use `php public/seed-data.php`.

**(b) Browser:**
Visit `http://localhost/blood-bank-system/public/seed-data.php`.

The seeder drops & recreates all 12 tables from
`database/migrations/001_create_tables.sql` and inserts 8 known accounts,
3 blood banks, 24 storage areas, 3 donors, and 48 blood units.

### 5. Open the app
```
http://localhost/blood-bank-system/public/
```

You will land on the public home page. Click **Login** to sign in.

---

## Test Login Credentials

All seeded accounts use the same password: **`Password123`**

| Role               | Email                         | Display name  |
|--------------------|-------------------------------|---------------|
| Super Admin        | `super_admin@example.com`     | Super Admin   |
| Blood Bank Admin   | `admin@centralbb.org`         | Aarav Sharma  |
| Hospital           | `city@metrohosp.org`          | Metro Hospital|
| Staff              | `staff@centralbb.org`         | Kavita Joshi  |
| Donor              | `ramesh@example.com`          | Ramesh Kumar  |
| Donor              | `priya@example.com`           | Priya Verma   |
| Donor              | `suresh@example.com`          | Suresh Patil  |
| Patient            | `patient@example.com`         | Anil Mehta    |

---

## How It's Wired

```
Browser
  ↓ ?route=…
public/index.php          ← web front controller (HTML responses)
  ↓
bootstrap/app.php        ← session, autoload, config, security headers
  ↓
routes/web.php           ← [method, name, handler] table
  ↓
routes/dispatcher.php    ← matches + enforces roles → controller method
  ↓
app/controllers/*.php    ← Auth, Page, Bank, Donor, … (all use App globals $pdo)

Browser  ←fetch()→ public/api.php
                            ↓
                       routes/api.php        ← [route, method, handler, opts]
                            ↓
                       routes/api_dispatcher.php
                            ↓
                       app/controllers/ApiController.php
                            ↓
                       other controllers
```

`BackgroundJobs::runAll($pdo)` runs at the top of every web/api request to
expire stale emergencies, mark expired blood units, and refresh donor
eligibility. Safe to call repeatedly (idempotent, fails-soft).

---

## Project Structure

```
blood-bank-system/
├── public/                      ← web root (point Apache here)
│   ├── index.php                ← thin front controller
│   ├── api.php                  ← thin API front controller
│   ├── seed-data.php            ← CLI: wipe + migrate + seed
│   ├── .htaccess                ← deny app/config/routes, security headers
│   ├── css/
│   ├── js/
│   ├── sse/notifications.php
│   └── uploads/                 ← user uploads (.gitkeep)
├── bootstrap/
│   └── app.php                  ← session, autoloader, config loader
├── config/
│   ├── app.php                  ← constants, security headers fn
│   ├── database.php             ← db_connect() factory
│   └── csrf.php                 ← generate_csrf_token(), require_csrf()
├── routes/
│   ├── web.php                  ← web route table (returns array)
│   ├── dispatcher.php           ← dispatch_web_route() + view() helper
│   ├── api.php                  ← API route table
│   └── api_dispatcher.php       ← dispatch_api_route() + pattern matcher
├── app/
│   ├── controllers/             ← Auth, Page, Api, BloodUnit, Donor, …
│   ├── models/                  ← User, BloodUnit, Donor, BloodBank, …
│   ├── helpers/                 ← Auth, Validation, Date, Location, AI, CSV
│   ├── middleware/              ← RoleMiddleware, ApiHelper
│   ├── services/                ← NotificationDispatcher, BackgroundJobs, …
│   └── views/                   ← HTML templates
├── database/
│   └── migrations/
│       └── 001_create_tables.sql   ← single unified migration
├── storage/
│   ├── logs/                    ← php_errors.log
│   ├── reports/                 ← CSV exports
│   └── cache/                   ← AI insights cache
├── scripts/
│   └── smoke.php                ← CLI end-to-end API test
└── README.md
```

No `vendor/`, no `composer.json`, no `package.json`, no `node_modules/`.
Everything is plain PHP + CDN-loaded Bootstrap 5 + Chart.js.

---

## User Roles

| Role               | Can do                                                      |
|--------------------|-------------------------------------------------------------|
| `super_admin`      | Everything. Sees data across all blood banks.               |
| `blood_bank_admin` | Manage inventory, storage, donors, emergencies, bookings.   |
| `staff`            | Add/edit blood units, log storage temperature.              |
| `donor`            | View own profile + donation history.                        |
| `patient`          | Search blood, book.                                         |
| `hospital`         | Post emergency requests, manage bookings.                   |

`admin_roles()` in `app/helpers/AuthHelper.php` returns
`['super_admin', 'blood_bank_admin', 'staff']` — used to gate all
write/admin API endpoints.

---

## API Reference

All API endpoints live under `public/api.php?route=<endpoint>`.

The shape is the same as the original (`?endpoint=` has been renamed
to `?route=` throughout the views).

### Public (no auth)

| Route                          | Method | Description                              |
|--------------------------------|--------|------------------------------------------|
| `public/stats`                 | GET    | Donors/units/banks/emergencies counters  |
| `public/blood-stock`           | GET    | Available units by blood group           |
| `public/blood-banks`           | GET    | List of registered blood banks           |
| `blood-search`                 | GET    | Search banks + donors (group, city, lat) |
| `emergency/feed`               | GET    | Active emergency requests                |
| `public/booking`               | POST   | Hospital creates a booking               |
| `public/emergency`             | POST   | Hospital posts an emergency              |
| `emergency/create`             | POST   | Alias for `public/emergency`             |
| `blood-banks/register`         | POST   | Self-service blood-bank registration     |

### Authenticated reads

| Route                          | Roles  | Description                              |
|--------------------------------|--------|------------------------------------------|
| `dashboard/stats`              | any    | Top-level counters                       |
| `dashboard/blood-stock`        | any    | Bar chart data                           |
| `dashboard/monthly-donations`  | any    | 12-month line chart                      |
| `profile/current`              | any    | Current user + linked blood bank         |
| `notifications/list`           | any    | Last 50 notifications                    |
| `notifications/count`          | any    | Unread count                             |
| `settings/hospital`            | any    | Hospital contact details                 |
| `settings/notifications`       | any    | Channel preferences                      |
| `emergency/stats`              | any    | Active / fulfilled / expired counters    |
| `bookings/stats`               | any    | Pending / approved / rejected counters   |
| `bookings/list`                | any    | Paginated booking list                   |
| `donors/list`                  | any    | All donors with user info                |
| `donors/stats`                 | any    | Donor counters                           |
| `storage/list`                 | any    | Scoped to user's blood bank              |
| `storage/stats`                | any    | Aggregate storage counters               |
| `blood-units/list`             | any    | Scoped to user's blood bank, filterable  |
| `reports/blood-stock`          | any    | Available units by group                 |
| `reports/ai-insights`          | any    | Gemini or static-fallback insights        |
| `reports/preview`              | any    | In-page preview of any report type       |
| `search/global`                | any    | Cross-table search                       |

### Admin-only reads

| Route                          | Description                              |
|--------------------------------|------------------------------------------|
| `settings/system-stats`        | System-wide counters                     |
| `reports/export-csv`           | Streams CSV of any report type           |

### Authenticated writes (CSRF handled by SameSite=Strict cookies)

| Route                          | Roles  | Description                              |
|--------------------------------|--------|------------------------------------------|
| `blood-units/add`              | admin  | Add a unit                               |
| `blood-units/update-status`    | admin  | Mark unit reserved/used/expired/discarded|
| `blood-units/delete`           | admin  | Remove unit                              |
| `donors/add`                   | admin  | Create donor profile                     |
| `storage/add`                  | admin  | Add storage area                         |
| `storage/log-temperature`      | admin  | Append a temperature reading             |
| `bookings/{id}/approve`        | admin  | Approve a booking                        |
| `bookings/{id}/reject`         | admin  | Reject a booking                         |
| `emergency/{id}/fulfill`       | admin  | Mark an emergency fulfilled              |
| `settings/hospital` (POST)     | any    | Update hospital contact details          |
| `settings/notifications` (POST)| any    | Update channel preferences               |
| `notifications/mark-read`      | any    | Mark one notification as read            |
| `notifications/send`           | admin  | Send ad-hoc notification to a user       |

### Standard response shape

```json
{ "ok": true,  ... }
{ "ok": false, "error": "human readable" }
```

---

## Configuration

All values in `config/app.php` can be overridden via environment variables.

| Env var          | Default                                          | Notes                            |
|------------------|--------------------------------------------------|----------------------------------|
| `APP_NAME`       | `RedPulse Blood Bank`                            | Title and email subject          |
| `APP_URL`        | `http://localhost/blood-bank-system/public`      | Used by `app_url()`              |
| `APP_DEBUG`      | `false`                                          | When true, shows PDO errors      |
| `DB_HOST`        | `localhost`                                      |                                  |
| `DB_PORT`        | `3306`                                           |                                  |
| `DB_USER`        | `root`                                           |                                  |
| `DB_PASS`        | *(empty)*                                        |                                  |
| `DB_NAME`        | `blood_bank_system`                              |                                  |
| `GEMINI_API_KEY` | *(empty)*                                        | Enables AI insights              |

`SESSION_TIMEOUT` = 1800s (30 min). `LOGIN_MAX_ATTEMPTS` = 5.
`LOGIN_LOCKOUT_MINUTES` = 15. `LOW_STOCK_THRESHOLD` = 10.
`STORAGE_TEMP_MIN` / `MAX` = 2 / 6 °C. `EMERGENCY_TTL_HOURS` is
`{ critical: 24, moderate: 36, low: 48 }`.

---

## Database Schema

12 tables (all `InnoDB` / `utf8mb4`):

| Table                   | Purpose                                          |
|-------------------------|--------------------------------------------------|
| `users`                 | Accounts (admin, staff, donor, hospital, patient)|
| `blood_banks`           | Bank / centre metadata                           |
| `blood_units`           | Individual units, with barcodes + expiry        |
| `donors`                | Donor profiles + eligibility                     |
| `emergency_requests`    | Time-bound emergency blood needs                 |
| `blood_bookings`        | Reserved / scheduled bookings                    |
| `storage_areas`         | Fridges, freezers, cold rooms                    |
| `temperature_logs`      | Historical temperature readings                  |
| `notifications`         | In-app notification feed                         |
| `notification_settings` | Per-user channel preferences                     |
| `audit_logs`            | Tamper-evident action log                        |
| `login_attempts`        | Rate-limit + lockout tracking                    |

Foreign-key constraints, check constraints (`expiry_date > collection_date`),
and indices on common lookup columns are baked in.  See
`database/migrations/001_create_tables.sql` for the source of truth.

---

## Security Notes

### Authentication & authorization tiers

API routes declare their required access level in `routes/api.php`:

| Tier | `auth` value | Example routes | What happens if no session |
|------|--------------|----------------|----------------------------|
| **Public read** | (omitted) | `public/stats`, `public/blood-stock`, `public/blood-banks`, `blood-search`, `emergency/feed` | Returns the data. |
| **Public write** | `'optional'` | `public/booking`, `public/emergency`, `blood-banks/register` | Falls back to the first active hospital user (so data is never lost). |
| **Authenticated** | `'required'` | `notifications/*`, `profile/*`, `dashboard/stats` | Returns `401 Unauthorized`. |
| **Admin** | `'admin'` | `inventory/*`, `donors/*`, `storage/*`, `emergency/admin/*`, `bookings/*`, `reports/*`, `settings/*` | `401` if no session, `403` if role isn't `super_admin`/`blood_bank_admin`/`staff` (or `hospital` for hospital-scoped routes). |

Web pages declare a `roles` option on the route; `RoleMiddleware` redirects
unauthenticated visitors to `/login` and authorized-but-wrong-role users to
`/home` with a flash error.

### Other defenses

- **Passwords** are bcrypt (`PASSWORD_BCRYPT`).
- **Session cookies** use `HttpOnly`, `SameSite=Strict`, and `Secure` when
  the request is HTTPS. Name: `blood_bank_session`.
- **Session timeout** — idle sessions expire after 30 minutes
  (`SESSION_TIMEOUT=1800`).
- **Login lockout** via `login_attempts` + `users.lockout_until` — 5 failed
  attempts within the rolling window locks the account for 15 minutes.
- **CSRF** is enforced on web form POSTs (login, register, forgot/reset,
  public blood-bank registration, public booking/emergency forms). The
  `_csrf_token` hidden input must match `$_SESSION['_csrf_token']`. API
  writes from the authenticated dashboard skip CSRF because the session
  cookie's `SameSite=Strict` already blocks cross-site submissions — this
  trade-off is acceptable because all dashboard API calls originate from
  same-origin XHR and we are not embedding third-party widgets.
- **SQL injection** — all queries use prepared statements with bound params
  (see `app/models/Model.php` and every controller).
- **XSS** — views call `htmlspecialchars()` on user input.
- **Security headers** — `X-Content-Type-Options: nosniff`,
  `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`,
  `Permissions-Policy: geolocation=(self), camera=()`.
- **`.htaccess`** in `public/` denies direct access to `app/`, `config/`,
  `database/`, `routes/`, `storage/`, `scripts/`, `bootstrap/`.

---

## Troubleshooting

### Database connection error
- Verify MySQL is running in XAMPP.
- Confirm the database name in `config/database.php` matches what's in
  phpMyAdmin.
- Set `APP_DEBUG=true` (env) for the underlying error message.

### Pages return 404
- Make sure the project is at `C:\xampp\htdocs\blood-bank-system\`.
- `APP_URL` in `config/app.php` must match the actual URL.
- Make sure Apache's `mod_rewrite` is enabled (not actually required for
  this app — but check if you have any restrictive `.htaccess`).

### Login keeps redirecting to login
- Check that the session cookie domain matches `APP_URL`'s host.
- Clear `blood_bank_session` cookies in the browser.
- Make sure PHP's `session.save_path` is writable (default `C:\xampp\tmp`).

### CSV export shows 0 rows
- This is correct if the report type has no matching records. Try
  clearing filters.

### "Endpoint not found"
- The new URL pattern is `?route=...`, not `?endpoint=...`.  All views
  have been updated.

---

## Smoke Test

A CLI smoke test exercises the full surface — public API, public writes,
every public + auth page render, the auth gate on every protected page,
and the authenticated dashboard:
```bash
& "C:\xampp\php\php.exe" scripts/smoke.php
```
Expected: **27 passed, 0 failed**.

The test depends on:
- Apache running on `localhost:80` with the project at
  `C:\xampp\htdocs\blood-bank-system\`.
- A fresh database (run `public/seed-data.php` first).
- A hospital super-admin user `admin@centralbb.org` / `Password123`
  (created by the seeder).

---

## License

Proprietary. Internal use only.
