<?php

declare(strict_types=1);

namespace App\Modules\Files;

/**
 * What a file's download limit counts.
 *
 * `Total` caps the file itself: once it has been downloaded that many
 * times by anyone, nobody may download it again. `PerUser` gives every
 * person their own allowance, so one client exhausting theirs leaves
 * everyone else untouched.
 *
 * Both come from ProjectSend v1, which stored them in
 * `tbl_files.download_limit_type` under these exact names — an imported
 * install keeps behaving the way its administrator set it up.
 *
 * A closed set on purpose: DownloadAllowance branches on it, and a third
 * value arriving from a database someone edited by hand should fail
 * loudly at the cast rather than silently fall through to "no limit".
 */
enum DownloadLimitScope: string
{
    case Total = 'total';

    case PerUser = 'per_user';
}
