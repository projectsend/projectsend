<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * RFC 6266 Content-Disposition values for download/preview responses.
 *
 * original_name is chosen by the uploader and may contain anything
 * printable, including non-ASCII. Putting those bytes raw inside
 * filename="..." (what every emission site did before this class) leaves
 * the saved filename to each browser's guesswork — historically Latin-1
 * per RFC 2616 — so "informe año.pdf" arrives mangled. The spec'd answer
 * is a plain-ASCII filename= for legacy clients plus a filename* UTF-8
 * ext-value (RFC 8187) that every current browser prefers.
 *
 * Built by hand rather than via HeaderUtils::makeDisposition() for one
 * reason: Symfony leaves token-safe names unquoted (filename=contract.pdf),
 * and the header this app has always sent is the quoted form — keeping
 * ASCII names byte-identical means no client, test or cache sees a format
 * change it didn't need.
 */
final class ContentDisposition
{
    public static function attachment(string $filename): string
    {
        return self::make('attachment', $filename);
    }

    public static function inline(string $filename): string
    {
        return self::make('inline', $filename);
    }

    private static function make(string $disposition, string $filename): string
    {
        // Control characters are already stripped at upload time
        // (StoreUploadedFile::sanitizeFilename), but this header is also fed
        // folder names and legacy rows — never trust one call site's input.
        $filename = preg_replace('/[\x00-\x1F\x7F]/u', '', $filename) ?? '';

        // A path separator in a suggested filename invites traversal-handling
        // bugs in download clients; display it as plain text instead.
        $filename = strtr($filename, ['/' => '_', '\\' => '_']);

        if (trim($filename) === '') {
            $filename = 'download';
        }

        // Legacy filename= parameter: ASCII only. '%' is stripped because
        // several clients percent-decode this value (the reason Symfony
        // rejects it outright); '"' and '\' survive via quoted-string
        // escaping below. Non-ASCII transliterates where possible.
        $ascii = preg_replace('/[^\x20-\x7E]/', '', str_replace('%', '', Str::ascii($filename))) ?? '';

        if (trim($ascii) === '') {
            $ascii = 'download';
        }

        $header = $disposition.'; filename="'.addcslashes($ascii, '"\\').'"';

        // Only when the ASCII form lost something: the RFC 8187 ext-value
        // carrying the real name, which every current browser prefers over
        // filename= when both are present.
        if ($ascii !== $filename) {
            $header .= "; filename*=utf-8''".rawurlencode($filename);
        }

        return $header;
    }
}
