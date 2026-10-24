<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Support;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Making a create safe to repeat.
 *
 * An assistant whose call timed out cannot tell whether it happened, so
 * it sends the same call again with the same key. The first call with a
 * key does the work and remembers what it returned; a repeat gets that
 * answer back rather than a second client or a second file.
 *
 * A key is remembered with a hash of the request that made it. The same
 * key with different arguments is a caller bug, and answering it with
 * the first call's result would look like a success, so it is refused.
 *
 * The record is cached for a day, not stored. Past that a repeat is a
 * new request, which is what a repeat a day later is.
 */
final class Idempotency
{
    private const LOCK_SECONDS = 30;

    private const LOCK_WAIT_SECONDS = 10;

    /**
     * @param  Closure(): array<string, mixed>  $create
     * @return array{0: array<string, mixed>, 1: bool} the payload, and whether it is a replay
     *
     * @throws IdempotencyConflict when the key was used for a different request, or is still running
     */
    public function remember(string $namespace, string $key, string $fingerprint, Closure $create): array
    {
        // The prefix this class had before it moved into core, kept
        // so a retry that straddles the move still finds its answer.
        $cacheKey = 'platform-control:'.$namespace.':idempotency:'.hash('sha256', $key);

        $seen = $this->read($cacheKey, $fingerprint);

        if ($seen !== null) {
            return [$seen, true];
        }

        $lock = Cache::lock($cacheKey.':lock', self::LOCK_SECONDS);

        try {
            $lock->block(self::LOCK_WAIT_SECONDS);
        } catch (LockTimeoutException) {
            throw new IdempotencyConflict('Another request with this idempotency key is still in progress.');
        }

        try {
            // Read again inside the lock: the request we queued behind may
            // have finished in between, and its answer is the one owed.
            $seen = $this->read($cacheKey, $fingerprint);

            if ($seen !== null) {
                return [$seen, true];
            }

            $payload = $create();

            // Only a success is remembered, so a refused create stays
            // retryable with the same key.
            Cache::put($cacheKey, ['fingerprint' => $fingerprint, 'payload' => $payload], (int) config('mcp-connector.idempotency_ttl_seconds', 86400));

            return [$payload, false];
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws IdempotencyConflict
     */
    private function read(string $cacheKey, string $fingerprint): ?array
    {
        $record = Cache::get($cacheKey);

        if (! is_array($record) || ! isset($record['fingerprint'], $record['payload'])) {
            return null;
        }

        if (! hash_equals((string) $record['fingerprint'], $fingerprint)) {
            throw new IdempotencyConflict('This idempotency key was already used for a different request.');
        }

        return is_array($record['payload']) ? $record['payload'] : null;
    }
}
