<?php

declare(strict_types=1);

namespace App\Modules\Platform\Storage;

use App\Models\User;
use App\Modules\Files\Storage\ResolvingUploadDisk;
use App\Modules\Files\Uploads\LocalPartStore;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

class StorageCapacity
{
    public function __construct(private readonly LocalPartStore $parts) {}

    /** @return array{storage_driver: string, storage_free_bytes: int, upload_temp_free_bytes: int} */
    public function inspect(User $uploader): array
    {
        $event = new ResolvingUploadDisk($uploader);
        Event::dispatch($event);
        $driver = (string) config('filesystems.disks.'.$event->disk.'.driver');

        return [
            'storage_driver' => $driver,
            // Object stores do not expose filesystem free space. Never report
            // the VPS disk as the capacity of a remote storage provider.
            'storage_free_bytes' => $driver === 'local'
                ? $this->freeBytes(Storage::disk($event->disk)->path(''))
                : -1,
            'upload_temp_free_bytes' => $this->freeBytes($this->parts->temporaryDirectory()),
        ];
    }

    protected function freeBytes(string $path): int
    {
        // Before the first upload, the directory may not exist yet. Its
        // nearest existing ancestor is on the filesystem that will hold it.
        while (! is_dir($path)) {
            $parent = dirname($path);
            if ($parent === $path) {
                return -1;
            }
            $path = $parent;
        }

        $bytes = @disk_free_space($path);

        return $bytes === false ? -1 : (int) $bytes;
    }
}
