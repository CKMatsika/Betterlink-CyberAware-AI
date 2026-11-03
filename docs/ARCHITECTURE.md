# Architecture decisions — Phase 0

Status: design approved for implementation planning; tenant security is not yet operational.
Source of scope: [master brief](../MASTER_BRIEF.md). Developer: Betterlink Engineering.

## Stack and runtime

Modular monolith: Laravel 12, PHP 8.4, PostgreSQL 16, React 19, TypeScript,
Inertia 2 and Tailwind 4. Vite emits versioned local assets. Laravel owns routing,
sessions, validation, policies, queues, notifications and scheduling. A single deployable
application has separately scalable web and queue processes. PostgreSQL is authoritative;
private object storage holds media and audit exports. No dependency on any other project.

Phase 0 serves a public, data-free foundation page and framework health route only.
Default Laravel identity/cache/queue migrations are retained for framework smoke testing;
they do not constitute tenant-aware authentication. No registration/login routes are exposed.

## Tenant isolation decision

Use one dedicated application database with shared schema and mandatory organisation_id
on every tenant-owned table. Global users represent identities; memberships link identities
to organisations. Tenant context is resolved from authenticated, active membership and
verified organisation selection, never an untrusted header/body identifier alone.

Phase 1 must implement a fail-closed TenantContext, Eloquent scopes, scoped route binding,
policies and PostgreSQL RLS. Each request and queue job runs tenant queries inside a
transaction using SET LOCAL app.organisation_id. RLS policies use missing_ok current_setting
and deny missing context; ENABLE and FORCE ROW LEVEL SECURITY on protected tables.
Production runtime role must be NOSUPERUSER NOBYPASSRLS and not a table owner. A separate
migration role owns schema. Composite foreign keys (organisation_id, entity_id) prevent
cross-tenant associations; tenant-specific unique indexes include organisation_id.

Platform administration uses separate audited control-plane actions, explicit time-limited
support access and no implicit tenant-data bypass. Cache keys, private storage paths,
exports, queued jobs, idempotency keys and integration tokens include tenant scope.
Workers reset tenant context after every job and re-check active authorisation at execution.
Cross-tenant negative tests using the restricted PostgreSQL role are a Phase 1 release gate.
SQLite tests cannot validate RLS. The Phase 0 cluster owner is development/migrations only.

## Identity and roles

Use framework session authentication, secure HTTP-only same-site cookies, CSRF validation,
email verification, invitation expiry, hashed single-use tokens and login throttling in Phase 1.
MFA-ready identity design includes encrypted TOTP secrets, hashed recovery codes and
step-up authentication for privileged actions; MFA remains unimplemented in Phase 0.

| Role | Intended permissions and boundaries |
| --- | --- |
| Platform administrator | Tenant lifecycle, plan support; no default employee-record access |
| Tenant administrator | Tenant settings, membership, role assignment, simulation authorisation |
| Training manager | Publish reviewed learning, assign courses, see authorised completion data |
| Security officer | Draft scoped exercises, analyse authorised simulation outcomes; cannot self-approve |
| Departmental manager | Read summaries and assignments for assigned departments/branches only |
| Learner | Own assignments, attempts, certificates and educational feedback |
| Auditor | Read-only, time-bound evidence access within explicitly granted scope |

Permissions are named actions (learning.publish, simulations.approve, reports.export),
evaluated by Laravel policies plus scope restrictions, never UI visibility alone. Membership
roles are tenant-scoped; administrative role changes are audited. Entitlements limit available
modules and seats but never grant permissions. Deny by default. Separate approval actors
are required for simulation release; a tenant administrator may approve only another actor's draft.

## Learning design

Courses have immutable published versions, locale variants, lesson ordering and an original
content provenance record. Draft/review/published/retired states prevent unreviewed AI drafts
from becoming learner content. Start with en, store locale codes for sn and nd plus future
locales; use framework translation keys and explicit fallback to English.

Learning paths reference versioned courses. Assignments target memberships or department/
branch cohorts and capture due dates, pass thresholds and version at assignment time.
Attempts store assessment version, score, result and completion time; server grading ignores
client scores. Accessible multimedia includes captions/transcripts and low-bandwidth alternatives.
Private media uses short-lived policy-checked URLs; scan uploads and enforce type/size limits.
Reminders are idempotent, tenant-scoped queued notifications with user preferences.
Certificates are immutable evidence with opaque verification IDs and revocation state; public
verification shows minimal consented information and is rate-limited, with no enumerable list.

## Risk, reporting and commercial design

Versioned risk weights, time windows, missing-data handling and explanations accompany every
snapshot. Example indicators: overdue assignment proportion, assessment gaps and simulation
learning outcomes. A click is an awareness signal, never proof of misconduct. Suppress small
cohorts and restrict person-level views. Reports use authorised snapshots and short-lived private
exports, recording actor, purpose, scope and retention expiry.

Proposed tiers: Starter for SMEs (core learning/reporting), Growth for medium organisations
(safe simulations and trends), Enterprise (advanced governance and future integrations).
Per-active-user annual plans, trial dates, seat limits and module entitlements are data design
only. Prices, taxation and live payments require later approval. Integrations use tenant-scoped
adapters and secrets, permission scopes, webhook verification and replay protection. Entra ID,
Microsoft 365, AD, SAML/OIDC, HR and external LMS connections are unimplemented.

## Operational design

Use UTC timestamps and tenant display time zones (initial default Africa/Harare). Queue and
scheduler processes use dedicated identities, bounded retries, failed-job handling and audit
correlation IDs. Never log credentials, simulation bearer tokens or raw AI prompts by default.
Encrypted transport, encrypted storage/backups, a secret manager, restore drills and retention
automation are deployment gates. Local mail defaults to log; Phase 0 has no simulation sender.
REST APIs will be versioned with scoped tokens, rate limits and idempotency where necessary.
PWA readiness means responsive assets and future manifest/offline strategy; no service worker
or offline employee-data caching is implemented. Avoid third-party fonts and tracking scripts.

References: [Laravel 12](https://laravel.com/docs/12.x),
[PostgreSQL RLS](https://www.postgresql.org/docs/16/ddl-rowsecurity.html),
[OWASP ASVS](https://owasp.org/www-project-application-security-verification-standard/).
