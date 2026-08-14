<?php

declare(strict_types=1);

namespace App\Modules\Platform\Updates\Console;

use App\Models\User;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\PermissionChecker;
use App\Modules\Identity\UserType;
use App\Modules\Notifications\Notifier;
use App\Modules\Platform\Capabilities\Capability;
use App\Modules\Platform\Capabilities\CapabilityRegistry;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Community edition only. There is no in-app self-updater — v1's
 * download-a-zip-and-extract-it-over-the-live-install approach doesn't
 * make sense once the app runs from a Docker image (see
 * docs/... — the rewrite brief explicitly rejects porting it). This
 * command only ever checks the public repo's latest GitHub release and
 * caches the result; upgrading is always an admin running
 * `docker compose pull && docker compose up -d` themselves.
 */
class CheckForUpdatesCommand extends Command
{
    protected $signature = 'projectsend:check-for-updates';

    protected $description = 'Check for a newer ProjectSend release and notify admins (Community edition, runs daily)';

    public function __construct(
        private readonly CapabilityRegistry $capabilities,
        private readonly Settings $settings,
        private readonly PermissionChecker $permissions,
        private readonly Notifier $notifier,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->capabilities->has(Capability::SystemUpdates)) {
            $this->info('Update checks are not available on this edition.');

            return self::SUCCESS;
        }

        if ($this->settings->get(Setting::CheckForUpdates) !== true) {
            $this->info('Update checks are disabled.');

            return self::SUCCESS;
        }

        $response = Http::withHeaders(['User-Agent' => 'ProjectSend'])
            ->timeout(10)
            ->get('https://api.github.com/repos/projectsend/projectsend/releases/latest');

        if (! $response->successful()) {
            $this->warn('Could not reach the update feed.');

            return self::FAILURE;
        }

        $tag = (string) ($response->json('tag_name') ?? '');
        $latestVersion = ltrim($tag, 'v');

        // The public repo's tags are still v1's r-number scheme (r2029,
        // not SemVer) until a real Community v2 release ships there —
        // skip rather than misreport an "update" against a tag we can't
        // meaningfully compare.
        if (! preg_match('/^\d+\.\d+\.\d+/', $latestVersion)) {
            $this->info("Latest release tag ({$tag}) is not a recognized version — skipping.");

            return self::SUCCESS;
        }

        $currentVersion = (string) config('projectsend.version');
        $previouslyKnownVersion = $this->settings->get(Setting::LatestKnownVersion);

        $this->settings->set(Setting::LatestKnownVersion, $latestVersion);
        $this->settings->set(Setting::LatestVersionCheckedAt, now()->toIso8601String());
        $this->settings->set(Setting::LatestReleaseTitle, (string) ($response->json('name') ?? $tag));
        $this->settings->set(Setting::LatestReleaseNotes, (string) ($response->json('body') ?? ''));
        $this->settings->set(Setting::LatestReleaseUrl, (string) ($response->json('html_url') ?? ''));
        $this->settings->set(Setting::LatestReleasePublishedAt, (string) ($response->json('published_at') ?? ''));

        $updateAvailable = version_compare($latestVersion, $currentVersion, '>');
        $alreadyKnown = $previouslyKnownVersion === $latestVersion;

        if ($updateAvailable && ! $alreadyKnown) {
            $recipients = array_values(User::query()->where('type', UserType::Staff)->get()
                ->filter(fn (User $staff): bool => $this->permissions->allows($staff, Permission::ManageUpdates))
                ->all());

            $this->notifier->send('update_available', $recipients, data: [
                'latestVersion' => $latestVersion,
                'currentVersion' => $currentVersion,
            ]);
        }

        $this->info("Latest version: {$latestVersion} (current: {$currentVersion}).");

        return self::SUCCESS;
    }
}
