<?php

namespace Tests\Feature;

use App\Http\Middleware\RequireTenantMembership;
use App\Models\User;
use App\Modules\Foundation\CurrentMembership;
use App\Modules\Foundation\Models\Membership;
use App\Modules\Foundation\Models\Organisation;
use App\Modules\Risk\AwarenessIndicatorService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AwarenessIndicatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_awareness_dashboard_is_available_only_to_report_readers(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $organisation = Organisation::create(['name' => 'Risk Test', 'slug' => 'risk-test']);
        $membership = Membership::create(['organisation_id' => $organisation->id, 'user_id' => $user->id, 'status' => 'active']);
        $this->withoutMiddleware(RequireTenantMembership::class);
        app(CurrentMembership::class)->membership = $membership;

        $this->actingAs($user)->get("/org/{$organisation->id}/risk")->assertForbidden();

        $roleId = (string) Str::uuid();
        DB::table('roles')->insert(['id' => $roleId, 'organisation_id' => $organisation->id, 'name' => 'auditor', 'label' => 'Auditor']);
        DB::table('role_permissions')->insert([
            'organisation_id' => $organisation->id,
            'role_id' => $roleId,
            'permission_id' => DB::table('permissions')->where('name', 'reports.view')->value('id'),
        ]);
        DB::table('membership_roles')->insert(['organisation_id' => $organisation->id, 'membership_id' => $membership->id, 'role_id' => $roleId, 'assigned_by' => $user->id]);

        $this->actingAs($user)->get("/org/{$organisation->id}/risk")
            ->assertOk()->assertInertia(fn ($page) => $page->component('Risk/Dashboard')
            ->where('dashboard.minimum_cohort', AwarenessIndicatorService::MINIMUM_COHORT)
            ->where('dashboard.latest', null)
            ->where('canConfigure', false));
        $this->assertDatabaseHas('audit_events', ['action' => 'risk.dashboard.viewed', 'organisation_id' => $organisation->id]);
    }

    public function test_tenant_admin_saves_an_append_only_weight_configuration(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $organisation = Organisation::create(['name' => 'Risk Admin Test', 'slug' => 'risk-admin-test']);
        $membership = Membership::create(['organisation_id' => $organisation->id, 'user_id' => $user->id, 'status' => 'active']);
        $this->withoutMiddleware(RequireTenantMembership::class);
        app(CurrentMembership::class)->membership = $membership;
        $roleId = (string) Str::uuid();
        DB::table('roles')->insert(['id' => $roleId, 'organisation_id' => $organisation->id, 'name' => 'tenant_admin', 'label' => 'Tenant admin']);
        DB::table('membership_roles')->insert(['organisation_id' => $organisation->id, 'membership_id' => $membership->id, 'role_id' => $roleId, 'assigned_by' => $user->id]);

        $this->actingAs($user)->post("/org/{$organisation->id}/risk/indicators", [
            'weights' => ['learning_completion' => 50, 'assessment_pass_rate' => 30, 'education_acknowledgement' => 20],
        ])->assertRedirect();
        $this->actingAs($user)->post("/org/{$organisation->id}/risk/indicators", [
            'weights' => ['learning_completion' => 45, 'assessment_pass_rate' => 35, 'education_acknowledgement' => 20],
        ])->assertRedirect();

        $this->assertDatabaseHas('risk_indicator_versions', [
            'organisation_id' => $organisation->id,
            'version' => 1,
            'created_by' => $user->id,
        ]);
        $this->assertDatabaseHas('risk_indicator_versions', [
            'organisation_id' => $organisation->id,
            'version' => 2,
            'created_by' => $user->id,
        ]);
        $this->assertDatabaseCount('risk_indicator_versions', 2);
        $dashboard = app(AwarenessIndicatorService::class)->dashboard($organisation->id, 'Africa/Harare');
        $currentPeriod = CarbonImmutable::now('Africa/Harare')->format('Y-m');
        $this->assertSame(2, collect($dashboard['periods'])->firstWhere('period', $currentPeriod)['configuration_version']);
        $this->assertDatabaseHas('audit_events', ['action' => 'risk.indicators.versioned', 'organisation_id' => $organisation->id]);
    }

    public function test_weights_must_total_one_hundred(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $organisation = Organisation::create(['name' => 'Risk Validation Test', 'slug' => 'risk-validation-test']);
        $membership = Membership::create(['organisation_id' => $organisation->id, 'user_id' => $user->id, 'status' => 'active']);
        $this->withoutMiddleware(RequireTenantMembership::class);
        app(CurrentMembership::class)->membership = $membership;
        $roleId = (string) Str::uuid();
        DB::table('roles')->insert(['id' => $roleId, 'organisation_id' => $organisation->id, 'name' => 'tenant_admin', 'label' => 'Tenant admin']);
        DB::table('membership_roles')->insert(['organisation_id' => $organisation->id, 'membership_id' => $membership->id, 'role_id' => $roleId, 'assigned_by' => $user->id]);

        $this->actingAs($user)->from("/org/{$organisation->id}/risk")->post("/org/{$organisation->id}/risk/indicators", [
            'weights' => ['learning_completion' => 50, 'assessment_pass_rate' => 30, 'education_acknowledgement' => 10],
        ])->assertSessionHasErrors('weights');
        $this->assertDatabaseCount('risk_indicator_versions', 0);
    }

    public function test_score_suppresses_small_cohorts_and_renormalizes_available_weights(): void
    {
        $period = app(AwarenessIndicatorService::class)->scorePeriod('2026-11', [
            'learning_completion' => ['numerator' => 2, 'denominator' => 4, 'cohort' => 4],
            'assessment_pass_rate' => ['numerator' => 4, 'denominator' => 5, 'cohort' => 5],
            'education_acknowledgement' => ['numerator' => 0, 'denominator' => 0, 'cohort' => 0],
        ], ['learning_completion' => 40, 'assessment_pass_rate' => 35, 'education_acknowledgement' => 25]);

        $this->assertSame(80.0, $period['score']);
        $this->assertSame('suppressed', $period['metrics']['learning_completion']['status']);
        $this->assertNull($period['metrics']['learning_completion']['sample_size']);
        $this->assertSame('available', $period['metrics']['assessment_pass_rate']['status']);
        $this->assertSame('no_data', $period['metrics']['education_acknowledgement']['status']);

        $noReport = app(AwarenessIndicatorService::class)->scorePeriod('2026-11', [], AwarenessIndicatorService::DEFAULT_CONFIGURATION);
        $this->assertNull($noReport['score']);
    }

    public function test_dashboard_computes_learning_completion_without_returning_member_rows(): void
    {
        $manager = User::factory()->create(['email_verified_at' => now()]);
        $organisation = Organisation::create(['name' => 'Aggregate Test', 'slug' => 'aggregate-test']);
        $userIds = [$manager->id];
        for ($index = 1; $index < 5; $index++) {
            $userIds[] = User::factory()->create(['email_verified_at' => now()])->id;
        }
        $memberships = collect($userIds)->map(fn ($userId) => Membership::create([
            'organisation_id' => $organisation->id, 'user_id' => $userId, 'status' => 'active',
        ]));

        $courseId = (string) Str::uuid();
        $versionId = (string) Str::uuid();
        $lessonId = (string) Str::uuid();
        DB::table('courses')->insert(['id' => $courseId, 'organisation_id' => $organisation->id, 'slug' => 'aggregate-course', 'status' => 'published', 'created_by' => $manager->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('course_versions')->insert(['id' => $versionId, 'organisation_id' => $organisation->id, 'course_id' => $courseId, 'version' => 1, 'locale' => 'en', 'title' => 'Aggregate course', 'description' => 'Training', 'status' => 'published', 'created_by' => $manager->id, 'reviewed_by' => $manager->id, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('lessons')->insert(['id' => $lessonId, 'organisation_id' => $organisation->id, 'course_version_id' => $versionId, 'position' => 1, 'title' => 'Lesson', 'content' => 'Content', 'created_at' => now(), 'updated_at' => now()]);

        foreach ($memberships as $index => $membership) {
            $assignmentId = (string) Str::uuid();
            DB::table('course_assignments')->insert(['id' => $assignmentId, 'organisation_id' => $organisation->id, 'membership_id' => $membership->id, 'course_version_id' => $versionId, 'assigned_by' => $manager->id, 'status' => 'assigned', 'created_at' => now(), 'updated_at' => now()]);
            if ($index < 4) {
                DB::table('lesson_progress')->insert(['organisation_id' => $organisation->id, 'assignment_id' => $assignmentId, 'lesson_id' => $lessonId, 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            } elseif ($index === 4) {
                DB::table('lesson_progress')->insert(['organisation_id' => $organisation->id, 'assignment_id' => $assignmentId, 'lesson_id' => $lessonId, 'completed_at' => now()->addMonths(2), 'created_at' => now()->addMonths(2), 'updated_at' => now()->addMonths(2)]);
            }
        }

        $dashboard = app(AwarenessIndicatorService::class)->dashboard($organisation->id, 'Africa/Harare');
        $currentPeriod = CarbonImmutable::now('Africa/Harare')->format('Y-m');
        $current = collect($dashboard['periods'])->firstWhere('period', $currentPeriod);

        $this->assertSame(80.0, $current['metrics']['learning_completion']['rate']);
        $this->assertSame(5, $current['metrics']['learning_completion']['sample_size']);
        $this->assertSame('no_data', $current['metrics']['assessment_pass_rate']['status']);
        $this->assertArrayNotHasKey('members', $dashboard);
    }
}
