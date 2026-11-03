# Implementation status — M01–M04 complete for controlled development review

Date: 2026-11-03 (Africa/Harare). Project: Betterlink CyberAware AI.
Repository: `https://github.com/CKMatsika/Betterlink-CyberAware-AI`, branch `main`.

## Phase status

M01 secure tenancy, M02 learning core, M03 safe simulation, and M04 human-risk intelligence are
implemented for controlled development review. M05 and later modules remain unapproved. This is not a production release,
security certification, or approval for real employee data. Do not load real tenant or employee
data.

## M03 delivered

- Tenant-scoped DNS TXT challenges for organisation domains. A domain must be verified before it
  can authorize any simulation recipient.
- Explicit administrator authorisations bind a verified domain, exact active memberships with
  verified email addresses, validity window, recipient cap and hourly delivery limit.
- One original fixed training template. There are no custom HTML/URLs, authentication clones,
  credential fields, attachments, executables or public target lists.
- Campaign drafts snapshot exact recipients, schedule, authorisation and optional published
  remedial course version into a SHA-256 scope digest. Approval is separate, time-limited and
  bound to that digest; an author cannot approve their own campaign.
- Scheduling revalidates the digest, approval, authorization, suppression list and tenant kill
  switch. Delivery rechecks authorization, verified membership email, suppression and the digest
  for every recipient. Cancellation and the kill switch cancel queued deliveries.
- The scheduler uses a minimal global queue containing tenant/outbox identifiers only. The worker
  establishes PostgreSQL tenant context before opening any tenant outbox or campaign data.
- All messages are forced through Laravel's in-memory `array` transport. The application does not
  send M03 messages through SMTP or an external mail API. The campaign landing page is educational,
  does not request credentials, stores no IP/user-agent, and accepts only an explicit
  `education_viewed` event using a 30-day, hashed bearer token in the URL fragment.
- Feedback events are deduplicated. Optional remedial assignments use published M02 course versions
  and the existing idempotent assignment key. Reports expose aggregate campaign counts only.
- M03 security-sensitive actions write tenant audit events; reports do not assign misconduct or
  negligence based on simulation behaviour.

## M04 delivered

- The tenant dashboard reports a six-month awareness index based on course-version completion,
  assessment pass outcomes, and positive post-simulation educational acknowledgements.
- The index is higher for stronger observed learning signals; it is not an individual risk score,
  and does not infer intent, negligence, or misconduct. Simulation interactions are not used as
  evidence of wrongdoing.
- Tenant administrators can append immutable indicator-weight versions. Each report period uses
  the configuration version effective at that period end. Learning completion timestamps are
  first-write-only so later repeat visits cannot rewrite an earlier period.
- Missing indicators are excluded and remaining weights are normalized. Metrics with fewer than
  five contributing memberships are suppressed; no names, individual rows, or department
  comparisons are returned.
- Dashboard access requires `reports.view`; configuration requires `risk.configure`. M04 source
  data and configuration are tenant-scoped by RLS, and dashboard/configuration actions are audited.

## Verification evidence

| Check | Result |
| --- | --- |
| PHP framework suite | 21 passed (126 assertions); includes M04 permission gates, append-only configuration, effective-period version selection, input validation, missing-data normalization, cohort suppression and aggregate completion scoring |
| Laravel Pint | Passed |
| TypeScript | `npm run typecheck` passed |
| Vite production build | Passed |
| Browser acceptance | Local public preview rendered the updated M04 phase and module status; authenticated dashboard access and controls are covered by feature tests rather than a signed-in browser session |
| PostgreSQL migrations | M03 and M04 migrations applied and `migrate:status` verified as `cyberaware_owner` |
| Restricted-role PostgreSQL check | Dispatcher and isolation probe used `cyberaware_app`; another tenant's simulation rows were invisible and a cross-tenant write was rejected by RLS |
| PostgreSQL end-to-end sandbox run | Synthetic `example.test` recipient passed approval, digest, outbox, scheduled dispatch, in-memory `ArrayTransport`, delivery event, public learning page, deduplicated education event and one remedial assignment |
| External delivery | None. The test recipient domain is reserved `.test`; the forced array transport recorded one in-memory message |
| M04 PostgreSQL migration | Tenant-scoped indicator-version migration applied and verified as `cyberaware_owner` |
| M04 PostgreSQL aggregate E2E | Synthetic five-member tenant run as restricted `cyberaware_app`: 80% course completion, 80% assessment pass rate, computed index 80, no member-level result rows, immutable configuration and RLS isolation verified |

The E2E fixture uses synthetic accounts and the local development database. Synthetic M03 QA rows
remain in that database. No real employee or customer records were used.

## Limits and follow-up

External mail delivery is deliberately disabled. Any future external delivery requires a separate
explicit approval, a reviewed provider adapter, verified sender-domain setup, provider-specific
retry/cancel and rate-limit abuse testing, and updated operational controls. The local sandbox
retry path stops after three failed attempts and schedules the first two retries five minutes
apart. The DNS verification path was exercised through a verified synthetic database fixture;
live DNS publication was not performed. A broad load/abuse matrix and formal accessibility audit
remain release-hardening work. Nothing in this status marks M03 production-ready.

M01 residual hardening evidence remains in [M01_STATUS.md](M01_STATUS.md). Continue to follow the
roadmap approval boundary; stop after M04 until the user authorises another module.
