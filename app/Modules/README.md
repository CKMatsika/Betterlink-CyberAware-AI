# Domain boundaries

Phase 0 reserves these namespaces. No domain tables or business workflows are implemented.

| Namespace | Ownership |
| --- | --- |
| Foundation | Organisations, membership, invitations, scoped RBAC, entitlements, audit |
| Learning | Course versions, lessons, assessments, assignments, certificates |
| Simulation | Authorisation, recipient verification, approvals, safe scheduling and events |
| Risk | Versioned indicator definitions, snapshots and explanations |
| Assistant | Provider configuration, approved knowledge, review workflow |
| Reporting | Authorised read models and short-lived exports |
| Integration | Tenant-scoped connections and least-privilege API adapters |
| Commercial | Plan catalogue, seats, trials and billing state |

Each future domain uses Application, Domain and Infrastructure subdirectories as needed.
Controllers delegate to application services. Other modules call explicit application contracts;
they do not mutate another module's tables. Events carry tenant ID and opaque entity IDs,
never credentials or unnecessary employee details. Shared infrastructure remains under app/.
