import { Head, Link, useForm } from '@inertiajs/react';

export default function Login() {
    const form = useForm({ email: '', password: '', remember: false });
    return <main className="mx-auto mt-20 max-w-md rounded-xl border border-slate-800 bg-slate-900 p-8 text-slate-100">
        <Head title="Sign in" /><Link className="text-sm text-teal-300" href="/">← Betterlink CyberAware AI</Link><h1 className="mt-4 text-2xl font-semibold">Sign in to CyberAware AI</h1>
        <form className="mt-6 space-y-4" onSubmit={(e) => { e.preventDefault(); form.post('/login'); }}>
            <label className="block text-sm">Work email<input className="mt-1 w-full rounded border border-slate-700 bg-slate-950 p-3" type="email" autoComplete="username" value={form.data.email} onChange={e => form.setData('email', e.target.value)} required /></label>
            <label className="block text-sm">Password<input className="mt-1 w-full rounded border border-slate-700 bg-slate-950 p-3" type="password" autoComplete="current-password" value={form.data.password} onChange={e => form.setData('password', e.target.value)} required /></label>
            <label className="flex gap-2 text-sm"><input type="checkbox" checked={form.data.remember} onChange={e => form.setData('remember', e.target.checked)} />Remember me</label>
            {form.errors.email && <p role="alert" className="text-red-300">{form.errors.email}</p>}
            <button disabled={form.processing} className="w-full rounded bg-teal-600 p-3 font-medium">Sign in</button>
        </form><Link className="mt-4 inline-block text-sm text-teal-300" href="/forgot-password">Forgot password?</Link>
        <p className="mt-6 text-sm text-slate-400">Access is by organisation invitation. Public registration is disabled.</p><p className="mt-2 text-sm text-slate-400">Need access or setting up the first organisation? <Link className="text-teal-300 underline" href="/get-started">Get started</Link>.</p>
    </main>;
}
