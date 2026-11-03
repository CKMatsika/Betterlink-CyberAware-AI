import { Head, Link, useForm } from '@inertiajs/react';

type Assignment = {
  id: string; due_at: string | null; status: string;
  course: { id: string; title: string; description: string; locale: string };
  lessons: { id: string; position: number; title: string; estimated_minutes: number; completed: boolean }[];
  assessment: { passing_score: number; max_attempts: number | null; questions: { id: string; prompt: string; options: { key: string; label: string }[] }[] };
  attempts: { attempt_number: number; score: number; passed: boolean; submitted_at: string }[];
};

function AssessmentForm({ organisationId, assignment }: { organisationId: string; assignment: Assignment }) {
  const form = useForm<{ answers: Record<string, string> }>({ answers: {} });
  const latest = assignment.attempts[0];
  const locked = (assignment.assessment.max_attempts !== null && assignment.attempts.length >= assignment.assessment.max_attempts) || Boolean(latest?.passed);
  return <form className="mt-5 space-y-5 border-t border-slate-700 pt-5" onSubmit={e => { e.preventDefault(); form.post(`/org/${organisationId}/learning/assignments/${assignment.id}/assessment`); }}>
    <h3 className="font-semibold">Knowledge check · pass at {assignment.assessment.passing_score}%</h3>
    {assignment.assessment.questions.map((question, index) => <fieldset key={question.id} className="space-y-2"><legend>{index + 1}. {question.prompt}</legend>{question.options.map(option => <label key={option.key} className="flex gap-2 text-slate-300"><input type="radio" required name={question.id} checked={form.data.answers[question.id] === option.key} onChange={() => form.setData('answers', { ...form.data.answers, [question.id]: option.key })} />{option.label}</label>)}</fieldset>)}
    {assignment.attempts.length > 0 && <p role="status" className="text-sm text-slate-300">Latest result: {latest.score}% · {latest.passed ? 'Passed' : 'Not yet passed'} · {assignment.attempts.length} attempt(s)</p>}
    <button disabled={form.processing || locked} className="rounded bg-teal-600 px-4 py-2">Submit assessment</button>
    {form.errors.answers && <p role="alert" className="text-red-300">Answer every question before submitting.</p>}
  </form>;
}

export default function Catalogue({ organisation, assignments }: { organisation: { id: string; name: string }; assignments: Assignment[] }) {
  return <main className="mx-auto max-w-5xl p-8 text-slate-100"><Head title={`Learning · ${organisation.name}`} /><Link className="text-teal-300" href={`/org/${organisation.id}`}>← {organisation.name}</Link><h1 className="mt-5 text-3xl font-semibold">My learning</h1><p className="mt-2 text-slate-400">Your assigned courses and progress.</p>
    {assignments.length === 0 ? <p className="mt-8 rounded-xl border border-slate-700 p-6 text-slate-300">You have no assigned courses yet.</p> : <div className="mt-8 space-y-6">{assignments.map(assignment => <article key={assignment.id} className="rounded-xl border border-slate-700 bg-slate-900 p-6"><div className="flex flex-wrap items-start justify-between gap-3"><div><h2 className="text-xl font-semibold">{assignment.course.title}</h2><p className="mt-1 text-slate-400">{assignment.course.description}</p></div><span className="text-sm text-slate-400">{assignment.course.locale.toUpperCase()}{assignment.due_at ? ` · Due ${new Date(assignment.due_at).toLocaleDateString()}` : ''}</span></div><h3 className="mt-5 font-medium">Lessons</h3><ol className="mt-2 space-y-2">{assignment.lessons.map(lesson => <li key={lesson.id} className="flex flex-wrap items-center justify-between gap-2 rounded border border-slate-800 p-3"><Link className="text-teal-300 underline" href={`/org/${organisation.id}/learning/assignments/${assignment.id}/lessons/${lesson.id}`}>{lesson.position}. {lesson.title}</Link><span className="text-sm text-slate-400">{lesson.completed ? 'Complete' : `${lesson.estimated_minutes} min`}</span></li>)}</ol><AssessmentForm organisationId={organisation.id} assignment={assignment} /></article>)}</div>}
  </main>;
}
