<?php

namespace App\Modules\Simulation;

use Illuminate\Support\Facades\DB;

final class DeliveryRetry
{
    public function recordFailure(string $organisationId, string $outboxId, int $previousAttempts): int
    {
        $attempts = $previousAttempts + 1;
        $attributes = [
            'status' => $attempts >= 3 ? 'failed' : 'queued',
            'attempts' => $attempts,
            'last_error' => 'Sandbox transport error',
            'updated_at' => now(),
        ];
        if ($attempts < 3) {
            $attributes['available_at'] = now()->addMinutes(5)->utc()->format('Y-m-d H:i:sP');
        }
        DB::table('simulation_delivery_outbox')->where('organisation_id', $organisationId)->where('id', $outboxId)->update($attributes);
        if ($attempts < 3) {
            DB::table('simulation_dispatch_queue')->where('organisation_id', $organisationId)->where('outbox_id', $outboxId)->update(['available_at' => $attributes['available_at']]);
        }

        return $attempts;
    }
}
