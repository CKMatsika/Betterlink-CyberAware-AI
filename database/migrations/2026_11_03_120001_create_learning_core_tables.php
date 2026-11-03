<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organisation_id');
            $table->string('slug', 100);
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'slug']);
            $table->foreign('organisation_id')->references('id')->on('organisations')->restrictOnDelete();
        });

        Schema::create('course_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organisation_id');
            $table->uuid('course_id');
            $table->unsignedInteger('version');
            $table->string('locale', 12)->default('en');
            $table->string('title', 180);
            $table->text('description');
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'course_id', 'version']);
            $table->foreign(['organisation_id', 'course_id'])->references(['organisation_id', 'id'])->on('courses')->restrictOnDelete();
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organisation_id');
            $table->uuid('course_version_id');
            $table->unsignedInteger('position');
            $table->string('title', 180);
            $table->longText('content');
            $table->string('media_path')->nullable();
            $table->text('transcript')->nullable();
            $table->unsignedSmallInteger('estimated_minutes')->default(5);
            $table->timestampsTz();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'course_version_id', 'position']);
            $table->foreign(['organisation_id', 'course_version_id'])->references(['organisation_id', 'id'])->on('course_versions')->restrictOnDelete();
        });

        Schema::create('assessments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organisation_id');
            $table->uuid('course_version_id');
            $table->unsignedTinyInteger('passing_score')->default(70);
            $table->unsignedSmallInteger('max_attempts')->nullable();
            $table->timestampsTz();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'course_version_id']);
            $table->foreign(['organisation_id', 'course_version_id'])->references(['organisation_id', 'id'])->on('course_versions')->restrictOnDelete();
        });

        Schema::create('assessment_questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organisation_id');
            $table->uuid('assessment_id');
            $table->unsignedInteger('position');
            $table->text('prompt');
            $table->jsonb('options');
            $table->string('correct_option', 24);
            $table->text('feedback')->nullable();
            $table->timestampsTz();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'assessment_id', 'position']);
            $table->foreign(['organisation_id', 'assessment_id'])->references(['organisation_id', 'id'])->on('assessments')->restrictOnDelete();
        });

        Schema::create('learning_paths', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organisation_id');
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->unique(['organisation_id', 'id']);
            $table->foreign('organisation_id')->references('id')->on('organisations')->restrictOnDelete();
        });

        Schema::create('learning_path_courses', function (Blueprint $table) {
            $table->uuid('organisation_id');
            $table->uuid('learning_path_id');
            $table->uuid('course_version_id');
            $table->unsignedInteger('position');
            $table->primary(['organisation_id', 'learning_path_id', 'course_version_id']);
            $table->foreign(['organisation_id', 'learning_path_id'])->references(['organisation_id', 'id'])->on('learning_paths')->cascadeOnDelete();
            $table->foreign(['organisation_id', 'course_version_id'])->references(['organisation_id', 'id'])->on('course_versions')->restrictOnDelete();
        });

        Schema::create('course_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organisation_id');
            $table->uuid('membership_id');
            $table->uuid('course_version_id');
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('due_at')->nullable();
            $table->string('status', 20)->default('assigned');
            $table->timestampsTz();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'membership_id', 'course_version_id']);
            $table->foreign(['organisation_id', 'membership_id'])->references(['organisation_id', 'id'])->on('memberships')->restrictOnDelete();
            $table->foreign(['organisation_id', 'course_version_id'])->references(['organisation_id', 'id'])->on('course_versions')->restrictOnDelete();
        });

        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->uuid('organisation_id');
            $table->uuid('assignment_id');
            $table->uuid('lesson_id');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->primary(['organisation_id', 'assignment_id', 'lesson_id']);
            $table->foreign(['organisation_id', 'assignment_id'])->references(['organisation_id', 'id'])->on('course_assignments')->cascadeOnDelete();
            $table->foreign(['organisation_id', 'lesson_id'])->references(['organisation_id', 'id'])->on('lessons')->restrictOnDelete();
        });

        Schema::create('assessment_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organisation_id');
            $table->uuid('assignment_id');
            $table->unsignedSmallInteger('attempt_number');
            $table->unsignedTinyInteger('score');
            $table->boolean('passed');
            $table->timestampTz('submitted_at');
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'assignment_id', 'attempt_number']);
            $table->foreign(['organisation_id', 'assignment_id'])->references(['organisation_id', 'id'])->on('course_assignments')->cascadeOnDelete();
        });

        Schema::create('assessment_responses', function (Blueprint $table) {
            $table->uuid('organisation_id');
            $table->uuid('attempt_id');
            $table->uuid('question_id');
            $table->string('selected_option', 24);
            $table->boolean('correct');
            $table->primary(['organisation_id', 'attempt_id', 'question_id']);
            $table->foreign(['organisation_id', 'attempt_id'])->references(['organisation_id', 'id'])->on('assessment_attempts')->cascadeOnDelete();
            $table->foreign(['organisation_id', 'question_id'])->references(['organisation_id', 'id'])->on('assessment_questions')->restrictOnDelete();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            foreach (['courses', 'course_versions', 'lessons', 'assessments', 'assessment_questions', 'learning_paths', 'learning_path_courses', 'course_assignments', 'lesson_progress', 'assessment_attempts', 'assessment_responses'] as $table) {
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
                DB::statement("CREATE POLICY tenant_isolation ON {$table} USING (organisation_id = NULLIF(current_setting('app.organisation_id', true), '')::uuid) WITH CHECK (organisation_id = NULLIF(current_setting('app.organisation_id', true), '')::uuid)");
            }
            DB::unprepared(<<<'SQL'
                CREATE FUNCTION prevent_published_course_edits() RETURNS trigger AS $$
                DECLARE version_id uuid;
                DECLARE version_status text;
                BEGIN
                    IF TG_TABLE_NAME = 'course_versions' THEN
                        IF TG_OP <> 'INSERT' AND OLD.status = 'published' THEN
                            RAISE EXCEPTION 'Published course versions are immutable';
                        END IF;
                    ELSE
                        IF TG_TABLE_NAME = 'assessment_questions' THEN
                            SELECT course_version_id INTO version_id FROM assessments
                            WHERE organisation_id = COALESCE(OLD.organisation_id, NEW.organisation_id)
                              AND id = COALESCE(OLD.assessment_id, NEW.assessment_id);
                        ELSE
                            version_id := COALESCE(OLD.course_version_id, NEW.course_version_id);
                        END IF;
                        SELECT status INTO version_status FROM course_versions
                        WHERE organisation_id = COALESCE(OLD.organisation_id, NEW.organisation_id) AND id = version_id;
                        IF version_status = 'published' THEN
                            RAISE EXCEPTION 'Published course content is immutable';
                        END IF;
                    END IF;
                    IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql
            SQL);
            foreach (['course_versions', 'lessons', 'assessments', 'assessment_questions'] as $table) {
                DB::statement("CREATE TRIGGER immutable_published_course BEFORE INSERT OR UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION prevent_published_course_edits()");
            }
        }
    }

    public function down(): void
    {
        foreach (['assessment_responses', 'assessment_attempts', 'lesson_progress', 'course_assignments', 'learning_path_courses', 'learning_paths', 'assessment_questions', 'assessments', 'lessons', 'course_versions', 'courses'] as $table) {
            if (DB::connection()->getDriverName() === 'pgsql') {
                if ($table === 'assessment_questions' || $table === 'assessments' || $table === 'lessons' || $table === 'course_versions') {
                    DB::statement("DROP TRIGGER IF EXISTS immutable_published_course ON {$table}");
                }
                DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
                DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
            }
            Schema::dropIfExists($table);
        }
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS prevent_published_course_edits()');
        }
    }
};
