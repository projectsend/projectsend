<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Renames `$2a$` and `$2b$` password digests to `$2y$`.
 *
 * All three name the same algorithm, and `password_verify()` reads any of
 * them. Laravel's bcrypt driver does not get that far: it first asks
 * `password_get_info()`, which answers "unknown" for `$2a$` and `$2b$`,
 * and throws `RuntimeException: This password does not use the Bcrypt
 * algorithm.` before it looks at the password at all. The sign-in form
 * renders that as a 500.
 *
 * v1 wrote `$2y$`, so a ProjectSend that has only ever been v2 has
 * nothing here to fix. The rows that need it come from installations
 * migrated from a v1 whose PHP or platform emitted one of the other
 * labels — reported as projectsend/projectsend#1706, where every migrated
 * account got an error page instead of a login. The v1 migration tool now
 * relabels on import; this repairs the installations that were migrated
 * before it did.
 *
 * Only the four label bytes change. The salt and the digest after them
 * are left exactly as they were, so everybody keeps the password they
 * already had — there is no reset mail and nothing for an operator to do.
 *
 * `$2x$` is deliberately left alone. It is not another spelling of
 * `$2y$`: it asks for the old, broken handling of bytes above 127 to be
 * reproduced on purpose, so renaming it would lock out anybody whose
 * password is not plain ASCII. Such a row stays as it is and keeps
 * erroring — which is visible, unlike a password that quietly stopped
 * working.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where(function ($query): void {
                $query->where('password', 'like', '$2a$%')
                    ->orWhere('password', 'like', '$2b$%');
            })
            // Chunked because a migrated installation can carry a lot of
            // these, and each row is rewritten from its own value.
            ->orderBy('id')
            ->chunkById(500, function (Collection $rows): void {
                foreach ($rows as $row) {
                    $relabelled = '$2y$'.substr((string) $row->password, 4);

                    // Belt and braces: a truncated or otherwise malformed
                    // digest would still read as "unknown" after the
                    // rename, so rewriting it would achieve nothing and
                    // only make a broken row harder to recognise. Write
                    // only where the rename actually produces something
                    // the hasher will read.
                    if (password_get_info($relabelled)['algoName'] !== 'bcrypt') {
                        continue;
                    }

                    DB::table('users')
                        ->where('id', $row->id)
                        ->update(['password' => $relabelled]);
                }
            });
    }

    /**
     * Deliberately does not put the old labels back. They named the same
     * algorithm, so nothing is lost by keeping the new one, and restoring
     * them would hand the 500 back to every account this repaired.
     */
    public function down(): void {}
};
