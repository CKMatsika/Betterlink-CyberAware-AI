<?php

namespace App\Modules\Risk;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AwarenessIndicatorService
{
    public const MINIMUM_COHORT = 5;

    public const DEFAULT_CONFIGURATION = [
        'learning_completion' => 40,
        'assessment_pass_rate' => 35,
        'education_acknowledgement' => 25,
    ];

    public function configuration(string $organisationId): array
    {
        $version = DB::table('risk_indicator_versions')->where('organisation_id', $organisationId)->orderByDesc('version')->first();

        return $this->configurationFromVersion($version);
    }

    public function saveConfiguration(string $organisationId, int $createdBy, array $weights): int
    {
        $version = ((int) DB::table('risk_indicator_versions')->where('organisation_id', $organisationId)->max('version')) + 1;
        DB::table('risk_indicator_versions')->insert([
            'id' => (string) Str::uuid(),
            'organisation_id' => $organisationId,
            'version' => $version,
            'configuration' => json_encode($weights, JSON_THROW_ON_ERROR),
            'created_by' => $createdBy,
            'created_at' => now(),
        ]);

        return $version;
    }

    public function dashboard(string $organisationId, string $timezone): array
    {
        $configuration = $this->configuration($organisationId);
        $periods = [];
        $now = CarbonImmutable::now($timezone)->startOfMonth();

        for ($offset = 5; $offset >= 0; $offset--) {
            $month = $now->subMonths($offset);
            $start = $month->startOfMonth()->utc()->format('Y-m-d H:i:sP');
            $end = $month->addMonth()->startOfMonth()->utc()->format('Y-m-d H:i:sP');
            $metrics = $this->metrics($organisationId, $start, $end);
            $periodConfiguration = $this->configurationAt($organisationId, $end);
            $periods[] = $this->scorePeriod($month->format('Y-m'), $metrics, $periodConfiguration['weights']) + ['configuration_version' => $periodConfiguration['version']];
        }

        $reportable = collect($periods)->filter(fn (array $period) => $period['score'] !== null)->values();
        $latest = $reportable->last();
        $previous = $reportable->count() > 1 ? $reportable->get($reportable->count() - 2) : null;
        $change = $latest && $previous ? round($latest['score'] - $previous['score'], 1) : null;
        $summary = match (true) {
            ! $latest => 'There is not enough cohort data to produce an awareness index yet.',
            $change === null => 'This is the first reportable period. The index reflects observed learning signals, not individual behaviour.',
            $change > 0 => 'The awareness index rose '.number_format($change, 1).' points from the previous reportable period. Use this as a prompt to continue supportive training.',
            $change < 0 => 'The awareness index fell '.number_format(abs($change), 1).' points from the previous reportable period. Review training coverage and learning support.',
            default => 'The awareness index is unchanged from the previous reportable period. Continue reviewing coverage and learning support.',
        };

        return [
            'configuration' => $configuration,
            'minimum_cohort' => self::MINIMUM_COHORT,
            'periods' => $periods,
            'latest' => $latest,
            'change' => $change,
            'summary' => $summary,
        ];
    }

    public function scorePeriod(string $period, array $metrics, array $weights): array
    {
        $visible = [];
        $weighted = 0.0;
        $availableWeight = 0;
        foreach (self::DEFAULT_CONFIGURATION as $key => $_defaultWeight) {
            $metric = $metrics[$key] ?? ['numerator' => 0, 'denominator' => 0, 'cohort' => 0];
            $cohort = (int) ($metric['cohort'] ?? 0);
            $denominator = (int) ($metric['denominator'] ?? 0);
            $numerator = (int) ($metric['numerator'] ?? 0);
            $weight = (int) ($weights[$key] ?? 0);

            if ($cohort < self::MINIMUM_COHORT || $denominator === 0) {
                $visible[$key] = [
                    'available' => false,
                    'status' => $cohort > 0 && $cohort < self::MINIMUM_COHORT ? 'suppressed' : 'no_data',
                    'sample_size' => null,
                    'rate' => null,
                    'weight' => $weight,
                ];

                continue;
            }

            $rate = round(max(0, min(100, ($numerator / $denominator) * 100)), 1);
            $visible[$key] = ['available' => true, 'status' => 'available', 'sample_size' => $cohort, 'rate' => $rate, 'weight' => $weight];
            $weighted += $rate * $weight;
            $availableWeight += $weight;
        }

        return [
            'period' => $period,
            'score' => $availableWeight > 0 ? round($weighted / $availableWeight, 1) : null,
            'metrics' => $visible,
            'has_suppressed_metrics' => collect($visible)->contains(fn (array $metric) => $metric['status'] === 'suppressed'),
        ];
    }

    private function metrics(string $organisationId, string $start, string $end): array
    {
        $assignments = DB::table('course_assignments as a')
            ->join('memberships as m', function ($join) {
                $join->on('m.organisation_id', '=', 'a.organisation_id')->on('m.id', '=', 'a.membership_id');
            })
            ->join('lessons as l', function ($join) {
                $join->on('l.organisation_id', '=', 'a.organisation_id')->on('l.course_version_id', '=', 'a.course_version_id');
            })
            ->leftJoin('lesson_progress as lp', function ($join) use ($end) {
                $join->on('lp.organisation_id', '=', 'a.organisation_id')->on('lp.assignment_id', '=', 'a.id')->on('lp.lesson_id', '=', 'l.id')->where('lp.completed_at', '<', $end);
            })
            ->where('a.organisation_id', $organisationId)
            ->where('a.created_at', '>=', $start)->where('a.created_at', '<', $end)
            ->groupBy('a.id', 'a.membership_id')
            ->select('a.id', 'a.membership_id', DB::raw('COUNT(l.id) as lesson_count'), DB::raw('COUNT(lp.completed_at) as completed_count'))
            ->get();
        $completedAssignments = $assignments->filter(fn ($row) => (int) $row->lesson_count > 0 && (int) $row->lesson_count === (int) $row->completed_count);

        $attempts = DB::table('assessment_attempts as aa')
            ->join('course_assignments as a', function ($join) {
                $join->on('a.organisation_id', '=', 'aa.organisation_id')->on('a.id', '=', 'aa.assignment_id');
            })
            ->join('memberships as m', function ($join) {
                $join->on('m.organisation_id', '=', 'a.organisation_id')->on('m.id', '=', 'a.membership_id');
            })
            ->where('aa.organisation_id', $organisationId)
            ->where('aa.submitted_at', '>=', $start)->where('aa.submitted_at', '<', $end)
            ->groupBy('a.id', 'a.membership_id')->select('a.id', 'a.membership_id', DB::raw('MAX(CASE WHEN aa.passed THEN 1 ELSE 0 END) as passed'))->get();

        $deliveries = DB::table('simulation_campaign_recipients as r')
            ->join('memberships as m', function ($join) {
                $join->on('m.organisation_id', '=', 'r.organisation_id')->on('m.id', '=', 'r.membership_id');
            })
            ->leftJoin('simulation_events as e', function ($join) use ($end) {
                $join->on('e.organisation_id', '=', 'r.organisation_id')->on('e.campaign_recipient_id', '=', 'r.id')->where('e.event_type', '=', 'education_viewed')->where('e.occurred_at', '<', $end);
            })
            ->where('r.organisation_id', $organisationId)->where('r.delivery_status', 'sandbox_delivered')
            ->where('r.delivered_at', '>=', $start)->where('r.delivered_at', '<', $end)
            ->groupBy('r.id', 'r.membership_id')->select('r.id', 'r.membership_id', DB::raw('COUNT(e.id) as acknowledgements'))->get();

        return [
            'learning_completion' => [
                'numerator' => $completedAssignments->count(), 'denominator' => $assignments->count(), 'cohort' => $assignments->pluck('membership_id')->unique()->count(),
            ],
            'assessment_pass_rate' => [
                'numerator' => $attempts->filter(fn ($row) => (bool) $row->passed)->count(), 'denominator' => $attempts->count(), 'cohort' => $attempts->pluck('membership_id')->unique()->count(),
            ],
            'education_acknowledgement' => [
                'numerator' => $deliveries->where('acknowledgements', '>', 0)->count(), 'denominator' => $deliveries->count(), 'cohort' => $deliveries->pluck('membership_id')->unique()->count(),
            ],
        ];
    }

    private function configurationAt(string $organisationId, string $periodEnd): array
    {
        $version = DB::table('risk_indicator_versions')->where('organisation_id', $organisationId)
            ->where('created_at', '<', $periodEnd)->orderByDesc('version')->first();

        return $this->configurationFromVersion($version);
    }

    private function configurationFromVersion(?object $version): array
    {
        return [
            'version' => $version?->version ?? 0,
            'weights' => $version ? (array) json_decode($version->configuration, true) : self::DEFAULT_CONFIGURATION,
        ];
    }
}
