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
     * it inherits Setting::DefaultClientStorageQuotaMb instead of being
     * unlimited, so a site-wide default (once set) also protects clients
     * who never got an explicit quota, including self-registered ones.
     * The site default itself being 0 is what actually means unlimited.
     *
     * @return int 0 means unlimited.
     */
    public function quotaMb(User $client): int
    {
        return $client->storage_quota_mb > 0
            ? $client->storage_quota_mb
            : (int) $this->settings->get(Setting::DefaultClientStorageQuotaMb);
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
