<?php

namespace App\Modules\Foundation;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Explicit tenant database scope. Call inside a transaction; SET LOCAL cannot
 * outlive its transaction and does not leak between pooled connections.
 */
final class TenantContext
{
    private ?string $organisationId = null;

    public function organisationId(): string
    {
        return $this->organisationId ?? throw new RuntimeException('Tenant context is not established.');
    }

    public function run(string $organisationId, callable $callback): mixed
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('Tenant row security requires PostgreSQL.');
        }

        return DB::transaction(function () use ($organisationId, $callback) {
            DB::select("select set_config('app.organisation_id', ?, true)", [$organisationId]);
            $previous = $this->organisationId;
            $this->organisationId = $organisationId;

            try {
                return $callback($this);
            } finally {
                $this->organisationId = $previous;
            }
        });
    }
}
