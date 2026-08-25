<?php

declare(strict_types=1);

namespace App\Modules\Platform\Settings;

/**
 * The choices in the Email settings "Provider" dropdown.
 *
 * Two kinds share the one list, distinguished by isOAuth(): the SMTP
 * presets (every one of them supports SMTP relay, so selecting one just
 * pre-fills the well-known host/port — the app sends via Laravel's "smtp"
 * mailer regardless of which was picked; Custom covers anything else),
 * and the OAuth API providers, which switch the transport itself to a
 * dedicated mailer that talks the vendor's HTTP API with tokens from
 * MailOAuthConnection instead of a password. One dropdown rather than a
 * separate screen because "where does outgoing email go" should have
 * exactly one answer.
 */
enum MailProvider: string
{
    case Custom = 'custom';
    case SendGrid = 'sendgrid';
    case Mailgun = 'mailgun';
    case Postmark = 'postmark';
    case AmazonSes = 'ses';
    case Microsoft365 = 'microsoft365';
    case Gmail = 'gmail';

    public function label(): string
    {
        return match ($this) {
            self::Custom => 'Custom SMTP',
            self::SendGrid => 'SendGrid',
            self::Mailgun => 'Mailgun',
            self::Postmark => 'Postmark',
            self::AmazonSes => 'Amazon SES',
            self::Microsoft365 => 'Microsoft 365 (OAuth)',
            self::Gmail => 'Google / Gmail (OAuth)',
        };
    }

    public function defaultHost(): ?string
    {
        return match ($this) {
            self::Custom, self::Microsoft365, self::Gmail => null,
            self::SendGrid => 'smtp.sendgrid.net',
            self::Mailgun => 'smtp.mailgun.org',
            self::Postmark => 'smtp.postmarkapp.com',
            self::AmazonSes => 'email-smtp.us-east-1.amazonaws.com',
        };
    }

    public function defaultPort(): ?int
    {
        return match ($this) {
            self::Custom, self::Microsoft365, self::Gmail => null,
            self::SendGrid, self::Mailgun, self::Postmark, self::AmazonSes => 587,
        };
    }

    /**
     * Whether this provider sends through a vendor HTTP API authorized by
     * an admin-connected mailbox (MailOAuthConnection) rather than through
     * the generic SMTP transport.
     */
    public function isOAuth(): bool
    {
        return $this === self::Microsoft365 || $this === self::Gmail;
    }

    /**
     * The custom Laravel mailer this provider sends through — the name
     * registered via Mail::extend() and declared in config/mail.php.
     */
    public function oauthMailer(): ?string
    {
        return match ($this) {
            self::Microsoft365 => 'microsoft-graph',
            self::Gmail => 'gmail-api',
            default => null,
        };
    }

    /**
     * Whether the connect flow needs a directory/tenant to build its
     * endpoints. Microsoft's authorize/token URLs are tenant-scoped;
     * blank falls back to 'common', which admits work/school accounts of
     * any tenant plus personal accounts — the inclusive default for this
     * app's audience. Unlike social login's tenant pinning this is not a
     * security control: the flow is started by an administrator and the
     * resulting token can only send as the one mailbox that consented.
     */
    public function needsTenant(): bool
    {
        return $this === self::Microsoft365;
    }
}
