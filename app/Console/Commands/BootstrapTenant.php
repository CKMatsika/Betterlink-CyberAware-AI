<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Foundation\Audit;
use App\Modules\Foundation\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class BootstrapTenant extends Command
{
    protected $signature = 'cyberaware:bootstrap-tenant {organisation} {slug} {admin-email} {admin-name}';

    protected $description = 'Create the first verified tenant administrator and organisation in a fresh PostgreSQL environment';

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->components->error('Initial tenant setup requires PostgreSQL with row-level security enabled.');

            return self::FAILURE;
        }

        $email = mb_strtolower((string) $this->argument('admin-email'));
        $name = (string) $this->argument('admin-name');
        $password = config('cyberaware.bootstrap_password');

        try {
            Validator::make([
                'organisation' => $this->argument('organisation'),
                'slug' => $this->argument('slug'),
                'email' => $email,
                'name' => $name,
            ], [
                'organisation' => ['required', 'string', 'max:160'],
                'slug' => ['required', 'alpha_dash', 'max:96'],
                'email' => ['required', 'email', 'max:254'],
                'name' => ['required', 'string', 'max:255'],
            ])->validate();

            if (! is_string($password) || $password === '') {
                if (! $this->input->isInteractive()) {
                    throw new RuntimeException('Set CYBERAWARE_BOOTSTRAP_PASSWORD as a temporary Cloud secret before running this command.');
                }

                $password = $this->secret('Create an initial administrator password (16 characters minimum)');
                $confirmation = $this->secret('Confirm the initial administrator password');
                if (! is_string($password) || ! hash_equals((string) $password, (string) $confirmation)) {
                    throw new RuntimeException('The passwords did not match.');
                }
            }

            if (mb_strlen($password) < 16) {
                throw new RuntimeException('The initial administrator password must be at least 16 characters.');
            }

            $organisationId = DB::transaction(function () use ($email, $name, $password) {
                DB::select("select pg_advisory_xact_lock(hashtext('cyberaware-first-tenant-bootstrap'))");

                if (User::query()->exists()) {
                    throw new RuntimeException('Bootstrap is only available while the users table is empty.');
                }

                $user = User::forceCreate([
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                    'email_verified_at' => now(),
                ]);

                $organisationId = app(CreateOrganisation::class)->createFor(
                    $user,
                    (string) $this->argument('organisation'),
                    (string) $this->argument('slug'),
                    app(TenantContext::class),
                    app(Audit::class),
                );

                $user->forceFill(['home_organisation_id' => $organisationId])->save();

                return $organisationId;
            });

            $this->components->info('First organisation and administrator created. Sign in at '.rtrim((string) config('app.url'), '/').'/org/'.$organisationId);
            $this->line('Remove CYBERAWARE_BOOTSTRAP_PASSWORD from the Cloud environment immediately after this command.');

            return self::SUCCESS;
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->components->error($message);
                }
            }

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            config(['cyberaware.bootstrap_password' => null]);
            putenv('CYBERAWARE_BOOTSTRAP_PASSWORD');
        }
    }
}
