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
        $membership = app(CurrentMembership::class)->membership;
        if ($request->user()->home_organisation_id !== $membership->organisation_id) {
            $request->user()->forceFill(['home_organisation_id' => $membership->organisation_id])->save();
        }
        app(Audit::class)->record($request, 'tenant.dashboard.viewed', 'membership', $membership->id);

        return Inertia::render('Tenant/Home', [
            'organisation' => $membership->organisation->only(['id', 'name', 'timezone']),
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
