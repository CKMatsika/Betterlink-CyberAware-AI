<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\Foundation\Audit;
use App\Modules\Foundation\Models\Invitation;
use App\Modules\Foundation\Models\Membership;
use App\Modules\Foundation\Models\Organisation;
use App\Modules\Foundation\TenantContext;
use App\Notifications\OrganisationInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class InvitationController extends Controller
{
    public function show(string $organisation, string $token): Response
    {
        return app(TenantContext::class)->run($organisation, function () use ($token) {
            $invitation = Invitation::where('token_hash', hash('sha256', $token))
                ->whereNull('accepted_at')->where('expires_at', '>', now())->firstOrFail();

            return Inertia::render('Auth/AcceptInvitation', ['email' => $invitation->email]);
        });
    }

    public function store(Request $request, string $organisation, string $token): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'password' => ['required', 'string', 'min:12', 'confirmed']]);
        $hash = hash('sha256', $token);

        app(TenantContext::class)->run($organisation, function () use ($data, $hash, $request, $organisation) {
            DB::transaction(function () use ($data, $hash, $request, $organisation) {
                $invitation = Invitation::where('token_hash', $hash)->whereNull('accepted_at')
                    ->where('expires_at', '>', now())->lockForUpdate()->firstOrFail();
                abort_unless($invitation->organisation_id === $organisation, 404);
                $user = User::firstOrNew(['email' => Str::lower($invitation->email)]);
                if ($user->exists) {
                    abort_unless(Hash::check($request->string('password')->toString(), $user->password), 422, 'This email already has an account. Sign in to accept the invitation.');
                } else {
                    $user->name = $data['name'];
                    $user->password = $data['password'];
                    $user->email_verified_at = now(); // possession of this email-bound invite verifies the address
                    $user->save();
                }

                $membership = Membership::firstOrCreate(
                    ['organisation_id' => $invitation->organisation_id, 'user_id' => $user->id],
                    ['status' => 'active', 'joined_at' => now()],
                );
                $role = DB::table('roles')->where('organisation_id', $invitation->organisation_id)->where('name', 'learner')->first();
                abort_unless($role, 409, 'The organisation has not completed role setup.');
                DB::table('membership_roles')->insertOrIgnore(['organisation_id' => $invitation->organisation_id, 'membership_id' => $membership->id, 'role_id' => $role->id, 'assigned_by' => $invitation->invited_by, 'created_at' => now()]);
                $invitation->update(['accepted_at' => now()]);
                app(Audit::class)->record($request, 'invitation.accepted', 'membership', $membership->id);
                $request->session()->put('organisation_id', $invitation->organisation_id);
                $request->session()->put('user_id_after_invitation', $user->id);
            });
        });

        return redirect()->route('login')->with('status', 'Invitation accepted. Sign in to continue.');
    }

    public function create(Request $request, string $organisation): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:254']]);
        $org = Organisation::findOrFail($organisation);
        $token = Str::random(64);
        $invitation = Invitation::create([
            'organisation_id' => $organisation,
            'email' => Str::lower($data['email']),
            'token_hash' => hash('sha256', $token),
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addHours(48),
        ]);
        Notification::route('mail', $invitation->email)->notify(new OrganisationInvitation(url('/invitations/'.$organisation.'/'.$token), $org->name));
        app(Audit::class)->record($request, 'invitation.created', 'invitation', $invitation->id);

        return back()->with('status', 'Invitation sent.');
    }
}
