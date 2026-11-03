<?php

namespace App\Http\Controllers;

use App\Modules\Foundation\Audit;
use App\Modules\Foundation\CurrentMembership;
use App\Modules\Foundation\Models\Membership;
use App\Modules\Foundation\TenantContext;
use App\Modules\Learning\Models\CourseAssignment;
use App\Modules\Learning\Models\CourseVersion;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class SimulationController extends Controller
{
    public function manage(Request $request): Response
    {
        $org = app(CurrentMembership::class)->membership->organisation_id;
        abort_unless(app(CurrentMembership::class)->membership->hasPermission('simulations.manage'), 403);
        $settings = DB::table('simulation_settings')->where('organisation_id', $org)->first();
        $canAuthorise = app(CurrentMembership::class)->membership->hasPermission('simulations.authorise');
        $domains = DB::table('simulation_domains')->where('organisation_id', $org)->orderBy('domain')->get(['id', 'domain', 'verified_at', 'verification_token'])->map(function ($domain) use ($canAuthorise) {
            $domain->verification_token = $canAuthorise && ! $domain->verified_at ? $domain->verification_token : null;

            return $domain;
        });
        $authorisations = DB::table('simulation_authorisations as a')->join('simulation_domains as d', function ($join) {
            $join->on('a.domain_id', '=', 'd.id')->on('a.organisation_id', '=', 'd.organisation_id');
        })->where('a.organisation_id', $org)->select('a.*', 'd.domain')->latest('a.created_at')->get()->map(function ($auth) use ($org) {
            $auth->membership_ids = DB::table('simulation_authorised_recipients')->where('organisation_id', $org)->where('authorisation_id', $auth->id)->pluck('membership_id')->all();

            return $auth;
        });
        $campaigns = DB::table('simulation_campaigns as c')->where('c.organisation_id', $org)->latest('c.created_at')->get()->map(function ($campaign) use ($org) {
            $campaign->recipient_count = DB::table('simulation_campaign_recipients')->where('organisation_id', $org)->where('campaign_id', $campaign->id)->count();
            $campaign->delivered_count = DB::table('simulation_campaign_recipients')->where('organisation_id', $org)->where('campaign_id', $campaign->id)->where('delivery_status', 'sandbox_delivered')->count();
            $campaign->education_viewed_count = DB::table('simulation_events')->where('organisation_id', $org)->where('campaign_id', $campaign->id)->where('event_type', 'education_viewed')->count();
            $campaign->can_approve = (int) $campaign->created_by !== (int) $request->user()->id && app(CurrentMembership::class)->membership->hasPermission('simulations.approve');

            return $campaign;
        });
        $members = Membership::with('user:id,name,email,email_verified_at')->where('organisation_id', $org)->where('status', 'active')->whereHas('user', fn ($query) => $query->whereNotNull('email_verified_at'))->limit(250)->get()->map(fn ($member) => [
            'id' => $member->id, 'name' => $member->user->name, 'email' => $member->user->email,
        ]);
        $courses = CourseVersion::where('organisation_id', $org)->where('status', 'published')->get(['id', 'title', 'version']);
        $suppressedMembershipIds = DB::table('simulation_suppressions')->where('organisation_id', $org)->pluck('membership_id')->all();

        return Inertia::render('Simulation/Manage', [
            'organisation' => app(CurrentMembership::class)->membership->organisation->only(['id', 'name', 'timezone']),
            'settings' => $settings ?? ['enabled' => false, 'kill_switch_active' => true],
            'domains' => $domains, 'authorisations' => $authorisations, 'campaigns' => $campaigns,
            'members' => $members, 'courses' => $courses, 'suppressedMembershipIds' => $suppressedMembershipIds,
        ]);
    }

    public function addDomain(Request $request, Audit $audit): RedirectResponse
    {
        abort_unless(app(CurrentMembership::class)->membership->hasPermission('simulations.authorise'), 403);
        $data = $request->validate(['domain' => ['required', 'string', 'max:253', 'regex:/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i']]);
        $org = app(CurrentMembership::class)->membership->organisation_id;
        $domain = Str::lower(rtrim($data['domain'], '.'));
        $token = Str::random(40);
        $id = (string) Str::uuid();
        DB::table('simulation_domains')->insert(['id' => $id, 'organisation_id' => $org, 'domain' => $domain, 'verification_token' => $token, 'added_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        $audit->record($request, 'simulation.domain.challenge_created', 'simulation_domain', $id, ['domain' => $domain]);

        return back()->with('status', "Add TXT _betterlink-cyberaware.{$domain} with value betterlink-verification={$token}, then verify the domain.");
    }

    public function verifyDomain(Request $request, string $organisation, string $domainId, Audit $audit): RedirectResponse
    {
        abort_unless(app(CurrentMembership::class)->membership->hasPermission('simulations.authorise'), 403);
        $org = app(CurrentMembership::class)->membership->organisation_id;
        $domain = DB::table('simulation_domains')->where('organisation_id', $org)->where('id', $domainId)->firstOrFail();
        $records = @dns_get_record('_betterlink-cyberaware.'.$domain->domain, DNS_TXT) ?: [];
        $expected = 'betterlink-verification='.$domain->verification_token;
        $verified = collect($records)->contains(fn ($record) => ($record['txt'] ?? '') === $expected || ($record['entries'][0] ?? '') === $expected);
        if (! $verified) {
            throw ValidationException::withMessages(['domain' => 'The required DNS TXT verification record was not found.']);
        }
        DB::table('simulation_domains')->where('organisation_id', $org)->where('id', $domainId)->update(['verified_at' => now(), 'updated_at' => now()]);
        $audit->record($request, 'simulation.domain.verified', 'simulation_domain', $domainId, ['domain' => $domain->domain]);

        return back()->with('status', 'Organization domain verified.');
    }

    public function authorise(Request $request, Audit $audit): RedirectResponse
    {
        abort_unless(app(CurrentMembership::class)->membership->hasPermission('simulations.authorise'), 403);
        $data = $request->validate([
            'domain_id' => ['required', 'uuid'], 'membership_ids' => ['required', 'array', 'min:1', 'max:250'],
            'membership_ids.*' => ['required', 'uuid', 'distinct'], 'starts_at' => ['required', 'date'],
            'expires_at' => ['required', 'date', 'after:starts_at'], 'max_deliveries_per_hour' => ['required', 'integer', 'between:1,100'],
        ]);
        $org = app(CurrentMembership::class)->membership->organisation_id;
        $timezone = app(CurrentMembership::class)->membership->organisation->timezone;
        $startsAt = CarbonImmutable::parse($data['starts_at'], $timezone);
        $expiresAt = CarbonImmutable::parse($data['expires_at'], $timezone);
        abort_unless($startsAt->isFuture() && $expiresAt->greaterThan($startsAt), 422, 'The authorisation must use a future start and later expiry in the organisation timezone.');
        $domain = DB::table('simulation_domains')->where('organisation_id', $org)->where('id', $data['domain_id'])->whereNotNull('verified_at')->first();
        abort_unless($domain, 422, 'Choose a domain verified by DNS.');
        $members = Membership::with('user')->where('organisation_id', $org)->where('status', 'active')->whereIn('id', $data['membership_ids'])->get();
        abort_unless($members->count() === count($data['membership_ids']), 422, 'Recipients must be active members of this organisation.');
        foreach ($members as $member) {
            abort_unless($member->user->email_verified_at && Str::lower(Str::afterLast($member->user->email, '@')) === $domain->domain, 422, 'Every recipient must have a verified email at the approved domain.');
        }
        $authId = (string) Str::uuid();
        DB::transaction(function () use ($data, $org, $authId, $request, $startsAt, $expiresAt) {
            DB::table('simulation_authorisations')->insert(['id' => $authId, 'organisation_id' => $org, 'domain_id' => $data['domain_id'], 'authorised_by' => $request->user()->id, 'starts_at' => $startsAt->utc()->format('Y-m-d H:i:sP'), 'expires_at' => $expiresAt->utc()->format('Y-m-d H:i:sP'), 'max_recipients' => count($data['membership_ids']), 'max_deliveries_per_hour' => $data['max_deliveries_per_hour'], 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            foreach ($data['membership_ids'] as $membershipId) {
                DB::table('simulation_authorised_recipients')->insert(['organisation_id' => $org, 'authorisation_id' => $authId, 'membership_id' => $membershipId]);
            }
        });
        $audit->record($request, 'simulation.scope.authorised', 'simulation_authorisation', $authId, ['recipient_count' => count($data['membership_ids']), 'domain' => $domain->domain, 'expires_at' => $data['expires_at']]);

        return back()->with('status', 'Simulation scope authorised for the listed recipients and validity window.');
    }

    public function createCampaign(Request $request, Audit $audit): RedirectResponse
    {
        abort_unless(app(CurrentMembership::class)->membership->hasPermission('simulations.manage'), 403);
        $data = $request->validate([
            'authorisation_id' => ['required', 'uuid'], 'membership_ids' => ['required', 'array', 'min:1', 'max:250'],
            'membership_ids.*' => ['required', 'uuid', 'distinct'], 'scheduled_at' => ['required', 'date'],
            'remedial_course_version_id' => ['nullable', 'uuid'],
        ]);
        $org = app(CurrentMembership::class)->membership->organisation_id;
        $scheduledAt = CarbonImmutable::parse($data['scheduled_at'], app(CurrentMembership::class)->membership->organisation->timezone);
        abort_unless($scheduledAt->isFuture(), 422, 'Schedule the campaign for a future time in the organisation timezone.');
        $scheduledAtUtc = $scheduledAt->utc()->format('Y-m-d H:i:sP');
        $authorisation = DB::table('simulation_authorisations')->where('organisation_id', $org)->where('id', $data['authorisation_id'])->where('status', 'active')->where('starts_at', '<=', $scheduledAtUtc)->where('expires_at', '>', $scheduledAtUtc)->firstOrFail();
        $allowed = DB::table('simulation_authorised_recipients')->where('organisation_id', $org)->where('authorisation_id', $authorisation->id)->whereIn('membership_id', $data['membership_ids'])->pluck('membership_id')->all();
        abort_unless(count($allowed) === count($data['membership_ids']) && count($allowed) <= $authorisation->max_recipients, 422, 'Campaign recipients must be a subset of the authorised scope.');
        abort_if(DB::table('simulation_suppressions')->where('organisation_id', $org)->whereIn('membership_id', $allowed)->exists(), 422, 'The recipient list contains a suppressed member.');
        if (! empty($data['remedial_course_version_id'])) {
            abort_unless(CourseVersion::where('organisation_id', $org)->where('id', $data['remedial_course_version_id'])->where('status', 'published')->exists(), 422, 'Remedial learning must use a published course.');
        }
        sort($allowed);
        $digest = hash('sha256', json_encode(['authorisation' => $authorisation->id, 'template' => 'safe_document_review', 'scheduled_at' => $scheduledAt->utc()->toIso8601String(), 'recipients' => $allowed, 'remedial_course_version_id' => $data['remedial_course_version_id'] ?? null], JSON_THROW_ON_ERROR));
        $campaignId = (string) Str::uuid();
        DB::transaction(function () use ($data, $org, $campaignId, $digest, $allowed, $request, $scheduledAtUtc) {
            DB::table('simulation_campaigns')->insert(['id' => $campaignId, 'organisation_id' => $org, 'authorisation_id' => $data['authorisation_id'], 'remedial_course_version_id' => $data['remedial_course_version_id'] ?? null, 'created_by' => $request->user()->id, 'template_key' => 'safe_document_review', 'status' => 'draft', 'scheduled_at' => $scheduledAtUtc, 'scope_digest' => $digest, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($allowed as $memberId) {
                DB::table('simulation_campaign_recipients')->insert(['id' => (string) Str::uuid(), 'organisation_id' => $org, 'campaign_id' => $campaignId, 'membership_id' => $memberId, 'delivery_status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
            }
        });
        $audit->record($request, 'simulation.campaign.drafted', 'simulation_campaign', $campaignId, ['recipient_count' => count($allowed), 'scope_digest' => $digest, 'template' => 'safe_document_review']);

        return back()->with('status', 'Campaign draft created. Its scope is locked; changing recipients or timing requires a new draft.');
    }

    public function approve(Request $request, string $organisation, string $campaignId, Audit $audit): RedirectResponse
    {
        abort_unless(app(CurrentMembership::class)->membership->hasPermission('simulations.approve'), 403);
        $org = app(CurrentMembership::class)->membership->organisation_id;
        $campaign = DB::table('simulation_campaigns')->where('organisation_id', $org)->where('id', $campaignId)->where('status', 'draft')->firstOrFail();
        abort_unless((int) $campaign->created_by !== (int) $request->user()->id, 403, 'The campaign author cannot approve their own campaign.');
        abort_unless(hash_equals($campaign->scope_digest, $this->campaignDigest($campaign, $org)), 409, 'Campaign scope changed; create a new campaign draft.');
        $validUntil = now()->addHours(24)->utc()->format('Y-m-d H:i:sP');
        DB::table('simulation_approvals')->insert(['id' => (string) Str::uuid(), 'organisation_id' => $org, 'campaign_id' => $campaign->id, 'approved_by' => $request->user()->id, 'scope_digest' => $campaign->scope_digest, 'expires_at' => $validUntil, 'created_at' => now()]);
        DB::table('simulation_campaigns')->where('organisation_id', $org)->where('id', $campaign->id)->update(['status' => 'approved', 'approved_at' => now(), 'updated_at' => now()]);
        $audit->record($request, 'simulation.campaign.approved', 'simulation_campaign', $campaign->id, ['scope_digest' => $campaign->scope_digest, 'approval_expires_at' => CarbonImmutable::parse($validUntil)->toIso8601String()]);

        return back()->with('status', 'Campaign approved by a different authorised administrator.');
    }

    public function schedule(Request $request, string $organisation, string $campaignId, Audit $audit): RedirectResponse
    {
        abort_unless(app(CurrentMembership::class)->membership->hasPermission('simulations.manage'), 403);
        $org = app(CurrentMembership::class)->membership->organisation_id;
        $campaign = DB::table('simulation_campaigns')->where('organisation_id', $org)->where('id', $campaignId)->where('status', 'approved')->firstOrFail();
        $settings = DB::table('simulation_settings')->where('organisation_id', $org)->first();
        abort_unless($settings && $settings->enabled && ! $settings->kill_switch_active, 423, 'Simulations are disabled or the kill switch is active.');
        $approval = DB::table('simulation_approvals')->where('organisation_id', $org)->where('campaign_id', $campaignId)->where('scope_digest', $campaign->scope_digest)->where('approved_by', '!=', $campaign->created_by)->where('expires_at', '>', now()->utc()->format('Y-m-d H:i:sP'))->latest('created_at')->first();
        abort_unless($approval && CarbonImmutable::parse($campaign->scheduled_at)->isFuture(), 422, 'A current separate approval and future schedule are required.');
        abort_unless(hash_equals($campaign->scope_digest, $this->campaignDigest($campaign, $org)), 409, 'Campaign scope changed; create a new campaign draft.');
        $authorisation = DB::table('simulation_authorisations')->where('organisation_id', $org)->where('id', $campaign->authorisation_id)->where('status', 'active')->where('starts_at', '<=', $campaign->scheduled_at)->where('expires_at', '>', $campaign->scheduled_at)->first();
        abort_unless($authorisation, 422, 'The authorisation is not valid for the scheduled delivery.');
        $count = DB::table('simulation_campaign_recipients')->where('organisation_id', $org)->where('campaign_id', $campaignId)->count();
        abort_unless($count > 0 && $count <= $authorisation->max_recipients, 422);
        abort_if(DB::table('simulation_campaign_recipients as r')->join('simulation_suppressions as s', function ($join) {
            $join->on('s.membership_id', '=', 'r.membership_id')->on('s.organisation_id', '=', 'r.organisation_id');
        })->where('r.organisation_id', $org)->where('r.campaign_id', $campaignId)->exists(), 422, 'Campaign includes a suppressed recipient.');
        DB::table('simulation_campaign_recipients')->where('organisation_id', $org)->where('campaign_id', $campaignId)->get()->each(function ($recipient) use ($org, $campaign) {
            $outboxId = (string) Str::uuid();
            DB::table('simulation_delivery_outbox')->insert(['id' => $outboxId, 'organisation_id' => $org, 'campaign_id' => $campaign->id, 'campaign_recipient_id' => $recipient->id, 'status' => 'queued', 'available_at' => $campaign->scheduled_at, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('simulation_dispatch_queue')->insert(['id' => (string) Str::uuid(), 'organisation_id' => $org, 'outbox_id' => $outboxId, 'available_at' => $campaign->scheduled_at, 'created_at' => now()]);
        });
        DB::table('simulation_campaigns')->where('organisation_id', $org)->where('id', $campaignId)->update(['status' => 'scheduled', 'updated_at' => now()]);
        $audit->record($request, 'simulation.campaign.scheduled', 'simulation_campaign', $campaignId, ['recipient_count' => $count, 'scheduled_at' => $campaign->scheduled_at]);

        return back()->with('status', 'Campaign scheduled for sandbox delivery. No external mail transport is enabled.');
    }

    public function cancel(Request $request, string $organisation, string $campaignId, Audit $audit): RedirectResponse
    {
        abort_unless(app(CurrentMembership::class)->membership->hasPermission('simulations.manage'), 403);
        $org = app(CurrentMembership::class)->membership->organisation_id;
        $campaign = DB::table('simulation_campaigns')->where('organisation_id', $org)->where('id', $campaignId)->whereIn('status', ['draft', 'approved', 'scheduled'])->firstOrFail();
        DB::transaction(function () use ($org, $campaignId) {
            DB::table('simulation_campaigns')->where('organisation_id', $org)->where('id', $campaignId)->update(['status' => 'cancelled', 'cancelled_at' => now(), 'updated_at' => now()]);
            DB::table('simulation_delivery_outbox')->where('organisation_id', $org)->where('campaign_id', $campaignId)->where('status', 'queued')->update(['status' => 'cancelled', 'updated_at' => now()]);
            DB::table('simulation_dispatch_queue')->where('organisation_id', $org)->whereIn('outbox_id', DB::table('simulation_delivery_outbox')->where('organisation_id', $org)->where('campaign_id', $campaignId)->select('id'))->delete();
        });
        $audit->record($request, 'simulation.campaign.cancelled', 'simulation_campaign', $campaignId);

        return back()->with('status', 'Campaign cancelled; queued deliveries were stopped.');
    }

    public function setEnabled(Request $request, Audit $audit): RedirectResponse
    {
        abort_unless(app(CurrentMembership::class)->membership->hasPermission('simulations.kill'), 403);
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $org = app(CurrentMembership::class)->membership->organisation_id;
        if ($data['enabled']) {
            abort_unless(DB::table('simulation_domains')->where('organisation_id', $org)->whereNotNull('verified_at')->exists() && DB::table('simulation_authorisations')->where('organisation_id', $org)->where('status', 'active')->where('expires_at', '>', now()->utc()->format('Y-m-d H:i:sP'))->exists(), 422, 'Verify a domain and create a current authorisation before enabling simulations.');
        }
        DB::table('simulation_settings')->updateOrInsert(['organisation_id' => $org], ['enabled' => $data['enabled'], 'kill_switch_active' => ! $data['enabled'], 'updated_at' => now(), 'created_at' => now()]);
        if (! $data['enabled']) {
            DB::table('simulation_delivery_outbox')->where('organisation_id', $org)->where('status', 'queued')->update(['status' => 'cancelled', 'updated_at' => now()]);
            DB::table('simulation_campaigns')->where('organisation_id', $org)->where('status', 'scheduled')->update(['status' => 'cancelled', 'cancelled_at' => now(), 'updated_at' => now()]);
            DB::table('simulation_dispatch_queue')->where('organisation_id', $org)->delete();
        }
        $audit->record($request, $data['enabled'] ? 'simulation.kill_switch.released' : 'simulation.kill_switch.activated', 'simulation_settings', $org, ['enabled' => (bool) $data['enabled']]);

        return back()->with('status', $data['enabled'] ? 'Sandbox simulation is enabled.' : 'Kill switch active. All queued deliveries will stop.');
    }

    public function suppress(Request $request, Audit $audit): RedirectResponse
    {
        abort_unless(app(CurrentMembership::class)->membership->hasPermission('simulations.manage'), 403);
        $data = $request->validate(['membership_id' => ['required', 'uuid'], 'reason' => ['required', 'string', 'max:120']]);
        $org = app(CurrentMembership::class)->membership->organisation_id;
        abort_unless(Membership::where('organisation_id', $org)->where('id', $data['membership_id'])->exists(), 404);
        DB::table('simulation_suppressions')->updateOrInsert(['organisation_id' => $org, 'membership_id' => $data['membership_id']], ['reason' => $data['reason'], 'created_by' => $request->user()->id, 'created_at' => now()]);
        $audit->record($request, 'simulation.recipient.suppressed', 'membership', $data['membership_id'], ['reason' => $data['reason']]);

        return back()->with('status', 'Member added to the simulation suppression list.');
    }

    public function unsuppress(Request $request, string $organisation, string $membershipId, Audit $audit): RedirectResponse
    {
        abort_unless(app(CurrentMembership::class)->membership->hasPermission('simulations.manage'), 403);
        $org = app(CurrentMembership::class)->membership->organisation_id;
        $suppression = DB::table('simulation_suppressions')->where('organisation_id', $org)->where('membership_id', $membershipId)->firstOrFail();
        DB::table('simulation_suppressions')->where('organisation_id', $org)->where('membership_id', $membershipId)->delete();
        $audit->record($request, 'simulation.recipient.unsuppressed', 'membership', $membershipId, ['reason' => $suppression->reason]);

        return back()->with('status', 'Member removed from the simulation suppression list.');
    }

    public function landing(Request $request)
    {
        app(CurrentMembership::class)->membership = null;
        $context = $request->query('context');
        abort_unless(is_string($context) && strlen($context) <= 4096, 404);
        try {
            $payload = json_decode(Crypt::decryptString($context), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            abort(404);
        }
        abort_unless(isset($payload['organisation_id'], $payload['recipient_id']), 404);

        return app(TenantContext::class)->run($payload['organisation_id'], function () use ($payload, $context) {
            $recipient = DB::table('simulation_campaign_recipients as r')->join('simulation_campaigns as c', function ($join) {
                $join->on('c.id', '=', 'r.campaign_id')->on('c.organisation_id', '=', 'r.organisation_id');
            })->where('r.organisation_id', $payload['organisation_id'])->where('r.id', $payload['recipient_id'])->where('r.delivery_status', 'sandbox_delivered')->whereRaw('r.token_expires_at > now()')->where('c.status', 'completed')->first(['r.id', 'c.template_key']);
            abort_unless($recipient, 404);

            return Inertia::render('Simulation/TrainingLanding', ['context' => $context, 'template' => $recipient->template_key]);
        });
    }

    public function event(Request $request, Audit $audit)
    {
        app(CurrentMembership::class)->membership = null;
        $data = $request->validate(['context' => ['required', 'string', 'max:4096'], 'token' => ['required', 'string', 'size:64'], 'event' => ['required', 'in:education_viewed']]);
        try {
            $payload = json_decode(Crypt::decryptString($data['context']), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            abort(404);
        }
        abort_unless(isset($payload['organisation_id'], $payload['recipient_id']), 404);

        return app(TenantContext::class)->run($payload['organisation_id'], function () use ($data, $payload, $request, $audit) {
            $recipient = DB::table('simulation_campaign_recipients as r')->join('simulation_campaigns as c', function ($join) {
                $join->on('c.id', '=', 'r.campaign_id')->on('c.organisation_id', '=', 'r.organisation_id');
            })->join('memberships as m', function ($join) {
                $join->on('m.id', '=', 'r.membership_id')->on('m.organisation_id', '=', 'r.organisation_id');
            })->where('r.organisation_id', $payload['organisation_id'])->where('r.id', $payload['recipient_id'])->where('r.delivery_status', 'sandbox_delivered')->whereRaw('r.token_expires_at > now()')->where('c.status', 'completed')->where('m.status', 'active')->first(['r.*', 'c.remedial_course_version_id', 'c.created_by']);
            abort_unless($recipient && $recipient->token_hash && hash_equals($recipient->token_hash, hash('sha256', $data['token'])), 404);
            $newEvent = DB::table('simulation_events')->insertOrIgnore(['id' => (string) Str::uuid(), 'organisation_id' => $payload['organisation_id'], 'campaign_id' => $recipient->campaign_id, 'campaign_recipient_id' => $recipient->id, 'event_type' => 'education_viewed', 'occurred_at' => DB::raw('now()')]);
            if ($recipient->remedial_course_version_id) {
                CourseAssignment::firstOrCreate(['organisation_id' => $payload['organisation_id'], 'membership_id' => $recipient->membership_id, 'course_version_id' => $recipient->remedial_course_version_id], ['assigned_by' => $recipient->created_by, 'status' => 'assigned']);
            }
            app(CurrentMembership::class)->membership = null;
            if ($newEvent) {
                $audit->record($request, 'simulation.education.viewed', 'simulation_campaign', $recipient->campaign_id);
            }

            return response()->json(['status' => 'Thank you. The learning note has been recorded.']);
        });
    }

    public static function landingUrl(string $organisationId, string $recipientId): string
    {
        $context = Crypt::encryptString(json_encode(['organisation_id' => $organisationId, 'recipient_id' => $recipientId], JSON_THROW_ON_ERROR));

        return route('simulation.landing', ['context' => $context]);
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
