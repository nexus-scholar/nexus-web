# Nexus Scholar Web

Nexus Scholar Web is the hosted Laravel application for the Nexus Scholar product. It is a separate host application that consumes `nexus-scholar/core` for scholarly workflow behavior while owning authentication, workspaces, projects, dashboards, SaaS limits, and product UI.

This repository is currently in Phase 0: scaffold and local development setup.

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

The app expects the sibling Nexus Scholar repositories to exist in the parent `repos` folder:

```text
repos/core
repos/graph-core
repos/graph-algorithms
repos/nexus-web
```

The Composer path repositories in `composer.json` load these packages locally.

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

## Phase 0 Validation

Use these commands before committing scaffold changes:

```powershell
composer validate --strict
composer test
npm run build
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
