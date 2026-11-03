<?php

namespace App\Http\Middleware;

use App\Modules\Foundation\CurrentMembership;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequirePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $membership = app(CurrentMembership::class)->membership;
        abort_unless($membership && $membership->hasPermission($permission), 403);

        return $next($request);
    }
}
