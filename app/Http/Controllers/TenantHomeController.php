<?php

namespace App\Http\Controllers;

use App\Modules\Foundation\Audit;
use App\Modules\Foundation\CurrentMembership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TenantHomeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        app(Audit::class)->record($request, 'tenant.dashboard.viewed', 'membership', app(CurrentMembership::class)->membership->id);

        $membership = app(CurrentMembership::class)->membership;

        return Inertia::render('Tenant/Home', [
            'organisation' => app(CurrentMembership::class)->membership->organisation->only(['id', 'name', 'timezone']),
            'membership' => $membership->only(['id', 'department_id', 'branch_id']),
            'role' => $membership->roleNames(),
            'canViewRisk' => $membership->hasPermission('reports.view'),
        ]);
    }

    public function select(Request $request, string $organisation): RedirectResponse
    {
        $request->validate(['organisation_id' => ['required', 'uuid', 'in:'.$organisation]]);
        $request->session()->put('organisation_id', $organisation);

        return redirect()->route('tenant.home', ['organisation' => $organisation]);
    }
}
