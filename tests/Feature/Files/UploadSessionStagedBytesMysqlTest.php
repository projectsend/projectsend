<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Uploads\UploadSession;
use Illuminate\Support\Facades\DB;

/**
 * The part of GHSA-6jh6-gvj5-pv8v's fix the ordinary suite cannot see.
 *
 * A session's staged-byte total is a running count that goes up when a
 * part is claimed and down when the part turns out smaller, is replaced,
 * or never arrives. The column is `BIGINT UNSIGNED`, and on MySQL that
 * type does not clamp: an expression that would go below zero raises
 * SQLSTATE 22003 — and it raises it in a `WHERE` as readily as in a `SET`,
 * so the bound written to prevent the underflow is itself the statement
 * that underflows.
 *
 * **SQLite has no unsigned integers.** Every one of these cases passes
 * there whether the arithmetic is arranged correctly or not, which is why
 * this file skips loudly instead of passing quietly.
 *
 * To run it:
 *
 *   docker compose exec -T -e DB_CONNECTION=mysql -e DB_HOST=db \
 *     -e DB_DATABASE=staged_bytes_check -e DB_USERNAME=root -e DB_PASSWORD=root \
 *     app vendor/bin/pest tests/Feature/Files/UploadSessionStagedBytesMysqlTest.php
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        test()->markTestSkipped('Needs MySQL: SQLite has no unsigned integers and cannot show an underflow.');
    }

    $this->session = UploadSession::query()->create([
        'user_id' => User::factory()->create()->id,
        'original_name' => 'staged.zip',
        'size' => 100,
    ]);
});

function stagedBytes(): int
{
    return (int) UploadSession::query()->findOrFail(test()->session->id)->staged_bytes;
}

test('the column really is unsigned', function () {
    // Asserted rather than assumed: everything below only means something
    // if the column in use is the one that cannot go negative.
    // information_schema rather than SHOW COLUMNS: the latter takes no
    // bound parameter for its LIKE, and interpolating a table name into a
    // query is not a habit worth keeping for a test's convenience.
    $type = DB::selectOne(
        'select column_type as type from information_schema.columns
         where table_schema = database() and table_name = ? and column_name = ?',
        ['upload_sessions', 'staged_bytes'],
    )->type ?? '';

    expect(strtolower((string) $type))->toContain('unsigned');
});

test('a session fills to its declared size and no further', function () {
    expect($this->session->reserveStaged(60))->toBeTrue()
        ->and(stagedBytes())->toBe(60);

    expect($this->session->reserveStaged(60))->toBeFalse()
        ->and(stagedBytes())->toBe(60);

    expect($this->session->reserveStaged(40))->toBeTrue()
        ->and(stagedBytes())->toBe(100);

    expect($this->session->reserveStaged(1))->toBeFalse()
        ->and(stagedBytes())->toBe(100);
});

test('a claim larger than the whole session is refused rather than attempted', function () {
    // The upper bound is rearranged to `staged_bytes <= size - delta`, and
    // that rearrangement is only valid while the right-hand side is not
    // negative. Without this early exit a claim of 400 against a session of
    // 100 compares against 0, which an empty session satisfies.
    expect($this->session->reserveStaged(400))->toBeFalse()
        ->and(stagedBytes())->toBe(0);
});

test('a refund larger than what is held changes nothing instead of erroring', function () {
    $this->session->reserveStaged(40);

    // The shape that raised SQLSTATE 22003: settling a 100-byte claim that
    // was never fully counted. It has to be a refusal, not an exception —
    // this runs in putPart()'s finally, where a throw would replace the
    // real response with a 500.
    $this->session->settleStaged(100, 0);

    expect(stagedBytes())->toBe(40);
});

test('a refund of exactly what is held empties the session', function () {
    $this->session->reserveStaged(40);
    $this->session->settleStaged(40, 0);

    expect(stagedBytes())->toBe(0);

    // And the room really is free again, not merely reported as zero.
    expect($this->session->reserveStaged(100))->toBeTrue()
        ->and(stagedBytes())->toBe(100);
});
