<?php

declare(strict_types=1);

namespace App\Modules\Api\Auth;

use App\Models\User;
use App\Modules\Identity\Permissions\Permission;
use Illuminate\Support\Collection;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * What tokens an account holds, for screens that describe somebody
 * *else's* — the staff list's indicator and the API tab on a staff
 * account.
 *
 * Read-only on purpose. ApiTokensController deliberately scopes every
 * mutation to the caller's own tokens ("managing them is not an
 * administrative power here"), and nothing in this class widens that:
 * an administrator can see that an integration exists and what it is
 * allowed to do, which is their installation's security posture, but
 * renaming, re-scoping or revoking somebody's token is still only the
 * owner's to do. No secret is exposed either way — the database holds a
 * hash.
 */
class ApiTokens
{
    public function __construct(
        private readonly TokenAbilities $abilities,
    ) {}

    /**
     * A token is "active" when it has not expired. A null expiry means it
     * never does.
     *
     * The single definition of the word: it was written out by hand in
     * ApiTokensController and again in ApiUsage before this, and adding a
     * third copy for the staff list is what prompted collecting it here.
     */
    public static function isActive(PersonalAccessToken $token): bool
    {
        return $token->expires_at === null || $token->expires_at->isFuture();
    }

    /**
     * Totals for one account.
     *
     * @return array{total: int, active: int}
     */
    public function summarize(User $user): array
    {
        return $this->summarizeMany([$user->id])[$user->id] ?? ['total' => 0, 'active' => 0];
    }

    /**
     * Totals for a page of accounts, in one query — a listing that asked
     * per row would be an N+1 on a screen that already paginates 25 at a
     * time.
     *
     * Counted in the database rather than by loading the rows: nothing
     * here needs a token's name or abilities, only how many there are.
     * Expiry is compared in SQL for the same reason.
     *
     * @param  iterable<int>  $userIds
     * @return array<int, array{total: int, active: int}>
     */
    public function summarizeMany(iterable $userIds): array
    {
        $ids = Collection::make($userIds)->map(fn ($id): int => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->whereIn('tokenable_id', $ids)
            ->selectRaw('tokenable_id')
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when expires_at is null or expires_at > ? then 1 else 0 end) as active', [now()])
            ->groupBy('tokenable_id')
            ->get()
            ->mapWithKeys(fn (PersonalAccessToken $row): array => [
                (int) $row->getAttribute('tokenable_id') => [
                    'total' => (int) $row->getAttribute('total'),
                    'active' => (int) $row->getAttribute('active'),
                ],
            ])
            ->all();
    }

    /**
     * Every token an account holds, with its abilities resolved to the
     * labels the permission screens use — bare keys like
     * `edit_others_files` are not what an administrator should have to
     * read to answer "what can this integration do".
     *
     * @return list<array<string, mixed>>
     */
    public function detailFor(User $user): array
    {
        $stillGranted = $this->abilities->availableFor($user);

        return array_values($user->tokens()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PersonalAccessToken $token): array => [
                'id' => (string) $token->getKey(),
                'name' => $token->name,
                'active' => self::isActive($token),
                'created_at' => $token->created_at?->toIso8601String(),
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
                // array_values because Sanctum casts the column straight
                // from JSON, so nothing guarantees the keys are sequential.
                'abilities' => $this->describeAbilities(array_values($token->abilities ?? []), $stillGranted),
            ])
            ->all());
    }

    /**
     * @param  list<string>  $keys
     * @param  list<string>  $stillGranted
     * @return list<array{key: string, label: string, category: string, effective: bool}>
     */
    private function describeAbilities(array $keys, array $stillGranted): array
    {
        $described = [];

        foreach ($keys as $key) {
            $permission = Permission::tryFrom($key);

            $described[] = [
                'key' => $key,
                // An unrecognised key is still worth showing rather than
                // hiding: it means the token carries something this
                // vocabulary no longer has, which is exactly the sort of
                // leftover an administrator is looking at this list for.
                'label' => $permission?->label() ?? $key,
                'category' => $permission?->category()->label() ?? '',
                // Whether it does anything today. A token keeps the
                // abilities it was issued with, but EnsureTokenCan
                // re-checks the owner's live permissions on every
                // request, so an ability the owner has since lost is
                // carried and ignored.
                'effective' => in_array($key, $stillGranted, true),
            ];
        }

        return $described;
    }
}
