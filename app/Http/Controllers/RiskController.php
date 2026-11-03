<?php

namespace App\Http\Controllers;

use App\Modules\Foundation\Audit;
use App\Modules\Foundation\CurrentMembership;
use App\Modules\Risk\AwarenessIndicatorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class RiskController extends Controller
{
    public function dashboard(Request $request, AwarenessIndicatorService $indicators, Audit $audit): Response
    {
        $membership = app(CurrentMembership::class)->membership;
        $organisation = $membership->organisation;
        $audit->record($request, 'risk.dashboard.viewed', 'organisation', $organisation->id);

        return Inertia::render('Risk/Dashboard', [
            'organisation' => $organisation->only(['id', 'name', 'timezone']),
            'dashboard' => $indicators->dashboard($organisation->id, $organisation->timezone),
            'canConfigure' => $membership->hasPermission('risk.configure'),
            'labels' => [
                'learning_completion' => ['title' => 'Learning completion', 'description' => 'Assigned course versions completed during the period.'],
                'assessment_pass_rate' => ['title' => 'Assessment pass rate', 'description' => 'Assigned assessments with a passing attempt during the period.'],
                'education_acknowledgement' => ['title' => 'Post-simulation learning acknowledgement', 'description' => 'Sandbox-delivered participants who opened the educational follow-up. This is a positive learning signal.'],
            ],
        ]);
    }

    public function configure(Request $request, AwarenessIndicatorService $indicators, Audit $audit): RedirectResponse
    {
        $membership = app(CurrentMembership::class)->membership;
        abort_unless($membership->hasPermission('risk.configure'), 403);

        $data = $request->validate([
            'weights' => ['required', 'array:learning_completion,assessment_pass_rate,education_acknowledgement'],
            'weights.learning_completion' => ['required', 'integer', 'between:0,100'],
            'weights.assessment_pass_rate' => ['required', 'integer', 'between:0,100'],
            'weights.education_acknowledgement' => ['required', 'integer', 'between:0,100'],
        ]);
        $weights = $data['weights'];
        if (array_sum($weights) !== 100) {
            throw ValidationException::withMessages(['weights' => 'Indicator weights must add up to 100%.']);
        }

        $version = DB::transaction(function () use ($indicators, $membership, $request, $audit, $weights) {
            $version = $indicators->saveConfiguration($membership->organisation_id, $request->user()->id, $weights);
            $audit->record($request, 'risk.indicators.versioned', 'risk_indicator_configuration', $membership->organisation_id, ['version' => $version, 'weights' => $weights]);

            return $version;
        });

        return back()->with('status', "Awareness indicator configuration saved as version {$version}.");
    }
}
