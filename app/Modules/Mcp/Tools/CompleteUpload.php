<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools;

use App\Modules\Files\Models\File;
use App\Modules\Mcp\Support\Account;
use App\Modules\Mcp\Support\Envelope;
use App\Modules\Mcp\Support\Links;
use App\Modules\Mcp\Support\PlanGate;
use App\Modules\Mcp\Support\Presenter;
use App\Modules\Mcp\Support\ToolError;
use App\Modules\Mcp\Support\Uploads;
use App\Modules\Mcp\Support\Web;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('complete_upload')]
#[Title('Finish an upload')]
#[Description('Assembles the parts of an upload started with create_upload and returns the saved file. Use it once every part has been PUT. Pass share: true to get the file\'s private link in the same response, so uploading and sharing is one step, not two. Do not call it before the parts are in place: if any is missing the response lists them and nothing is created. Calling it again for an upload that already completed returns the same file. After this, the file is ready to send with send_file_to_client or to hand over by its link.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsIdempotent(false)]
class CompleteUpload extends ToolBase
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'upload_id' => $schema->string()->max(64)->required()->description('From create_upload.'),
            'share' => $schema->boolean()->default(false)->description('Also mint a private link and return it. On a free account the link always exists; this flag is for staff accounts.'),
            'name' => $schema->string()->max(255)->description('A display name other than the file name.'),
            'description' => $schema->string()->max(1000)->description('A description shown beside the file.'),
            'link_expires_at' => $schema->string()->description('Expiry for the link (ISO 8601 date), if share is true and the account may set one; otherwise ignored with a warning.'),
            'link_max_downloads' => $schema->integer()->min(1)->description('Download cap for the link, same conditions.'),
        ];
    }

    protected function run(Request $request, Account $account): Response|ResponseFactory|ToolError
    {
        $validated = $request->validate([
            'upload_id' => ['required', 'string', 'max:64'],
            'share' => ['nullable', 'boolean'],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'link_expires_at' => ['nullable', 'date'],
            'link_max_downloads' => ['nullable', 'integer', 'min:1'],
        ]);

        $this->requirePermission($account, 'upload');

        $session = Uploads::session((string) $validated['upload_id'], $account);

        if ($session === null) {
            return ToolError::notFound('upload');
        }

        $declared = (int) $session->getAttribute('size');
        $filename = (string) $session->getAttribute('original_name');
        $total = Uploads::partsTotal($declared);
        $received = Uploads::received($session);
        $missing = array_values(array_diff(range(1, $total), $received['numbers']));

        if ($missing !== []) {
            $key = Uploads::keyOfSession($account, (string) $session->getKey());
            $resume = $key === null ? null : ['tool' => 'create_upload', 'arguments' => ['filename' => $filename, 'size_bytes' => $declared, 'idempotency_key' => $key]];

            return ToolError::uploadIncomplete($missing, $received['numbers'], $total, $resume);
        }

        if ($received['bytes'] !== $declared) {
            return ToolError::sizeMismatch($declared, $received['bytes'], $filename);
        }

        $file = Uploads::complete($session, $account);

        $edits = array_filter([
            'name' => isset($validated['name']) ? (string) $validated['name'] : null,
            'description' => isset($validated['description']) ? (string) $validated['description'] : null,
        ], static fn (?string $v): bool => $v !== null && $v !== '');

        if ($edits !== []) {
            $file->forceFill($edits)->save();
        }

        return self::saved($file, $account, (bool) ($validated['share'] ?? false), $validated['link_expires_at'] ?? null, $validated['link_max_downloads'] ?? null);
    }

    /**
     * The answer for a file that just landed, shared by the two tools
     * that store one: the file, its link when there is one, the space
     * now, and what to do next.
     */
    public static function saved(File $file, Account $account, bool $share, mixed $expiresAt, mixed $maxDownloads): ResponseFactory
    {
        $warnings = [];

        // What this account may set, first. On the shared instance that is
        // neither option, and asking for one is the moment to say what a
        // portal of their own adds: the file already has its plain link, so
        // without this the request would vanish in silence.
        [$expiresAt, $maxDownloads, $planLimited] = Links::allowedOptions(
            $account,
            is_string($expiresAt) ? $expiresAt : null,
            is_numeric($maxDownloads) ? (int) $maxDownloads : null,
            $warnings,
        );

        // Where clients are a free plan, the hosted edition minted their
        // link the moment the file was stored; a client asking for one
        // gets that one. Staff get one only when they ask.
        $link = Links::plainActive($file);

        if ($link === null && ($share || $account->isClient())) {
            $link = Links::mint($file, $account, $expiresAt, $maxDownloads, $warnings);
        }

        $fileId = (int) $file->getAttribute('id');
        $storage = $account->refreshedStorage();
        $warning = $account->storageWarning();

        if ($warning !== null) {
            $warnings[] = $warning;
        }

        return Envelope::success(
            [
                'file' => Presenter::file($file, $account),
                'link' => $link === null ? null : Presenter::link($link),
            ] + ($planLimited ? ['upgrade_url' => PlanGate::upgradeUrl('complete_upload', $account)] : []),
            Web::file($account, $fileId),
            $link === null
                ? 'Share it with share_by_link, or send it to a client account with send_file_to_client.'
                : "Give the person the link URL; anyone with it can download. get_download_status with file_id {$fileId} shows when it is opened.",
            $warnings,
            $storage,
        );
    }
}
