# Testing and CI conventions

Use PHP 8.4, PostgreSQL 16 and Node 22 in CI. Lock both dependency graphs. The local Node
20.19.2 runtime is also used for Phase 0 verification. Use composer install and npm ci on checkout.

Commands: composer validate --strict; vendor/bin/pint --test; php artisan test;
npm run typecheck; npm run build; composer audit; npm audit --audit-level=high.
Windows uses `php vendor/bin/pint --test`. Framework tests force SQLite :memory: so they
cannot erase local PostgreSQL data. RefreshDatabase is restricted to that ephemeral test DB.
PostgreSQL CI separately migrates a disposable service database and checks migration status.

Future tenant tests must run on PostgreSQL using non-owner NOSUPERUSER NOBYPASSRLS roles;
SQLite smoke tests are not isolation evidence. Every domain action needs positive and negative
scope tests. Use factories and synthetic data; never copy real employee databases.

Phase 0 tests cover Inertia page/props, unavailable operational routes, framework health,
private-disk configuration and baseline database migration support. Browser acceptance checks
built assets at desktop and mobile widths, module content, honest status labels and console errors.
No product-authentication or tenancy tests can pass before those features are implemented.

CI workflow is supplied under .github/workflows/ci.yml; it has no deployment step. Its service
credentials are disposable CI-only values. Remote execution is unverified until a repository is
connected. Dependency audit failures require triage; do not silently lower severity thresholds.
CI action pinning and automated dependency updates should be hardened before release.
