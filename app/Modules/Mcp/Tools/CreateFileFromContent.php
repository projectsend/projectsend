<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools;

use App\Modules\Files\Models\File;
use App\Modules\Mcp\Support\Account;
use App\Modules\Mcp\Support\Folders;
use App\Modules\Mcp\Support\Idempotency;
use App\Modules\Mcp\Support\IdempotencyConflict;
use App\Modules\Mcp\Support\ToolError;
use App\Modules\Mcp\Support\ToolFailure;
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

#[Name('create_file_from_content')]
#[Title('Save text as a file')]
#[Description('Saves content you wrote in this conversation as a file and returns it with its private link, in one call. Use it for anything you produced here, up to 1 MB: a report, a summary, notes, a CSV, a small generated PDF (base64). Do not use it for a file that already exists somewhere as bytes (create_upload) or for a file on the person\'s computer that you cannot read (get_web_upload_link); do not split large content across several calls. Type and space are checked before saving. After this, give the person the link, or send the file to a client account with send_file_to_client.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsIdempotent(false)]
class CreateFileFromContent extends ToolBase
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'filename' => $schema->string()->max(255)->required()->description('With its extension: notes.md, invoice.csv, summary.html. The extension decides how the file opens.'),
            'content' => $schema->string()->required()->description('The content itself, up to 1 MB. UTF-8 text by default; set encoding to base64 for binary you produced (a small PDF, an image).'),
            'encoding' => $schema->string()->enum(['utf-8', 'base64'])->default('utf-8')->description('How content is encoded: utf-8 text, or base64 for binary.'),
            'description' => $schema->string()->max(1000)->description('A description shown beside the file.'),
            'folder_id' => $schema->integer()->min(1)->description('The folder to save it in, from list_folders. Staff libraries only.'),
            'share' => $schema->boolean()->default(true)->description("Return the file's private link in the same response. Set false on a staff account to save without a link."),
            'idempotency_key' => $schema->string()->max(64)->description('Reuse it to retry safely without a duplicate file.'),
        ];
    }

    protected function run(Request $request, Account $account): Response|ResponseFactory|ToolError
    {
        $validated = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'encoding' => ['nullable', 'in:utf-8,base64'],
            'description' => ['nullable', 'string', 'max:1000'],
            'folder_id' => ['nullable', 'integer', 'min:1'],
            'share' => ['nullable', 'boolean'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);

        $this->requirePermission($account, 'upload');

        $share = (bool) ($validated['share'] ?? true);
        $key = isset($validated['idempotency_key']) ? (string) $validated['idempotency_key'] : null;

        $save = fn (): ResponseFactory => CompleteUpload::saved(
            self::save(
                $account,
                (string) $validated['filename'],
                (string) $validated['content'],
                (string) ($validated['encoding'] ?? 'utf-8'),
                isset($validated['description']) ? (string) $validated['description'] : null,
                isset($validated['folder_id']) ? (int) $validated['folder_id'] : null,
                $this->name(),
            ),
            $account,
            $share,
            null,
            null,
        );

        if ($key === null) {
            return $save();
        }

        // The same key with the same content is the same file, answered
        // from what the first call returned: a retry after a timeout must
        // not save a second copy. A different body under the same key is
        // a caller bug and is refused.
        $fingerprint = Uploads::fingerprint(array_diff_key($validated, ['idempotency_key' => true]));

        try {
            [$payload] = app(Idempotency::class)->remember(
                'mcp',
                'mcp:'.$account->id().':create_file_from_content:'.$key,
                $fingerprint,
                function () use ($save): array {
                    $factory = $save();
                    $structured = $factory->getStructuredContent();

                    return is_array($structured) ? $structured : [];
                },
            );
        } catch (IdempotencyConflict) {
            return ToolError::idempotencyConflict(now()->toIso8601String());
        }

        return Response::structured($payload);
    }

    /**
     * Content that arrived inline, saved through the same upload path
     * bytes take: one session, one part, one completion — so the size
     * cap, the quota, the type check from the bytes and the app's own
     * events apply exactly as they do to a file uploaded on the web.
     */
    public static function save(Account $account, string $filename, string $content, string $encoding, ?string $description, ?int $folderId, string $tool): File
    {
        $bytes = self::decode($content, $encoding);
        $size = strlen($bytes);
        $limit = (int) config('mcp-connector.inline_content_max_bytes', 1024 * 1024);

        if ($size > $limit) {
            throw new ToolFailure(ToolError::contentTooLargeForInline($size, $limit, $filename));
        }

        if ($size === 0) {
            throw new ToolFailure(ToolError::validationFailed(['content' => ['The content is empty.']]));
        }

        if ($folderId !== null && ! $account->isClient()
            && Folders::find($account, $folderId) === null) {
            throw new ToolFailure(Folders::notFound());
        }

        Uploads::check($account, $filename, $size, $tool);

        $session = Uploads::open($account, $filename, $size, self::mimeOf($bytes), $description, $folderId);

        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new ToolFailure(ToolError::instanceUnavailable(Web::portal()));
        }

        fwrite($stream, $bytes);
        rewind($stream);

        try {
            Uploads::putPart($session, 1, $stream);
        } finally {
            fclose($stream);
        }

        return Uploads::complete($session, $account);
    }

    private static function decode(string $content, string $encoding): string
    {
        if ($encoding === 'base64') {
            $decoded = base64_decode($content, true);

            if ($decoded === false) {
                throw new ToolFailure(ToolError::invalidEncoding('base64'));
            }

            return $decoded;
        }

        if (! mb_check_encoding($content, 'UTF-8')) {
            throw new ToolFailure(ToolError::invalidEncoding('utf-8'));
        }

        return $content;
    }

    private static function mimeOf(string $bytes): ?string
    {
        $info = finfo_open(FILEINFO_MIME_TYPE);

        if ($info === false) {
            return null;
        }

        $mime = finfo_buffer($info, $bytes);
        finfo_close($info);

        return is_string($mime) ? $mime : null;
    }
}
