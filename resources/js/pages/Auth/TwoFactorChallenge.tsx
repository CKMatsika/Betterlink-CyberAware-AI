import { Head, useForm } from '@inertiajs/react';

export default function TwoFactorChallenge() {
    const form = useForm({ code: '', recovery_code: '' });
    return <main className="mx-auto mt-20 max-w-md rounded-xl border border-slate-800 bg-slate-900 p-8 text-slate-100"><Head title="Two factor check" /><h1 className="text-2xl font-semibold">Verify your sign in</h1><form className="mt-6 space-y-4" onSubmit={e => { e.preventDefault(); form.post('/two-factor-challenge'); }}><label className="block text-sm">Authenticator code<input className="mt-1 w-full rounded border border-slate-700 bg-slate-950 p-3" inputMode="numeric" autoComplete="one-time-code" value={form.data.code} onChange={e => form.setData('code', e.target.value)} /></label><label className="block text-sm">Recovery code<input className="mt-1 w-full rounded border border-slate-700 bg-slate-950 p-3" value={form.data.recovery_code} onChange={e => form.setData('recovery_code', e.target.value)} /></label><button className="w-full rounded bg-teal-600 p-3">Continue</button></form></main>;
}
