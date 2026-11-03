<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 160);
            $table->string('slug', 96)->unique();
            $table->string('timezone', 64)->default('Africa/Harare');
            $table->string('status', 24)->default('active');
            $table->timestampsTz();
            $table->unique(['id', 'name']);
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organisation_id');
            $table->string('name', 120);
            $table->timestampsTz();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'name']);
            $table->foreign('organisation_id')->references('id')->on('organisations')->restrictOnDelete();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organisation_id');
            $table->string('name', 120);
            $table->timestampsTz();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'name']);
            $table->foreign('organisation_id')->references('id')->on('organisations')->restrictOnDelete();
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organisation_id');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->uuid('department_id')->nullable();
            $table->uuid('branch_id')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestampTz('joined_at')->nullable();
            $table->timestampsTz();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'user_id']);
            $table->foreign('organisation_id')->references('id')->on('organisations')->restrictOnDelete();
            $table->foreign(['organisation_id', 'department_id'])->references(['organisation_id', 'id'])->on('departments')->restrictOnDelete();
            $table->foreign(['organisation_id', 'branch_id'])->references(['organisation_id', 'id'])->on('branches')->restrictOnDelete();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organisation_id')->nullable();
            $table->string('name', 80);
            $table->string('label', 120);
            $table->timestampsTz();
            $table->unique(['organisation_id', 'id']);
            $table->foreign('organisation_id')->references('id')->on('organisations')->restrictOnDelete();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description', 240);
        });

        Schema::create('membership_roles', function (Blueprint $table) {
            $table->uuid('organisation_id');
            $table->uuid('membership_id');
            $table->uuid('role_id');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->primary(['organisation_id', 'membership_id', 'role_id']);
            $table->foreign(['organisation_id', 'membership_id'])->references(['organisation_id', 'id'])->on('memberships')->cascadeOnDelete();
            $table->foreign(['organisation_id', 'role_id'])->references(['organisation_id', 'id'])->on('roles')->cascadeOnDelete();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->uuid('organisation_id');
            $table->uuid('role_id');
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['organisation_id', 'role_id', 'permission_id']);
            $table->foreign(['organisation_id', 'role_id'])->references(['organisation_id', 'id'])->on('roles')->cascadeOnDelete();
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organisation_id');
            $table->string('email')->index();
            $table->string('token_hash', 64)->unique();
            $table->foreignId('invited_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('expires_at');
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampsTz();
            $table->foreign('organisation_id')->references('id')->on('organisations')->restrictOnDelete();
        });

        Schema::create('audit_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organisation_id')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->string('subject_type', 100)->nullable();
            $table->string('subject_id', 100)->nullable();
            $table->string('correlation_id', 64)->nullable()->index();
            $table->jsonb('metadata')->default('{}');
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('organisation_id')->references('id')->on('organisations')->restrictOnDelete();
        });

        // Seed non-tenant permission definitions. Tenant roles are created per organisation.
        DB::table('permissions')->insert([
            ['name' => 'tenant.manage', 'description' => 'Manage organisation settings and membership.'],
            ['name' => 'members.manage', 'description' => 'Invite and manage organisation members.'],
            ['name' => 'roles.manage', 'description' => 'Assign tenant-scoped roles.'],
            ['name' => 'learning.manage', 'description' => 'Manage learning assignments and content.'],
            ['name' => 'simulations.manage', 'description' => 'Manage authorised simulations.'],
            ['name' => 'reports.view', 'description' => 'View organisation reports.'],
            ['name' => 'audit.view', 'description' => 'View audit evidence.'],
        ]);

    }

    public function down(): void
    {
        foreach (['audit_events', 'invitations', 'role_permissions', 'membership_roles', 'permissions', 'roles', 'memberships', 'branches', 'departments', 'organisations'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
