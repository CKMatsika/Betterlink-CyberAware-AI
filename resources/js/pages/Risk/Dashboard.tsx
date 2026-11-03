import { Head, Link, useForm } from '@inertiajs/react';

type MetricKey = 'learning_completion' | 'assessment_pass_rate' | 'education_acknowledgement';
type Metric = { available: boolean; status: 'available' | 'suppressed' | 'no_data'; sample_size: number | null; rate: number | null; weight: number };
type Period = { period: string; score: number | null; metrics: Record<MetricKey, Metric>; has_suppressed_metrics: boolean };
type Props = {
  organisation: { id: string; name: string; timezone: string };
  dashboard: { configuration: { version: number; weights: Record<MetricKey, number> }; minimum_cohort: number; periods: Period[]; latest: Period | null; change: number | null; summary: string };
  canConfigure: boolean;
  labels: Record<MetricKey, { title: string; description: string }>;
};

const keys: MetricKey[] = ['learning_completion', 'assessment_pass_rate', 'education_acknowledgement'];
const field = 'mt-1 w-full rounded border border-slate-700 bg-slate-950 p-3';
const card = 'mt-6 rounded-xl border border-slate-800 bg-slate-900 p-5 sm:p-6';

function TrendChart({ periods }: { periods: Period[] }) {
  const points = periods.map((period, index) => period.score === null ? null : {
    x: 40 + index * 100,
    y: 145 - period.score * 1.25,
    label: period.period,
    score: period.score,
  });

  return <div>
    <svg role="img" aria-labelledby="trend-title trend-description" viewBox="0 0 560 180" className="mt-3 w-full">
      <title id="trend-title">Six-month awareness index trend</title>
      <desc id="trend-description">Higher values indicate stronger observed learning signals. Periods with insufficient cohort data are omitted from the plotted line.</desc>
      {[0, 50, 100].map(value => <g key={value}><line x1="32" x2="540" y1={145 - value * 1.25} y2={145 - value * 1.25} stroke="#334155" strokeDasharray="4 5"/><text x="4" y={149 - value * 1.25} fill="#94a3b8" fontSize="11">{value}</text></g>)}
      {points.map((point, index) => {
        const next = points[index + 1];
        return point && next ? <line key={`line-${point.label}`} x1={point.x} y1={point.y} x2={next.x} y2={next.y} stroke="#2dd4bf" strokeWidth="3"/> : null;
      })}
      {points.map((point, index) => point ? <g key={point.label}><circle cx={point.x} cy={point.y} r="5" fill="#2dd4bf"/><text x={point.x} y="168" textAnchor="middle" fill="#cbd5e1" fontSize="11">{new Date(`${point.label}-01T12:00:00`).toLocaleDateString(undefined, { month: 'short' })}</text><text x={point.x} y={point.y - 11} textAnchor="middle" fill="#f1f5f9" fontSize="11">{point.score}</text></g> : <text key={`missing-${index}`} x={40 + index * 100} y="168" textAnchor="middle" fill="#64748b" fontSize="11">—</text>)}
    </svg>
  </div>;
}

export default function Dashboard({ organisation, dashboard, canConfigure, labels }: Props) {
  const form = useForm({ weights: { ...dashboard.configuration.weights } });
  const total = keys.reduce((sum, key) => sum + Number(form.data.weights[key] ?? 0), 0);
  const current = dashboard.latest;

  return <main className="mx-auto max-w-6xl p-5 text-slate-100 sm:p-8">
    <Head title={`Awareness insights · ${organisation.name}`} />
    <Link href={`/org/${organisation.id}`} className="text-teal-300">← {organisation.name}</Link>
    <header className="mt-5"><p className="text-sm uppercase tracking-widest text-teal-300">M04 · Human cyber-risk intelligence</p><h1 className="mt-2 text-3xl font-semibold">Awareness insights</h1><p className="mt-2 max-w-3xl text-slate-400">An organization-level view of learning coverage and supportive training signals.</p></header>

    <section className={card} aria-live="polite">
      <div className="grid gap-5 md:grid-cols-[1fr_auto] md:items-center">
        <div><p className="text-sm text-slate-400">Current reportable period</p><h2 className="mt-1 text-2xl font-semibold">{current ? new Date(`${current.period}-01T12:00:00`).toLocaleDateString(undefined, { month: 'long', year: 'numeric' }) : 'Insufficient cohort data'}</h2><p className="mt-3 max-w-3xl text-slate-300">{dashboard.summary}</p></div>
        <div className="rounded-lg border border-slate-700 bg-slate-950 px-6 py-4 text-center"><p className="text-xs uppercase tracking-wide text-slate-400">Awareness index</p><p className="mt-1 text-4xl font-semibold text-teal-300">{current ? `${current.score}` : '—'}<span className="text-base text-slate-400"> / 100</span></p><p className="mt-1 text-xs text-slate-500">Higher is stronger</p></div>
      </div>
      <h3 className="mt-8 font-semibold">Six-month trend</h3><TrendChart periods={dashboard.periods} />
      <div className="overflow-x-auto"><table className="mt-3 w-full min-w-[520px] text-left text-sm"><caption className="sr-only">Awareness index by month</caption><thead className="text-slate-400"><tr><th className="py-2">Period</th><th className="py-2">Index</th><th className="py-2">Data note</th></tr></thead><tbody>{dashboard.periods.map(period => <tr key={period.period} className="border-t border-slate-800"><th scope="row" className="py-2 font-medium">{new Date(`${period.period}-01T12:00:00`).toLocaleDateString(undefined, { month: 'long', year: 'numeric' })}</th><td className="py-2">{period.score === null ? '—' : `${period.score} / 100`}</td><td className="py-2 text-slate-400">{period.score === null ? 'Not enough reportable data' : period.has_suppressed_metrics ? 'Some small-cohort indicators are withheld' : 'Aggregate indicators available'}</td></tr>)}</tbody></table></div>
    </section>

    <section className={card}>
      <h2 className="text-xl font-semibold">What informs this index</h2>
      <p className="mt-2 text-sm text-slate-400">For the current reportable period. Metrics with fewer than {dashboard.minimum_cohort} contributing members are withheld. No member-level results or department comparisons are shown.</p>
      <div className="mt-5 grid gap-4 md:grid-cols-3">{keys.map(key => {
        const metric = current?.metrics[key];
        return <article key={key} className="rounded-lg border border-slate-800 bg-slate-950 p-4"><p className="font-medium">{labels[key].title}</p><p className="mt-2 text-2xl font-semibold text-teal-300">{metric?.available ? `${metric.rate}%` : '—'}</p><p className="mt-2 text-sm text-slate-400">{labels[key].description}</p><p className="mt-3 text-xs text-slate-500">{metric?.available ? `Cohort: ${metric.sample_size} · Weight: ${metric.weight}%` : metric?.status === 'suppressed' ? 'Withheld because the cohort is below the privacy threshold.' : 'No eligible records in this period.'}</p></article>;
      })}</div>
    </section>

    {canConfigure && <section className={card}>
      <h2 className="text-xl font-semibold">Indicator configuration</h2>
      <p className="mt-2 text-sm text-slate-400">Configuration version {dashboard.configuration.version}. Saving creates an immutable new version. Weights must total 100%; missing or suppressed indicators are excluded and the remaining weights are normalized.</p>
      <form className="mt-5" onSubmit={event => { event.preventDefault(); form.post(`/org/${organisation.id}/risk/indicators`); }}>
        <div className="grid gap-4 md:grid-cols-3">{keys.map(key => <label key={key} className="text-sm">{labels[key].title} weight (%)<input type="number" min="0" max="100" className={field} value={form.data.weights[key]} onChange={event => form.setData('weights', { ...form.data.weights, [key]: Number(event.target.value) })} /></label>)}</div>
        <p className={`mt-3 text-sm ${total === 100 ? 'text-slate-400' : 'text-amber-300'}`}>Total: {total}% {total !== 100 && '· weights must total 100%'}</p>
        {form.errors.weights && <p role="alert" className="mt-2 text-rose-300">{form.errors.weights}</p>}
        <button disabled={form.processing || total !== 100} className="mt-4 rounded bg-teal-600 px-4 py-2 font-medium disabled:cursor-not-allowed disabled:opacity-50">Save new configuration version</button>
      </form>
    </section>}

    <section className={`${card} border-amber-900/70`}>
      <h2 className="font-semibold text-amber-200">How to use this report</h2>
      <p className="mt-2 text-sm leading-6 text-slate-300">This index summarizes observed learning activity for an organization. It is not an employee risk score, a measure of intent, or evidence of negligence. A simulation interaction is never treated as misconduct; this report uses only the educational follow-up acknowledgement. Use changes to improve training access and support, and consider missing data before comparing periods.</p>
    </section>
  </main>;
}
