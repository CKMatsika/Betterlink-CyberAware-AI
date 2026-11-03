<?php

namespace App\Modules\Learning\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Course extends Model
{
    use HasUuids;

    protected $fillable = ['organisation_id', 'slug', 'status', 'created_by'];

    public function versions(): HasMany
    {
        return $this->hasMany(CourseVersion::class);
    }
}
