<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_foundation_exposes_public_module_status_without_private_records(): void
    {
        $this->withoutVite();
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Foundation')
            ->where('phase', 'Phase 5 · M04 Human-risk intelligence')
            ->has('modules', 8)
            ->where('modules.0.code', 'M01')
            ->where('modules.7.code', 'M08')
            ->where('modules.0.status', 'Complete')
            ->where('modules.1.status', 'Complete')
            ->where('modules.2.status', 'Complete')
            ->where('modules.3.status', 'Complete')
            ->missing('users')
            ->missing('auth'));
    }

    public function test_framework_health_endpoint_is_available(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_sign_in_and_get_started_pages_are_available(): void
    {
        $this->get('/login')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
        $this->get('/get-started')->assertOk()->assertInertia(fn (Assert $page) => $page->component('GettingStarted'));
    }

    public function test_authenticated_users_without_a_workspace_get_clear_onboarding(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Tenant/ChooseOrganisation'));
    }

    public function test_first_tenant_bootstrap_fails_closed_outside_postgresql(): void
    {
        $this->artisan('cyberaware:bootstrap-tenant', [
            'organisation' => 'Test organisation',
            'slug' => 'test-organisation',
            'admin-email' => 'admin@example.test',
            'admin-name' => 'Test Admin',
        ])->assertExitCode(1)->expectsOutputToContain('requires PostgreSQL');
    }

    public function test_public_registration_and_product_workflows_are_not_exposed(): void
    {
        $this->get('/login')->assertOk();
        foreach (['/register', '/campaigns', '/api/campaigns', '/billing', '/assistant'] as $path) {
            $this->get($path)->assertNotFound();
        }
    }

    public function test_default_media_disk_does_not_offer_public_serving(): void
    {
        $this->assertSame('local', config('filesystems.default'));
        $this->assertFalse(config('filesystems.disks.local.serve'));
        $this->get('/storage/private-example.pdf')->assertNotFound();
    }
}
