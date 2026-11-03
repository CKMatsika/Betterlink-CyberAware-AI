import { Head, useForm } from '@inertiajs/react';

export default function ForgotPassword() {
    const form=useForm({email:''});
    return <main className="mx-auto mt-20 max-w-md rounded-xl border border-slate-800 bg-slate-900 p-8 text-slate-100"><Head title="Reset password"/><h1 className="text-2xl font-semibold">Reset your password</h1><p className="mt-2 text-sm text-slate-400">We’ll email a reset link if the account exists.</p><form className="mt-6 space-y-4" onSubmit={e=>{e.preventDefault();form.post('/forgot-password');}}><label className="block text-sm">Work email<input required type="email" autoComplete="email" className="mt-1 w-full rounded border border-slate-700 bg-slate-950 p-3" value={form.data.email} onChange={e=>form.setData('email',e.target.value)}/></label>{form.errors.email&&<p role="alert" className="text-red-300">{form.errors.email}</p>}<button className="w-full rounded bg-teal-600 p-3">Send reset link</button></form></main>;
}
