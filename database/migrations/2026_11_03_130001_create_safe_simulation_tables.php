<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->insertOrIgnore([
            ['name' => 'simulations.authorise', 'description' => 'Authorise a bounded simulation scope.'],
            ['name' => 'simulations.approve', 'description' => 'Approve a campaign created by another member.'],
            ['name' => 'simulations.kill', 'description' => 'Enable or disable the tenant simulation kill switch.'],
        ]);

        Schema::create('simulation_settings', function (Blueprint $table) {
            $table->uuid('organisation_id')->primary();
            $table->boolean('enabled')->default(false);
            $table->boolean('kill_switch_active')->default(true);
            $table->timestampsTz();
            $table->foreign('organisation_id')->references('id')->on('organisations')->restrictOnDelete();
        });

        Schema::create('simulation_domains', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organisation_id');
            $table->string('domain', 253);
            $table->string('verification_token', 64);
            $table->timestampTz('verified_at')->nullable();
            $table->foreignId('added_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'domain']);
            $table->foreign('organisation_id')->references('id')->on('organisations')->restrictOnDelete();
        });

        Schema::create('simulation_authorisations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organisation_id');
            $table->uuid('domain_id');
            $table->foreignId('authorised_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('starts_at');
            $table->timestampTz('expires_at');
            $table->unsignedSmallInteger('max_recipients');
            $table->unsignedSmallInteger('max_deliveries_per_hour');
            $table->string('status', 24)->default('active');
            $table->timestampsTz();
            $table->unique(['organisation_id', 'id']);
            $table->foreign(['organisation_id', 'domain_id'])->references(['organisation_id', 'id'])->on('simulation_domains')->restrictOnDelete();
            $table->foreign('organisation_id')->references('id')->on('organisations')->restrictOnDelete();
        });

        Schema::create('simulation_authorised_recipients', function (Blueprint $table) {
            $table->uuid('organisation_id');
            $table->uuid('authorisation_id');
            $table->uuid('membership_id');
            $table->primary(['organisation_id', 'authorisation_id', 'membership_id']);
            $table->foreign(['organisation_id', 'authorisation_id'])->references(['organisation_id', 'id'])->on('simulation_authorisations')->cascadeOnDelete();
            $table->foreign(['organisation_id', 'membership_id'])->references(['organisation_id', 'id'])->on('memberships')->restrictOnDelete();
        });

        Schema::create('simulation_campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organisation_id');
            $table->uuid('authorisation_id');
            $table->uuid('remedial_course_version_id')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('template_key', 48)->default('safe_document_review');
            $table->string('status', 24)->default('draft');
            $table->timestampTz('scheduled_at');
            $table->char('scope_digest', 64);
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampsTz();
            $table->unique(['organisation_id', 'id']);
            $table->foreign(['organisation_id', 'authorisation_id'])->references(['organisation_id', 'id'])->on('simulation_authorisations')->restrictOnDelete();
            $table->foreign(['organisation_id', 'remedial_course_version_id'])->references(['organisation_id', 'id'])->on('course_versions')->restrictOnDelete();
            $table->foreign('organisation_id')->references('id')->on('organisations')->restrictOnDelete();
        });

        Schema::create('simulation_approvals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organisation_id');
            $table->uuid('campaign_id');
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->char('scope_digest', 64);
            $table->timestampTz('expires_at');
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['organisation_id', 'id']);
            $table->foreign(['organisation_id', 'campaign_id'])->references(['organisation_id', 'id'])->on('simulation_campaigns')->cascadeOnDelete();
        });

        Schema::create('simulation_campaign_recipients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organisation_id');
            $table->uuid('campaign_id');
            $table->uuid('membership_id');
            $table->char('token_hash', 64)->nullable();
            $table->string('delivery_status', 24)->default('pending');
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampsTz();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'campaign_id', 'membership_id']);
            $table->foreign(['organisation_id', 'campaign_id'])->references(['organisation_id', 'id'])->on('simulation_campaigns')->cascadeOnDelete();
            $table->foreign(['organisation_id', 'membership_id'])->references(['organisation_id', 'id'])->on('memberships')->restrictOnDelete();
        });

        Schema::create('simulation_suppressions', function (Blueprint $table) {
            $table->uuid('organisation_id');
            $table->uuid('membership_id');
            $table->string('reason', 120);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->primary(['organisation_id', 'membership_id']);
            $table->foreign(['organisation_id', 'membership_id'])->references(['organisation_id', 'id'])->on('memberships')->cascadeOnDelete();
        });

        Schema::create('simulation_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organisation_id');
            $table->uuid('campaign_id');
            $table->uuid('campaign_recipient_id');
            $table->string('event_type', 32);
            $table->timestampTz('occurred_at')->useCurrent();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'campaign_recipient_id', 'event_type']);
            $table->foreign(['organisation_id', 'campaign_id'])->references(['organisation_id', 'id'])->on('simulation_campaigns')->cascadeOnDelete();
            $table->foreign(['organisation_id', 'campaign_recipient_id'])->references(['organisation_id', 'id'])->on('simulation_campaign_recipients')->cascadeOnDelete();
        });

        Schema::create('simulation_delivery_outbox', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organisation_id');
            $table->uuid('campaign_id');
            $table->uuid('campaign_recipient_id');
            $table->string('status', 24)->default('queued');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('available_at');
            $table->timestampTz('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'campaign_recipient_id'], 'sim_delivery_org_recipient_unique');
            $table->foreign(['organisation_id', 'campaign_id'])->references(['organisation_id', 'id'])->on('simulation_campaigns')->cascadeOnDelete();
            $table->foreign(['organisation_id', 'campaign_recipient_id'])->references(['organisation_id', 'id'])->on('simulation_campaign_recipients')->cascadeOnDelete();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            $tables = ['simulation_settings', 'simulation_domains', 'simulation_authorisations', 'simulation_authorised_recipients', 'simulation_campaigns', 'simulation_approvals', 'simulation_campaign_recipients', 'simulation_suppressions', 'simulation_events', 'simulation_delivery_outbox'];
            foreach ($tables as $table) {
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
                DB::statement("CREATE POLICY tenant_isolation ON {$table} USING (organisation_id = NULLIF(current_setting('app.organisation_id', true), '')::uuid) WITH CHECK (organisation_id = NULLIF(current_setting('app.organisation_id', true), '')::uuid)");
            }

        }
    }

    public function down(): void
    {
        $tables = ['simulation_delivery_outbox', 'simulation_events', 'simulation_suppressions', 'simulation_campaign_recipients', 'simulation_approvals', 'simulation_campaigns', 'simulation_authorised_recipients', 'simulation_authorisations', 'simulation_domains', 'simulation_settings'];
        foreach ($tables as $table) {
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
                DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
            }
            Schema::dropIfExists($table);
        }
        DB::table('permissions')->whereIn('name', ['simulations.authorise', 'simulations.approve', 'simulations.kill'])->delete();
    }
};
