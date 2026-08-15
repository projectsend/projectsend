<?php

declare(strict_types=1);

namespace App\Modules\Identity\Console;

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
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
        if ($this->option('if-none') && User::query()->where('type', UserType::Staff)->exists()) {
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
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', Password::defaults()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'type' => UserType::Staff,
            'active' => true,
            'role_id' => Role::query()->where('name', SystemRole::SystemAdministrator->value)->value('id'),
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'email_verified_at' => now(),
        ]);

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
}
