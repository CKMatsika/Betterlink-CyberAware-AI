<?php

namespace App\Http\Middleware;

use App\Modules\Foundation\CurrentMembership;
use App\Modules\Foundation\Models\Membership;
use App\Modules\Foundation\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireTenantMembership
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless($request->user(), 401);
        $organisationId = (string) $request->route('organisation');
        abort_unless(preg_match('/^[0-9a-f-]{36}$/i', $organisationId) === 1, 404);

        return app(TenantContext::class)->run($organisationId, function () use ($request, $next, $roles, $organisationId) {
            $membership = Membership::query()
                ->where('organisation_id', $organisationId)
                ->where('user_id', $request->user()->id)
                ->where('status', 'active')
                ->first();
            abort_unless($membership, 404);
            app(CurrentMembership::class)->membership = $membership;
            if ($roles !== []) {
                abort_unless($membership->hasAnyRole($roles), 403);
            }

            return $next($request);
        });
    }
}
