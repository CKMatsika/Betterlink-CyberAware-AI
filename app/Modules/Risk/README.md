# Human cyber-risk intelligence (M04)

M04 provides tenant-wide, explainable awareness indicators from course completion,
assessment pass outcomes, and positive educational acknowledgements after sandbox simulations.
The awareness index is higher when observed learning signals are stronger. It is not an
individual risk score and does not infer intent, negligence, or misconduct.

Tenants can append immutable indicator-weight versions. Each month in the trend uses the
configuration effective at that period end. A metric with fewer than five contributing members
is suppressed; available weights are renormalized when other signals have no data or are
suppressed. No member names, individual rows, or department comparisons are returned.

The dashboard requires `reports.view`; changing weights requires `risk.configure` (tenant
administrators). Tenant RLS applies to indicator versions and all source data. Dashboard views
and configuration changes are audited.

See [the M04 verification evidence](../../../docs/IMPLEMENTATION_STATUS.md). M04 is for controlled
development review only; it is not a production employee-risk assessment.
