import { Head, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function TrainingLanding({ context, template }: { context: string; template: string }) {
  const [token, setToken] = useState('');
  const event = useForm({ context, token, event: 'education_viewed' });
  useEffect(() => {
    const value = new URLSearchParams(window.location.hash.slice(1)).get('t') ?? '';
    setToken(value);
    event.setData('token', value);
    window.history.replaceState(null, '', window.location.pathname + window.location.search);
  }, []);
  return <main className="mx-auto max-w-2xl p-6 text-slate-100">
    <Head title="Security learning note" />
    <article className="rounded-xl border border-slate-700 bg-slate-900 p-6 sm:p-8">
      <p className="text-sm font-semibold uppercase tracking-wide text-teal-300">Cybersecurity awareness</p>
      <h1 className="mt-3 text-3xl font-semibold">Pause and check before opening a link</h1>
      <p className="mt-4 text-slate-300">Messages can create urgency to make us act quickly. Before opening a link, check the sender and read the full destination. When in doubt, open the organisation’s known website directly or contact your security team.</p>
      <ul className="mt-5 list-disc space-y-2 pl-5 text-slate-300">
        <li>Check the complete domain, including small spelling changes.</li>
        <li>Do not enter passwords or verification codes from an email link.</li>
        <li>Report unexpected messages using your organisation’s normal process.</li>
      </ul>
      <p className="mt-5 rounded-lg bg-slate-800 p-4 text-sm text-slate-300">This was a controlled awareness exercise. It collected no credentials or personal form data. The exercise is for learning and is not a measure of a person’s performance.</p>
      <button disabled={!token || event.processing || event.wasSuccessful} onClick={() => event.post('/simulation/learn-event')} className="mt-6 rounded bg-teal-600 px-5 py-3 disabled:opacity-50">{event.wasSuccessful ? 'Learning note acknowledged' : 'I have reviewed this learning note'}</button>
      {event.errors.token && <p role="alert" className="mt-3 text-amber-300">This training link could not be recorded. You can still read the learning note.</p>}
    </article>
  </main>;
}
