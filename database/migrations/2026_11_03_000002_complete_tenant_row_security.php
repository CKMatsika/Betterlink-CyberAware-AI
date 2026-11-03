<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['departments', 'branches', 'memberships', 'invitations', 'roles', 'membership_roles', 'role_permissions', 'audit_events'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            DB::statement("CREATE POLICY tenant_isolation ON {$table} USING (organisation_id = NULLIF(current_setting('app.organisation_id', true), '')::uuid) WITH CHECK (organisation_id = NULLIF(current_setting('app.organisation_id', true), '')::uuid)");
        }

        DB::statement('ALTER TABLE organisations ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE organisations FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON organisations');
        DB::statement("CREATE POLICY tenant_isolation ON organisations USING (id = NULLIF(current_setting('app.organisation_id', true), '')::uuid) WITH CHECK (id = NULLIF(current_setting('app.organisation_id', true), '')::uuid)");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['organisations', 'departments', 'branches', 'memberships', 'invitations', 'roles', 'membership_roles', 'role_permissions', 'audit_events'] as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
