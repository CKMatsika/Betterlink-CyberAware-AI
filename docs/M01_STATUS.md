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
configuration; local default is the Laravel log mailer.

Migrations: tenant schema, tenant RLS, Fortify two-factor fields and passkey table. Passkeys are
not enabled in routes. Runtime tenant tables require restricted-role access; use the dedicated
`cyberaware_app` role, never the cluster owner. A loopback-only PostgreSQL 16 probe verified
missing-context denial, tenant A visibility and tenant B denial, with test rows rolled back.

Known verification gap: the RLS probe was run directly with `psql`, not through the Laravel
HTTP/queue stack using the restricted role. Invitation issuance/acceptance, MFA setup/challenge,
role denial and audit event transactions still need end-to-end integration/browser tests and
role/bootstrap testing on a fresh database. Do not use employee data until those checks pass.
No Phase 2 learning work is included.
