# Security and simulation threat model

Phase 0 design, 2026-11-03. Controls below are implementation requirements unless
explicitly identified as already present. No simulation sender or employee dataset exists.
Target OWASP ASVS 5.0 Level 2 for future authenticated releases; this is not a certification.

## Assets, actors and trust boundaries

Assets: employee identities, training evidence, private media, tenant boundaries, organisational
sender reputation, approval records, provider secrets, AI context and audit integrity.
Actors: learners, tenant staff, privileged operators, malicious insiders, external attackers,
AI providers and future email/identity vendors. Boundaries: browser/web, web/database,
web/private storage, request/queue, application/AI vendor, simulation/email recipient,
platform operator/tenant. Authenticate and authorise independently at each boundary.

| Threat | Required controls | Acceptance evidence before release |
| --- | --- | --- |
| Forged tenant IDs or object references | Verified membership context, scoped bindings, policies, composite foreign keys, forced RLS using restricted runtime role | Two-tenant read/write/export/storage and queue negative tests, including raw SQL |
| Worker context leakage | Tenant IDs in jobs, transactional SET LOCAL, reset in finally, revalidate membership and approvals | Sequential jobs for different tenants on same worker; missing/stale context denied |
| Role escalation and compromised admin | Deny-default policies, scoped RBAC, MFA/step-up, expiring invitations, privileged audit | Privilege matrix tests, revoked membership and department scope tests |
| Public-target simulation abuse | Verified organisation domains plus verified active employee recipients; immutable approved list; no arbitrary send API | External/unverified recipient denied, suppression checked at execution |
| Credential collection or harmful content | No password/MFA/token/banking fields; no arbitrary form bodies, executable attachments, custom JavaScript or login clones | Template allowlist tests; payloads discarded without logs; reviewed educational landing pages |
| Scope changes after approval | Approval binds digest of content, recipients, sender, schedule and limits; edits invalidate approval | Tampered/expired/self-approved/revoked campaigns cannot queue or deliver |
| Delivery amplification/replay | Tenant and global quotas, recipient dedupe, bounded batches, idempotent outbox, kill switch, re-check suppressions | Retry, cancellation, cap and emergency-stop tests |
| Link leakage and forged events | Opaque high-entropy tokens stored hashed, TLS, short expiry, constant-time lookup, no raw IP retention by default | Expired/replayed links rejected; scanner events distinguished from confirmed learning actions |
| Employee surveillance or punitive scoring | Purpose limitation, minimised telemetry, access scopes, transparent indicators, small-cohort suppression, retention | Privacy review, scoped exports, correction/deletion workflow tests |
| Malicious media and export injection | Private storage, type/size validation, malware scanning, policy-gated download, CSV formula escaping | Upload bypass, signed-URL expiry, cross-tenant download and CSV tests |
| Audit tampering or repudiation | Append-only application audit, restricted deletion, external immutable export/checksum, actor and correlation IDs | Approval and role changes reconstructable; no secrets in audit payloads |
| AI prompt injection or data exfiltration | Tenant-filtered approved retrieval, untrusted input separation, no autonomous actions, bounded outputs | Injection/cross-tenant retrieval tests and reviewed publication gate |
| SQL/XSS/CSRF/session abuse | Bound queries, React escaping, no unsafe HTML, CSRF, secure cookies, session rotation, rate limiting | ASVS-based tests and browser checks on authenticated flows |
| Dependency compromise and secret leaks | Lockfiles, CI audits, limited token scopes, ignored local secrets, release review | Audit results, secret scanning and reproducible builds |

## Simulation lifecycle — future implementation

1. Tenant administrator records explicit authorisation, owned domains, verified recipient scope,
   permitted dates, maximum volume and a responsible contact. Expiry/revocation halts work.
2. Security officer creates a draft using reviewed original templates and verified memberships.
   Templates use approved sender domains and educational links; no executable attachments,
   credential harvesting, tracking evasion or unrestricted target imports.
3. A distinct authorised administrator reviews and approves the immutable scope digest.
4. Scheduler queues bounded deliveries only within the approved time window. Before each
   send, check approval validity, authorisation, active recipient, suppression, quota and kill switch.
5. A landing page explains the exercise and teaches relevant concepts. Track only allowlisted
   event types (delivered, link_visit, education_viewed); collect no submitted secrets. Do not
   ingest arbitrary POST bodies. Tokens must not appear in analytics, referrers or application logs.
6. Distinguish automated mail scanners and duplicate requests; do not treat raw opens/clicks
   as reliable human behaviour or evidence of negligence. Reports disclose uncertainty.
7. Assign remedial learning idempotently according to reviewed rules; provide supportive
   feedback and an appeal/correction route. Audit cancellation, approval and report access.

Phase 0 has no delivery service, send routes, simulation templates or credentials. Laravel's
general mail driver is log for local development only; it must not log future campaign tokens.

## Privacy and deployment gates

Before handling employee data, confirm controller/processor responsibilities, lawful purpose,
employee notices, retention schedules, subject rights, subprocessors, hosting region and
cross-border transfer requirements with qualified local review. Zimbabwean obligations must
be checked against current official guidance at that phase; Phase 0 makes no compliance claim.
Do not collect sensitive employee categories without a demonstrated need and separate review.
Initial retention proposals for review: raw simulation events 90 days, AI request metadata
30 days, audit/completion evidence 12 months; contractual/legal obligations may alter these.

Require TLS, APP_DEBUG=false, trusted-host configuration, secure session cookies, CSP tuned
to built assets, secret management, encrypted backups, recovery tests, least-privilege database
roles, queue isolation and incident-response contacts before any external deployment.
Residual risks include compromised administrators, mail scanner false positives, indirect
identification in small cohorts and AI provider exposure. Security and privacy owners must
review these before the corresponding feature phase is approved.

References: [OWASP ASVS 5.0](https://asvs.owasp.org/),
[PostgreSQL 16 row security](https://www.postgresql.org/docs/16/ddl-rowsecurity.html).
