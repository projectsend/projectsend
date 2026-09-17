<?php

declare(strict_types=1);

namespace App\Modules\Files\Scanning;

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\PermissionChecker;
use App\Modules\Identity\UserType;
use App\Modules\Notifications\Notifier;

/**
 * Who hears about a quarantined file.
 *
 * Two audiences, deliberately not three. Staff who can do something about
 * it are told, because a file sitting in quarantine that nobody looks at
 * is the same as a file silently lost. The person who uploaded it is
 * told, because on an honest account this is how they find out their own
 * machine has something on it — and because otherwise their file simply
 * never arrives and they have no idea why.
 *
 * The people the file was shared with are **not** told. They never
 * received it, and a message about a virus in a file they never saw
 * would alarm without informing.
 *
 * Recipients are resolved here rather than inside Notifier, which
 * authorizes nothing by design — see its security contract.
 */
class QuarantineNotifier
{
    public function __construct(
        private readonly Notifier $notifier,
        private readonly PermissionChecker $permissions,
    ) {}

    public function quarantined(File $file, string $threat): void
    {
        $uploader = $file->uploader;

        $this->notifier->send('file_quarantined', $this->staff(), subject: $file, data: [
            'itemName' => $file->name,
            'uploaderName' => $uploader->name ?? __('a deleted account'),
            'threat' => $threat,
        ]);

        // The uploader hears it once. Without this check a staff member
        // who uploaded an infected file would get both messages, which
        // read as two different files.
        if ($uploader !== null && ! $this->staff()->contains(fn (User $staff): bool => $staff->is($uploader))) {
            $this->notifier->send('upload_blocked', [$uploader], subject: $file, data: [
                'itemName' => $file->name,
                'threat' => $threat,
            ]);
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function staff(): \Illuminate\Support\Collection
    {
        return User::query()
            ->where('type', UserType::Staff)
            ->where('active', true)
            ->get()
            ->filter(fn (User $staff): bool => $this->permissions->allows($staff, Permission::ReleaseQuarantinedFiles))
            ->values();
    }
}
