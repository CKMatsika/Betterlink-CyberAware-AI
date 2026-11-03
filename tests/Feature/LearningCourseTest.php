<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Foundation\CurrentMembership;
use App\Modules\Foundation\Models\Membership;
use App\Modules\Foundation\Models\Organisation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class LearningCourseTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_manager_can_save_a_course_draft_with_private_media(): void
    {
        $this->withoutMiddleware();
        Storage::fake('local');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $organisation = Organisation::create(['name' => 'Learning Test', 'slug' => 'learning-test']);
        $membership = Membership::create(['organisation_id' => $organisation->id, 'user_id' => $user->id, 'status' => 'active']);
        $roleId = (string) Str::uuid();
        DB::table('roles')->insert(['id' => $roleId, 'organisation_id' => $organisation->id, 'name' => 'training_manager', 'label' => 'Training manager']);
        $permissionId = DB::table('permissions')->where('name', 'learning.manage')->value('id');
        DB::table('role_permissions')->insert(['organisation_id' => $organisation->id, 'role_id' => $roleId, 'permission_id' => $permissionId]);
        DB::table('membership_roles')->insert(['organisation_id' => $organisation->id, 'membership_id' => $membership->id, 'role_id' => $roleId, 'assigned_by' => $user->id]);
        app(CurrentMembership::class)->membership = $membership;

        $response = $this->actingAs($user)->post("/org/{$organisation->id}/learning/courses", [
            'title' => 'Safe Links',
            'description' => 'Learn to check a destination before opening it.',
            'locale' => 'en',
            'passing_score' => 70,
            'lessons' => [[
                'title' => 'Check the destination',
                'content' => 'Read the full domain before you open a link.',
                'transcript' => 'Transcript for the private media lesson.',
                'media' => UploadedFile::fake()->create('safe-links.pdf', 12, 'application/pdf'),
            ]],
            'questions' => [[
                'prompt' => 'What should you check?',
                'options' => [['key' => 'a', 'label' => 'The destination'], ['key' => 'b', 'label' => 'Nothing']],
                'correct_option' => 'a',
            ]],
        ]);

        $response->assertRedirect();
        $lesson = DB::table('lessons')->where('title', 'Check the destination')->first();
        $this->assertNotNull($lesson);
        $this->assertStringStartsWith("learning/{$organisation->id}/", $lesson->media_path);
        Storage::disk('local')->assertExists($lesson->media_path);
        $this->assertDatabaseHas('course_versions', ['title' => 'Safe Links', 'status' => 'draft', 'created_by' => $user->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'learning.course.drafted', 'subject_id' => $lesson->course_version_id]);
    }

    public function test_learning_routes_require_authentication(): void
    {
        $organisation = (string) Str::uuid();
        $this->get("/org/{$organisation}/learning")->assertRedirect('/login');
    }
}
