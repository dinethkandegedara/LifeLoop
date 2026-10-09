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
| **Styling** | [Tailwind CSS](https://tailwindcss.com) | Utility-first responsive design system |
| **Database** | [MariaDB 11](https://mariadb.org) / MySQL 8+ | Relational schema with strict foreign keys & user isolation |
| **Authentication** | [Laravel Sanctum](https://laravel.com/docs/sanctum) | First-party browser session cookies with CSRF protection |
| **Testing** | [PHPUnit 11](https://phpunit.de) | Automated unit and feature testing suites |

### Deployment Architecture
- **Same-Origin Deployment:** Laravel directly serves the application and compiled Vue assets from `public/build/`.
- **No Node Server in Production:** Node.js and Vite are used strictly at build time (`npm run build`). Production only requires standard Apache/Nginx + PHP-FPM or PHP shared hosting.
- **Session-Based Security:** Authentication relies on secure HTTP-only session cookies and CSRF tokens rather than storing JWTs in browser storage.

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
│   │   └── app.css                         # Tailwind CSS entrypoint
│   ├── js/
│   │   ├── Pages/                          # Vue 3 Inertia page components
│   │   ├── app.ts                          # Frontend client bootstrap
│   │   ├── bootstrap.ts                    # Axios HTTP configuration
│   │   └── vite-env.d.ts                   # Ambient TypeScript typings
│   └── views/
│       └── app.blade.php                   # Root Inertia Blade template
├── routes/
│   └── web.php                             # Web routes
├── tests/
│   ├── Feature/                            # Feature test suites
│   └── Unit/                               # Unit test suites
├── tsconfig.json                           # TypeScript configuration
└── vite.config.ts                          # Vite build configuration
```

---

## License

This software is licensed under the [MIT License](LICENSE).
