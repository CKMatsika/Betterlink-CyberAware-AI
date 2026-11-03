<?php

namespace App\Modules\Foundation;

use App\Modules\Foundation\Models\AuditEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class Audit
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function record(Request $request, string $action, ?string $subjectType = null, ?string $subjectId = null, array $metadata = []): void
    {
        AuditEvent::create([
            'organisation_id' => app(CurrentMembership::class)->membership?->organisation_id ?? $this->tenant->organisationId(),
            'actor_id' => $request->user()?->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'correlation_id' => $request->attributes->get('request_id') ?? (string) Str::uuid(),
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
