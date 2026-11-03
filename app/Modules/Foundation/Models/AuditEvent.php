<?php

namespace App\Modules\Foundation\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class AuditEvent extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['organisation_id', 'actor_id', 'action', 'subject_type', 'subject_id', 'correlation_id', 'metadata', 'created_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'immutable_datetime'];
    }
}
