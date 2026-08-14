<?php

declare(strict_types=1);

namespace App\Modules\Platform\Settings;

/**
 * A preset picker over the single generic SMTP transport — every provider
 * here supports SMTP relay, so selecting one just pre-fills the well-known
 * host/port; the app always sends via Laravel's "smtp" mailer regardless
 * of which preset was picked. Custom covers anything else ("etc").
 */
enum MailProvider: string
{
    case Custom = 'custom';
    case SendGrid = 'sendgrid';
    case Mailgun = 'mailgun';
    case Postmark = 'postmark';
    case AmazonSes = 'ses';

    public function label(): string
    {
        return match ($this) {
            self::Custom => 'Custom SMTP',
            self::SendGrid => 'SendGrid',
            self::Mailgun => 'Mailgun',
            self::Postmark => 'Postmark',
            self::AmazonSes => 'Amazon SES',
        };
    }

    public function defaultHost(): ?string
    {
        return match ($this) {
            self::Custom => null,
            self::SendGrid => 'smtp.sendgrid.net',
            self::Mailgun => 'smtp.mailgun.org',
            self::Postmark => 'smtp.postmarkapp.com',
            self::AmazonSes => 'email-smtp.us-east-1.amazonaws.com',
        };
    }

    public function defaultPort(): ?int
    {
        return match ($this) {
            self::Custom => null,
            self::SendGrid, self::Mailgun, self::Postmark, self::AmazonSes => 587,
        };
    }
}
