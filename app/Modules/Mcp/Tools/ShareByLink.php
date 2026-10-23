<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools;

use App\Modules\Mcp\Support\Account;
use App\Modules\Mcp\Support\Envelope;
use App\Modules\Mcp\Support\Files;
use App\Modules\Mcp\Support\Links;
use App\Modules\Mcp\Support\PlanGate;
use App\Modules\Mcp\Support\Presenter;
use App\Modules\Mcp\Support\ToolError;
use App\Modules\Mcp\Support\Web;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('share_by_link')]
#[Title('Get a private link')]
#[Description('Returns a private link for a file: a URL anyone holding it can use to download, with no account. Use it to hand a file to someone who is not a client account, or when the person asks for "a link". If the file already has an active link with no expiry or cap, that link is returned; a new one is minted only when you ask for an expiry or a download cap the existing one lacks. Do not use it to share with a client account (send_file_to_client) — a link is not tied to a person and cannot tell you who opened it. Give the URL to the person; revoke_link stops it.')]
#[IsIdempotent]
class ShareByLink extends ToolBase
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'file_id' => $schema->integer()->min(1)->required()->description("The file's id, from list_files or a previous response."),
            'expires_at' => $schema->string()->description("Day the link stops working (end of that day in the account's time zone), as an ISO 8601 date. Only if the account may set expiry; otherwise ignored with a warning."),
            'max_downloads' => $schema->integer()->min(1)->description('Stop the link after this many downloads. Same conditions.'),
        ];
    }

    protected function run(Request $request, Account $account): Response|ResponseFactory|ToolError
    {
        $validated = $request->validate([
            'file_id' => ['required', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
            'max_downloads' => ['nullable', 'integer', 'min:1'],
        ]);

        $file = Files::find($account, (int) $validated['file_id']);

        if ($file === null) {
            return ToolError::notFound('file');
        }

        Files::requireSharingRoot($file, $this->name(), array_diff_key($validated, ['file_id' => true]));
        Files::requireUpdate($file, $account);

        $warnings = [];

        // What this account may actually set, first: an option it cannot
        // use is no reason to mint a second plain link beside the first.
        [$expiresAt, $maxDownloads, $planLimited] = Links::allowedOptions(
            $account,
            isset($validated['expires_at']) ? (string) $validated['expires_at'] : null,
            isset($validated['max_downloads']) ? (int) $validated['max_downloads'] : null,
            $warnings,
        );

        $wantsOptions = $expiresAt !== null || $maxDownloads !== null;
        $existing = $wantsOptions ? null : Links::plainActive($file);
        $created = false;

        if ($existing !== null) {
            $link = $existing;
        } else {
            $link = Links::mint($file, $account, $expiresAt, $maxDownloads, $warnings);
            $created = true;
        }

        $fileId = (int) $file->getAttribute('id');
        $linkId = (int) $link->getAttribute('id');

        return Envelope::success(
            [
                'file' => ['id' => $fileId, 'name' => (string) $file->getAttribute('name')],
                'link' => Presenter::link($link),
                'created' => $created,
            ] + ($planLimited ? ['upgrade_url' => PlanGate::upgradeUrl($this->name(), $account)] : []),
            Web::file($account, $fileId),
            "Give the person the link URL. Anyone with it can download; get_download_status with link_id {$linkId} shows when. revoke_link with link_id {$linkId} stops it.",
            $warnings,
        );
    }
}
