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
            ['name' => 'risk.configure', 'description' => 'Create a new version of awareness indicator weights.'],
        ]);

        DB::table('role_permissions')->insertUsing(
            ['organisation_id', 'role_id', 'permission_id'],
            DB::table('roles')->crossJoin('permissions')
                ->where('roles.name', 'tenant_admin')->where('permissions.name', 'risk.configure')
                ->select('roles.organisation_id', 'roles.id', 'permissions.id')
        );

        Schema::create('risk_indicator_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organisation_id');
            $table->unsignedInteger('version');
            $table->jsonb('configuration');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'version']);
            $table->foreign('organisation_id')->references('id')->on('organisations')->restrictOnDelete();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE risk_indicator_versions ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE risk_indicator_versions FORCE ROW LEVEL SECURITY');
            DB::statement("CREATE POLICY tenant_isolation ON risk_indicator_versions USING (organisation_id = NULLIF(current_setting('app.organisation_id', true), '')::uuid) WITH CHECK (organisation_id = NULLIF(current_setting('app.organisation_id', true), '')::uuid)");
            DB::unprepared(<<<'SQL'
                CREATE FUNCTION prevent_risk_indicator_version_mutation() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'Risk indicator versions are immutable';
                END;
                $$ LANGUAGE plpgsql
            SQL);
            DB::statement('CREATE TRIGGER immutable_risk_indicator_version BEFORE UPDATE OR DELETE ON risk_indicator_versions FOR EACH ROW EXECUTE FUNCTION prevent_risk_indicator_version_mutation()');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS immutable_risk_indicator_version ON risk_indicator_versions');
            DB::statement('DROP FUNCTION IF EXISTS prevent_risk_indicator_version_mutation()');
            DB::statement('DROP POLICY IF EXISTS tenant_isolation ON risk_indicator_versions');
            DB::statement('ALTER TABLE risk_indicator_versions NO FORCE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE risk_indicator_versions DISABLE ROW LEVEL SECURITY');
        }
        Schema::dropIfExists('risk_indicator_versions');
        DB::table('role_permissions')->whereIn('permission_id', DB::table('permissions')->where('name', 'risk.configure')->select('id'))->delete();
        DB::table('permissions')->where('name', 'risk.configure')->delete();
    }
};
