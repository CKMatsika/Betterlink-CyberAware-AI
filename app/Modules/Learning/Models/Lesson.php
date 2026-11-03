<?php

namespace App\Modules\Learning\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class Lesson extends Model
{
    use HasUuids;

    protected $fillable = ['organisation_id', 'course_version_id', 'position', 'title', 'content', 'media_path', 'transcript', 'estimated_minutes'];
}
