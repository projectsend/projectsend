<?php

declare(strict_types=1);

namespace App\Modules\Platform\Mail;

use App\Modules\Platform\Settings\MailProvider;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * Sends through the Gmail API as the connected Google account.
 *
 * Gmail's messages.send takes the raw RFC 822 message base64url-encoded
 * — the same "ship Symfony's exact bytes" approach as
 * MicrosoftGraphTransport, and for the same reason: re-describing an
 * already-rendered message in a vendor's JSON shape is a second
 * serializer to get subtly wrong. Gmail reads recipients from the MIME
 * headers and strips Bcc on delivery.
 *
 * Gmail rewrites the From header to the authenticated account (or one
 * of its configured send-as aliases), which is why MailConfigApplier
 * pins mail.from.address to the connected account while this provider
 * is active. The connection row is read fresh on every send — a queue
 * worker holds this transport for its whole life, and tokens change
 * underneath it.
 */
class GmailTransport extends AbstractTransport
{
    public function __construct(private readonly GoogleMailBroker $broker)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $connection = MailOAuthConnection::for(MailProvider::Gmail);

        if (! $connection->usable()) {
            throw new TransportException('Gmail is selected as the mail provider, but no account is connected.');
        }

        try {
            $token = $this->broker->freshAccessToken($connection);
        } catch (MailOAuthException $e) {
            throw new TransportException('Could not get a Google access token: '.$e->getMessage(), 0, $e);
        }

        $response = Http::withToken($token)->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', [
            'raw' => rtrim(strtr(base64_encode($message->toString()), '+/', '-_'), '='),
        ]);

        if (! $response->successful()) {
            $status = $response->json('error.status');
            $detail = $response->json('error.message');

            throw new TransportException(
                'Gmail refused the message (HTTP '.$response->status()
                .(is_string($status) && $status !== '' ? ', '.$status : '').')'
                .(is_string($detail) && $detail !== '' ? ': '.$detail : '.'),
            );
        }
    }

    public function __toString(): string
    {
        return 'gmail-api';
    }
}
