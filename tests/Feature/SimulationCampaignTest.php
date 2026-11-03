<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Foundation\CurrentMembership;
use App\Modules\Foundation\Models\Membership;
use App\Modules\Foundation\Models\Organisation;
use App\Modules\Simulation\DeliveryRetry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SimulationCampaignTest extends TestCase
{
    use RefreshDatabase;

    public function test_simulation_management_requires_authentication(): void
    {
        $this->get('/org/'.Str::uuid().'/simulations/manage')->assertRedirect('/login');
    }

    public function test_only_an_authorised_admin_can_authorise_members_at_the_verified_domain(): void
    {
        $this->withoutMiddleware();
        [$organisation, $admin, $adminMembership, $security, $securityMembership, $recipient] = $this->setupMembers();
        $domainId = (string) Str::uuid();
        DB::table('simulation_domains')->insert(['id' => $domainId, 'organisation_id' => $organisation->id, 'domain' => 'example.org', 'verification_token' => Str::random(40), 'verified_at' => now(), 'added_by' => $admin->id, 'created_at' => now(), 'updated_at' => now()]);
        $data = [
            'domain_id' => $domainId,
            'membership_ids' => [$recipient->id],
            'starts_at' => now('Africa/Harare')->addMinute()->toDateTimeString(),
            'expires_at' => now('Africa/Harare')->addDays(2)->toDateTimeString(),
            'max_deliveries_per_hour' => 10,
        ];

        app(CurrentMembership::class)->membership = $securityMembership;
        $this->actingAs($security)->post("/org/{$organisation->id}/simulations/authorisations", $data)->assertForbidden();

        DB::table('users')->where('id', $recipient->user_id)->update(['email' => 'learner@attacker.test']);
        app(CurrentMembership::class)->membership = $adminMembership;
        $this->actingAs($admin)->post("/org/{$organisation->id}/simulations/authorisations", $data)->assertStatus(422);
        $this->assertDatabaseMissing('simulation_authorisations', ['organisation_id' => $organisation->id]);
    }

    public function test_scope_is_exact_verified_members_and_campaign_needs_distinct_approval_and_enabled_sandbox(): void
    {
        $this->withoutMiddleware();
        [$organisation, $admin, $adminMembership, $security, $securityMembership, $recipient] = $this->setupMembers();
        $domainId = (string) Str::uuid();
        DB::table('simulation_domains')->insert(['id' => $domainId, 'organisation_id' => $organisation->id, 'domain' => 'example.org', 'verification_token' => Str::random(40), 'verified_at' => now(), 'added_by' => $admin->id, 'created_at' => now(), 'updated_at' => now()]);

        app(CurrentMembership::class)->membership = $adminMembership;
        $authResponse = $this->actingAs($admin)->post("/org/{$organisation->id}/simulations/authorisations", [
            'domain_id' => $domainId,
            'membership_ids' => [$recipient->id],
            'starts_at' => now('Africa/Harare')->addMinute()->toDateTimeString(),
            'expires_at' => now('Africa/Harare')->addDays(2)->toDateTimeString(),
            'max_deliveries_per_hour' => 10,
        ]);
        $authResponse->assertRedirect();
        $authorisationId = DB::table('simulation_authorisations')->where('organisation_id', $organisation->id)->value('id');

        app(CurrentMembership::class)->membership = $securityMembership;
        $this->actingAs($security);
        $campaignResponse = $this->post("/org/{$organisation->id}/simulations/campaigns", [
            'authorisation_id' => $authorisationId,
            'membership_ids' => [$recipient->id],
            'scheduled_at' => now('Africa/Harare')->addHour()->toDateTimeString(),
        ]);
        $campaignResponse->assertRedirect();
        $campaign = DB::table('simulation_campaigns')->where('organisation_id', $organisation->id)->first();

        $this->post("/org/{$organisation->id}/simulations/campaigns/{$campaign->id}/approve")->assertForbidden();
        $this->assertDatabaseMissing('simulation_approvals', ['campaign_id' => $campaign->id]);

        app(CurrentMembership::class)->membership = $adminMembership;
        $this->actingAs($admin);
        $this->post("/org/{$organisation->id}/simulations/campaigns/{$campaign->id}/approve")->assertRedirect();
        DB::table('simulation_settings')->insert(['organisation_id' => $organisation->id, 'enabled' => false, 'kill_switch_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        app(CurrentMembership::class)->membership = $securityMembership;
        $this->actingAs($security);
        $this->post("/org/{$organisation->id}/simulations/campaigns/{$campaign->id}/schedule")->assertStatus(423);

        DB::table('simulation_settings')->where('organisation_id', $organisation->id)->update(['enabled' => true, 'kill_switch_active' => false]);
        DB::table('simulation_authorisations')->where('id', $authorisationId)->update(['starts_at' => now()->subMinute()->utc()->format('Y-m-d H:i:sP')]);
        $this->post("/org/{$organisation->id}/simulations/campaigns/{$campaign->id}/schedule")->assertRedirect();
        $this->assertDatabaseHas('simulation_campaigns', ['id' => $campaign->id, 'status' => 'scheduled']);
        $this->assertDatabaseHas('simulation_delivery_outbox', ['campaign_id' => $campaign->id, 'status' => 'queued']);
        $this->assertDatabaseHas('audit_events', ['action' => 'simulation.campaign.approved', 'subject_id' => $campaign->id]);
    }

    public function test_campaign_scope_change_invalidates_approval(): void
    {
        $this->withoutMiddleware();
        [$organisation, $admin, $adminMembership, $security, $securityMembership, $recipient, $otherRecipient] = $this->setupMembers(twoRecipients: true);
        $domainId = (string) Str::uuid();
        DB::table('simulation_domains')->insert(['id' => $domainId, 'organisation_id' => $organisation->id, 'domain' => 'example.org', 'verification_token' => Str::random(40), 'verified_at' => now(), 'added_by' => $admin->id, 'created_at' => now(), 'updated_at' => now()]);
        $authId = (string) Str::uuid();
        DB::table('simulation_authorisations')->insert(['id' => $authId, 'organisation_id' => $organisation->id, 'domain_id' => $domainId, 'authorised_by' => $admin->id, 'starts_at' => now()->subMinute()->utc()->format('Y-m-d H:i:sP'), 'expires_at' => now()->addDay()->utc()->format('Y-m-d H:i:sP'), 'max_recipients' => 2, 'max_deliveries_per_hour' => 10, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([$recipient, $otherRecipient] as $member) {
            DB::table('simulation_authorised_recipients')->insert(['organisation_id' => $organisation->id, 'authorisation_id' => $authId, 'membership_id' => $member->id]);
        }
        app(CurrentMembership::class)->membership = $securityMembership;
        $this->actingAs($security)->post("/org/{$organisation->id}/simulations/campaigns", ['authorisation_id' => $authId, 'membership_ids' => [$recipient->id], 'scheduled_at' => now('Africa/Harare')->addHour()->toDateTimeString()])->assertRedirect();
        $campaign = DB::table('simulation_campaigns')->where('organisation_id', $organisation->id)->first();
        DB::table('simulation_campaign_recipients')->where('campaign_id', $campaign->id)->update(['membership_id' => $otherRecipient->id]);

        app(CurrentMembership::class)->membership = $adminMembership;
        $this->actingAs($admin);
        $this->post("/org/{$organisation->id}/simulations/campaigns/{$campaign->id}/approve")->assertStatus(409);
        $this->assertDatabaseMissing('simulation_approvals', ['campaign_id' => $campaign->id]);
    }

    public function test_suppression_blocks_campaigns_and_cancellation_removes_queued_delivery(): void
    {
        $this->withoutMiddleware();
        [$organisation, $admin, $adminMembership, $security, $securityMembership, $recipient] = $this->setupMembers();
        $domainId = (string) Str::uuid();
        $authId = (string) Str::uuid();
        DB::table('simulation_domains')->insert(['id' => $domainId, 'organisation_id' => $organisation->id, 'domain' => 'example.org', 'verification_token' => Str::random(40), 'verified_at' => now(), 'added_by' => $admin->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('simulation_authorisations')->insert(['id' => $authId, 'organisation_id' => $organisation->id, 'domain_id' => $domainId, 'authorised_by' => $admin->id, 'starts_at' => now()->subMinute()->utc()->format('Y-m-d H:i:sP'), 'expires_at' => now()->addDay()->utc()->format('Y-m-d H:i:sP'), 'max_recipients' => 1, 'max_deliveries_per_hour' => 5, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('simulation_authorised_recipients')->insert(['organisation_id' => $organisation->id, 'authorisation_id' => $authId, 'membership_id' => $recipient->id]);
        app(CurrentMembership::class)->membership = $securityMembership;
        $this->actingAs($security)->post("/org/{$organisation->id}/simulations/suppressions", ['membership_id' => $recipient->id, 'reason' => 'Requested exclusion'])->assertRedirect();
        $campaignData = ['authorisation_id' => $authId, 'membership_ids' => [$recipient->id], 'scheduled_at' => now('Africa/Harare')->addHour()->toDateTimeString()];
        $this->post("/org/{$organisation->id}/simulations/campaigns", $campaignData)->assertStatus(422);
        $this->post("/org/{$organisation->id}/simulations/suppressions/{$recipient->id}/remove")->assertRedirect();
        $this->post("/org/{$organisation->id}/simulations/campaigns", $campaignData)->assertRedirect();
        $campaign = DB::table('simulation_campaigns')->where('organisation_id', $organisation->id)->first();
        app(CurrentMembership::class)->membership = $adminMembership;
        $this->actingAs($admin)->post("/org/{$organisation->id}/simulations/campaigns/{$campaign->id}/approve")->assertRedirect();
        DB::table('simulation_settings')->insert(['organisation_id' => $organisation->id, 'enabled' => true, 'kill_switch_active' => false, 'created_at' => now(), 'updated_at' => now()]);
        app(CurrentMembership::class)->membership = $securityMembership;
        $this->actingAs($security)->post("/org/{$organisation->id}/simulations/campaigns/{$campaign->id}/schedule")->assertRedirect();
        $this->post("/org/{$organisation->id}/simulations/campaigns/{$campaign->id}/cancel")->assertRedirect();
        $this->assertDatabaseHas('simulation_campaigns', ['id' => $campaign->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('simulation_delivery_outbox', ['campaign_id' => $campaign->id, 'status' => 'cancelled']);
        $this->assertDatabaseMissing('simulation_dispatch_queue', ['organisation_id' => $organisation->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'simulation.recipient.suppressed', 'subject_id' => $recipient->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'simulation.recipient.unsuppressed', 'subject_id' => $recipient->id]);
    }

    public function test_sandbox_delivery_retries_twice_then_stops_after_the_third_failure(): void
    {
        [$organisation, $admin, , $security, , $recipient] = $this->setupMembers();
        $domainId = (string) Str::uuid();
        $authorisationId = (string) Str::uuid();
        $campaignId = (string) Str::uuid();
        $campaignRecipientId = (string) Str::uuid();
        $outboxId = (string) Str::uuid();
        DB::table('simulation_domains')->insert(['id' => $domainId, 'organisation_id' => $organisation->id, 'domain' => 'example.org', 'verification_token' => Str::random(40), 'verified_at' => now(), 'added_by' => $admin->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('simulation_authorisations')->insert(['id' => $authorisationId, 'organisation_id' => $organisation->id, 'domain_id' => $domainId, 'authorised_by' => $admin->id, 'starts_at' => now()->subMinute()->utc()->format('Y-m-d H:i:sP'), 'expires_at' => now()->addDay()->utc()->format('Y-m-d H:i:sP'), 'max_recipients' => 1, 'max_deliveries_per_hour' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('simulation_campaigns')->insert(['id' => $campaignId, 'organisation_id' => $organisation->id, 'authorisation_id' => $authorisationId, 'created_by' => $security->id, 'template_key' => 'safe_document_review', 'status' => 'scheduled', 'scheduled_at' => now()->addHour()->utc()->format('Y-m-d H:i:sP'), 'scope_digest' => str_repeat('a', 64), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('simulation_campaign_recipients')->insert(['id' => $campaignRecipientId, 'organisation_id' => $organisation->id, 'campaign_id' => $campaignId, 'membership_id' => $recipient->id, 'delivery_status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('simulation_delivery_outbox')->insert(['id' => $outboxId, 'organisation_id' => $organisation->id, 'campaign_id' => $campaignId, 'campaign_recipient_id' => $campaignRecipientId, 'status' => 'queued', 'available_at' => now()->utc()->format('Y-m-d H:i:sP'), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('simulation_dispatch_queue')->insert(['id' => (string) Str::uuid(), 'organisation_id' => $organisation->id, 'outbox_id' => $outboxId, 'available_at' => now()->utc()->format('Y-m-d H:i:sP'), 'created_at' => now()]);

        $retry = app(DeliveryRetry::class);
        $this->assertSame(1, $retry->recordFailure($organisation->id, $outboxId, 0));
        $this->assertDatabaseHas('simulation_delivery_outbox', ['id' => $outboxId, 'status' => 'queued', 'attempts' => 1]);
        $this->assertSame(2, $retry->recordFailure($organisation->id, $outboxId, 1));
        $this->assertDatabaseHas('simulation_delivery_outbox', ['id' => $outboxId, 'status' => 'queued', 'attempts' => 2]);
        $this->assertSame(3, $retry->recordFailure($organisation->id, $outboxId, 2));
        $this->assertDatabaseHas('simulation_delivery_outbox', ['id' => $outboxId, 'status' => 'failed', 'attempts' => 3, 'last_error' => 'Sandbox transport error']);
    }

    private function setupMembers(bool $twoRecipients = false): array
    {
        $admin = User::factory()->create(['email' => 'admin@example.org', 'email_verified_at' => now()]);
        $security = User::factory()->create(['email' => 'security@example.org', 'email_verified_at' => now()]);
        $recipientUser = User::factory()->create(['email' => 'learner@example.org', 'email_verified_at' => now()]);
        $organisation = Organisation::create(['name' => 'Simulation Test', 'slug' => 'simulation-test']);
        $adminMembership = Membership::create(['organisation_id' => $organisation->id, 'user_id' => $admin->id, 'status' => 'active']);
        $securityMembership = Membership::create(['organisation_id' => $organisation->id, 'user_id' => $security->id, 'status' => 'active']);
        $recipient = Membership::create(['organisation_id' => $organisation->id, 'user_id' => $recipientUser->id, 'status' => 'active']);
        $roleIds = [];
        foreach (['tenant_admin', 'security_officer'] as $roleName) {
            $roleIds[$roleName] = (string) Str::uuid();
            DB::table('roles')->insert(['id' => $roleIds[$roleName], 'organisation_id' => $organisation->id, 'name' => $roleName, 'label' => $roleName]);
        }
        foreach (['simulations.authorise', 'simulations.approve', 'simulations.kill'] as $permission) {
            $permissionId = DB::table('permissions')->where('name', $permission)->value('id');
            DB::table('role_permissions')->insert(['organisation_id' => $organisation->id, 'role_id' => $roleIds['tenant_admin'], 'permission_id' => $permissionId]);
        }
        foreach (['simulations.manage', 'simulations.approve'] as $permission) {
            $permissionId = DB::table('permissions')->where('name', $permission)->value('id');
            DB::table('role_permissions')->insert(['organisation_id' => $organisation->id, 'role_id' => $roleIds['security_officer'], 'permission_id' => $permissionId]);
        }
        DB::table('membership_roles')->insert(['organisation_id' => $organisation->id, 'membership_id' => $adminMembership->id, 'role_id' => $roleIds['tenant_admin'], 'assigned_by' => $admin->id]);
        DB::table('membership_roles')->insert(['organisation_id' => $organisation->id, 'membership_id' => $securityMembership->id, 'role_id' => $roleIds['security_officer'], 'assigned_by' => $admin->id]);
        $result = [$organisation, $admin, $adminMembership, $security, $securityMembership, $recipient];
        if ($twoRecipients) {
            $otherUser = User::factory()->create(['email' => 'learner2@example.org', 'email_verified_at' => now()]);
            $result[] = Membership::create(['organisation_id' => $organisation->id, 'user_id' => $otherUser->id, 'status' => 'active']);
        }
        app(CurrentMembership::class)->membership = $adminMembership;

        return $result;
    }
}
