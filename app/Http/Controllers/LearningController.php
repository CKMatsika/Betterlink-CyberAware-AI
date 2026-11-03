<?php

namespace App\Http\Controllers;

use App\Modules\Foundation\Audit;
use App\Modules\Foundation\CurrentMembership;
use App\Modules\Foundation\Models\Membership;
use App\Modules\Learning\AssessmentGrader;
use App\Modules\Learning\Models\AssessmentAttempt;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseAssignment;
use App\Modules\Learning\Models\CourseVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

final class LearningController extends Controller
{
    public function admin(Request $request): Response
    {
        abort_unless(app(CurrentMembership::class)->membership->hasPermission('learning.manage'), 403);
        $courses = Course::with('versions.lessons', 'versions.assessment.questions')
            ->where('organisation_id', app(CurrentMembership::class)->membership->organisation_id)
            ->latest()->get();
        $courses->each(fn ($course) => $course->versions->each(fn ($version) => $version->setAttribute('can_review', $version->created_by !== $request->user()->id)));
        $members = Membership::with('user:id,name,email')->where('organisation_id', app(CurrentMembership::class)->membership->organisation_id)
            ->where('status', 'active')->limit(250)->get()->map(fn ($member) => [
                'id' => $member->id, 'name' => $member->user->name, 'email' => $member->user->email,
            ]);

        return Inertia::render('Learning/Admin', ['organisation' => app(CurrentMembership::class)->membership->organisation->only(['id', 'name']), 'courses' => $courses, 'members' => $members]);
    }

    public function store(Request $request, Audit $audit): RedirectResponse
    {
        abort_unless(app(CurrentMembership::class)->membership->hasPermission('learning.manage'), 403);
        $data = $this->draftData($request);
        $orgId = app(CurrentMembership::class)->membership->organisation_id;
        $version = DB::transaction(function () use ($data, $orgId, $request) {
            $slug = Str::slug($data['title']).'-'.Str::lower(Str::random(8));
            $course = Course::create(['organisation_id' => $orgId, 'slug' => $slug, 'status' => 'draft', 'created_by' => $request->user()->id]);
            $version = CourseVersion::create(['organisation_id' => $orgId, 'course_id' => $course->id, 'version' => 1, 'locale' => $data['locale'], 'title' => $data['title'], 'description' => $data['description'], 'status' => 'draft', 'created_by' => $request->user()->id]);
            foreach ($data['lessons'] as $position => $lesson) {
                $path = isset($lesson['media']) ? $lesson['media']->store("learning/{$orgId}", 'local') : null;
                $version->lessons()->create(['organisation_id' => $orgId, 'position' => $position + 1, 'title' => $lesson['title'], 'content' => $lesson['content'], 'transcript' => $lesson['transcript'] ?? null, 'media_path' => $path]);
            }
            $assessment = $version->assessment()->create(['organisation_id' => $orgId, 'passing_score' => $data['passing_score']]);
            foreach ($data['questions'] as $position => $question) {
                $keys = collect($question['options'])->pluck('key');
                if (! $keys->contains($question['correct_option']) || $keys->unique()->count() !== $keys->count()) {
                    throw ValidationException::withMessages(['questions' => 'Each answer key must be unique and the correct option must exist.']);
                }
                $assessment->questions()->create(['organisation_id' => $orgId, 'position' => $position + 1, 'prompt' => $question['prompt'], 'options' => $question['options'], 'correct_option' => $question['correct_option'], 'feedback' => $question['feedback'] ?? null]);
            }

            return $version;
        });
        $audit->record($request, 'learning.course.drafted', 'course_version', $version->id);

        return back()->with('status', 'Course draft saved. A different authorised reviewer must publish it.');
    }

    public function newVersion(Request $request, string $organisation, string $courseId, Audit $audit): RedirectResponse
    {
        abort_unless(app(CurrentMembership::class)->membership->hasPermission('learning.manage'), 403);
        $course = Course::where('organisation_id', $organisation)->findOrFail($courseId);
        $source = $course->versions()->where('status', 'published')->with('lessons', 'assessment.questions')->orderByDesc('version')->firstOrFail();
        $version = DB::transaction(function () use ($course, $source, $request, $organisation) {
            $next = CourseVersion::create([
                'organisation_id' => $organisation, 'course_id' => $course->id,
                'version' => ((int) $course->versions()->max('version')) + 1,
                'locale' => $source->locale, 'title' => $source->title, 'description' => $source->description,
                'status' => 'draft', 'created_by' => $request->user()->id,
            ]);
            foreach ($source->lessons as $lesson) {
                $next->lessons()->create($lesson->only(['organisation_id', 'position', 'title', 'content', 'media_path', 'transcript', 'estimated_minutes']));
            }
            $assessment = $next->assessment()->create(['organisation_id' => $organisation, 'passing_score' => $source->assessment->passing_score, 'max_attempts' => $source->assessment->max_attempts]);
            foreach ($source->assessment->questions as $question) {
                $assessment->questions()->create($question->only(['organisation_id', 'position', 'prompt', 'options', 'correct_option', 'feedback']));
            }

            return $next;
        });
        $audit->record($request, 'learning.course.version_drafted', 'course_version', $version->id, ['version' => $version->version]);

        return back()->with('status', 'A new editable course version was created from the latest publication.');
    }

    public function updateDraft(Request $request, string $organisation, string $versionId, Audit $audit): RedirectResponse
    {
        abort_unless(app(CurrentMembership::class)->membership->hasPermission('learning.manage'), 403);
        $version = CourseVersion::where('organisation_id', $organisation)->where('status', 'draft')->findOrFail($versionId);
        abort_unless($version->created_by === $request->user()->id, 403, 'Only the draft author may edit this version.');
        $data = $this->draftData($request);
        DB::transaction(function () use ($version, $data, $organisation) {
            $oldLessons = $version->lessons()->get()->keyBy('position');
            $oldAssessment = $version->assessment()->with('questions')->firstOrFail();
            $oldAssessment->questions()->delete();
            $oldAssessment->delete();
            $version->lessons()->delete();
            $version->update(['title' => $data['title'], 'description' => $data['description'], 'locale' => $data['locale']]);
            foreach ($data['lessons'] as $position => $lesson) {
                $oldMedia = $oldLessons->get($position + 1)?->media_path;
                $path = isset($lesson['media']) ? $lesson['media']->store("learning/{$organisation}", 'local') : $oldMedia;
                $version->lessons()->create(['organisation_id' => $organisation, 'position' => $position + 1, 'title' => $lesson['title'], 'content' => $lesson['content'], 'transcript' => $lesson['transcript'] ?? null, 'media_path' => $path]);
            }
            $assessment = $version->assessment()->create(['organisation_id' => $organisation, 'passing_score' => $data['passing_score'], 'max_attempts' => $oldAssessment->max_attempts]);
            foreach ($data['questions'] as $position => $question) {
                $assessment->questions()->create(['organisation_id' => $organisation, 'position' => $position + 1, 'prompt' => $question['prompt'], 'options' => $question['options'], 'correct_option' => $question['correct_option'], 'feedback' => $question['feedback'] ?? null]);
            }
        });
        $audit->record($request, 'learning.course.draft_updated', 'course_version', $version->id);

        return back()->with('status', 'Course draft updated. A different authorised reviewer must publish it.');
    }

    public function publish(Request $request, string $organisation, string $versionId, Audit $audit): RedirectResponse
    {
        abort_unless(app(CurrentMembership::class)->membership->hasPermission('learning.manage'), 403);
        $version = CourseVersion::where('organisation_id', $organisation)->withCount(['lessons'])->findOrFail($versionId);
        abort_unless($version->created_by !== $request->user()->id, 403, 'A different reviewer must publish this course.');
        abort_unless($version->status === 'draft' && $version->lessons_count > 0 && $version->assessment()->whereHas('questions')->exists(), 422);
        DB::transaction(function () use ($version) {
            $version->update(['status' => 'published', 'reviewed_by' => request()->user()->id, 'published_at' => now()]);
            $version->course()->update(['status' => 'published']);
        });
        $audit->record($request, 'learning.course.published', 'course_version', $version->id);

        return back()->with('status', 'Reviewed course version published.');
    }

    public function assign(Request $request, string $organisation, Audit $audit): RedirectResponse
    {
        abort_unless(app(CurrentMembership::class)->membership->hasPermission('learning.manage'), 403);
        $data = $request->validate([
            'course_version_id' => ['required', 'uuid'],
            'membership_ids' => ['required', 'array', 'min:1', 'max:250'],
            'membership_ids.*' => ['required', 'uuid', 'distinct'],
            'due_at' => ['nullable', 'date', 'after:now'],
        ]);
        $membership = app(CurrentMembership::class)->membership;
        $version = CourseVersion::where('organisation_id', $organisation)->where('status', 'published')->findOrFail($data['course_version_id']);
        $activeMembers = Membership::where('organisation_id', $organisation)->where('status', 'active')
            ->whereIn('id', $data['membership_ids'])->pluck('id');
        abort_unless($activeMembers->count() === count($data['membership_ids']), 422, 'Every learner must be an active member of this organisation.');
        foreach ($activeMembers as $memberId) {
            CourseAssignment::firstOrCreate(
                ['organisation_id' => $organisation, 'membership_id' => $memberId, 'course_version_id' => $version->id],
                ['assigned_by' => $request->user()->id, 'due_at' => $data['due_at'] ?? null, 'status' => 'assigned'],
            );
        }
        $audit->record($request, 'learning.assignment.created', 'course_version', $version->id, ['learner_count' => $activeMembers->count()]);

        return back()->with('status', 'Course assigned to selected learners.');
    }

    public function catalogue(): Response
    {
        $membership = app(CurrentMembership::class)->membership;
        $assignments = CourseAssignment::with('courseVersion.lessons', 'courseVersion.assessment.questions')
            ->where('organisation_id', $membership->organisation_id)->where('membership_id', $membership->id)
            ->latest()->get();
        $progress = DB::table('lesson_progress')->where('organisation_id', $membership->organisation_id)
            ->whereIn('assignment_id', $assignments->pluck('id'))->whereNotNull('completed_at')->pluck('lesson_id')->all();
        $attempts = AssessmentAttempt::where('organisation_id', $membership->organisation_id)->whereIn('assignment_id', $assignments->pluck('id'))
            ->orderByDesc('attempt_number')->get()->groupBy('assignment_id');
        $viewAssignments = $assignments->map(fn ($assignment) => [
            'id' => $assignment->id, 'due_at' => $assignment->due_at, 'status' => $assignment->status,
            'course' => ['id' => $assignment->courseVersion->id, 'title' => $assignment->courseVersion->title, 'description' => $assignment->courseVersion->description, 'locale' => $assignment->courseVersion->locale],
            'lessons' => $assignment->courseVersion->lessons->map(fn ($lesson) => ['id' => $lesson->id, 'position' => $lesson->position, 'title' => $lesson->title, 'estimated_minutes' => $lesson->estimated_minutes, 'completed' => in_array($lesson->id, $progress, true)]),
            'assessment' => ['passing_score' => $assignment->courseVersion->assessment->passing_score, 'max_attempts' => $assignment->courseVersion->assessment->max_attempts,
                'questions' => $assignment->courseVersion->assessment->questions->map(fn ($q) => ['id' => $q->id, 'prompt' => $q->prompt, 'options' => $q->options])],
            'attempts' => ($attempts->get($assignment->id) ?? collect())->map(fn ($attempt) => ['attempt_number' => $attempt->attempt_number, 'score' => $attempt->score, 'passed' => $attempt->passed, 'submitted_at' => $attempt->submitted_at]),
        ]);

        return Inertia::render('Learning/Catalogue', ['organisation' => $membership->organisation->only(['id', 'name']), 'assignments' => $viewAssignments]);
    }

    public function lesson(Request $request, string $organisation, string $assignmentId, string $lessonId): Response
    {
        [$assignment, $lesson] = $this->assignedLesson($assignmentId, $lessonId);

        return Inertia::render('Learning/Lesson', ['organisation' => app(CurrentMembership::class)->membership->organisation->only(['id', 'name']), 'course' => $assignment->courseVersion->only(['id', 'title', 'locale']), 'assignmentId' => $assignment->id, 'lesson' => $lesson->only(['id', 'position', 'title', 'content', 'transcript', 'estimated_minutes']) + ['media_url' => $lesson->media_path ? route('learning.media', ['organisation' => $organisation, 'assignmentId' => $assignment->id, 'lessonId' => $lesson->id]) : null]]);
    }

    public function completeLesson(Request $request, string $organisation, string $assignmentId, string $lessonId, Audit $audit): RedirectResponse
    {
        [$assignment, $lesson] = $this->assignedLesson($assignmentId, $lessonId);
        DB::table('lesson_progress')->insertOrIgnore([
            'organisation_id' => $assignment->organisation_id,
            'assignment_id' => $assignment->id,
            'lesson_id' => $lesson->id,
            'completed_at' => now(),
            'updated_at' => now(),
            'created_at' => now(),
        ]);
        $audit->record($request, 'learning.lesson.completed', 'lesson', $lesson->id);

        return redirect()->route('learning.catalogue', ['organisation' => $organisation])->with('status', 'Lesson marked complete.');
    }

    public function media(string $organisation, string $assignmentId, string $lessonId)
    {
        [, $lesson] = $this->assignedLesson($assignmentId, $lessonId);
        abort_unless($lesson->media_path, 404);

        return Storage::disk('local')->download($lesson->media_path);
    }

    public function submitAssessment(Request $request, string $organisation, string $assignmentId, Audit $audit): RedirectResponse
    {
        $membership = app(CurrentMembership::class)->membership;
        $assignment = CourseAssignment::with('courseVersion.assessment.questions')->where('organisation_id', $organisation)->where('membership_id', $membership->id)->findOrFail($assignmentId);
        abort_unless($assignment->courseVersion->status === 'published', 404);
        $assessment = $assignment->courseVersion->assessment;
        $questions = $assessment->questions;
        $data = $request->validate(['answers' => ['required', 'array', 'size:'.$questions->count()], 'answers.*' => ['required', 'string', 'max:24']]);
        $allowed = $questions->keyBy(fn ($question) => (string) $question->id);
        abort_unless(array_diff(array_keys($data['answers']), $allowed->keys()->all()) === [], 422);
        $grade = null;
        $attemptNumber = DB::transaction(function () use ($questions, $data, $assessment, $assignment, &$grade) {
            CourseAssignment::where('organisation_id', $assignment->organisation_id)->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $nextAttempt = AssessmentAttempt::where('organisation_id', $assignment->organisation_id)->where('assignment_id', $assignment->id)->count() + 1;
            abort_if($assessment->max_attempts && $nextAttempt > $assessment->max_attempts, 422, 'Maximum attempts reached.');
            abort_if(AssessmentAttempt::where('organisation_id', $assignment->organisation_id)->where('assignment_id', $assignment->id)->where('passed', true)->exists(), 422, 'Assessment already passed.');
            try {
                $grade = app(AssessmentGrader::class)->grade($questions->map(fn ($question) => [
                    'id' => (string) $question->id,
                    'options' => $question->options,
                    'correct_option' => $question->correct_option,
                ])->all(), $data['answers'], $assessment->passing_score);
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['answers' => $exception->getMessage()]);
            }
            $attempt = AssessmentAttempt::create(['organisation_id' => $assignment->organisation_id, 'assignment_id' => $assignment->id, 'attempt_number' => $nextAttempt, 'score' => $grade['score'], 'passed' => $grade['passed'], 'submitted_at' => now()]);
            foreach ($questions as $question) {
                $selected = $data['answers'][(string) $question->id];
                DB::table('assessment_responses')->insert(['organisation_id' => $assignment->organisation_id, 'attempt_id' => $attempt->id, 'question_id' => $question->id, 'selected_option' => $selected, 'correct' => $question->correct_option === $selected]);
            }

            return $nextAttempt;
        });
        $audit->record($request, 'learning.assessment.submitted', 'assignment', $assignment->id, ['score' => $grade['score'], 'passed' => $grade['passed']]);

        return redirect()->route('learning.catalogue', ['organisation' => $organisation])->with('status', "Assessment submitted: {$grade['score']}%.");
    }

    private function assignedLesson(string $assignmentId, string $lessonId): array
    {
        $membership = app(CurrentMembership::class)->membership;
        $assignment = CourseAssignment::with('courseVersion.lessons')->where('organisation_id', $membership->organisation_id)->where('membership_id', $membership->id)->findOrFail($assignmentId);
        abort_unless($assignment->courseVersion->status === 'published', 404);
        $lesson = $assignment->courseVersion->lessons->firstWhere('id', $lessonId);
        abort_unless($lesson, 404);

        return [$assignment, $lesson];
    }

    private function draftData(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:5000'],
            'locale' => ['required', 'in:en,sn,nd'],
            'lessons' => ['required', 'array', 'min:1', 'max:40'],
            'lessons.*.title' => ['required', 'string', 'max:180'],
            'lessons.*.content' => ['required', 'string', 'max:50000'],
            'lessons.*.transcript' => ['nullable', 'string', 'max:50000'],
            'lessons.*.media' => ['nullable', 'file', 'mimes:mp4,webm,mp3,wav,ogg,pdf', 'max:51200'],
            'questions' => ['required', 'array', 'min:1', 'max:50'],
            'questions.*.prompt' => ['required', 'string', 'max:3000'],
            'questions.*.options' => ['required', 'array', 'min:2', 'max:6'],
            'questions.*.options.*.key' => ['required', 'alpha_dash', 'max:24'],
            'questions.*.options.*.label' => ['required', 'string', 'max:500'],
            'questions.*.correct_option' => ['required', 'string', 'max:24'],
            'questions.*.feedback' => ['nullable', 'string', 'max:1000'],
            'passing_score' => ['required', 'integer', 'between:1,100'],
        ]);
        foreach ($data['questions'] as $question) {
            $keys = collect($question['options'])->pluck('key');
            if (! $keys->contains($question['correct_option']) || $keys->unique()->count() !== $keys->count()) {
                throw ValidationException::withMessages(['questions' => 'Each answer key must be unique and the correct option must exist.']);
            }
        }

        return $data;
    }
}
