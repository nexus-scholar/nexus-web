# Nexus Scholar Web

Nexus Scholar Web is the hosted Laravel application for the Nexus Scholar product. It is a separate host application that consumes `nexus-scholar/core` for scholarly workflow behavior while owning authentication, workspaces, projects, dashboards, SaaS limits, and product UI.

The current MVP includes authentication, workspaces, project setup, protocol
editing, search planning, draft corpus review, deduplication review, corpus
lock, title-and-abstract screening, and full-text retrieval with artifact
audit.

For developer onboarding, start with [`docs/developer-handoff.md`](docs/developer-handoff.md).

## Stack

- Laravel 13
- React 19
- Inertia 3
- TypeScript
- Tailwind 4
- shadcn/ui-based starter components
- Pest
- SQLite for local development
- Database-backed queues for the local baseline

## Local Setup

From the repository root:

```powershell
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
npm run build
```

The app consumes the published `nexus-scholar/core:^1.0` package from
Packagist. Use a local path repository only for deliberate package development,
not for the default web-app setup or CI.

## Development Server

Run the full local development stack:

```powershell
composer run dev
```

That starts:

- Laravel HTTP server,
- database-backed queue listener,
- Laravel Pail logs,
- Vite dev server.

The default Laravel URL is:

```text
http://localhost:8000
```

## Validation

Use these commands before committing product changes:

```powershell
composer validate --strict
composer test
npm run test:ui
npm run build:check
```

## Product Boundary

`nexus-web` owns:

- users and authentication,
- workspaces and memberships,
- project shell records,
- protocol UI,
- dashboards,
- SaaS limits,
- product routes,
- operator screens,
- web presentation.

`nexus-scholar/core` owns:

- scholarly search,
- deduplication,
- corpus snapshots,
- screening workflows,
- full-text retrieval,
- citation graphs,
- exports,
- Laravel package migrations, jobs, handlers, ports, and read APIs.

Do not move product UI, SaaS tenancy, or hosted-app policy into `core`.
