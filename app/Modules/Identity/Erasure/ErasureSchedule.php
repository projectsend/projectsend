<?php

declare(strict_types=1);

namespace App\Modules\Identity\Erasure;

use App\Models\User;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;

/**
 * Stamps the moment a soft-deleted account graduates to permanent
 * erasure: now plus the installation's grace period.
 *
 * Every deletion path calls this right before delete(), self-service and
 * administrative alike, so PurgeErasuresCommand eventually reaches every
 * deleted account — and the email address its row keeps reserved under
 * the unique index is freed. Only self-deletion did this at first, which
 * left admin-deleted accounts trashed forever and their addresses
 * unusable (#1648).
 */
class ErasureSchedule
{
    public function __construct(
        private readonly Settings $settings,
    ) {}

    public function apply(User $user): void
    {
        $graceDays = (int) $this->settings->get(Setting::AccountErasureGraceDays);

        $user->forceFill(['erase_after' => now()->addDays($graceDays)])->save();
    }
}
