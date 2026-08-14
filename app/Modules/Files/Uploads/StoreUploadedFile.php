<?php

declare(strict_types=1);

namespace App\Modules\Files\Uploads;

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Models\File;

/**
 * The single place a stored payload becomes a File record — shared by
 * the plain intake and the chunked-upload completion, so metadata
 * persistence and auditing never diverge.
 */
class StoreUploadedFile
{
    public function __construct(
        private readonly ActivityLogger $activity,
    ) {}

    public function create(
        User $uploader,
        string $originalName,
        string $path,
        string $mimeType,
        int $size,
        string $checksum,
        ?string $name = null,
        ?string $description = null,
        ?int $folderId = null,
        string $disk = 'files',
        Action $action = Action::FileUploaded,
    ): File {
        $originalName = self::sanitizeFilename($originalName);

        $file = File::query()->create([
            'uploaded_by' => $uploader->id,
            'folder_id' => $folderId,
            'name' => $name !== null && $name !== ''
                ? $name
                : pathinfo($originalName, PATHINFO_FILENAME),
            'description' => $description,
            'original_name' => $originalName,
            'path' => $path,
            'disk' => $disk,
            'mime_type' => $mimeType,
            'size' => $size,
            'checksum' => $checksum,
        ]);

        $this->activity->log($action, $uploader, $file);

        return $file;
    }

    /**
     * An uploader picks original_name freely — it is validated for length
     * and nothing else — and it is echoed back in the Content-Disposition
     * header of every download, preview and thumbnail response. A CR or LF
     * in a header value is header injection; PHP's header() refuses to
     * emit one, so today that fails closed as a 500 rather than a split
     * response, but a filename should not be able to break the response at
     * all. Strip the whole C0/C1 control range once, here, rather than
     * hoping each of the five emission sites remembers to.
     */
    public static function sanitizeFilename(string $name): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? $name;
        $clean = trim($clean);

        return $clean === '' ? 'file' : $clean;
    }
}
