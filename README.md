# Betterlink CyberAware AI

Developer: **Betterlink Engineering**. Repository: **CyberAware-AI**.

Independent cybersecurity awareness SaaS project for Zimbabwe, Southern Africa and future
international use. **Phase 0 foundation only; no product workflows are operational.**
No existing project or database is used. No production deployment or external service is configured.

## Stack

Laravel 12 / PHP 8.4 / PostgreSQL 16 / React 19 / TypeScript / Inertia 2 / Tailwind 4 / Vite 6.
PHP requires curl, fileinfo, mbstring, openssl, intl, PDO PostgreSQL and SQLite, and zip.
Node 22 is the CI baseline; local validation passed on Node 20.19.2.

## Run on this workstation

From this directory in PowerShell:

```powershell
. ./scripts/Use-LocalRuntime.ps1
# Start this project's database after a restart; it may already be running:
& 'C:\Program Files\PostgreSQL\16\bin\pg_ctl.exe' -D "$PWD/.runtime/postgres" -l "$PWD/.runtime/postgres.log" -w start
php artisan serve --host=127.0.0.1 --port=8010
```

Preview: [http://127.0.0.1:8010](http://127.0.0.1:8010). A server may already be running there.
The dedicated database is `cyberaware_ai_dev` on `127.0.0.1:55432`. Generated credentials
exist only in ignored `.env` and `.runtime/db-password`. Local PHP 8.4.27 came from
windows.php.net and was SHA-256 verified against official release metadata. System PHP 8.2
and existing PostgreSQL services were unchanged. These ignored runtimes are not distributed
in Git. Keep local credential/runtime files private.

Stop this project's database when needed:

```powershell
& 'C:\Program Files\PostgreSQL\16\bin\pg_ctl.exe' -D "$PWD/.runtime/postgres" -m fast -w stop
```

## Fresh checkout

Install PHP 8.4, Composer, Node 22/npm and PostgreSQL 16 first. A system PHP 8.4 works without
Use-LocalRuntime.ps1. The Composer requirement deliberately enforces PHP 8.4.x.

```powershell
composer install --no-interaction
Copy-Item .env.example .env
php artisan key:generate
# Windows project-only cluster; refuses to replace an existing cluster:
./scripts/Initialize-Database.ps1
php artisan migrate
npm ci
npm run typecheck
npm run build
php artisan test
php artisan serve --host=127.0.0.1 --port=8010
```

Alternatively provision an empty, dedicated PostgreSQL 16 database and explicitly set this
project's DB_* values. Never reuse another application's database or credentials. On other
operating systems use normal PostgreSQL provisioning tools. The Windows script is for local
development only. Do not use a shared data directory. The cluster owner is a development
superuser; runtime and migration roles will be separated in Phase 1.

Run `npm run dev` in a second terminal for frontend development. Later queue/scheduler work
can use `php artisan queue:work` and `php artisan schedule:work`; no domain jobs or schedules
are defined in Phase 0.

## Verification and documentation

```powershell
composer validate --strict
php vendor/bin/pint --test
php artisan test
npm run typecheck
npm run build
composer audit
npm audit --audit-level=high
```

Tests force SQLite :memory: and cannot reset local PostgreSQL. CI also checks migrations on
a disposable PostgreSQL 16 service. No deploy step is supplied.

- [Status and verification evidence](docs/IMPLEMENTATION_STATUS.md)
- [Architecture, tenant isolation, roles and learning design](docs/ARCHITECTURE.md)
- [Proposed entities and ER diagram](docs/DATABASE_DESIGN.md)
- [Threat model and safe simulation lifecycle](docs/SECURITY_THREAT_MODEL.md)
- [AI governance](docs/AI_GOVERNANCE.md)
- [Independently approved roadmap](docs/ROADMAP.md)
- [Testing and CI conventions](docs/TESTING.md)
- [Master development brief](MASTER_BRIEF.md)

Authentication, MFA, tenant RLS, learning, simulations, AI, billing, integrations, certificates
and reports are unimplemented. Private local storage is the default; automatic serving is
disabled. Policy-gated media routes are future work. Responsive layout is present; PWA offline
support is not implemented. This foundation is not ready for employee data.

**Stop after Phase 0. Phase 1 requires explicit approval.**
