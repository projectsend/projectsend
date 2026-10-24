<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Support;

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Models\ShareLink;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * The repeated shapes of spec §2.1 — a file, a folder, a link, a client — built
 * one way so every tool returns the same object for the same thing.
 */
final class Presenter
{
    /**
     * @return array<string, mixed>
     */
    public static function file(File $file, Account $account): array
    {
        $id = (int) $file->getAttribute('id');
        $size = (int) $file->getAttribute('size');

        return [
            'id' => $id,
            'name' => (string) $file->getAttribute('name'),
            'description' => $file->getAttribute('description'),
            'size_bytes' => $size,
            'size' => Bytes::human($size),
            'mime_type' => $file->getAttribute('mime_type'),
            'created_at' => Presenter::iso($file->getAttribute('created_at')),
            'updated_at' => Presenter::iso($file->getAttribute('updated_at')),
            'expires_at' => Presenter::iso($file->getAttribute('expires_at')),
            'folder' => self::fileFolder($file, $account),
            'web_url' => Web::file($account, $id),
        ];
    }

    /**
     * The folder a file sits in, for staff. Never for a client: a file
     * shared with them directly can sit in a staff folder they cannot
     * see, and its name is not theirs to read.
     *
     * @return array{id: int, name: string}|null
     */
    private static function fileFolder(File $file, Account $account): ?array
    {
        if ($account->isClient() || $file->folder_id === null) {
            return null;
        }

        $folder = $file->folder;

        return $folder !== null
            ? ['id' => (int) $folder->getAttribute('id'), 'name' => (string) $folder->getAttribute('name')]
            : null;
    }

    /**
     * A folder of the staff library.
     *
     * @param  array<int, string>  $paths  from Folders::paths(), for the page
     * @return array<string, mixed>
     */
    public static function folder(Folder $folder, array $paths = []): array
    {
        $id = (int) $folder->getAttribute('id');
        $parentId = $folder->getAttribute('parent_id');

        return [
            'id' => $id,
            'name' => (string) $folder->getAttribute('name'),
            'path' => $paths[$id] ?? (string) $folder->getAttribute('name'),
            'parent_id' => $parentId === null ? null : (int) $parentId,
            // A client's home folder: its name follows the account and it
            // is never deleted from a folder screen. Fill it; leave it be.
            'is_client_home' => $folder->getAttribute('home_for_user_id') !== null,
            'public' => (bool) $folder->getAttribute('public'),
            'web_url' => Web::staffFolder($id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function link(ShareLink $link): array
    {
        return [
            'id' => (int) $link->getAttribute('id'),
            'url' => Web::shareLink((string) $link->getAttribute('token')),
            'expires_at' => Presenter::iso($link->getAttribute('expires_at')),
            'max_downloads' => $link->getAttribute('max_downloads') === null ? null : (int) $link->getAttribute('max_downloads'),
            'downloads_count' => (int) $link->getAttribute('downloads_count'),
            'created_at' => Presenter::iso($link->getAttribute('created_at')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function client(User $client): array
    {
        $id = (int) $client->getAttribute('id');

        return [
            'id' => $id,
            'name' => (string) $client->getAttribute('name'),
            'email' => (string) $client->getAttribute('email'),
            'active' => (bool) $client->getAttribute('active'),
            'sign_in_url' => Web::signIn(),
            'web_url' => Web::client($id),
        ];
    }

    /**
     * The most recent link that still works, or null. "Works" is the
     * app's word: not expired and not past its cap.
     *
     * @param  iterable<int, ShareLink>  $links
     */
    public static function activeLink(iterable $links): ?ShareLink
    {
        $best = null;

        foreach ($links as $link) {
            if (! $link->isActive()) {
                continue;
            }

            if ($best === null || (int) $link->getAttribute('id') > (int) $best->getAttribute('id')) {
                $best = $link;
            }
        }

        return $best;
    }

    public static function iso(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof DateTimeInterface
            ? Carbon::instance($value)->toIso8601String()
            : (string) $value;
    }
}
