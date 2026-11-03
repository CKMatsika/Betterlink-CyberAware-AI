<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_foundation_exposes_only_planned_module_metadata(): void
    {
        $this->withoutVite();
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Foundation')
            ->where('phase', 'Phase 0')
            ->has('modules', 8)
            ->where('modules.0.code', 'M01')
            ->where('modules.7.code', 'M08')
            ->missing('users')
            ->missing('auth'));
    }

    public function test_framework_health_endpoint_is_available(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_operational_endpoints_are_not_exposed(): void
    {
        foreach (['/login', '/register', '/campaigns', '/api/campaigns', '/billing', '/assistant'] as $path) {
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
