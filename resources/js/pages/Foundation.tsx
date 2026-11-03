import { Head } from '@inertiajs/react';

type Module = { code: string; name: string; description: string };
type Props = { modules: Module[]; phase: string };

export default function Foundation({ modules, phase }: Props) {
    return (
        <>
            <Head title="Programme foundation" />
            <div className="min-h-screen bg-slate-950 text-slate-100">
                <header className="border-b border-slate-800">
                    <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-6 py-6">
                        <a href="/" className="font-semibold tracking-tight">Betterlink <span className="text-teal-400">CyberAware AI</span></a>
                        <span className="rounded-full border border-teal-800 bg-teal-950 px-3 py-1 text-xs text-teal-200">{phase} · Development foundation</span>
                    </div>
                </header>
                <main className="mx-auto max-w-6xl px-6 py-14">
                    <p className="text-sm font-medium uppercase tracking-widest text-teal-400">Betterlink Engineering</p>
                    <h1 className="mt-5 max-w-3xl text-4xl font-semibold leading-tight tracking-tight sm:text-6xl">Build awareness.<br />Strengthen your people.</h1>
                    <p className="mt-6 max-w-2xl text-lg leading-relaxed text-slate-300">Cybersecurity learning and human-risk management designed for organisations in Zimbabwe, Southern Africa and beyond.</p>
                    <aside className="mt-10 rounded-xl border border-amber-800/60 bg-amber-950/30 p-5" aria-label="Implementation status">
                        <h2 className="font-semibold text-amber-200">Foundation preview</h2>
                        <p className="mt-2 text-sm leading-relaxed text-amber-100/80">This page verifies the application stack. Accounts, training, simulations, AI, billing and integrations are planned and are not operational. No employee data is displayed.</p>
                    </aside>
                    <section className="mt-12" aria-labelledby="modules-heading">
                        <h2 id="modules-heading" className="text-2xl font-semibold">The programme ahead</h2>
                        <p className="mt-2 text-slate-400">Eight domains, one independently deployable platform.</p>
                        <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {modules.map((module) => (
                                <article key={module.code} className="rounded-xl border border-slate-800 bg-slate-900 p-5">
                                    <div className="flex items-center justify-between gap-2"><span className="font-mono text-xs text-teal-400">{module.code}</span><span className="text-xs text-slate-400">Planned</span></div>
                                    <h3 className="mt-5 font-semibold">{module.name}</h3>
                                    <p className="mt-3 text-sm leading-relaxed text-slate-400">{module.description}</p>
                                </article>
                            ))}
                        </div>
                    </section>
                    <section className="mt-12 grid gap-6 border-t border-slate-800 pt-8 sm:grid-cols-3" aria-label="Design principles">
                        <div><h2 className="font-medium">Private by design</h2><p className="mt-2 text-sm text-slate-400">Tenant isolation and minimal employee data.</p></div>
                        <div><h2 className="font-medium">Learning first</h2><p className="mt-2 text-sm text-slate-400">Educational feedback and explainable indicators.</p></div>
                        <div><h2 className="font-medium">Accessible foundations</h2><p className="mt-2 text-sm text-slate-400">Responsive layouts, local assets and no external fonts.</p></div>
                    </section>
                </main>
                <footer className="border-t border-slate-800 px-6 py-6 text-center text-xs text-slate-500">Betterlink Engineering · Independent development environment</footer>
            </div>
        </>
    );
}
