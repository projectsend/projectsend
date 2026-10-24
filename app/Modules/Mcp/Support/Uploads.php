<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Support;

use App\Modules\Files\Http\Controllers\ChunkedUploadsController;
use App\Modules\Files\Models\File;
use App\Modules\Files\Uploads\LocalPartStore;
use App\Modules\Files\Uploads\UploadExtensionPolicy;
use App\Modules\Files\Uploads\UploadSession;
use App\Modules\Mcp\McpServiceProvider;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Getting bytes into the account through the app's own upload path.
 *
 * The web and the API upload in parts through `ChunkedUploadsController`:
 * open a session, PUT each part to a signed URL, complete. That
 * controller is where the site's file-size cap, the account's quota, the
 * extension policy, the open-session cap, the assembly, the mime
 * detection from the bytes, the quota re-check against the real size,
 * the `FileWasStored` event (which mints the free customer's link) and
 * the admin notifications all live. Reimplementing any of that here
 * would be a second copy that drifts, so the connector *calls the
 * controller* — with a request built for it — and only classifies its
 * refusals into the errors an assistant can act on.
 *
 * The checks that decide *which* error are run here first, before the
 * controller, because the controller reports every refusal as a
 * validation message on one field and an assistant needs to know
 * whether the file is too big for the site or too big for the space.
 */
final class Uploads
{
    private const MEGABYTE = 1024 * 1024;

    /** How many part URLs one answer signs; the rest come on the next call. */
    public const PARTS_PER_RESPONSE = 100;

    /** The app signs a part URL for this long. */
    public const SIGNATURE_MINUTES = 30;

    /** The app purges a session this long after it was opened. */
    public const SESSION_HOURS = 24;

    public static function partSizeBytes(): int
    {
        $mb = config('projectsend.upload_part_size_mb', 20);

        return max(1, (int) (is_numeric($mb) ? $mb : 20)) * self::MEGABYTE;
    }

    public static function partsTotal(int $sizeBytes): int
    {
        return max(1, (int) ceil($sizeBytes / self::partSizeBytes()));
    }

    /**
     * The checks the controller makes at open, asked first so the
     * refusal has a name: the site's cap, the type, then the space.
     * Nothing is created; nothing has moved.
     */
    public static function check(Account $account, string $filename, int $sizeBytes, string $tool): void
    {
        $maxMb = (int) app(Settings::class)->get(Setting::MaxFileSizeMb);

        if ($maxMb > 0 && $sizeBytes > $maxMb * self::MEGABYTE) {
            throw new ToolFailure(ToolError::fileTooLarge($sizeBytes, $maxMb * self::MEGABYTE));
        }

        $policy = app(UploadExtensionPolicy::class);

        if (! $policy->isAllowed($account->user, $filename)) {
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            throw new ToolFailure(ToolError::fileTypeNotAllowed($extension === '' ? '(none)' : $extension, $policy->hintFor($account->user)));
        }

        $storage = $account->storage();

        if ($storage !== null) {
            $upgrade = PlanGate::upgradeUrl($tool, $account);

            if ($storage['remaining_bytes'] <= 0) {
                throw new ToolFailure(ToolError::storageFull(self::block($storage), $upgrade));
            }

            if ($sizeBytes > $storage['remaining_bytes']) {
                throw new ToolFailure(ToolError::storageWouldExceed($sizeBytes, $storage['remaining_bytes'], self::block($storage), $upgrade));
            }
        }
    }

    /**
     * Open a session through the controller. The checks above have run;
     * what is left to refuse here (a folder the person cannot upload to,
     * too many open sessions) comes back as `validation_failed`.
     */
    public static function open(Account $account, string $filename, int $sizeBytes, ?string $mimeType, ?string $description, ?int $folderId): UploadSession
    {
        $request = self::request($account, [
            'filename' => $filename,
            'size' => $sizeBytes,
            'type' => $mimeType,
            'description' => $description,
            'folder_id' => $account->isClient() ? null : $folderId,
        ]);

        try {
            $response = app(ChunkedUploadsController::class)->store($request);
        } catch (ValidationException $exception) {
            throw new ToolFailure(ToolError::validationFailed(self::errors($exception)));
        }

        $id = data_get($response->getData(true), 'uploadId');
        $session = is_string($id) ? self::session($id, $account) : null;

        if ($session === null) {
            throw new ToolFailure(ToolError::notFound('upload'));
        }

        return $session;
    }

    /** A session of this account, or null: someone else's is nobody's. */
    public static function session(string $id, Account $account): ?UploadSession
    {
        $session = UploadSession::query()->whereKey($id)->first();

        if ($session === null || (int) $session->getAttribute('user_id') !== $account->id()) {
            return null;
        }

        return $session;
    }

    /**
     * Which parts have arrived, and how many bytes they add up to.
     *
     * @return array{numbers: list<int>, bytes: int}
     */
    public static function received(UploadSession $session): array
    {
        $numbers = [];
        $bytes = 0;

        foreach (app(LocalPartStore::class)->listParts($session) as $part) {
            $numbers[] = $part['PartNumber'];
            $bytes += $part['Size'];
        }

        sort($numbers);

        return ['numbers' => $numbers, 'bytes' => $bytes];
    }

    /**
     * Signed PUT URLs for the parts named, sized as the store will cut
     * them. No Authorization header is needed: the signature is the
     * credential, which is what lets a plain `curl` send the bytes.
     *
     * @param  list<int>  $numbers
     * @return list<array{number: int, put_url: string, size_bytes: int, expires_at: string}>
     */
    public static function sign(UploadSession $session, array $numbers): array
    {
        $store = app(LocalPartStore::class);
        $size = (int) $session->getAttribute('size');
        $partSize = self::partSizeBytes();
        $total = self::partsTotal($size);
        $expires = now()->addMinutes(self::SIGNATURE_MINUTES)->toIso8601String();
        $signed = [];

        foreach ($numbers as $number) {
            $signed[] = [
                'number' => $number,
                'put_url' => (string) $store->signPartUrl($session, $number, McpServiceProvider::PART_ROUTE),
                'size_bytes' => $number === $total ? $size - ($total - 1) * $partSize : $partSize,
                'expires_at' => $expires,
            ];
        }

        return $signed;
    }

    /**
     * Write bytes the connector holds as part 1, for content that
     * arrived inline. Mirrors what a PUT to the signed URL does.
     *
     * @param  resource  $stream
     */
    public static function putPart(UploadSession $session, int $number, $stream): void
    {
        app(LocalPartStore::class)->storePart($session, $number, $stream);
    }

    /**
     * Complete through the controller, which assembles, re-checks the
     * real size against the cap and the quota, sniffs the type from the
     * bytes and stores the file. Returns the file it made.
     */
    public static function complete(UploadSession $session, Account $account): File
    {
        $request = self::request($account, [], 'POST');

        try {
            $response = app(ChunkedUploadsController::class)->complete($request, $session);
        } catch (ValidationException $exception) {
            throw new ToolFailure(self::classifyCompletion($exception, $session, $account));
        }

        $fileId = data_get($response->getData(true), 'file_id');
        $file = is_numeric($fileId) ? File::query()->whereKey((int) $fileId)->first() : null;

        if ($file === null) {
            throw new ToolFailure(ToolError::notFound('upload'));
        }

        return $file;
    }

    /**
     * What the controller refused at completion, as the error an
     * assistant can act on. The controller speaks in fields; `size` is
     * the cap or the quota, `parts` is assembly, `filename` is the type.
     */
    private static function classifyCompletion(ValidationException $exception, UploadSession $session, Account $account): ToolError
    {
        $errors = self::errors($exception);
        $declared = (int) $session->getAttribute('size');
        $filename = (string) $session->getAttribute('original_name');

        if (isset($errors['parts'])) {
            $received = self::received($session);
            $total = self::partsTotal($declared);
            $missing = array_values(array_diff(range(1, $total), $received['numbers']));

            return $missing === []
                ? ToolError::sizeMismatch($declared, $received['bytes'], $filename)
                : ToolError::uploadIncomplete($missing, $received['numbers'], $total, null);
        }

        if (isset($errors['size'])) {
            $maxMb = (int) app(Settings::class)->get(Setting::MaxFileSizeMb);
            $storage = $account->refreshedStorage();

            if ($storage !== null && $storage['remaining_bytes'] < $declared) {
                return ToolError::storageWouldExceed($declared, $storage['remaining_bytes'], self::block($storage), PlanGate::upgradeUrl('complete_upload', $account));
            }

            return ToolError::fileTooLarge($declared, $maxMb * self::MEGABYTE);
        }

        if (isset($errors['filename']) || isset($errors['type'])) {
            return ToolError::fileTypeNotAllowed(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), null);
        }

        return ToolError::validationFailed($errors);
    }

    /**
     * A request the app's controller can read as if it came from the
     * web: the arguments as input, and the account as its user.
     *
     * @param  array<string, mixed>  $input
     */
    private static function request(Account $account, array $input, string $method = 'POST'): HttpRequest
    {
        $request = HttpRequest::create('/mcp/uploads', $method, array_filter($input, static fn (mixed $v): bool => $v !== null));
        $request->headers->set('Accept', 'application/json');
        $request->setUserResolver(static fn () => $account->user);

        return $request;
    }

    /**
     * @return array<string, list<string>>
     */
    private static function errors(ValidationException $exception): array
    {
        /** @var array<string, list<string>> $errors */
        $errors = $exception->errors();

        return $errors;
    }

    /**
     * @param  array{used_bytes: int, used: string, quota_bytes: int, quota: string, remaining_bytes: int, remaining: string, percent: int}  $storage
     * @return array<string, mixed>
     */
    public static function block(array $storage): array
    {
        return [
            'used_bytes' => $storage['used_bytes'],
            'used' => $storage['used'],
            'quota_bytes' => $storage['quota_bytes'],
            'quota' => $storage['quota'],
            'percent' => $storage['percent'],
        ];
    }

    // ── idempotency for uploads ───────────────────────────────────────

    /**
     * Remember which session an idempotency key opened, so the same key
     * resumes it: the parts still missing, freshly signed, instead of a
     * second upload. Not the cached response the other creates keep,
     * because signed URLs expire and a resume needs live ones.
     */
    public static function rememberSession(Account $account, string $key, string $fingerprint, string $sessionId): void
    {
        $ttl = (int) config('mcp-connector.idempotency_ttl_seconds', 86400);

        Cache::put(self::keyFor($account, $key), ['session_id' => $sessionId, 'fingerprint' => $fingerprint, 'first_seen_at' => now()->toIso8601String()], $ttl);
        Cache::put(self::sessionKeyFor($account, $sessionId), $key, $ttl);
    }

    /**
     * @return array{session_id: string, fingerprint: string, first_seen_at: string}|null
     */
    public static function recallSession(Account $account, string $key): ?array
    {
        $value = Cache::get(self::keyFor($account, $key));

        return is_array($value) && isset($value['session_id'], $value['fingerprint'], $value['first_seen_at']) ? $value : null;
    }

    /** The key that opened this session, for a resume's `next_call`. */
    public static function keyOfSession(Account $account, string $sessionId): ?string
    {
        $value = Cache::get(self::sessionKeyFor($account, $sessionId));

        return is_string($value) ? $value : null;
    }

    private static function keyFor(Account $account, string $key): string
    {
        return 'mcp:upload:'.$account->id().':'.hash('sha256', $key);
    }

    private static function sessionKeyFor(Account $account, string $sessionId): string
    {
        return 'mcp:upload-session:'.$account->id().':'.$sessionId;
    }

    /**
     * A stable fingerprint of the arguments that matter to a key.
     *
     * @param  array<string, mixed>  $arguments
     */
    public static function fingerprint(array $arguments): string
    {
        ksort($arguments);

        try {
            return hash('sha256', (string) json_encode($arguments, JSON_THROW_ON_ERROR));
        } catch (Throwable) {
            return hash('sha256', serialize($arguments));
        }
    }
}
