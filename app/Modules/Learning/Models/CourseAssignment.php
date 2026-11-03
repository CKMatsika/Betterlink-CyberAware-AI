<?php

namespace App\Modules\Learning\Models;

use App\Modules\Foundation\Models\Membership;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CourseAssignment extends Model
{
    use HasUuids;

    protected $fillable = ['organisation_id', 'membership_id', 'course_version_id', 'assigned_by', 'due_at', 'status'];

    protected function casts(): array
    {
        return ['due_at' => 'immutable_datetime'];
    }

    public function courseVersion(): BelongsTo
    {
        return $this->belongsTo(CourseVersion::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }
}
