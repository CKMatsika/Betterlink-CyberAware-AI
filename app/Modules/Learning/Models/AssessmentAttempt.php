<?php

namespace App\Modules\Learning\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class AssessmentAttempt extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['organisation_id', 'assignment_id', 'attempt_number', 'score', 'passed', 'submitted_at'];

    protected function casts(): array
    {
        return ['passed' => 'boolean', 'submitted_at' => 'immutable_datetime'];
    }
}
