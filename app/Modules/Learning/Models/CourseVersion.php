<?php

namespace App\Modules\Learning\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class CourseVersion extends Model
{
    use HasUuids;

    protected $fillable = ['organisation_id', 'course_id', 'version', 'locale', 'title', 'description', 'status', 'created_by', 'reviewed_by', 'published_at'];

    protected function casts(): array
    {
        return ['published_at' => 'immutable_datetime'];
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('position');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function assessment(): HasOne
    {
        return $this->hasOne(Assessment::class);
    }
}
