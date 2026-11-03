import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

type LessonDraft = { title: string; content: string; transcript: string; media: File | null };
type Option = { key: string; label: string };
type QuestionDraft = { prompt: string; options: Option[]; correct_option: string; feedback: string };
type Version = { id: string; version: number; title: string; description: string; locale: string; status: string; can_review: boolean; lessons: { id: string; title: string; content: string; transcript: string | null }[]; assessment: { passing_score: number; questions: QuestionDraft[] } };
type Course = { id: string; versions: Version[] };
type Member = { id: string; name: string; email: string };

export default function Admin({ organisation, courses, members }: { organisation: { id: string; name: string }; courses: Course[]; members: Member[] }) {
  const [editingVersion, setEditingVersion] = useState<string | null>(null);
  const draft = useForm<{ title: string; description: string; locale: string; passing_score: number; lessons: LessonDraft[]; questions: QuestionDraft[] }>({
    title: '', description: '', locale: 'en', passing_score: 70,
    lessons: [{ title: '', content: '', transcript: '', media: null }],
    questions: [{ prompt: '', options: [{ key: 'a', label: '' }, { key: 'b', label: '' }], correct_option: 'a', feedback: '' }],
  });
  const assignment = useForm<{ course_version_id: string; membership_ids: string[]; due_at: string }>({ course_version_id: '', membership_ids: [], due_at: '' });
  const versions = courses.flatMap(course => course.versions).filter(version => version.status === 'published');
  const updateLesson = (index: number, key: keyof LessonDraft, value: string | File | null) => draft.setData('lessons', draft.data.lessons.map((lesson, i) => i === index ? { ...lesson, [key]: value } : lesson));
  const updateQuestion = (index: number, key: keyof QuestionDraft, value: string | Option[]) => draft.setData('questions', draft.data.questions.map((question, i) => i === index ? { ...question, [key]: value } : question));
  const updateOption = (qIndex: number, oIndex: number, key: keyof Option, value: string) => {
    const question = draft.data.questions[qIndex];
    updateQuestion(qIndex, 'options', question.options.map((option, i) => i === oIndex ? { ...option, [key]: value } : option));
  };
  const editDraft = (version: Version) => {
    setEditingVersion(version.id);
    draft.setData({
      title: version.title, description: version.description, locale: version.locale,
      passing_score: version.assessment.passing_score,
      lessons: version.lessons.map(({ title, content, transcript }) => ({ title, content, transcript: transcript ?? '', media: null })),
      questions: version.assessment.questions.map(question => ({ ...question, feedback: question.feedback ?? '' })),
    });
  };
  return <main className="mx-auto max-w-5xl p-8 text-slate-100">
    <Head title={`Learning management · ${organisation.name}`} />
    <Link className="text-teal-300" href={`/org/${organisation.id}`}>← {organisation.name}</Link>
    <h1 className="mt-5 text-3xl font-semibold">Learning management</h1>
    <p className="mt-2 text-slate-400">Create original drafts. A different authorised manager reviews and publishes each version.</p>

    <form className="mt-8 space-y-5 rounded-xl border border-slate-700 bg-slate-900 p-6" onSubmit={e => { e.preventDefault(); editingVersion ? draft.put(`/org/${organisation.id}/learning/versions/${editingVersion}/draft`, { forceFormData: true }) : draft.post(`/org/${organisation.id}/learning/courses`, { forceFormData: true }); }}>
      <h2 className="text-xl font-semibold">{editingVersion ? 'Edit course draft' : 'Create course draft'}</h2>
      <label className="block">Course title<input required maxLength={180} className="mt-1 block w-full rounded border border-slate-700 bg-slate-950 p-3" value={draft.data.title} onChange={e => draft.setData('title', e.target.value)} /></label>
      <label className="block">Description<textarea required maxLength={5000} rows={3} className="mt-1 block w-full rounded border border-slate-700 bg-slate-950 p-3" value={draft.data.description} onChange={e => draft.setData('description', e.target.value)} /></label>
      <label className="block">Language<select className="mt-1 block rounded border border-slate-700 bg-slate-950 p-3" value={draft.data.locale} onChange={e => draft.setData('locale', e.target.value)}><option value="en">English</option><option value="sn">Shona</option><option value="nd">Ndebele</option></select></label>
      <section className="space-y-3"><h3 className="font-medium">Lessons</h3>{draft.data.lessons.map((lesson, i) => <fieldset key={i} className="space-y-2 rounded border border-slate-700 p-4"><legend>Lesson {i + 1}</legend><input required aria-label={`Lesson ${i + 1} title`} placeholder="Lesson title" className="w-full rounded border border-slate-700 bg-slate-950 p-3" value={lesson.title} onChange={e => updateLesson(i, 'title', e.target.value)} /><textarea required aria-label={`Lesson ${i + 1} content`} placeholder="Lesson content" rows={5} className="w-full rounded border border-slate-700 bg-slate-950 p-3" value={lesson.content} onChange={e => updateLesson(i, 'content', e.target.value)} /><label className="block text-sm">Optional video, audio or PDF (max 50 MB)<input type="file" accept="video/mp4,video/webm,audio/mpeg,audio/wav,audio/ogg,application/pdf" className="mt-1 block w-full" onChange={e => updateLesson(i, 'media', e.target.files?.[0] ?? null)} /></label><textarea aria-label={`Lesson ${i + 1} transcript`} placeholder="Optional media transcript" rows={2} className="w-full rounded border border-slate-700 bg-slate-950 p-3" value={lesson.transcript} onChange={e => updateLesson(i, 'transcript', e.target.value)} /></fieldset>)}<button type="button" className="text-teal-300" onClick={() => draft.setData('lessons', [...draft.data.lessons, { title: '', content: '', transcript: '', media: null }])}>+ Add lesson</button></section>
      <section className="space-y-3"><h3 className="font-medium">Assessment questions</h3>{draft.data.questions.map((question, qi) => <fieldset key={qi} className="space-y-2 rounded border border-slate-700 p-4"><legend>Question {qi + 1}</legend><textarea required aria-label={`Question ${qi + 1}`} placeholder="Question prompt" className="w-full rounded border border-slate-700 bg-slate-950 p-3" value={question.prompt} onChange={e => updateQuestion(qi, 'prompt', e.target.value)} />{question.options.map((option, oi) => <div key={option.key} className="flex flex-wrap items-center gap-2"><span className="w-6">{option.key.toUpperCase()}</span><input required aria-label={`Option ${option.key} text`} placeholder="Answer choice" className="min-w-0 flex-1 rounded border border-slate-700 bg-slate-950 p-2" value={option.label} onChange={e => updateOption(qi, oi, 'label', e.target.value)} /><label><input type="radio" name={`correct-${qi}`} checked={question.correct_option === option.key} onChange={() => updateQuestion(qi, 'correct_option', option.key)} /> Correct</label></div>)}<button type="button" className="text-teal-300" onClick={() => updateQuestion(qi, 'options', [...question.options, { key: String.fromCharCode(97 + question.options.length), label: '' }])} disabled={question.options.length >= 6}>+ Add choice</button></fieldset>)}<button type="button" className="text-teal-300" onClick={() => draft.setData('questions', [...draft.data.questions, { prompt: '', options: [{ key: 'a', label: '' }, { key: 'b', label: '' }], correct_option: 'a', feedback: '' }])}>+ Add question</button></section>
      <label className="block">Passing score (%)<input type="number" min={1} max={100} required className="mt-1 block w-32 rounded border border-slate-700 bg-slate-950 p-3" value={draft.data.passing_score} onChange={e => draft.setData('passing_score', Number(e.target.value))} /></label>
      {Object.keys(draft.errors).length > 0 && <p role="alert" className="text-red-300">Please review the course fields and try again.</p>}
      <button disabled={draft.processing} className="rounded bg-teal-600 px-5 py-3">{editingVersion ? 'Update draft' : 'Save draft'}</button>{editingVersion && <button type="button" className="ml-3 text-slate-300" onClick={() => { setEditingVersion(null); draft.reset(); }}>Cancel edit</button>}
    </form>

    <section className="mt-8 rounded-xl border border-slate-700 bg-slate-900 p-6"><h2 className="text-xl font-semibold">Courses and review</h2>{courses.length === 0 && <p className="mt-3 text-slate-400">No courses yet.</p>}{courses.map(course => course.versions.map(version => <div key={version.id} className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-700 pt-4"><div><h3 className="font-medium">{version.title} · v{version.version}</h3><p className="text-sm text-slate-400">{version.status} · {version.lessons.length} lessons</p></div><div className="flex gap-2">{version.status === 'draft' && !version.can_review && <button onClick={() => editDraft(version)} className="rounded border border-slate-500 px-4 py-2">Edit draft</button>}{version.status === 'draft' && (version.can_review ? <button onClick={() => router.post(`/org/${organisation.id}/learning/versions/${version.id}/publish`)} className="rounded border border-teal-500 px-4 py-2">Review &amp; publish</button> : <span className="self-center text-sm text-slate-400">Awaiting a different reviewer</span>)}{version.status === 'published' && <button onClick={() => router.post(`/org/${organisation.id}/learning/courses/${course.id}/versions`)} className="rounded border border-teal-500 px-4 py-2">Create next version</button>}</div></div>))}</section>

    <form className="mt-8 space-y-4 rounded-xl border border-slate-700 bg-slate-900 p-6" onSubmit={e => { e.preventDefault(); assignment.post(`/org/${organisation.id}/learning/assignments`); }}>
      <h2 className="text-xl font-semibold">Assign a published course</h2>
      <label className="block">Course version<select required className="mt-1 block w-full rounded border border-slate-700 bg-slate-950 p-3" value={assignment.data.course_version_id} onChange={e => assignment.setData('course_version_id', e.target.value)}><option value="">Select a course</option>{versions.map(version => <option key={version.id} value={version.id}>{version.title} · v{version.version}</option>)}</select></label>
      <fieldset><legend className="mb-2">Active learners</legend><div className="max-h-52 space-y-2 overflow-y-auto">{members.map(member => <label key={member.id} className="flex gap-3"><input type="checkbox" checked={assignment.data.membership_ids.includes(member.id)} onChange={e => assignment.setData('membership_ids', e.target.checked ? [...assignment.data.membership_ids, member.id] : assignment.data.membership_ids.filter(id => id !== member.id))} /><span>{member.name} <span className="text-slate-400">{member.email}</span></span></label>)}</div></fieldset>
      <label className="block">Due date (optional)<input type="datetime-local" className="mt-1 block rounded border border-slate-700 bg-slate-950 p-3" value={assignment.data.due_at} onChange={e => assignment.setData('due_at', e.target.value)} /></label>
      <button disabled={assignment.processing || versions.length === 0 || assignment.data.membership_ids.length === 0} className="rounded bg-teal-600 px-5 py-3">Assign course</button>
    </form>
  </main>;
}
