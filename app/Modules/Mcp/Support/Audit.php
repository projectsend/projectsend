<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Support;

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Writing to the activity log.
 *
 * The logger asks ResolvingActivityOrigin who is acting, and the module's
 * listener answers "AI assistant" with the connector's name (see the
 * service provider). So a tool only has to say what happened; the who
 * and the how are the log's own.
 *
 * Never fatal: an entry that could not be written is logged for whoever
 * looks after the instance, and the action it describes stands. The
 * bytes are already stored, the link already minted — telling the
 * person it failed would have them do it twice.
 */
final class Audit
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function log(Action $action, Account $account, ?Model $subject = null, array $context = []): void
    {
        self::logFor($action, $account->user, $subject, $context);
    }

    /**
     * The same entry for an actor who carries no connector context: the
     * person at the consent, or the account a token endpoint acts for.
     *
     * @param  array<string, mixed>  $context
     */
    public static function logFor(Action $action, User $user, ?Model $subject = null, array $context = []): void
    {
        try {
            app(ActivityLogger::class)->log($action, $user, $subject, $context);
        } catch (Throwable $e) {
            Log::warning('The AI connector could not write an activity entry.', [
                'action' => $action->value,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
