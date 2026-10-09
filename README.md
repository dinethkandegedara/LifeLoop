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

## Testing & Quality Assurance

Run TypeScript type-checking:
```bash
npx tsc --noEmit
```

Build production assets:
```bash
npm run build
```

Run automated backend tests:
```bash
podman run --rm --userns=keep-id --net=host -v $(pwd):/app:Z -w /app localhost/schedule-tracker-php:8.3 php artisan test
```

---

## Project Structure

```text
├── app/
│   ├── Http/
│   │   └── Middleware/
│   │       └── HandleInertiaRequests.php   # Inertia shared props & root view
│   └── Models/                             # Eloquent models
├── bootstrap/
│   └── app.php                             # Middleware & routing bootstrap
├── config/                                 # Laravel application config
├── database/
│   └── migrations/                         # Database schema migrations
├── resources/
│   ├── css/
│   │   └── app.css                         # Design tokens, themes & typography
│   ├── js/
│   │   ├── Components/
│   │   │   ├── layout/                     # AppLayout, AppSidebar, AppHeader, PageHeader
│   │   │   └── ui/                         # Reusable UI component library
│   │   ├── composables/                    # useTheme, useToast composables
│   │   ├── Pages/
│   │   │   ├── Today.vue                   # Today schedule visual prototype
│   │   │   └── Welcome.vue                 # Foundation status view
│   │   ├── app.ts                          # Frontend client bootstrap
│   │   ├── bootstrap.ts                    # Axios HTTP configuration
│   │   └── vite-env.d.ts                   # Ambient TypeScript typings
│   └── views/
│       └── app.blade.php                   # Root Inertia Blade template with anti-flash script
├── routes/
│   └── web.php                             # Web routes (/ and /foundation)
├── tests/
│   ├── Feature/                            # Feature test suites (TodayTest, WelcomeTest, ExampleTest)
│   └── Unit/                               # Unit test suites
├── tsconfig.json                           # TypeScript configuration
└── vite.config.ts                          # Vite build configuration
```

---

## License

This software is licensed under the [MIT License](LICENSE).
