<?php

declare(strict_types=1);

namespace App\Modules\Platform\Updates;

use App\Models\User;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\PermissionChecker;
use App\Modules\Identity\UserType;
use App\Modules\Notifications\Notifier;
use App\Modules\Platform\Capabilities\Capability;
use App\Modules\Platform\Capabilities\CapabilityRegistry;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Http;

/**
 * Ask the public repository what the newest release is, and remember the
 * answer.
 *
 * There is no in-app self-updater and this is not one: it reads a feed
 * and writes settings. Applying an update is always somebody running
 * `update.sh` or pulling a new image.
 *
 * This lives in a class rather than in the command because there are two
 * callers now — the daily scheduled run and the button in the settings —
 * and the part that must not drift between them is the part with
 * consequences: which staff get notified, and the guard that stops them
 * being notified twice for the same release. A second copy of that in a
 * controller would be found wrong six months later, by a staff member
 * receiving the same notification every time somebody pressed a button.
 */
class CheckForUpdates
{
    public function __construct(
        private readonly CapabilityRegistry $capabilities,
        private readonly Settings $settings,
        private readonly PermissionChecker $permissions,
        private readonly Notifier $notifier,
    ) {}

    /**
     * @return array{ok: bool, outcome: 'unavailable'|'unreachable'|'unrecognised'|'checked', message: string, latest_version: string, update_available: bool}
     */
    public function run(): array
    {
        if (! $this->capabilities->has(Capability::SystemUpdates)) {
            return $this->result(true, 'unavailable', __('Update checks are not available on this edition.'));
        }

        $response = Http::withHeaders(['User-Agent' => 'ProjectSend'])
            ->timeout(10)
            ->get('https://api.github.com/repos/projectsend/projectsend/releases/latest');

        if (! $response->successful()) {
            return $this->result(false, 'unreachable', __('Could not reach the update feed.'));
        }

        $tag = (string) ($response->json('tag_name') ?? '');
        $latestVersion = ltrim($tag, 'v');

        // The public repo's tags are still v1's r-number scheme (r2029,
        // not SemVer) until a real Community v2 release ships there —
        // skip rather than misreport an "update" against a tag we can't
        // meaningfully compare.
        if (! preg_match('/^\d+\.\d+\.\d+/', $latestVersion)) {
            return $this->result(true, 'unrecognised', __('The latest release (:tag) is not a version this can compare.', ['tag' => $tag]));
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

        // Only the first time a genuinely newer release is seen. This is
        // what keeps the settings button from notifying every staff member
        // again on every press, and it belongs here rather than in either
        // caller for exactly that reason.
        if ($updateAvailable && ! $alreadyKnown) {
            $recipients = array_values(User::query()->where('type', UserType::Staff)->get()
                ->filter(fn (User $staff): bool => $this->permissions->allows($staff, Permission::ManageUpdates))
                ->all());

            $this->notifier->send('update_available', $recipients, data: [
                'latestVersion' => $latestVersion,
                'currentVersion' => $currentVersion,
            ]);
        }

        return $this->result(
            true,
            'checked',
            $updateAvailable
                ? __('Version :version is available. You are running :current.', ['version' => $latestVersion, 'current' => $currentVersion])
                : __('You are up to date, running version :current.', ['current' => $currentVersion]),
            $latestVersion,
            $updateAvailable,
        );
    }

    /**
     * @param  'unavailable'|'unreachable'|'unrecognised'|'checked'  $outcome
     * @return array{ok: bool, outcome: 'unavailable'|'unreachable'|'unrecognised'|'checked', message: string, latest_version: string, update_available: bool}
     */
    private function result(bool $ok, string $outcome, string $message, string $latestVersion = '', bool $updateAvailable = false): array
    {
        return [
            'ok' => $ok,
            'outcome' => $outcome,
            'message' => $message,
            'latest_version' => $latestVersion,
            'update_available' => $updateAvailable,
        ];
    }
}
