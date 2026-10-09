# LifeLoop

**LifeLoop** is a lightweight, multi-user personal scheduling and actual-work tracking web application engineered for deployment on conventional PHP shared hosting environments while providing a reactive, single-page user experience.

---

## Architecture & Technology Stack

| Layer | Technology | Details |
|---|---|---|
| **Backend** | [Laravel 11](https://laravel.com) | Clean application skeleton, Eloquent ORM, PHP 8.2+ / 8.3 |
| **Frontend** | [Vue 3](https://vuejs.org) + [TypeScript](https://www.typescriptlang.org) | Composition API with strict TypeScript typing |
| **Bridge** | [Inertia.js](https://inertiajs.com) | Same-origin architecture with zero API synchronization boilerplate |
| **Build Tool** | [Vite](https://vitejs.dev) | Lightning-fast HMR and optimized production asset bundling |
| **Styling** | [Tailwind CSS](https://tailwindcss.com) | Utility-first responsive design system with CSS custom property tokens |
| **Database** | [MariaDB 11](https://mariadb.org) / MySQL 8+ | Relational schema with strict foreign keys & user isolation |
| **Authentication** | [Laravel Sanctum](https://laravel.com/docs/sanctum) | First-party browser session cookies with CSRF protection |
| **Testing** | [PHPUnit 11](https://phpunit.de) | Automated unit and feature testing suites |

### Deployment Architecture
- **Same-Origin Deployment:** Laravel directly serves the application and compiled Vue assets from `public/build/`.
- **No Node Server in Production:** Node.js and Vite are used strictly at build time (`npm run build`). Production only requires standard Apache/Nginx + PHP-FPM or PHP shared hosting.
- **Session-Based Security:** Authentication relies on secure HTTP-only session cookies and CSRF tokens rather than storing JWTs in browser storage.

---

## Design System

LifeLoop implements a calm, modern, and minimal design system engineered for high-focus productivity:

### Color Palette & Design Tokens
- **Primary Signature:** Lavender (`#9B8AFB`), with subtle tint states (`rgba(155, 138, 251, 0.12)`) and focus rings.
- **Secondary Accent:** Indigo (`#6366F1`) for ancillary badges and highlights.
- **Light Theme Surfaces:** Soft near-white background (`#F8F9FC`) and crisp white surfaces (`#FFFFFF`).
- **Dark Theme Surfaces:** Deep charcoal background (`#121316`) and slightly lighter charcoal surfaces (`#1A1B20`), avoiding harsh pure black.
- **Semantic Feedback:** Dedicated green, amber, red, and blue tokens strictly for task completion, in-progress states, and warnings.
- **Accessibility & Motion:** High contrast text ratios and `@media (prefers-reduced-motion: reduce)` support.

### Theme Engine
- **Persistent Selection:** Light, Dark, or System preference stored in `localStorage`.
- **Zero-Flash Hydration:** Synchronous inline bootstrapper in `app.blade.php` prevents theme flickering on page load.
- **Controls:** Embedded theme toggles available in the desktop sidebar, mobile header, and top action bar.

### Reusable UI Components (`resources/js/Components/ui/`)
- **Buttons:** `Button.vue` (primary, secondary, subtle, ghost, danger) and `IconButton.vue`.
- **Form Controls:** `Input.vue`, `Select.vue`, `Checkbox.vue`, and `DateTimeInput.vue`.
- **Surfaces & Overlays:** `Card.vue` and accessible `Dialog.vue` (modal with escape key & backdrop dismiss).
- **Navigation & Menus:** `Dropdown.vue`, `Tooltip.vue`, `Tabs.vue`, and `SegmentedControl.vue`.
- **Feedback & States:** `Badge.vue`, `Toast.vue` (`useToast` composable), `EmptyState.vue`, `Skeleton.vue`, and `ErrorAlert.vue`.
- **Layout:** `AppLayout.vue`, `AppSidebar.vue`, `AppHeader.vue`, and `PageHeader.vue`.

---

## Visual Prototype: Today Screen

The visual prototype is accessible at `/` (`resources/js/Pages/Today.vue`) featuring realistic mock data:
- **Greeting & Date:** Dynamic greeting ("Good morning / afternoon / evening, Alex") and current date.
- **Summary Metrics:** Planned vs. Completed vs. Extra Work hours with interactive progress bar.
- **Interactive Scheduled Tasks:** Live checkboxes to toggle completion state and trigger toast notifications.
- **Log Extra Work Action:** Modal dialog to record ad-hoc tasks, durations, and notes, updating daily totals in real time.
- **View Previews:** Interactive switcher to inspect the Live Schedule, Empty State, and Skeleton Loading states.

---

## Local Development Setup

### 1. Prerequisites
- **Node.js** (v20+ or v22 LTS) & **npm**
- **Podman** or **Docker** (for local MariaDB and containerized PHP toolchain)
- (Optional) **PHP 8.2+** and **Composer 2.x** installed natively on host

### 2. Database Service (Podman)
Start the local MariaDB container:
```bash
podman run -d --name schedule-tracker-mariadb \
  -e MARIADB_ROOT_PASSWORD=root \
  -e MARIADB_DATABASE=schedule_tracker \
  -e MARIADB_USER=tracker \
  -e MARIADB_PASSWORD=secret \
  -p 127.0.0.1:3306:3306 \
  docker.io/library/mariadb:11
```

### 3. Environment Configuration
Copy `.env.example` to `.env`:
```bash
cp .env.example .env
```
Ensure database credentials match:
```ini
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=schedule_tracker
DB_USERNAME=tracker
DB_PASSWORD=secret
```

### 4. Install Dependencies
Install frontend packages:
```bash
npm install
```

Install backend PHP packages (using host Composer or the local PHP container):
```bash
# Using local Podman PHP runtime:
podman run --rm --userns=keep-id -v $(pwd):/app:Z -w /app localhost/schedule-tracker-php:8.3 composer install
```

### 5. Run Migrations
```bash
podman run --rm --userns=keep-id --net=host -v $(pwd):/app:Z -w /app localhost/schedule-tracker-php:8.3 php artisan migrate
```

### 6. Development Servers
To run the Vite hot-reloading development server:
```bash
npm run dev
```

To run the Laravel backend server:
```bash
podman run --rm -it --userns=keep-id --net=host -v $(pwd):/app:Z -w /app localhost/schedule-tracker-php:8.3 php artisan serve --host=127.0.0.1 --port=8000
```

---

---

## Authentication & Security Architecture

LifeLoop implements a robust, privacy-first authentication system using Laravel 11, Inertia.js, and Laravel Sanctum session-based authentication:

### 1. Email OTP Verification Flow
- **Cryptographically Secure OTP:** 6-digit numeric codes generated using `random_int(100000, 999999)`.
- **Hashed Storage:** OTPs are hashed using `Hash::make()` (bcrypt) before database persistence. Plaintext OTPs are never stored or logged in application logs.
- **Expiry & Invalidation:** OTPs expire strictly after **5 minutes**. A successful verification marks the code as `consumed_at`. Re-sending an OTP automatically invalidates all previous active OTPs for that user and purpose.
- **Rate Limiting & Attempt Caps:**
  - Max **5 attempts** per OTP before it is locked and invalidated.
  - **60-second cooldown** enforced between OTP resends.
- **Anti-Enumeration Protection:** Password reset requests return an identical generic response regardless of whether the submitted email address exists in the database.

### 2. Session Management & 24-Hour Persistence
- **Inactivity-Based Expiry:** Configured with `SESSION_LIFETIME=1440` (24 hours).
  > [!NOTE]
  > Session expiry is **inactivity-based**: as long as the user actively interacts with the application, the session is refreshed. Inactivity exceeding 24 hours invalidates the session.
- **Persistent Browser Cookies:** `SESSION_EXPIRE_ON_CLOSE=false` ensures that closing and reopening the browser window does not terminate the session within the valid window.
- **24-Hour Persistent Recaller:** The authentication guard is configured with a 24-hour (1440 minutes / 86,400s) persistent `remember_web_...` cookie. This is issued on registration and login by default, ensuring sessions survive complete browser exits and process restarts within the 24-hour window.
- **Cookie Hardening:** `SESSION_HTTP_ONLY=true` prevents XSS token extraction, `SESSION_SAME_SITE=lax` defends against CSRF attacks, and `SESSION_SECURE_COOKIE=true` is used in production over HTTPS.
- **Sanctum First-Party SPA:** Employs stateful session cookies and standard Laravel CSRF tokens (`X-XSRF-TOKEN`).

### 3. Tenant Data Isolation
- **Strict Authorization:** Authenticated requests are scoped to `auth()->id()`. Direct Object Reference (IDOR) attacks are prevented by validating ownership before record inspection or mutation.
- **Mass Assignment Protection:** Models define explicit `$fillable` attributes (`name`, `email`, `password`, `timezone`).

### 4. User Settings & Timezone Support
- Authenticated users can manage their account profile and preferred timezone at `/settings`.
- Timezones are selectable from standard IANA identifiers (`UTC`, `America/New_York`, `Asia/Tokyo`, `Europe/London`, etc.).

---

## Mail Configuration

LifeLoop uses Laravel's built-in Mail services. 

### Local Development / Testing
To log outgoing emails locally without connecting to an external mail server, configure `.env`:
```ini
MAIL_MAILER=log
MAIL_FROM_ADDRESS="lifeloopsupport@yourdomain.com"
MAIL_FROM_NAME="LifeLoop"
```
Email messages including OTP codes will be recorded in `storage/logs/laravel.log`.

### Production / Custom SMTP
To deliver real emails over SSL/TLS SMTP, configure the following in `.env` (never commit real credentials to version control):
```ini
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=mail.yourdomain.com
MAIL_PORT=465
MAIL_USERNAME=your-username@yourdomain.com
MAIL_PASSWORD=your-secure-password
MAIL_FROM_ADDRESS="your-username@yourdomain.com"
MAIL_FROM_NAME="LifeLoop"
```

---

## Testing & Quality Assurance

Run TypeScript type-checking:
```bash
npx tsc --noEmit
```

Build production assets:
```bash
npm run build
```

Run automated backend tests (32 tests covering auth, OTP limits, data isolation, and session security):
```bash
php artisan test
```

### Feature Test Coverage
- `Tests\Feature\Auth\RegistrationTest`: Registration, duplicate email rejection, hashed OTP dispatch.
- `Tests\Feature\Auth\EmailVerificationOtpTest`: Valid OTP verification, attempt throttling, expiration, reuse blocking, 60s cooldown.
- `Tests\Feature\Auth\AuthenticationTest`: Login, invalid credentials, unverified user redirect, session destruction on logout.
- `Tests\Feature\Auth\PasswordResetOtpTest`: Enumeration-resistant reset request, OTP verification, password updating.
- `Tests\Feature\Auth\UserDataIsolationTest`: Route protection, unauthenticated redirects, cross-user data isolation.
- `Tests\Feature\Auth\SessionSecurityTest`: 24-hour lifetime, persistent cookies, security attributes (`http_only`, `same_site`).

---

## Project Structure

```text
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/
│   │   │   │   ├── AuthenticatedSessionController.php   # Login / Logout
│   │   │   │   ├── EmailVerificationOtpController.php   # OTP verification & resend
│   │   │   │   ├── PasswordResetOtpController.php       # Password reset via OTP
│   │   │   │   └── RegisteredUserController.php         # User registration
│   │   │   └── SettingsController.php                   # Timezone & profile settings
│   │   └── Middleware/
│   │       ├── EnsureEmailIsOtpVerified.php             # Gates unverified users to /verify-otp
│   │       └── HandleInertiaRequests.php                # Inertia shared props & auth session
│   ├── Mail/
│   │   └── OtpMail.php                                  # Mailable for 6-digit OTP delivery
│   ├── Models/
│   │   ├── EmailOtp.php                                 # Hashed OTP model & helper methods
│   │   └── User.php                                     # User model with MustVerifyEmail & timezone
│   └── Services/
│       └── OtpService.php                               # Cryptographic OTP generator & verifier
├── bootstrap/
│   └── app.php                                          # Middleware aliases & routing bootstrap
├── config/                                              # Laravel application configuration
├── database/
│   └── migrations/
│       ├── 2026_10_09_132355_add_timezone_to_users_table.php
│       └── 2026_10_09_132355_create_email_otps_table.php
├── resources/
│   ├── css/
│   │   └── app.css                                      # Design tokens, themes & typography
│   ├── js/
│   │   ├── Components/
│   │   │   ├── layout/                                  # AppLayout, AppSidebar, AppHeader, PageHeader
│   │   │   └── ui/                                      # Reusable UI component library
│   │   ├── Pages/
│   │   │   ├── Auth/
│   │   │   │   ├── ForgotPassword.vue                   # Request password reset code
│   │   │   │   ├── Login.vue                            # User sign-in
│   │   │   │   ├── Register.vue                         # User sign-up with timezone
│   │   │   │   ├── ResetPassword.vue                    # Enter OTP & new password
│   │   │   │   └── VerifyOtp.vue                        # Enter OTP code & resend
│   │   │   ├── Settings.vue                             # Timezone & account settings
│   │   │   ├── Today.vue                                # Today schedule visual prototype
│   │   │   └── Welcome.vue                              # Foundation status view
│   │   └── app.ts                                       # Frontend client bootstrap
│   └── views/
│       ├── app.blade.php                                # Root Inertia Blade template
│       └── emails/
│           └── otp.blade.php                            # Styled HTML OTP email notification
├── routes/
│   └── web.php                                          # Application routes (Guest, Auth, Verified)
└── tests/
    └── Feature/
        └── Auth/                                        # Complete authentication test suites
```

---

## License

This software is licensed under the [MIT License](LICENSE).
