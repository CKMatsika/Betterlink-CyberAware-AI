<?php

namespace App\Modules\Learning\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Assessment extends Model
{
    use HasUuids;

    protected $fillable = ['organisation_id', 'course_version_id', 'passing_score', 'max_attempts'];

    public function questions(): HasMany
    {
        return $this->hasMany(AssessmentQuestion::class)->orderBy('position');
    }
}
