<?php

declare(strict_types=1);

namespace App\Modules\Files\Uploads;

use App\Models\User;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;

/**
 * Whether an uploader is allowed to upload a given filename's extension,
 * per Setting::UploadTypeRestriction (who is checked) and
 * Setting::AllowedUploadExtensions (what's on the list).
 */
class UploadExtensionPolicy
{
    public function __construct(
        private readonly Settings $settings,
    ) {}

    public function isAllowed(User $user, string $filename): bool
    {
        if (! $this->restriction()->appliesTo($user->type)) {
            return true;
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ($extension === '') {
            return false;
        }

        return in_array($extension, $this->allowedExtensions(), true);
    }

    /**
     * Client-side UX hint only (server-side enforcement is `isAllowed()`
     * above) — the allowed extensions when the restriction currently
     * applies to this user, or null when it doesn't (nothing to hint).
     *
     * @return list<string>|null
     */
    public function hintFor(User $user): ?array
    {
        return $this->restriction()->appliesTo($user->type) ? $this->allowedExtensions() : null;
    }

    private function restriction(): UploadTypeRestriction
    {
        $value = $this->settings->get(Setting::UploadTypeRestriction);

        return (is_string($value) ? UploadTypeRestriction::tryFrom($value) : null)
            ?? UploadTypeRestriction::All;
    }

    /**
     * @return list<string>
     */
    private function allowedExtensions(): array
    {
        $value = $this->settings->get(Setting::AllowedUploadExtensions);

        return is_array($value) ? array_values(array_map(fn ($extension): string => strtolower((string) $extension), $value)) : [];
    }
}
