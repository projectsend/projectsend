<?php

declare(strict_types=1);

namespace App\Modules\Mcp\OAuth;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Sanctum's personal access tokens, minted and deleted the way the API
 * tokens screen does it (design §1).
 */
final class Tokens
{
    public const TABLE = 'personal_access_tokens';

    /**
     * @param  list<string>  $abilities
     * @return array{id: int, plain: string}
     */
    public static function mint(User $user, string $name, array $abilities, CarbonInterface $expiresAt): array
    {
        $minted = $user->createToken($name, $abilities, $expiresAt);

        return [
            'id' => (int) $minted->accessToken->getKey(),
            'plain' => (string) $minted->plainTextToken,
        ];
    }

    public static function delete(?int $id): void
    {
        if ($id !== null) {
            DB::table(self::TABLE)->where('id', $id)->delete();
        }
    }

    /**
     * The row a plain token names, if it names one: Sanctum's shape is
     * `{id}|{secret}` with the secret stored as its SHA-256.
     */
    public static function idOf(string $plain): ?int
    {
        [$id, $secret] = array_pad(explode('|', $plain, 2), 2, null);

        if ($id === null || $secret === null || ! ctype_digit($id)) {
            return null;
        }

        $row = DB::table(self::TABLE)->where('id', (int) $id)->where('token', hash('sha256', $secret))->first();

        return $row === null ? null : (int) $id;
    }
}
