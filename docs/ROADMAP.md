# Independently approved implementation phases

M01 and M02 are complete for controlled development review. Stop here; later phases need explicit approval.

| Phase | Deliverables | Exit gate |
| --- | --- | --- |
| 0 — Foundation | Independent repository, framework shell, dedicated dev database, domain design, ERD, threat model, AI policy, CI | Framework/DB smoke checks, TypeScript, build, browser acceptance, documented limits, commit |
| 1 — Secure tenancy | M01 organisations, memberships, scoped roles, invitations, authentication, verification, MFA, policies, audit, RLS | Restricted-role PostgreSQL cross-tenant tests across HTTP/jobs/storage; RBAC matrix, recovery and session tests |
| 2 — Learning core | M02 original course drafts/review/versioning, lessons, private media, quizzes, assignments, learner dashboard | Server grading, progress/retry tests, accessibility and low-bandwidth browser acceptance |
| 3 — Evidence and reporting | Certificates/revocation/verification, reminders, M06 tenant/department reporting, private audit exports | Evidence integrity, minimal public verification, export authorisation, retention tests |
| 4 — Safe simulation | M03 authorisation, verified recipients, distinct approval, safe templates, kill switch, scheduled controlled delivery, remedial assignments | Abuse/threat tests, approval invalidation, quotas/suppression/retry/cancel, mail sandbox pilot only before external delivery approval |
| 5 — Human-risk intelligence | M04 configurable versioned indicators, trends, explanations and executive summaries | Reproducible scoring, missing-data rules, small-cohort privacy checks; no misconduct inference |
| 6 — AI assistant | M05 approved provider adapter, private retrieval, original drafts and mandatory review | AI privacy/injection evaluation, publication gate and kill switch |
| 7 — Commercial SaaS | M08 per-user/annual tiers, trials, seats, onboarding, entitlement enforcement and controlled billing | Entitlements cannot bypass policies; live payment integration requires explicit approval and sandbox verification |
| 8 — Enterprise integrations | M07 separately scoped Entra/M365/AD/SAML/OIDC/HR/LMS connectors | Provider-specific security, revocation, tenant mapping, replay and contract tests; label each integration operational only after verification |
| 9 — Release readiness | Independent deployment, backups/restore, observability, incident response, privacy/legal review, performance and localisation/PWA work | Security review, recovery drill, pilot acceptance and separate production deployment approval |

Before every phase: inspect repository/Git; review master specification and status; confirm
the authorised scope; run baseline tests; assess migration/compatibility risks. After every phase:
run automated tests, TypeScript, production build and browser acceptance; update evidence,
commit verified changes, report limits, then stop for approval. Never silently skip failed gates.
