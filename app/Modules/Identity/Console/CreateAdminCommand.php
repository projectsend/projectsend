<?php

declare(strict_types=1);

namespace App\Modules\Identity\Console;

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Identity\Erasure\AvailableEmailRule;
use App\Modules\Identity\FirstAdministrator;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Identity\UserType;
use App\Modules\Platform\Onboarding\InstallationWelcome;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdminCommand extends Command
{
    protected $signature = 'projectsend:admin
        {--name= : Full name of the administrator}
        {--email= : Email address (used to log in)}
        {--password= : Password (prompted interactively when omitted)}
        {--if-none : Do nothing when a staff user already exists (idempotent provisioning)}';

    protected $description = 'Create a staff administrator account';

    public function handle(): int
    {
        $ifNone = (bool) $this->option('if-none');

        // Asked early so an unattended boot does not prompt for a name and
        // a password it is about to throw away. It is asked again below,
        // under a lock, because this read on its own has the same hole the
        // setup screen had: two containers coming up against one database
        // both see no staff and both create an administrator.
        if ($ifNone && $this->staffExists()) {
            $this->info('A staff user already exists; nothing to do.');

            return self::SUCCESS;
        }

        $name = $this->option('name') ?? $this->ask('Name');
        $email = $this->option('email') ?? $this->ask('Email address');
        $password = $this->option('password') ?? $this->secret('Password');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', new AvailableEmailRule],
                'password' => ['required', Password::defaults()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $create = function () use ($name, $email, $password): User {
            $user = User::create([
                'type' => UserType::Staff,
                'active' => true,
                'role_id' => Role::query()->where('name', SystemRole::SystemAdministrator->value)->value('id'),
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);

            // forceFill, for the reason SetupController gives beside it:
            // email_verified_at is not in User::$fillable, so passing it
            // into create() lost it without a word. Whoever provisioned
            // this container supplied the address themselves.
            $user->forceFill(['email_verified_at' => now()])->save();

            return $user;
        };

        // Without --if-none an operator is asking for an administrator
        // outright, whoever else exists, so there is nothing to claim.
        $user = $ifNone
            ? FirstAdministrator::claim(fn (): bool => ! $this->staffExists(), $create)
            : $create();

        if ($user === null) {
            $this->info('A staff user already exists; nothing to do.');

            return self::SUCCESS;
        }

        app(ActivityLogger::class)->log(Action::UserCreated, null, $user);

        $settings = app(Settings::class);
        if ($settings->get(Setting::AdminNotificationEmails) === []) {
            $settings->set(Setting::AdminNotificationEmails, [$user->email]);
        }

        // Unattended provisioning skips the setup screen entirely, so this
        // is the only place that can record "somebody just installed this"
        // for a container that came up from environment variables. They
        // still deserve showing around on their first visit.
        app(InstallationWelcome::class)->raise();

        $this->info("Administrator {$user->email} created.");

        return self::SUCCESS;
    }

    /**
     * @phpstan-impure another process can create one between two calls
     */
    private function staffExists(): bool
    {
        return User::query()->where('type', UserType::Staff)->exists();
    }
}
