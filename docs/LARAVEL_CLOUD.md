# Laravel Cloud preview setup

The public root is a programme overview. The app now links to `/login` and `/get-started` from
that page. The deployed preview was checked at the Cloud URL supplied during support; `/login`
rendered successfully, while the root had no sign-in or onboarding links. Public registration is
intentionally disabled.

## First administrator

1. Attach a PostgreSQL database to the Cloud environment and let the deployment migrations finish.
   Laravel Cloud's Laravel deploy defaults include `php artisan migrate --force`; verify the
   environment's deploy command and deployment output before bootstrapping.
2. In Cloud environment secrets, set `CYBERAWARE_BOOTSTRAP_PASSWORD` to a unique password of at
   least 16 characters.
3. Use the Cloud command runner to execute:

   ```sh
   php artisan cyberaware:bootstrap-tenant "Organisation name" organisation-slug admin@example.com "Admin Name"
   ```

4. The command succeeds only on PostgreSQL while the `users` table is empty. It creates the first
   verified tenant administrator and tenant, then prints the tenant sign-in URL. Use a mailbox you
   control for the administrator email.
5. Remove `CYBERAWARE_BOOTSTRAP_PASSWORD` from Cloud secrets immediately after the command. The
   command cannot create another tenant after the first user exists.
6. Sign in at the printed `/org/{organisation-id}` URL. After the first visit, successful logins
   return to that organisation dashboard.

Invited employees use the link sent by their organisation administrator. Configure a mail provider
in Cloud before sending invitations or password-reset links. Simulation messages remain restricted
to the in-memory array transport.

## Deployment boundary

This repo is approved for controlled development review only. Do not load real employee data or use
this deployment as a production service. PostgreSQL migrations must run as a dedicated schema owner;
web traffic must use the restricted `cyberaware_app` role with RLS enforced. Confirm that role split,
Cloud environment variables, backups, mail delivery, queues, and security controls independently
before any production use. Do not run `db:seed` in a shared Cloud environment.
