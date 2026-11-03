# M01 — SaaS foundation status

Work authorised on 2026-11-03. This implementation establishes the M01 foundation and
invite-only tenant access. It is a development increment, not a production security certification.

Implemented: organisations, departments, branches, memberships, composite tenant-safe foreign
keys, tenant roles and permissions, invitations with hashed single-use random tokens and 48-hour
expiry, audit event records, forced PostgreSQL RLS, and transaction-local tenant context.
Fortify provides sign-in, password reset, email verification, password changes and two-factor
authentication. Public registration, profile email changes and passkeys are disabled. Tenant
routes require authenticated verified users, an active membership and (for invitations) the
named `members.manage` permission. The provisioning command creates a tenant and initial roles
for an existing verified user. The invitation link binds organisation ID and token; RLS scopes
the lookup and acceptance to that organisation.

Initial tenant roles are tenant_admin, learner, training_manager, security_officer,
departmental_manager and auditor. Permission definitions live globally; grants are tenant role
rows. Tenant administrators receive all defined permissions. Department/branch scoped manager
record filtering and platform administrator operations are not implemented. Audit writes exist
for invitations and tenant home access; a full immutable audit pipeline for every auth and admin
operation is not complete. Recovery email and invitation email delivery depend on mail provider
configuration; the local default is the array mailer, which suppresses delivery and avoids writing one-time links into logs.

Migrations: tenant schema, tenant RLS, Fortify two-factor fields and passkey table. Passkeys are
not enabled in routes. Runtime tenant tables require restricted-role access; use the dedicated
`cyberaware_app` role, never the cluster owner. A loopback-only PostgreSQL 16 probe verified
missing-context denial, tenant A visibility and tenant B denial, with test rows rolled back.

## End-to-end checks — 2026-11-03

Using a fresh synthetic test tenant and the non-superuser `cyberaware_app` PostgreSQL login:

| Flow | Result |
| --- | --- |
| Verified administrator password sign-in | Passed in browser; Fortify redirected to the configured landing page |
| Tenant dashboard | Passed through Laravel HTTP middleware and RLS as the restricted role; tenant admin role displayed |
| Member administration | Passed; tenant-scoped member row and role displayed |
| Invitation issue | Passed from member UI; generated 64-character token stored only as SHA-256; local log mailer delivered the message |
| Invitation acceptance | Passed in a guest browser session; account and learner membership created; invitation consumed |
| Invited learner sign-in | Passed; learner membership and learner role visible on tenant dashboard |
| Permission denial | Learner received HTTP 403 for member administration |
| Unrelated tenant URL | Received HTTP 404; no tenant membership data returned |
| MFA recovery challenge | Passed through Fortify two-factor challenge using a one-time recovery code |
| Tenant audit rows | Verified through restricted PostgreSQL role under tenant context: organisation creation, invitation creation/acceptance, member listing and dashboard events recorded |
| RLS isolation | Direct PostgreSQL probe: no context returned 0 tenant rows; tenant A returned its row; tenant B returned 0 rows. Probe rows rolled back |

These checks used a disposable local database tenant and browser account. The application server
was configured to connect as `cyberaware_app` during HTTP testing. The local migration/owner
credentials remain separate. The invitation email used the configured local log mailer; no external
email was sent.

Still unverified: authenticator-app TOTP entry (recovery-code MFA was verified), enrolling or
disabling MFA through a user-facing security settings page, password reset email delivery,
email verification resend/expiry, concurrent/replayed invite races, queue worker tenant context,
private object storage access, department/branch row scopes, remote CI and fresh-cluster bootstrap
under production-like role separation. The app remains a development project, not a production
readiness certification. No Phase 2 learning work is included.
