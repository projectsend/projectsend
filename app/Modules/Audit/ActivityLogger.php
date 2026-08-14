<?php

declare(strict_types=1);

namespace App\Modules\Audit;

use App\Models\User;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    public function __construct(
        private readonly Settings $settings,
    ) {}

    /**
     * Record an action. The actor defaults to the authenticated user;
     * pass one explicitly for flows without a session (CLI, setup).
     * Actor and subject names are snapshotted so entries survive
     * deletions.
     *
     * @param  array<string, mixed>  $context
     */
    public function log(Action $action, ?User $actor = null, ?Model $subject = null, array $context = []): void
    {
        $user = $actor ?? Auth::user();

        // How the action arrived is resolved here rather than at each call
        // site: the API reuses the same controllers and services the UI does
        // (FileDownloadController and StoreUploadedFile are both shared
        // verbatim), so asking every caller to remember would guarantee
        // gaps. Reading the current request's credential is the same kind of
        // implicit lookup this class already does for the actor and the IP.
        $token = $user?->currentAccessToken();

        ActivityLog::query()->create([
            'actor_id' => $user?->getKey(),
            'actor_name' => $user?->name,
            'actor_type' => $user?->type->value,
            'origin' => $this->originFor($user, $token),
            'api_token_id' => $token?->getKey(),
            // Snapshotted beside the id for the same reason actor_name is:
            // a revoked token must not leave its entries pointing at nothing.
            'api_token_name' => $token?->getAttribute('name'),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'subject_name' => $this->subjectName($subject),
            'context' => $context === [] ? null : $context,
            'ip_address' => $this->shouldRecordIp($action, $user) ? request()->ip() : null,
            'created_at' => now(),
        ]);
    }

    /**
     * Setting::DownloadIpLogging governs download-shaped entries and
     * file previews alike — both are ways of viewing a file's contents,
     * so previews would otherwise leak IPs through a privacy setting a
     * client believes covers "viewing my files." A security audit trail
     * (staff actions, logins, …) always records IP regardless, since
     * that's an operational concern, not a client-privacy one.
     */
    /**
     * A token means the API; an actor without one means a browser session.
     * No actor at all is either a console command or a request from
     * somebody not signed in — and those are not the same thing, so
     * something has to tell them apart rather than both landing on System.
     * (Scheduled tasks do not reach here at all: they call logSystem(),
     * which sets System outright.)
     *
     * That something is a matched route, not App::runningInConsole():
     * the whole test suite runs in console, so the console check would
     * classify every HTTP test as System and quietly make this
     * untestable — the failure mode being that it looks right in
     * production and nothing proves it. A console command and a queued job
     * have no route; a request does.
     */
    private function originFor(?User $actor, mixed $token): ActivityOrigin
    {
        if ($token !== null) {
            return ActivityOrigin::Api;
        }

        if ($actor !== null) {
            return ActivityOrigin::Ui;
        }

        return request()->route() === null ? ActivityOrigin::System : ActivityOrigin::Public;
    }

    private function shouldRecordIp(Action $action, ?User $actor): bool
    {
        if (! in_array($action, [Action::FileDownloaded, Action::FilePreviewed, Action::ShareLinkDownloaded, Action::PublicFileDownloaded], true)) {
            return true;
        }

        return match ($this->settings->get(Setting::DownloadIpLogging)) {
            'none' => false,
            'anonymous_only' => $actor === null,
            default => true,
        };
    }

    /**
     * Record an action as the system itself, never attributing the
     * authenticated user (compliance jobs, scheduled work).
     *
     * @param  array<string, mixed>  $context
     */
    public function logSystem(Action $action, array $context = []): void
    {
        ActivityLog::query()->create([
            'actor_id' => null,
            'actor_name' => null,
            'actor_type' => null,
            'origin' => ActivityOrigin::System,
            'action' => $action,
            'subject_type' => null,
            'subject_id' => null,
            'subject_name' => null,
            'context' => $context === [] ? null : $context,
            'created_at' => now(),
        ]);
    }

    private function subjectName(?Model $subject): ?string
    {
        if ($subject === null) {
            return null;
        }

        $name = $subject->getAttribute('name') ?? $subject->getAttribute('title');

        return is_string($name) ? $name : null;
    }
}
