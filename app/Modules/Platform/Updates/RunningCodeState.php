<?php

declare(strict_types=1);

namespace App\Modules\Platform\Updates;

use App\Modules\Platform\Installation\Installation;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;

/**
 * Whether the code this process is executing is the code the installation
 * was last brought in line with.
 *
 * It exists because of a failure with no symptom. On a server with OPcache
 * set the way production guides recommend — `validate_timestamps=0`, which
 * this project's own image ships — replacing the files changes nothing for
 * the running site: PHP never re-reads a file it has already compiled. The
 * database ends up on the new version while every visitor is served the
 * old code, and `php artisan` reports the new version throughout, because
 * a fresh CLI process compiles the new files. Nothing errors. The only
 * outward sign is behaviour that quietly does not match the release notes.
 *
 * `projectsend:update` records the version it applied, and this compares
 * that against what *this* process compiled. The two answers differ in
 * both directions, and both are worth saying:
 *
 *   applied > running  — files were updated, PHP was never reloaded.
 *   applied < running  — new files are in place and the update never ran,
 *                        so the schema is behind the code.
 *
 * Not gated on an edition. `manage_updates` maps to a Community-only
 * capability, but what code a server is executing is not a feature — it is
 * a fact about the machine, which is what view_system_info governs.
 */
class RunningCodeState
{
    public function __construct(
        private readonly Settings $settings,
        private readonly Installation $installation,
    ) {}

    /**
     * @return array{reason: string, applied: string, running: string, applied_at: string, install_kind: string}|null
     */
    public function current(): ?array
    {
        $applied = $this->settings->get(Setting::AppliedVersion);
        $running = (string) config('projectsend.version');

        // No update has ever been applied through the command: a fresh
        // install, or one older than the command itself. Nothing to say in
        // either direction.
        if (! is_string($applied) || $applied === '' || $running === '') {
            return null;
        }

        $reason = match (true) {
            version_compare($applied, $running, '>') => 'stale_code',
            version_compare($applied, $running, '<') => 'pending_update',
            default => null,
        };

        if ($reason === null) {
            return null;
        }

        $appliedAt = $this->settings->get(Setting::AppliedVersionAt);

        return [
            'reason' => $reason,
            'applied' => $applied,
            'running' => $running,
            'applied_at' => is_string($appliedAt) ? $appliedAt : '',
            'install_kind' => $this->installation->kind()->value,
        ];
    }
}
