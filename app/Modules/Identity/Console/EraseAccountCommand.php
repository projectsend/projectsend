<?php

declare(strict_types=1);

namespace App\Modules\Identity\Console;

use App\Models\User;
use App\Modules\Identity\Erasure\AccountEraser;
use Illuminate\Console\Command;

/**
 * Operator tool for erasure requests received out-of-band (GDPR
 * Art. 17): permanently removes the account and anonymizes its
 * activity-log snapshots, immediately.
 */
class EraseAccountCommand extends Command
{
    protected $signature = 'projectsend:erase-account
        {email : Email address of the account to erase}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Permanently erase an account and anonymize its activity-log entries (GDPR erasure)';

    public function handle(AccountEraser $eraser): int
    {
        $email = (string) $this->argument('email');

        $user = User::withTrashed()->where('email', $email)->first();

        if ($user === null) {
            $this->error("No account found for {$email}.");

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Permanently erase {$user->name} <{$email}> and anonymize their log entries?")) {
            return self::FAILURE;
        }

        $eraser->erase($user);

        $this->info('Account erased and log entries anonymized.');

        return self::SUCCESS;
    }
}
