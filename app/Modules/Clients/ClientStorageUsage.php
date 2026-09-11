<?php

declare(strict_types=1);

namespace App\Modules\Clients;

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;

/**
 * A client's cumulative storage usage against their quota. Usage is a
 * live sum (not a maintained counter) over files the client uploaded
 * themselves — matches how the quota is enforced (portal self-uploads
 * only, see ChunkedUploadsController::store()/complete()).
 */
class ClientStorageUsage
{
    public function __construct(
        private readonly Settings $settings,
    ) {}

    public function usedBytes(User $client): int
    {
        return (int) File::query()->where('uploaded_by', $client->id)->sum('size');
    }

    /**
     * A client's own storage_quota_mb of 0 means "no custom quota set" —
     * it inherits the installation's default instead of being unlimited,
     * so a default (once set) also protects clients who never got an
     * explicit quota, including self-registered ones.
     *
     * @return int 0 means unlimited.
     */
    public function quotaMb(User $client): int
    {
        return $client->storage_quota_mb > 0
            ? $client->storage_quota_mb
            : $this->defaultQuotaMb();
    }

    /**
     * What a client with no quota of their own actually gets.
     *
     * Three sources, narrowest first, and the third is why this is a
     * method rather than a `Settings::get()` at the point of use.
     *
     * `Setting::DefaultClientStorageQuotaMb` belongs to whoever runs the
     * installation, and its default is 0 — which means unlimited. That is
     * the right default for somebody setting up their own install, and the
     * wrong one for an installation a platform operates on other people's
     * behalf: there, an account that arrived without an explicit quota has
     * no ceiling at all, which on a shared installation is one account
     * away from unmetered hosting.
     *
     * So a platform may set a floor in the environment, exactly as it sets
     * the seat caps, and for the same reason those are not settings: it is
     * not a preference the installation's administrator is expressing, it
     * is the shape of what was sold. It applies only where the setting says
     * nothing, so an administrator who has chosen a number keeps it, and an
     * install with no platform behind it is unaffected.
     *
     * Unset and zero are the same answer here, on purpose: a platform that
     * wanted no ceiling would not set the variable.
     *
     * @return int 0 means unlimited.
     */
    public function defaultQuotaMb(): int
    {
        $site = (int) $this->settings->get(Setting::DefaultClientStorageQuotaMb);

        if ($site > 0) {
            return $site;
        }

        $floor = config('projectsend.platform.default_client_quota_mb');

        return is_numeric($floor) ? max(0, (int) $floor) : 0;
    }

    /**
     * @return int 0 means unlimited.
     */
    public function quotaBytes(User $client): int
    {
        return $this->quotaMb($client) * 1024 * 1024;
    }

    /**
     * @return int|null Null means unlimited.
     */
    public function remainingBytes(User $client): ?int
    {
        $quota = $this->quotaBytes($client);

        return $quota === 0 ? null : max(0, $quota - $this->usedBytes($client));
    }
}
