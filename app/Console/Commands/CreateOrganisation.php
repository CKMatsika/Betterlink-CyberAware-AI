<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Foundation\Audit;
use App\Modules\Foundation\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateOrganisation extends Command
{
    protected $signature = 'cyberaware:organisation {name} {slug} {admin-email}';

    protected $description = 'Create an organisation and assign its verified initial administrator';

    public function handle(TenantContext $tenant, Audit $audit): int
    {
        $user = User::where('email', Str::lower($this->argument('admin-email')))->first();
        if (! $user || ! $user->hasVerifiedEmail()) {
            $this->components->error('The initial administrator must already have a verified account.');

            return self::FAILURE;
        }

        $id = (string) Str::uuid();
        $tenant->run($id, function () use ($id, $user, $audit) {
            $name = $this->argument('name');
            DB::table('organisations')->insert(['id' => $id, 'name' => $name, 'slug' => $this->argument('slug'), 'timezone' => 'Africa/Harare', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            $membershipId = (string) Str::uuid();
            DB::table('memberships')->insert(['id' => $membershipId, 'organisation_id' => $id, 'user_id' => $user->id, 'status' => 'active', 'joined_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $permissions = DB::table('permissions')->pluck('id', 'name');
            $roleIds = [];
            foreach (['tenant_admin', 'learner', 'training_manager', 'security_officer', 'departmental_manager', 'auditor'] as $roleName) {
                $roleId = (string) Str::uuid();
                $roleIds[$roleName] = $roleId;
                DB::table('roles')->insert(['id' => $roleId, 'organisation_id' => $id, 'name' => $roleName, 'label' => Str::headline($roleName), 'created_at' => now(), 'updated_at' => now()]);
            }
            $grants = [
                'tenant_admin' => $permissions->keys()->all(),
                'training_manager' => ['learning.manage', 'reports.view'],
                'security_officer' => ['simulations.manage', 'reports.view'],
                'departmental_manager' => ['reports.view'],
                'auditor' => ['reports.view', 'audit.view'],
            ];
            foreach ($grants as $role => $names) {
                foreach ($names as $permission) {
                    DB::table('role_permissions')->insert(['organisation_id' => $id, 'role_id' => $roleIds[$role], 'permission_id' => $permissions[$permission]]);
                }
            }
            DB::table('membership_roles')->insert(['organisation_id' => $id, 'membership_id' => $membershipId, 'role_id' => $roleIds['tenant_admin'], 'assigned_by' => $user->id, 'created_at' => now()]);
            $audit->record(request(), 'organisation.created', 'organisation', $id, ['slug' => $this->argument('slug')]);
        });

        $this->components->info("Organisation created: {$id}");

        return self::SUCCESS;
    }
}
