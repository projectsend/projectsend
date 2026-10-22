<?php

declare(strict_types=1);

namespace App\Modules\Platform\Settings;

/**
 * What the Email screen's Encryption choice means to the SMTP transport.
 *
 * Laravel stopped reading `mail.mailers.smtp.encryption` years ago. It
 * takes the scheme from `scheme`, or from the port when that is empty, and
 * Symfony then upgrades any plain connection with STARTTLS whenever the
 * server offers it. So the choice used to change nothing: "SSL" only
 * worked on 465, and "None" still negotiated TLS, which is why a server
 * with a certificate for the wrong name failed even with it off (#1815).
 *
 * "TLS" keeps exactly the old behaviour, port rule included, because it is
 * the default and nobody on it should notice this change. So does a value
 * that says nothing: .env.example ships MAIL_ENCRYPTION=null, and reading
 * that as "None" would quietly stop every install configured from .env
 * alone from upgrading to TLS, against servers that refuse to log in
 * without it. Only "none", said in so many words, turns STARTTLS off.
 */
final class SmtpEncryption
{
    /**
     * @return array{scheme?: string, auto_tls?: bool}
     */
    public static function transportOptions(?string $encryption): array
    {
        return match ($encryption) {
            'ssl' => ['scheme' => 'smtps'],
            'none' => ['auto_tls' => false],
            default => [],
        };
    }
}
