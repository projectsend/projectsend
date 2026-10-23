<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('send-deliverable')]
#[Title('Send a deliverable')]
#[Description('Get a file to the person it is for: save it or upload it, then share it by link or send it to a client account, and offer to check whether it was downloaded.')]
class SendDeliverable extends Prompt
{
    public const TEXT = <<<'TEXT'
Help the person get a deliverable to its recipient with ProjectSend.

1. Find out where the file is: written here (create_file_from_content), bytes you can read (create_upload then complete_upload with share: true), or on their computer (get_web_upload_link, then list_files with the `since` you were given).
2. If the recipient is a client account and this account can have clients, use send_file_to_client (create_client first if they do not exist, using their email as idempotency_key). Otherwise, or if the person asked for a link, use share_by_link.
3. Reply with the link or the confirmation that it was sent, in one or two sentences, quoting the URL from the response. Do not invent URLs.
4. Offer to check later whether it was downloaded (get_download_status). Mention the paid plan only if an error told you to, once, with the upgrade_url you were given.
TEXT;

    public function arguments(): array
    {
        return [
            new Argument(name: 'what', description: 'What is being sent, in a few words.', required: true),
            new Argument(name: 'recipient', description: "A client's name or email, or 'link' for a private link.", required: false),
        ];
    }

    public function handle(Request $request): Response
    {
        $what = trim((string) $request->get('what', ''));
        $recipient = trim((string) $request->get('recipient', ''));

        $lead = $what === '' ? '' : "The deliverable: {$what}.";
        $lead .= $recipient === '' ? '' : " The recipient: {$recipient}.";

        return Response::text(trim($lead."\n\n".self::TEXT));
    }
}
