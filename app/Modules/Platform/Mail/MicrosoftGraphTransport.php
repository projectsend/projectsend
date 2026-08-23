<?php

declare(strict_types=1);

namespace App\Modules\Platform\Mail;

use App\Modules\Platform\Settings\MailProvider;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * Sends through Microsoft Graph's sendMail as the connected mailbox.
 *
 * Graph rather than smtp.office365.com because SMTP submission (both
 * password and XOAUTH2) is the endpoint Microsoft is winding down, while
 * Graph is where they invest — this transport is the future-proof half
 * of the Microsoft 365 provider, the connect flow in MicrosoftMailBroker
 * is the other.
 *
 * The message goes up as base64 MIME, not as Graph's JSON message shape:
 * Symfony already rendered the exact bytes (themed HTML, alternatives,
 * attachments), and re-describing them in JSON is a second
 * serializer to get subtly wrong. Exchange reads recipients from the
 * MIME headers and strips Bcc on delivery, so all three recipient kinds
 * behave. The connection row is read fresh on every send — a queue
 * worker holds this transport for its whole life, and tokens rotate
 * underneath it.
 *
 * Sending as the connected mailbox is a property of delegated Graph, not
 * a limitation of this class: the From header must be that mailbox (or
 * one it holds SendAs rights over), which is why MailConfigApplier pins
 * mail.from.address to the connected account while this provider is
 * active.
 */
class MicrosoftGraphTransport extends AbstractTransport
{
    public function __construct(private readonly MicrosoftMailBroker $broker)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $connection = MailOAuthConnection::for(MailProvider::Microsoft365);

        if (! $connection->usable()) {
            throw new TransportException('Microsoft 365 is selected as the mail provider, but no mailbox is connected.');
        }

        try {
            $token = $this->broker->freshAccessToken($connection);
        } catch (MailOAuthException $e) {
            throw new TransportException('Could not get a Microsoft 365 access token: '.$e->getMessage(), 0, $e);
        }

        $response = Http::withToken($token)
            ->withBody(base64_encode($message->toString()), 'text/plain')
            ->post('https://graph.microsoft.com/v1.0/me/sendMail');

        // Graph acknowledges an accepted submission with 202 and an empty
        // body; anything else is a refusal worth the admin's attention
        // (SendAsDenied when the From header isn't the connected mailbox,
        // ErrorMessageSubmissionBlocked, throttling).
        if ($response->status() !== 202) {
            $code = $response->json('error.code');
            $detail = $response->json('error.message');

            // The code carries the diagnosis ("ErrorQuotaExceeded",
            // "ErrorSendAsDenied"); Graph's message text is often generic
            // to the point of useless, so both go into the exception.
            throw new TransportException(
                'Microsoft Graph refused the message (HTTP '.$response->status()
                .(is_string($code) && $code !== '' ? ', '.$code : '').')'
                .(is_string($detail) && $detail !== '' ? ': '.$detail : '.'),
            );
        }
    }

    public function __toString(): string
    {
        return 'microsoft-graph';
    }
}
