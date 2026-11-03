<?php

namespace App\Modules\Foundation\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Organisation extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'slug', 'timezone'];

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }
}
