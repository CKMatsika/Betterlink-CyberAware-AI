<?php

namespace App\Modules\Foundation\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

final class Membership extends Model
{
    use HasUuids;

    protected $fillable = ['organisation_id', 'user_id', 'department_id', 'branch_id', 'status', 'joined_at'];

    protected function casts(): array
    {
        return ['joined_at' => 'immutable_datetime'];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasAnyRole(array $roles): bool
    {
        return DB::table('membership_roles')->join('roles', function ($join) {
            $join->on('roles.id', '=', 'membership_roles.role_id')->on('roles.organisation_id', '=', 'membership_roles.organisation_id');
        })->where('membership_roles.organisation_id', $this->organisation_id)
            ->where('membership_roles.membership_id', $this->id)->whereIn('roles.name', $roles)->exists();
    }

    public function hasPermission(string $permission): bool
    {
        return DB::table('membership_roles')->join('role_permissions', function ($join) {
            $join->on('role_permissions.role_id', '=', 'membership_roles.role_id')->on('role_permissions.organisation_id', '=', 'membership_roles.organisation_id');
        })->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('membership_roles.organisation_id', $this->organisation_id)
            ->where('membership_roles.membership_id', $this->id)->where('permissions.name', $permission)->exists();
    }

    public function roleNames(): array
    {
        return DB::table('membership_roles')->join('roles', function ($join) {
            $join->on('roles.id', '=', 'membership_roles.role_id')->on('roles.organisation_id', '=', 'membership_roles.organisation_id');
        })->where('membership_roles.organisation_id', $this->organisation_id)
            ->where('membership_roles.membership_id', $this->id)->pluck('roles.name')->all();
    }
}
