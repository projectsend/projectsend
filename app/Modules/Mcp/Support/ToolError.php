<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Support;

use Laravel\Mcp\Response;

/**
 * Every way a tool can fail, in one place (spec §2.2 and §6).
 *
 * A tool never throws at the assistant. It returns `isError: true` with a
 * JSON object the model can read: a stable `code` to branch on, an
 * `action` that says what to do now, and when there is a call that
 * resolves it, a `next_call` with its arguments already filled in. The
 * `message` is prose for the person and may change; the rest is
 * contract, and the convention tests walk this class.
 *
 * Why one class with a constructor per code rather than exceptions per
 * module: the catalogue is the harness. An assistant that reads
 * "storage_full" and finds `next_call: list_files(sort: size)` does the
 * right thing on the first try; one that reads a stack trace does not.
 * Keeping the texts together is what keeps them consistent — and what
 * lets a test forbid the words the product never uses.
 */
final class ToolError
{
    /**
     * @param  array<string, mixed>  $fields
     */
    private function __construct(
        public readonly string $code,
        public readonly string $message,
        public readonly string $action,
        private readonly array $fields = [],
    ) {}

    // ── plan and permissions ────────────────────────────────────────────

    /**
     * Something a free account does not have (spec §7): the alternative
     * first, the paid plan once, and a sign-up link that says which tool
     * asked. Whatever the free plan lacks against a portal of one's own is
     * offered here, never hidden; `$feature` says which lack it is, so the
     * sentence names the thing the person actually asked for.
     *
     * @param  array{code: string, name: string, price: string}  $plan
     * @param  array<string, mixed>|null  $nextCall
     * @param  'clients'|'folders'  $feature
     */
    public static function featureRequiresPlan(string $tool, array $plan, ?string $upgradeUrl, ?string $plansUrl, ?array $nextCall = null, string $feature = 'clients'): self
    {
        $portal = "a portal of their own ({$plan['name']}, {$plan['price']})";

        [$message, $action, $alternative] = match ($feature) {
            'folders' => [
                "Folders come with {$portal}. This free account keeps its files in one list and can share any of them by private link right now.",
                "Folders come with {$portal}. This free account keeps its files in one list: find a file with list_files and share it with share_by_link, and offer that first. Mention the paid plan once, with upgrade_url, only if the person wants their files in folders or a whole folder shared with a client.",
                $tool === 'share_folder_with_client' ? 'share_by_link' : 'list_files',
            ],
            default => [
                "Client accounts come with {$portal}. This free account can share any file by private link right now.",
                "Client accounts come with {$portal}. This free account can share any file by private link right now: offer that first with share_by_link. Mention the paid plan once, with upgrade_url, only if the person needs clients to sign in.",
                'share_by_link',
            ],
        };

        return new self(
            'feature_requires_plan',
            $message,
            $action,
            array_filter([
                'plan' => $plan,
                'upgrade_url' => $upgradeUrl,
                'web_url' => $plansUrl,
                'alternative_tool' => $alternative,
                'next_call' => $nextCall,
            ], static fn (mixed $value): bool => $value !== null),
        );
    }

    /**
     * A client account asking for something only staff have, where there
     * is no plan to offer instead. The same code as a missing permission,
     * because that is what it is and an assistant already knows it.
     */
    public static function staffOnly(string $webUrl): self
    {
        return new self(
            'permission_missing',
            'This needs a staff account. A client account reaches its own files only.',
            'This needs a staff account. A client account reaches its own files only: tell the person, and offer what list_files and share_by_link can do with their own files. Do not retry.',
            ['permission' => 'staff', 'web_url' => $webUrl],
        );
    }

    public static function scopeInsufficient(string $webUrl): self
    {
        return new self(
            'scope_insufficient',
            'This connection was authorised to read only.',
            "This connection was authorised to read only. Ask the person to open web_url and reconnect choosing 'Operate', then try again. Do not retry before that.",
            ['required_scope' => Scope::WRITE, 'web_url' => $webUrl],
        );
    }

    public static function permissionMissing(string $permission, string $webUrl): self
    {
        return new self(
            'permission_missing',
            "The person's ProjectSend role does not include {$permission}.",
            "The person's ProjectSend role does not include {$permission}. Tell them which permission it is; an administrator of the site can grant it. Do not retry.",
            ['permission' => $permission, 'web_url' => $webUrl],
        );
    }

    // ── things that are not there ───────────────────────────────────────

    /**
     * @param  'file'|'folder'|'link'|'client'|'upload'  $resource
     * @param  array<string, mixed>|null  $nextCall
     */
    public static function notFound(string $resource, ?array $nextCall = null, ?string $action = null): self
    {
        $default = match ($resource) {
            'client' => 'No client has this id or address in this site.',
            'upload' => 'This upload expired or never existed. Start again with create_upload.',
            'folder' => 'No such folder in what this account can see. Find the right id with list_folders and try again.',
            default => "No such {$resource} in what this account can see. Find the right id with list_files and try again.",
        };

        return new self(
            'not_found',
            $default,
            $action ?? $default,
            array_filter(['resource' => $resource, 'next_call' => $nextCall], static fn (mixed $value): bool => $value !== null),
        );
    }

    // ── content and size ────────────────────────────────────────────────

    public static function contentTooLargeForInline(int $sizeBytes, int $limitBytes, string $filename): self
    {
        $size = Bytes::human($sizeBytes);
        $limit = Bytes::human($limitBytes);

        return new self(
            'content_too_large_for_inline',
            "This content is {$size}; inline content is limited to {$limit}.",
            "This content is {$size}; inline content is limited to {$limit}. Upload it with create_upload using this filename and size, PUT the parts, then complete_upload with share: true. If you cannot send the bytes yourself, give the person a web upload link instead.",
            [
                'limit_bytes' => $limitBytes,
                'size_bytes' => $sizeBytes,
                'next_call' => ['tool' => 'create_upload', 'arguments' => ['filename' => $filename, 'size_bytes' => $sizeBytes]],
                'alternative_tool' => 'get_web_upload_link',
            ],
        );
    }

    public static function fileTooLarge(int $sizeBytes, int $limitBytes): self
    {
        $limit = Bytes::human($limitBytes);

        return new self(
            'file_too_large',
            "This site caps files at {$limit}.",
            "This site caps files at {$limit}. Ask the person whether the file can be split or compressed; the cap is the site's, not the plan's.",
            ['limit_bytes' => $limitBytes, 'size_bytes' => $sizeBytes],
        );
    }

    /**
     * @param  list<string>|null  $allowed  the allowed extensions when the site keeps a short list
     */
    public static function fileTypeNotAllowed(string $extension, ?array $allowed): self
    {
        $extension = ltrim($extension, '.');
        // The whole list travels in the field; the sentence names only
        // the first few, because a list of sixty is not something to
        // read out to a person.
        $list = $allowed === null ? '' : ' Allowed types include .'.implode(', .', array_slice($allowed, 0, 12)).(count($allowed) > 12 ? ' and others (see allowed_extensions)' : '').'.';

        return new self(
            'file_type_not_allowed',
            "This site does not accept .{$extension} files.",
            "This site does not accept .{$extension} files. Renaming does not help: the type is checked from the bytes. Ask the person whether a zip archive or another format is acceptable, and tell them which types are allowed if the list is short.{$list}",
            $allowed === null
                ? ['extension' => $extension, 'blocked' => true]
                : ['extension' => $extension, 'allowed_extensions' => $allowed],
        );
    }

    /**
     * @param  array<string, mixed>  $storage
     */
    public static function storageWouldExceed(int $sizeBytes, int $remainingBytes, array $storage, ?string $upgradeUrl): self
    {
        $size = Bytes::human($sizeBytes);
        $remaining = Bytes::human($remainingBytes);
        $upgrade = $upgradeUrl === null ? '' : ' On a free account, mention a portal of their own once, with upgrade_url, only if freeing space is not what they want.';

        return new self(
            'storage_would_exceed',
            "This file is {$size} and the account has {$remaining} left.",
            "This file is {$size} and the account has {$remaining} left. Offer to free space first: list_files sorted by size, then delete_file.{$upgrade}",
            array_filter([
                'size_bytes' => $sizeBytes,
                'remaining_bytes' => $remainingBytes,
                'remaining' => $remaining,
                'storage' => $storage,
                'upgrade_url' => $upgradeUrl,
                'next_call' => self::freeSpaceCall(),
                'alternative_tool' => 'delete_file',
            ], static fn (mixed $value): bool => $value !== null),
        );
    }

    /**
     * @param  array<string, mixed>  $storage
     */
    public static function storageFull(array $storage, ?string $upgradeUrl): self
    {
        $quota = is_string($storage['quota'] ?? null) ? $storage['quota'] : 'its space';
        $upgrade = $upgradeUrl === null ? '' : ' On a free account, mention a portal of their own once, with upgrade_url.';

        return new self(
            'storage_full',
            "This account has used all {$quota} of its space.",
            "This account has used all {$quota} of its space. Offer to free space first: list_files sorted by size, then delete_file.{$upgrade}",
            array_filter([
                'storage' => $storage,
                'upgrade_url' => $upgradeUrl,
                'next_call' => self::freeSpaceCall(),
                'alternative_tool' => 'delete_file',
            ], static fn (mixed $value): bool => $value !== null),
        );
    }

    // ── uploads ─────────────────────────────────────────────────────────

    /**
     * @param  list<int>  $missing
     * @param  list<int>  $received
     * @param  array<string, mixed>|null  $resumeCall
     */
    public static function uploadIncomplete(array $missing, array $received, int $partsTotal, ?array $resumeCall): self
    {
        $list = implode(', ', $missing);

        return new self(
            'upload_incomplete',
            "Parts {$list} have not arrived.",
            "Parts {$list} have not arrived. PUT them to their signed URLs and call complete_upload again. If the URLs expired, call create_upload with the same idempotency_key to get fresh ones for the missing parts only.",
            array_filter([
                'missing_parts' => $missing,
                'received_parts' => $received,
                'parts_total' => $partsTotal,
                'next_call' => $resumeCall,
            ], static fn (mixed $value): bool => $value !== null),
        );
    }

    public static function sizeMismatch(int $declared, int $assembled, string $filename): self
    {
        $a = Bytes::human($assembled);
        $d = Bytes::human($declared);

        return new self(
            'size_mismatch',
            "The assembled file is {$a} but {$d} was declared.",
            "The assembled file is {$a} but {$d} was declared. Check the file's real size and start again with create_upload; nothing was saved.",
            [
                'declared_bytes' => $declared,
                'assembled_bytes' => $assembled,
                'next_call' => ['tool' => 'create_upload', 'arguments' => ['filename' => $filename, 'size_bytes' => $assembled]],
            ],
        );
    }

    public static function invalidEncoding(string $encoding): self
    {
        return new self(
            'invalid_encoding',
            "The content could not be decoded as {$encoding}.",
            "The content could not be decoded as {$encoding}. Send it again with the right encoding.",
        );
    }

    /**
     * @param  array<string, mixed>  $nextArguments  the same call, on the root file
     */
    public static function fileIsRevision(string $tool, int $rootId, string $rootName, array $nextArguments): self
    {
        return new self(
            'file_is_revision',
            "This file is a new version of \"{$rootName}\" and is shared through it.",
            "This file is a new version of \"{$rootName}\" and is shared through it. Call the same tool with file_id {$rootId}.",
            [
                'sharing_root_id' => $rootId,
                'sharing_root_name' => $rootName,
                'next_call' => ['tool' => $tool, 'arguments' => ['file_id' => $rootId] + $nextArguments],
            ],
        );
    }

    // ── clients ─────────────────────────────────────────────────────────

    public static function clientEmailTaken(int $clientId, string $clientName): self
    {
        return new self(
            'client_email_taken',
            "A client with this address already exists (id {$clientId}, {$clientName}).",
            "A client with this address already exists (id {$clientId}, {$clientName}). Use that id; do not create another.",
            [
                'client_id' => $clientId,
                'client_name' => $clientName,
                'next_call' => ['tool' => 'get_client', 'arguments' => ['client_id' => $clientId]],
            ],
        );
    }

    public static function seatsFull(int $used, int $limit, string $portalUrl): self
    {
        return new self(
            'seats_full',
            "This site has used all {$limit} of its client seats.",
            "This site has used all {$limit} of its client seats. Tell the person how many were created; they can raise the plan from the portal (web_url) or deactivate a client on the web to free a seat. Do not retry.",
            ['clients_used' => $used, 'clients_limit' => $limit, 'web_url' => $portalUrl],
        );
    }

    // ── the rest ────────────────────────────────────────────────────────

    public static function idempotencyConflict(string $firstSeenAt): self
    {
        return new self(
            'idempotency_conflict',
            'This idempotency_key was already used with different arguments.',
            'This idempotency_key was already used with different arguments. Use a new key for a new request, or repeat the original arguments to get the original result.',
            ['first_seen_at' => $firstSeenAt],
        );
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    public static function validationFailed(array $errors): self
    {
        return new self(
            'validation_failed',
            'Some arguments are not valid.',
            "Fix the fields named in errors and call again. Field descriptions in the tool's schema say what is expected.",
            ['errors' => $errors],
        );
    }

    public static function rateLimited(int $retryAfterSeconds): self
    {
        return new self(
            'rate_limited',
            "Wait {$retryAfterSeconds} seconds before the next call.",
            "Wait {$retryAfterSeconds} seconds before the next call. Do not poll: pass since or a cursor to ask only for what is new.",
            ['retry_after_seconds' => $retryAfterSeconds],
        );
    }

    public static function instanceUnavailable(string $portalUrl): self
    {
        return new self(
            'instance_unavailable',
            'This ProjectSend site is not available right now.',
            'This ProjectSend site is not available right now. Tell the person; the portal at web_url says why. Do not retry in a loop.',
            ['web_url' => $portalUrl],
        );
    }

    // ── shape ───────────────────────────────────────────────────────────

    /**
     * Extra fields the caller adds after the fact: a `web_url` for the
     * error, the `storage` block, a `next_call` it knows better than the
     * catalogue. Never overwrites `code` or `action`.
     *
     * @param  array<string, mixed>  $fields
     */
    public function with(array $fields): self
    {
        unset($fields['code'], $fields['action']);

        return new self($this->code, $this->message, $this->action, array_merge($this->fields, $fields));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['error' => ['code' => $this->code, 'message' => $this->message, 'action' => $this->action] + $this->fields];
    }

    /** `isError: true`, with the object as text for the model to read. */
    public function toResponse(): Response
    {
        return Response::error((string) json_encode($this->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string, mixed>
     */
    private static function freeSpaceCall(): array
    {
        return ['tool' => 'list_files', 'arguments' => ['sort' => 'size', 'limit' => 20]];
    }
}
