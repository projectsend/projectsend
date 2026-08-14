<?php

declare(strict_types=1);

namespace App\Modules\Audit;

/**
 * How an audited action reached the application.
 *
 * Distinct from `actor_type`, which says *who* acted (staff, client, or
 * nobody). Origin says *through what*: the same staff member deleting the
 * same file from the web UI and from an integration produces two entries
 * that are otherwise identical, and an administrator reviewing the log
 * needs to tell them apart — "did I do that, or did the Zapier token?" is
 * the first question asked when something unexpected shows up.
 */
enum ActivityOrigin: string
{
    /** A browser session — the web UI. */
    case Ui = 'ui';

    /** An API token. `api_token_id` and `api_token_name` are set alongside. */
    case Api = 'api';

    /**
     * A web request with nobody signed in — a visitor commenting on a
     * public file today, and whatever else the public surface grows.
     *
     * Split out of System because the two are not the same thing and were
     * being shown with the same word: the scheduler deleting an expired
     * file and a stranger leaving a comment both read as "System", which
     * made the audit trail claim the installation had commented on its own
     * file. Scheduled tasks keep System — they go through
     * ActivityLogger::logSystem(), which never asks this method.
     */
    case Public = 'public';

    /** Scheduled tasks and console commands. */
    case System = 'system';

    /**
     * English label — also the translation key.
     */
    public function label(): string
    {
        return match ($this) {
            self::Ui => 'Web UI',
            self::Api => 'API',
            self::Public => 'Not signed in',
            self::System => 'System',
        };
    }
}
