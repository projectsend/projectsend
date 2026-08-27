<?php

declare(strict_types=1);

namespace App\Modules\Identity\Console;

use App\Models\User;
use App\Modules\Identity\Erasure\AccountEraser;
use Illuminate\Console\Command;

class PurgeErasuresCommand extends Command
{
    protected $signature = 'projectsend:purge-erasures';

    protected $description = 'Permanently erase deleted accounts whose grace period has passed (runs daily)';

    public function handle(AccountEraser $eraser): int
    {
        $due = User::onlyTrashed()
            ->whereNotNull('erase_after')
            ->where('erase_after', '<=', now())
            ->get();

        foreach ($due as $user) {
            $eraser->erase($user);
        }

        $this->info("Erased {$due->count()} account(s).");

        return self::SUCCESS;
    }
}
