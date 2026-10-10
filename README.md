# LifeLoop — Personal Schedule & Focus Tracker

[![Tests](https://img.shields.io/badge/tests-98%20passed-10b981?style=flat-square)](tests)
[![PHP](https://img.shields.io/badge/PHP-8.2%20%7C%208.3-777bb4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-11.x-ff2d20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com)
[![Vue](https://img.shields.io/badge/Vue-3.5%20%2B%20TypeScript-42b883?style=flat-square&logo=vue.js&logoColor=white)](https://vuejs.org)
[![Inertia](https://img.shields.io/badge/Inertia-v2-9553e9?style=flat-square)](https://inertiajs.com)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind-3.4-38bdf8?style=flat-square&logo=tailwind-css&logoColor=white)](https://tailwindcss.com)
[![License](https://img.shields.io/badge/license-MIT-blue?style=flat-square)](LICENSE)

LifeLoop is a self-hosted personal scheduling and focus tracker designed to bridge the gap between planned routines and actual execution. Built on Laravel 11, Inertia.js, and Vue 3 with TypeScript, it provides single-page responsiveness with server-side security, fully compatible with standard PHP shared hosting without requiring Redis servers, background Node processes, or persistent daemon supervisors.

---

## Key Capabilities

### 1. Flexible Recurring Schedules
- **Rule Types**: Supports daily, weekly (multi-day selection), monthly by date (with month-end clamping), and monthly by weekday position (e.g., *1st Monday*, *last Friday*).
- **Audit-Safe Historical Preservation**: Editing a recurring rule recalculates future pending occurrences without touching past history, completed items, or occurrences with logged work sessions.
- **One-Off Exceptions**: Reschedule or adjust durations for individual occurrences while preserving the original schedule rule.

### 2. Actual vs. Planned Work Logging
- **Focus Sessions**: Record real duration spent on scheduled occurrences or log unscheduled ad-hoc focus blocks.
- **Overdue Tracking**: Incomplete occurrences past their start time remain overdue until completed or explicitly marked skipped.
- **Skipped Semantics**: Explicitly skipping an occurrence removes it from overdue lists and planned completion calculations while preserving audit logs.

### 3. Analytics & Performance Reports
- **Multi-Window Reporting**: Analyze planned hours, actual hours, completion rates, and daily execution rhythms across Today, Week, Month, or Custom date ranges.
- **Period Navigation**: Browse backward and forward across historical dates, weeks, and months with timezone normalization.
- **Task Deep-Dives**: Detailed per-task reports with 8-week and 6-month historical trend graphs.

### 4. Streak & Habit Consistency
- **Daily Streak Engine**: Automatically tallies consecutive days meeting the consistency threshold (≥80% completion of scheduled tasks or 30+ minutes of recorded focus work).
- **Milestone Badges**: Progressive badges from *Active Streak* to *Diamond Consistency* (30+ days).

### 5. iCalendar / Webcal Sync Feed
- **External Calendar Subscription**: Subscribe to your LifeLoop schedule from Google Calendar, Apple Calendar, or Outlook via a tokenized RFC 5545 `.ics` feed.
- **On-Demand Token Invalidation**: Regenerate feed tokens at any time to instantly revoke prior calendar subscriptions.

### 6. Shared-Hosting Performance Architecture
- **Zero Redis Requirement**: Powered by Laravel's database/file cache drivers.
- **$O(1)$ User-Isolated Cache Versioning**: Bumping a user's version instantly invalidates cached reports and streaks on any completion, skip, reschedule, or work log without key scans.
- **Optimized SQL Aggregation**: Multi-task report overviews run in grouped SQL queries rather than N+1 PHP loops, slashing queries by over 95%.

---

## Technical Stack

```
Frontend:   Vue 3 (Composition API) + TypeScript + Tailwind CSS
Bridge:     Inertia.js v2 (Same-Origin Session Authentication)
Backend:    Laravel 11.x + Eloquent ORM + FormRequest Validation
Database:   MariaDB 10.5+ / MySQL 8.0+ / SQLite 3
Cache:      Database or File store (with deterministic versioned keys)
Mail:       SMTP / SMTPS (Email OTP verification and password resets)
```

---

## Getting Started (Local Development)

### Prerequisites
- **PHP** 8.2 or 8.3 with extensions: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `curl`, `fileinfo`
- **Composer** 2.x
- **Node.js** 20.x or 22.x LTS & **npm**
- **MySQL 8.0+** or **MariaDB 10.5+** (or SQLite for testing)

### Installation

1. **Clone the repository**:
   ```bash
   git clone https://github.com/your-username/schedule-tracker.git
   cd schedule-tracker
   ```

2. **Install PHP dependencies**:
   ```bash
   composer install
   ```

3. **Install JavaScript dependencies**:
   ```bash
   npm install
   ```

4. **Environment setup**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Configure database** in `.env`:
   ```ini
   DB_CONNECTION=mariadb
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=schedule_tracker
   DB_USERNAME=your_db_user
   DB_PASSWORD=your_db_password
   ```

6. **Run database migrations**:
   ```bash
   php artisan migrate
   ```

7. **Compile frontend assets**:
   ```bash
   # Development with Hot Module Replacement:
   npm run dev

   # Production build:
   npm run build
   ```

8. **Start the local development server**:
   ```bash
   php artisan serve --port=8000
   ```

---

## Security Architecture

- **Session Hardening**: 24-hour inactivity session lifetime (`SESSION_LIFETIME=1440`). Cookies are marked `HttpOnly` with `SameSite=Lax`. Session IDs are regenerated upon authentication.
- **Hashed One-Time Passwords**: 6-digit email OTPs are hashed with Bcrypt before persistence. Verification is capped at 5 attempts and expires after 5 minutes, with a 60-second resend cooldown.
- **Resource Authorization**: Every model mutation is guarded by dedicated Eloquent Policies verifying tenant ownership.
- **Sanitized Outputs & Strict Types**: Parameterized SQL queries throughout; user input passed through typed FormRequests.

---

## Verification & Test Suite

The test suite covers recurring schedule calculations, calendar range generations, work session audits, OTP security, and cache invalidation mechanics.

```bash
# Run all automated tests (98 tests, 669 assertions)
php artisan test

# Run frontend TypeScript type checking
npx tsc --noEmit

# Run production asset build
npm run build
```

---

## Production Shared-Hosting Deployment

LifeLoop is built to run on standard cPanel, DirectAdmin, or LiteSpeed/Apache shared hosting environments.

### 1. Document Root Configuration
Ensure the public web root points directly to the `/public` folder:
- **Subdomain or Addon Domain**: Set the Document Root in your hosting panel to `/home/username/schedule-tracker/public`.
- **Primary Domain (`public_html`)**: Upload the application files to `/home/username/schedule-tracker/` and move only the contents of `/public` into `/home/username/public_html/`. Update the vendor and bootstrap paths in `public_html/index.php`.

### 2. Production Environment (`.env`)
```ini
APP_NAME="LifeLoop"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cpanel_database
DB_USERNAME=cpanel_user
DB_PASSWORD="your_database_password"

CACHE_STORE=database
SESSION_DRIVER=database
SESSION_LIFETIME=1440
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
QUEUE_CONNECTION=sync

MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=mail.yourdomain.com
MAIL_PORT=465
MAIL_USERNAME=support@yourdomain.com
MAIL_PASSWORD="your_smtp_password"
MAIL_FROM_ADDRESS="support@yourdomain.com"
MAIL_FROM_NAME="LifeLoop"
```

### 3. File Permissions & Optimization
```bash
# Ensure storage and bootstrap caches are writable
chmod -R 775 storage bootstrap/cache

# Cache routes and configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## License

This project is licensed under the [MIT License](LICENSE).
