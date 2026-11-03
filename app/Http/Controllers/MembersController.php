<?php

namespace App\Http\Controllers;

use App\Modules\Foundation\Audit;
use App\Modules\Foundation\CurrentMembership;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class MembersController extends Controller
{
    public function index(Request $request, Audit $audit): Response
    {
        $membership = app(CurrentMembership::class)->membership;
        $audit->record($request, 'members.list.viewed', 'organisation', $membership->organisation_id);

        return Inertia::render('Tenant/Members', [
            'organisation' => $membership->organisation->only(['id', 'name']),
            'members' => $membership->newQuery()->where('organisation_id', $membership->organisation_id)
                ->with('user:id,name,email')->limit(250)->get()->map(fn ($row) => [
                    'id' => $row->id, 'name' => $row->user->name, 'email' => $row->user->email,
                    'status' => $row->status, 'roles' => $row->roleNames(),
                ]),
        ]);
    }
}
