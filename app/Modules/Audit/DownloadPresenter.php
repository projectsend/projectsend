<?php

declare(strict_types=1);

namespace App\Modules\Audit;

/**
 * Turns a download-action ActivityLog entry (FileDownloaded /
 * ShareLinkDownloaded / PublicFileDownloaded) into the flat shape every
 * downloads view renders, overriding the actor label for the two
 * anonymous public-download actions so callers don't re-derive it.
 */
class DownloadPresenter
{
    /**
     * @return array{id: int, created_at: string, actor_name: string, actor_type: ?string, ip_address: ?string}
     */
    public function present(ActivityLog $entry): array
    {
        $isPublic = in_array($entry->action, [Action::ShareLinkDownloaded, Action::PublicFileDownloaded], true);

        return [
            'id' => $entry->id,
            'created_at' => $entry->created_at->toIso8601String(),
            'actor_name' => match ($entry->action) {
                Action::ShareLinkDownloaded => __('Public link'),
                Action::PublicFileDownloaded => __('Public listing'),
                default => $entry->actor_name ?? __('(deleted account)'),
            },
            'actor_type' => $isPublic ? null : $entry->actor_type,
            'ip_address' => $entry->ip_address,
        ];
    }
}
