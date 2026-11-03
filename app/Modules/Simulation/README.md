# M03 — Safe phishing simulations

The simulation module is tenant-scoped and sandbox-only. Administrators prove domain control with
a DNS TXT challenge, authorize a bounded exact recipient list, and enable delivery separately.
Security officers can draft a campaign from the fixed original training template. A different
tenant administrator approves its digest-bound scope. Scheduling and each delivery recheck the
approval, authorisation, tenant kill switch, recipient membership/email verification and
suppression state.

The scheduler persists due items in the minimal `simulation_dispatch_queue` table, then opens each
tenant outbox under `TenantContext` and PostgreSQL RLS. It always selects Laravel's in-memory `array`
mailer. No SMTP/API provider is reachable through the simulation delivery code.

The educational page does not ask for secrets. The raw one-time feedback token lives in the URL
fragment, is removed by the page script, and is stored only as a hash with a 30-day expiry. The API
accepts only a deduplicated `education_viewed` event; it records no IP, user-agent or arbitrary form
payload. A published remedial course can be assigned idempotently. Reports contain aggregate
counts rather than employee risk labels.

See [the M03 status evidence](../../../docs/IMPLEMENTATION_STATUS.md) for verification and limits.
Do not enable real email sending or load real employee data under this phase approval.
