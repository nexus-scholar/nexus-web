# Phase 0 Local Development

Status: scaffold baseline
Date: 2026-05-29

## Purpose

Phase 0 establishes the Nexus Scholar Web repository and local development baseline. It does not implement product workflows yet.

## Repository

- GitHub: `https://github.com/nexus-scholar/nexus-web`
- Local path: `repos/nexus-web`
- Branch: `master`
- Product name: Nexus Scholar
- Repo name: `nexus-web`

## Scaffold Command

The app was created with the Laravel installer:

```powershell
laravel new repos/nexus-web --react --pest --database=sqlite --git --branch=master --npm --no-boost --no-interaction
```

The scaffold uses the official Laravel React starter kit with Inertia, TypeScript, Tailwind, and starter UI components.

## Local Core Dependency

The app uses Composer path repositories for local Nexus packages:

```text
../core
../graph-core
../graph-algorithms
```

The installed package baseline is:

```powershell
composer update nexus-scholar/core nexus-scholar/graph-core nexus-scholar/graph-algorithms --with-dependencies
```

## Published Core Assets

Core package config and migrations were published into the host app:

```powershell
php artisan vendor:publish --tag=nexus-config --force
php artisan vendor:publish --tag=nexus-migrations --force
php artisan migrate --graceful
```

This makes the host ready for project/workflow integration in Phase 1 without adding UI behavior yet.

## Local Commands

Install dependencies:

```powershell
composer install
npm install
```

Prepare the local database:

```powershell
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
```

Run the development stack:

```powershell
composer run dev
```

Validate the scaffold:

```powershell
composer validate --strict
composer test
npm run build
```

## Stop Line

Phase 0 stops after the scaffold, local package wiring, validation, and GitHub push.

Do not implement:

- workspaces,
- projects,
- protocols,
- search UI,
- corpus UI,
- screening UI,
- exports,
- operator admin screens.
