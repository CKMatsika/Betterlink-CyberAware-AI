import { Head, Link, router, useForm } from '@inertiajs/react';

type Domain = { id: string; domain: string; verified_at: string | null; verification_token: string | null };
type Authorisation = { id: string; domain: string; starts_at: string; expires_at: string; max_recipients: number; max_deliveries_per_hour: number; status: string; membership_ids: string[] };
type Member = { id: string; name: string; email: string };
type Campaign = { id: string; status: string; scheduled_at: string; template_key: string; recipient_count: number; delivered_count: number; education_viewed_count: number; can_approve: boolean };
type Course = { id: string; title: string; version: number };

const card = 'mt-6 space-y-4 rounded-xl border border-slate-700 bg-slate-900 p-5 sm:p-6';
const field = 'mt-1 block w-full rounded border border-slate-700 bg-slate-950 p-3';

export default function Manage({ organisation, settings, domains, authorisations, campaigns, members, courses, suppressedMembershipIds }: {
  organisation: { id: string; name: string; timezone: string }; settings: { enabled: boolean; kill_switch_active: boolean };
  domains: Domain[]; authorisations: Authorisation[]; campaigns: Campaign[]; members: Member[]; courses: Course[]; suppressedMembershipIds: string[];
}) {
  const base = `/org/${organisation.id}/simulations`;
  const domainForm = useForm({ domain: '' });
  const authForm = useForm({ domain_id: '', membership_ids: [] as string[], starts_at: '', expires_at: '', max_deliveries_per_hour: 10 });
  const campaignForm = useForm({ authorisation_id: '', membership_ids: [] as string[], scheduled_at: '', remedial_course_version_id: '' });
  const setEnabled = (enabled: boolean) => router.post(`${base}/settings`, { enabled });
  const toggle = <T extends { membership_ids: string[] }>(form: { data: T; setData: (key: 'membership_ids', value: string[]) => void }, id: string) => form.setData('membership_ids', form.data.membership_ids.includes(id) ? form.data.membership_ids.filter(value => value !== id) : [...form.data.membership_ids, id]);
  const selectedAuthorisation = authorisations.find(auth => auth.id === campaignForm.data.authorisation_id);
  const campaignMembers = members.filter(member => selectedAuthorisation?.membership_ids.includes(member.id) && !suppressedMembershipIds.includes(member.id));

  return <main className="mx-auto max-w-5xl p-5 text-slate-100 sm:p-8">
    <Head title={`Safe simulations · ${organisation.name}`} />
    <Link className="text-teal-300" href={`/org/${organisation.id}`}>← {organisation.name}</Link>
    <h1 className="mt-5 text-3xl font-semibold">Safe phishing simulations</h1>
    <p className="mt-2 text-slate-400">Controlled learning exercises. Delivery is fixed to an in-memory mail sandbox; no configured SMTP or external mail API is used.</p>

    <section className={card}>
      <h2 className="text-xl font-semibold">Emergency stop</h2>
      <p className="text-slate-300">Current state: {settings.kill_switch_active || !settings.enabled ? 'Stopped' : 'Sandbox enabled'}</p>
      <div className="flex flex-wrap gap-3"><button className="rounded bg-rose-700 px-4 py-2" onClick={() => setEnabled(false)}>Stop all queued delivery</button><button className="rounded border border-teal-500 px-4 py-2" disabled={settings.enabled && !settings.kill_switch_active} onClick={() => setEnabled(true)}>Enable sandbox</button></div>
      <div className="border-t border-slate-700 pt-3"><h3 className="font-medium">Recipient suppression list</h3><div className="mt-2 space-y-2">{members.map(member => <div key={member.id} className="flex flex-wrap items-center justify-between gap-2 text-sm"><span>{member.name} <span className="text-slate-400">{member.email}</span> · {suppressedMembershipIds.includes(member.id) ? 'Suppressed' : 'Eligible'}</span>{suppressedMembershipIds.includes(member.id) ? <button className="rounded border border-slate-500 px-3 py-1" onClick={() => router.post(`${base}/suppressions/${member.id}/remove`)}>Remove suppression</button> : <button className="rounded border border-rose-600 px-3 py-1" onClick={() => router.post(`${base}/suppressions`, { membership_id: member.id, reason: 'Administrative exclusion' })}>Suppress</button>}</div>)}</div></div>
    </section>

    <form className={card} onSubmit={e => { e.preventDefault(); domainForm.post(`${base}/domains`); }}>
      <h2 className="text-xl font-semibold">Verify an organisation email domain</h2>
      <p className="text-sm text-slate-400">Add the displayed TXT challenge at your DNS provider, then verify it. Only verified member email addresses at this exact domain can be authorised.</p>
      <label className="block">Domain<input required placeholder="example.org" className={field} value={domainForm.data.domain} onChange={e => domainForm.setData('domain', e.target.value)} /></label>
      <button disabled={domainForm.processing} className="rounded bg-teal-600 px-4 py-2">Create DNS challenge</button>
      <ul className="space-y-3">{domains.map(domain => <li key={domain.id} className="rounded border border-slate-700 p-3"><span>{domain.domain} · {domain.verified_at ? 'Verified' : 'Not verified'}</span>{!domain.verified_at && domain.verification_token && <><p className="mt-2 break-all text-sm text-slate-400">TXT name: _betterlink-cyberaware.{domain.domain}<br />TXT value: betterlink-verification={domain.verification_token}</p><button type="button" className="mt-2 rounded border border-teal-500 px-3 py-1" onClick={() => router.post(`${base}/domains/${domain.id}/verify`)}>Verify DNS</button></>}</li>)}</ul>
    </form>

    <form className={card} onSubmit={e => { e.preventDefault(); authForm.post(`${base}/authorisations`); }}>
      <h2 className="text-xl font-semibold">Authorise recipients</h2>
      <p className="text-sm text-slate-400">An organisation administrator authorises an exact verified-member list, time window and hourly delivery limit. Date and time fields use {organisation.timezone}.</p>
      <label className="block">Verified domain<select required className={field} value={authForm.data.domain_id} onChange={e => authForm.setData('domain_id', e.target.value)}><option value="">Select domain</option>{domains.filter(domain => domain.verified_at).map(domain => <option key={domain.id} value={domain.id}>{domain.domain}</option>)}</select></label>
      <div className="grid gap-3 sm:grid-cols-2"><label>Starts at<input required type="datetime-local" className={field} value={authForm.data.starts_at} onChange={e => authForm.setData('starts_at', e.target.value)} /></label><label>Expires at<input required type="datetime-local" className={field} value={authForm.data.expires_at} onChange={e => authForm.setData('expires_at', e.target.value)} /></label></div>
      <label className="block">Maximum deliveries per hour<input type="number" min={1} max={100} className={field} value={authForm.data.max_deliveries_per_hour} onChange={e => authForm.setData('max_deliveries_per_hour', Number(e.target.value))} /></label>
      <fieldset><legend className="mb-2">Verified active members at the selected domain</legend><div className="max-h-48 space-y-2 overflow-y-auto">{members.map(member => <label key={member.id} className="flex gap-3"><input type="checkbox" checked={authForm.data.membership_ids.includes(member.id)} disabled={suppressedMembershipIds.includes(member.id)} onChange={() => toggle(authForm, member.id)} /><span>{member.name} <span className="text-slate-400">{member.email}</span>{suppressedMembershipIds.includes(member.id) && <span className="text-rose-300"> · suppressed</span>}</span></label>)}</div></fieldset>
      <button disabled={authForm.processing || !authForm.data.domain_id || !authForm.data.membership_ids.length} className="rounded bg-teal-600 px-4 py-2">Create authorisation</button>
      {authorisations.map(auth => <p key={auth.id} className="text-sm text-slate-400">{auth.domain} · {auth.status} · {auth.max_recipients} recipients · until {new Date(auth.expires_at).toLocaleString()}</p>)}
    </form>

    <form className={card} onSubmit={e => { e.preventDefault(); campaignForm.post(`${base}/campaigns`); }}>
      <h2 className="text-xl font-semibold">Create a locked campaign draft</h2>
      <p className="text-sm text-slate-400">Only the fixed safe document-review template is available. No credential forms, attachments, custom links or custom HTML are supported. Approval binds to a digest of this exact scope. The scheduled time uses {organisation.timezone}.</p>
      <label className="block">Authorisation<select required className={field} value={campaignForm.data.authorisation_id} onChange={e => campaignForm.setData('authorisation_id', e.target.value)}><option value="">Select authorisation</option>{authorisations.filter(auth => auth.status === 'active').map(auth => <option key={auth.id} value={auth.id}>{auth.domain} · {auth.max_recipients} recipients · until {new Date(auth.expires_at).toLocaleString()}</option>)}</select></label>
      <label className="block">Scheduled time<input required type="datetime-local" className={field} value={campaignForm.data.scheduled_at} onChange={e => campaignForm.setData('scheduled_at', e.target.value)} /></label>
      <label className="block">Optional remedial course<select className={field} value={campaignForm.data.remedial_course_version_id} onChange={e => campaignForm.setData('remedial_course_version_id', e.target.value)}><option value="">No automatic assignment</option>{courses.map(course => <option key={course.id} value={course.id}>{course.title} · v{course.version}</option>)}</select></label>
      <fieldset><legend className="mb-2">Select from the authorised recipients</legend><div className="max-h-48 space-y-2 overflow-y-auto">{campaignMembers.map(member => <label key={member.id} className="flex gap-3"><input type="checkbox" checked={campaignForm.data.membership_ids.includes(member.id)} onChange={() => toggle(campaignForm, member.id)} /><span>{member.name} <span className="text-slate-400">{member.email}</span></span></label>)}</div>{selectedAuthorisation && campaignMembers.length === 0 && <p className="text-sm text-slate-400">No current verified recipients are available in this authorization.</p>}</fieldset>
      <button disabled={campaignForm.processing || !campaignForm.data.authorisation_id || !campaignForm.data.membership_ids.length} className="rounded bg-teal-600 px-4 py-2">Save campaign draft</button>
    </form>

    <section className={card}>
      <h2 className="text-xl font-semibold">Campaigns and aggregate results</h2>
      {campaigns.length === 0 && <p className="text-slate-400">No campaigns yet.</p>}
      {campaigns.map(campaign => <article key={campaign.id} className="space-y-2 border-t border-slate-700 pt-4"><h3 className="font-medium">{campaign.template_key.replaceAll('_', ' ')} · {campaign.status}</h3><p className="text-sm text-slate-400">Scheduled {new Date(campaign.scheduled_at).toLocaleString()} · {campaign.recipient_count} recipients · {campaign.delivered_count} sandbox deliveries · {campaign.education_viewed_count} learning acknowledgements</p><div className="flex flex-wrap gap-2">{campaign.status === 'draft' && campaign.can_approve && <button className="rounded border border-teal-500 px-3 py-1" onClick={() => router.post(`${base}/campaigns/${campaign.id}/approve`)}>Approve locked scope</button>}{campaign.status === 'approved' && <button className="rounded border border-teal-500 px-3 py-1" onClick={() => router.post(`${base}/campaigns/${campaign.id}/schedule`)}>Queue sandbox delivery</button>}{['draft', 'approved', 'scheduled'].includes(campaign.status) && <button className="rounded border border-rose-600 px-3 py-1" onClick={() => router.post(`${base}/campaigns/${campaign.id}/cancel`)}>Cancel</button>}</div></article>)}
    </section>
  </main>;
}
