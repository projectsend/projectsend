<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Support;

/**
 * Where to see it on the web: the `web_url` every response carries.
 *
 * Paths rather than route names, on purpose: these are the product's
 * public addresses — the ones in the person's browser and in the mails
 * the app sends — and change as rarely as those do. Absolute, over APP_URL, so an assistant
 * can hand them over without knowing which site this is.
 */
final class Web
{
    public static function base(): string
    {
        $url = config('app.url');

        return rtrim(is_string($url) && $url !== '' ? $url : 'http://localhost', '/');
    }

    public static function hostname(): string
    {
        $host = parse_url(self::base(), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : self::base();
    }

    public static function myFiles(): string
    {
        return self::base().'/my-files';
    }

    public static function myFilesUpload(): string
    {
        return self::base().'/my-files/upload';
    }

    public static function staffFiles(): string
    {
        return self::base().'/files';
    }

    public static function staffFile(int $fileId): string
    {
        return self::base().'/files/'.$fileId.'/details';
    }

    /** A folder of the staff library, opened in the library page. */
    public static function staffFolder(int $folderId): string
    {
        return self::base().'/files?folder='.$folderId;
    }

    public static function staffUpload(?int $folderId = null): string
    {
        return self::base().'/files/upload'.($folderId === null ? '' : '?folder='.$folderId);
    }

    public static function clients(): string
    {
        return self::base().'/clients';
    }

    public static function client(int $clientId): string
    {
        return self::base().'/clients/'.$clientId;
    }

    public static function signIn(): string
    {
        return self::base().'/login';
    }

    /** Where a connection is reconnected or cut. Lands with the OAuth step. */
    public static function aiConnections(): string
    {
        return self::base().'/settings/ai-connections';
    }

    public static function shareLink(string $token): string
    {
        return self::base().'/s/'.$token;
    }

    /**
     * Where this site's plan and seats are managed: a hosted edition's
     * portal, or the site itself when it manages its own.
     */
    public static function portal(): string
    {
        return ConnectorPlan::current()->portalUrl() ?? self::base();
    }

    /** A file's own page: the client's list, or the staff detail page. */
    public static function file(Account $account, int $fileId): string
    {
        return $account->isClient() ? self::myFiles() : self::staffFile($fileId);
    }

    public static function files(Account $account): string
    {
        return $account->isClient() ? self::myFiles() : self::staffFiles();
    }

    public static function upload(Account $account, ?int $folderId = null): string
    {
        return $account->isClient() ? self::myFilesUpload() : self::staffUpload($folderId);
    }
}
