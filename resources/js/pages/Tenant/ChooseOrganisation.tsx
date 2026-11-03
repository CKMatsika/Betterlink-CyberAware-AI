import { Head, Link, router } from '@inertiajs/react';

export default function ChooseOrganisation() {
  return <main className="mx-auto mt-16 max-w-xl rounded-xl border border-slate-800 bg-slate-900 p-8 text-slate-100">
    <Head title="Choose an organisation" />
    <p className="text-sm text-teal-300">Betterlink CyberAware AI</p>
    <h1 className="mt-3 text-2xl font-semibold">You’re signed in</h1>
    <p className="mt-3 leading-6 text-slate-300">This account has no selected organisation. Open your organisation invitation, or ask its administrator to invite your work email.</p>
    <div className="mt-6 flex flex-wrap gap-3"><Link href="/get-started" className="rounded bg-teal-600 px-4 py-2">Get started</Link><button onClick={() => router.post('/logout')} className="rounded border border-slate-700 px-4 py-2">Sign out</button></div>
  </main>;
}
