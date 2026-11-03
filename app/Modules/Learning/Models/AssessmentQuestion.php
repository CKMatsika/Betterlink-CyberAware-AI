<?php

namespace App\Modules\Learning\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class AssessmentQuestion extends Model
{
    use HasUuids;

    protected $fillable = ['organisation_id', 'assessment_id', 'position', 'prompt', 'options', 'correct_option', 'feedback'];

    protected function casts(): array
    {
        return ['options' => 'array'];
    }
}
