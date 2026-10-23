<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools;

use App\Modules\Files\Uploads\UploadSession;
use App\Modules\Mcp\Support\Account;
use App\Modules\Mcp\Support\Bytes;
use App\Modules\Mcp\Support\Envelope;
use App\Modules\Mcp\Support\Folders;
use App\Modules\Mcp\Support\ToolError;
use App\Modules\Mcp\Support\Uploads;
use App\Modules\Mcp\Support\Web;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('create_upload')]
#[Title('Start an upload')]
#[Description('Opens a resumable upload for bytes you can read yourself and returns signed URLs to PUT each part to. Use it for any file you hold as bytes (on disk, in a sandbox, produced by a script) and for anything over 1 MB. Do not use it for text you wrote in this conversation (create_file_from_content does it in one call) or when the file is on the person\'s computer and you cannot read it (get_web_upload_link). Size, type and space are checked here, before a single byte moves. After PUTting the parts, call complete_upload; pass share: true there to get the private link in the same response. Call this again with the same idempotency_key to resume an interrupted upload.')]
#[IsIdempotent]
class CreateUpload extends ToolBase
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'filename' => $schema->string()->max(255)->required()->description('The file name with its extension, e.g. report.pdf. The type is checked from the name now and from the bytes later.'),
            'size_bytes' => $schema->integer()->min(1)->required()->description("Exact size in bytes. Checked against the account's space and the file size limit before any byte is sent."),
            'mime_type' => $schema->string()->max(255)->description('If you know it. The stored type is detected from the bytes regardless.'),
            'description' => $schema->string()->max(1000)->description('A description shown beside the file.'),
            'folder_id' => $schema->integer()->min(1)->description('The folder to save it in, from list_folders. Staff libraries only.'),
            'idempotency_key' => $schema->string()->max(64)->description('Reuse the same key to resume: you get the parts still missing, freshly signed, instead of a second upload.'),
        ];
    }

    protected function run(Request $request, Account $account): Response|ResponseFactory|ToolError
    {
        $validated = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'size_bytes' => ['required', 'integer', 'min:1'],
            'mime_type' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'folder_id' => ['nullable', 'integer', 'min:1'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);

        $this->requirePermission($account, 'upload');

        $filename = (string) $validated['filename'];
        $size = (int) $validated['size_bytes'];
        $key = isset($validated['idempotency_key']) ? (string) $validated['idempotency_key'] : null;
        $fingerprint = Uploads::fingerprint(['filename' => $filename, 'size_bytes' => $size]);

        if (isset($validated['folder_id']) && ! $account->isClient()
            && Folders::find($account, (int) $validated['folder_id']) === null) {
            return Folders::notFound();
        }

        $session = null;

        if ($key !== null) {
            $remembered = Uploads::recallSession($account, $key);

            if ($remembered !== null) {
                if ($remembered['fingerprint'] !== $fingerprint) {
                    return ToolError::idempotencyConflict($remembered['first_seen_at']);
                }

                $session = Uploads::session($remembered['session_id'], $account);

                if ($session === null) {
                    return ToolError::notFound('upload', ['tool' => 'create_upload', 'arguments' => ['filename' => $filename, 'size_bytes' => $size]],
                        'The upload behind this idempotency_key already completed or expired. If the file is not in list_files, start again with create_upload and a new key.');
                }
            }
        }

        if ($session === null) {
            Uploads::check($account, $filename, $size, $this->name());

            $session = Uploads::open(
                $account,
                $filename,
                $size,
                isset($validated['mime_type']) ? (string) $validated['mime_type'] : null,
                isset($validated['description']) ? (string) $validated['description'] : null,
                isset($validated['folder_id']) ? (int) $validated['folder_id'] : null,
            );

            if ($key !== null) {
                Uploads::rememberSession($account, $key, $fingerprint, (string) $session->getKey());
            }
        }

        return self::describe($session, $account, $filename, $size);
    }

    /** The session as the assistant needs it: what to PUT where, and what is still missing. */
    public static function describe(UploadSession $session, Account $account, string $filename, int $size): ResponseFactory
    {
        $sessionId = (string) $session->getKey();
        $total = Uploads::partsTotal($size);
        $received = Uploads::received($session);
        $missing = array_values(array_diff(range(1, $total), $received['numbers']));
        $toSign = array_slice($missing, 0, Uploads::PARTS_PER_RESPONSE);
        $parts = Uploads::sign($session, $toSign);
        $first = $parts[0]['put_url'] ?? '<put_url of part 1>';

        return Envelope::success(
            [
                'upload_id' => $sessionId,
                'filename' => $filename,
                'size_bytes' => $size,
                'size' => Bytes::human($size),
                'part_size_bytes' => Uploads::partSizeBytes(),
                'parts_total' => $total,
                'parts' => $parts,
                'received_parts' => $received['numbers'],
                'more_parts' => count($missing) > count($toSign),
                'expires_at' => self::sessionExpiry($session),
                'example' => sprintf('curl -X PUT --data-binary @%s "%s"', escapeshellarg($filename), $first),
            ],
            Web::upload($account),
            "PUT each part's bytes to its put_url (no Authorization header; the URL is signed). Then call complete_upload with upload_id \"{$sessionId}\" and share: true.",
        );
    }

    private static function sessionExpiry(UploadSession $session): string
    {
        $created = $session->getAttribute('created_at');

        if ($created instanceof \DateTimeInterface) {
            return Carbon::instance($created)->addHours(Uploads::SESSION_HOURS)->toIso8601String();
        }

        return now()->addHours(Uploads::SESSION_HOURS)->toIso8601String();
    }
}
