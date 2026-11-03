import { Head, Link } from '@inertiajs/react';

const command = 'php artisan cyberaware:bootstrap-tenant "Organisation name" organisation-slug admin@example.com "Admin Name"';

export default function GettingStarted() {
  return <main className="mx-auto max-w-4xl p-5 text-slate-100 sm:p-8">
    <Head title="Get started · Betterlink CyberAware AI" />
    <Link href="/" className="text-teal-300">← Betterlink CyberAware AI</Link>
    <header className="mt-8"><p className="text-sm uppercase tracking-widest text-teal-300">Early access</p><h1 className="mt-2 text-3xl font-semibold">Get started with CyberAware AI</h1><p className="mt-3 max-w-2xl text-slate-300">This development preview uses organisation-controlled access. Public account registration is disabled so that people can only join through their organisation.</p></header>

    <section className="mt-8 grid gap-5 md:grid-cols-2">
      <article className="rounded-xl border border-slate-800 bg-slate-900 p-6"><h2 className="text-xl font-semibold">Joining an organisation</h2><ol className="mt-4 list-inside list-decimal space-y-3 text-sm leading-6 text-slate-300"><li>Ask your organisation administrator to invite your work email.</li><li>Open the invitation email and accept it to set up your account.</li><li>Return here and sign in to open your organisation workspace.</li></ol><Link href="/login" className="mt-5 inline-block rounded bg-teal-600 px-4 py-2 font-medium">Sign in</Link></article>
      <article className="rounded-xl border border-slate-800 bg-slate-900 p-6"><h2 className="text-xl font-semibold">Setting up the first organisation</h2><p className="mt-3 text-sm leading-6 text-slate-300">The deployment owner creates the first administrator from Laravel Cloud’s command runner. This one-time command is available only while the production users table is empty; it does not enable public registration.</p><p className="mt-4 text-sm text-slate-400">Add a temporary secret named <code className="text-teal-200">CYBERAWARE_BOOTSTRAP_PASSWORD</code> in the Cloud environment. Use a unique password of at least 16 characters.</p><pre className="mt-3 overflow-x-auto rounded bg-slate-950 p-4 text-xs text-teal-100"><code>{command}</code></pre><p className="mt-3 text-sm leading-6 text-slate-400">Run it after the database migrations complete. The command prints the sign-in link, then removes the temporary bootstrap secret from the current process. Remove the Cloud secret from the environment immediately afterward.</p></article>
    </section>

    <p className="mt-8 rounded-lg border border-amber-900/70 bg-amber-950/30 p-4 text-sm leading-6 text-amber-100">This is a controlled development preview, not a production service. Use synthetic data only until the deployment’s tenant database roles, mail delivery, backups and security controls have been reviewed.</p>
  </main>;
}
