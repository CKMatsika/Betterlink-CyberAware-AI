<?php

namespace App\Console\Commands;

use App\Http\Controllers\SimulationController;
use App\Mail\SimulationTrainingMessage;
use App\Modules\Foundation\TenantContext;
use App\Modules\Simulation\DeliveryRetry;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

final class DispatchSimulationSandbox extends Command
{
    protected $signature = 'cyberaware:simulations:dispatch-due';

    protected $description = 'Dispatch due simulation messages to Laravel’s in-memory array mail transport only';

    public function handle(TenantContext $tenant): int
    {
        $due = DB::table('simulation_dispatch_queue')->whereRaw('available_at <= now()')->orderBy('available_at')->limit(100)->get(['id', 'organisation_id', 'outbox_id']);
        foreach ($due as $queueItem) {
            $tenant->run($queueItem->organisation_id, function () use ($queueItem) {
                $this->dispatchOne($queueItem->organisation_id, $queueItem->outbox_id);
                $outbox = DB::table('simulation_delivery_outbox')->where('organisation_id', $queueItem->organisation_id)->where('id', $queueItem->outbox_id)->first();
                if (! $outbox || $outbox->status !== 'queued') {
                    DB::table('simulation_dispatch_queue')->where('id', $queueItem->id)->delete();
                }
                if ($outbox) {
                    $pending = DB::table('simulation_delivery_outbox')->where('organisation_id', $queueItem->organisation_id)->where('campaign_id', $outbox->campaign_id)->where('status', 'queued')->exists();
                    if (! $pending) {
                        DB::table('simulation_campaigns')->where('organisation_id', $queueItem->organisation_id)->where('id', $outbox->campaign_id)->where('status', 'scheduled')->update(['status' => 'completed', 'updated_at' => now()]);
                    }
                }
            });
        }

        return self::SUCCESS;
    }

    private function dispatchOne(string $organisationId, string $outboxId): void
    {
        DB::transaction(function () use ($organisationId, $outboxId) {
            $outbox = DB::table('simulation_delivery_outbox')->where('organisation_id', $organisationId)->where('id', $outboxId)->lockForUpdate()->first();
            if (! $outbox || $outbox->status !== 'queued' || CarbonImmutable::parse($outbox->available_at)->isFuture()) {
                return;
            }
            $campaign = DB::table('simulation_campaigns')->where('organisation_id', $organisationId)->where('id', $outbox->campaign_id)->first();
            $settings = DB::table('simulation_settings')->where('organisation_id', $organisationId)->first();
            if (! $campaign || $campaign->status !== 'scheduled' || ! $settings || ! $settings->enabled || $settings->kill_switch_active) {
                DB::table('simulation_delivery_outbox')->where('organisation_id', $organisationId)->where('id', $outboxId)->update(['status' => 'cancelled', 'updated_at' => now()]);

                return;
            }
            $approval = DB::table('simulation_approvals')->where('organisation_id', $organisationId)->where('campaign_id', $campaign->id)->where('scope_digest', $campaign->scope_digest)->where('approved_by', '!=', $campaign->created_by)->whereRaw('expires_at > now()')->latest('created_at')->first();
            $authorisation = DB::table('simulation_authorisations as a')->join('simulation_domains as d', function ($join) {
                $join->on('a.domain_id', '=', 'd.id')->on('a.organisation_id', '=', 'd.organisation_id');
            })->where('a.organisation_id', $organisationId)->where('a.id', $campaign->authorisation_id)->where('a.status', 'active')->whereRaw('a.starts_at <= now()')->whereRaw('a.expires_at > now()')->whereNotNull('d.verified_at')->first(['a.*', 'd.domain']);
            if (! $approval || ! $authorisation) {
                DB::table('simulation_delivery_outbox')->where('organisation_id', $organisationId)->where('id', $outboxId)->update(['status' => 'cancelled', 'updated_at' => now()]);

                return;
            }
            if (! hash_equals($campaign->scope_digest, $this->campaignDigest($campaign, $organisationId))) {
                DB::table('simulation_delivery_outbox')->where('organisation_id', $organisationId)->where('id', $outboxId)->update(['status' => 'cancelled', 'last_error' => 'Approved campaign scope changed', 'updated_at' => now()]);

                return;
            }
            $recipient = DB::table('simulation_campaign_recipients as r')->join('memberships as m', function ($join) {
                $join->on('m.id', '=', 'r.membership_id')->on('m.organisation_id', '=', 'r.organisation_id');
            })->join('users as u', 'u.id', '=', 'm.user_id')->join('simulation_authorised_recipients as ar', function ($join) use ($campaign) {
                $join->on('ar.membership_id', '=', 'm.id')->on('ar.organisation_id', '=', 'm.organisation_id')->where('ar.authorisation_id', '=', $campaign->authorisation_id);
            })->where('r.organisation_id', $organisationId)->where('r.id', $outbox->campaign_recipient_id)->where('r.campaign_id', $campaign->id)->where('m.status', 'active')->whereNotNull('u.email_verified_at')->whereRaw('lower(split_part(u.email, ?, 2)) = ?', ['@', $authorisation->domain])->whereNotExists(function ($query) use ($organisationId) {
                $query->selectRaw('1')->from('simulation_suppressions as s')->whereColumn('s.organisation_id', 'm.organisation_id')->whereColumn('s.membership_id', 'm.id')->where('s.organisation_id', $organisationId);
            })->first(['r.id', 'r.membership_id', 'u.email']);
            if (! $recipient) {
                DB::table('simulation_campaign_recipients')->where('organisation_id', $organisationId)->where('id', $outbox->campaign_recipient_id)->update(['delivery_status' => 'suppressed', 'updated_at' => now()]);
                DB::table('simulation_delivery_outbox')->where('organisation_id', $organisationId)->where('id', $outboxId)->update(['status' => 'cancelled', 'updated_at' => now()]);

                return;
            }
            $hourCount = DB::table('simulation_delivery_outbox as o')->join('simulation_campaigns as c', function ($join) {
                $join->on('c.id', '=', 'o.campaign_id')->on('c.organisation_id', '=', 'o.organisation_id');
            })->where('o.organisation_id', $organisationId)->where('c.authorisation_id', $campaign->authorisation_id)->where('o.status', 'sent')->whereRaw("o.sent_at >= now() - interval '1 hour'")->count();
            if ($hourCount >= $authorisation->max_deliveries_per_hour) {
                DB::table('simulation_delivery_outbox')->where('organisation_id', $organisationId)->where('id', $outboxId)->update(['available_at' => DB::raw("now() + interval '5 minutes'"), 'updated_at' => DB::raw('now()')]);
                DB::table('simulation_dispatch_queue')->where('organisation_id', $organisationId)->where('outbox_id', $outboxId)->update(['available_at' => DB::raw("now() + interval '5 minutes'")]);

                return;
            }
            try {
                $token = Str::random(64);
                $url = SimulationController::landingUrl($organisationId, $recipient->id).'#t='.$token;
                // Explicitly select the array transport. Simulation code cannot use configured SMTP/API transports.
                Mail::mailer('array')->to($recipient->email)->send(new SimulationTrainingMessage($url));
                DB::table('simulation_campaign_recipients')->where('organisation_id', $organisationId)->where('id', $recipient->id)->update(['token_hash' => hash('sha256', $token), 'token_expires_at' => DB::raw("now() + interval '30 days'"), 'delivery_status' => 'sandbox_delivered', 'delivered_at' => DB::raw('now()'), 'updated_at' => DB::raw('now()')]);
                DB::table('simulation_delivery_outbox')->where('organisation_id', $organisationId)->where('id', $outboxId)->update(['status' => 'sent', 'attempts' => $outbox->attempts + 1, 'sent_at' => DB::raw('now()'), 'updated_at' => DB::raw('now()')]);
                DB::table('simulation_events')->insertOrIgnore(['id' => (string) Str::uuid(), 'organisation_id' => $organisationId, 'campaign_id' => $campaign->id, 'campaign_recipient_id' => $recipient->id, 'event_type' => 'delivered', 'occurred_at' => DB::raw('now()')]);
                $pending = DB::table('simulation_delivery_outbox')->where('organisation_id', $organisationId)->where('campaign_id', $campaign->id)->where('status', 'queued')->exists();
                if (! $pending) {
                    DB::table('simulation_campaigns')->where('organisation_id', $organisationId)->where('id', $campaign->id)->update(['status' => 'completed', 'updated_at' => DB::raw('now()')]);
                }
            } catch (Throwable $exception) {
                app(DeliveryRetry::class)->recordFailure($organisationId, $outboxId, (int) $outbox->attempts);
                report($exception);
            }
        });
    }

    private function campaignDigest(object $campaign, string $organisationId): string
    {
        $recipients = DB::table('simulation_campaign_recipients')->where('organisation_id', $organisationId)->where('campaign_id', $campaign->id)->pluck('membership_id')->all();
        sort($recipients);

        return hash('sha256', json_encode([
            'authorisation' => $campaign->authorisation_id,
            'template' => $campaign->template_key,
            'scheduled_at' => CarbonImmutable::parse($campaign->scheduled_at)->utc()->toIso8601String(),
            'recipients' => $recipients,
            'remedial_course_version_id' => $campaign->remedial_course_version_id,
        ], JSON_THROW_ON_ERROR));
    }
}
