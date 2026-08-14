<?php

declare(strict_types=1);

namespace App\Modules\Identity\Social;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A provider identity bound to a local account.
 *
 * This row *is* the authorization to sign in as that account. It is
 * created deliberately — from the Connected accounts screen, or once by
 * the resolver when the provider vouches for a verified address — and
 * never inferred at sign-in time. That distinction is the whole
 * difference between this and v1, which re-derived the match from an
 * unverified email on every login.
 *
 * @property int $user_id
 * @property SocialProvider $provider
 * @property string $provider_user_id
 * @property string|null $email
 * @property-read User $user
 */
class SocialAccount extends Model
{
    protected $table = 'social_accounts';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'provider' => SocialProvider::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The account this identity signs in as, or null.
     *
     * The subject, never the email. A provider that lets somebody change
     * their address — most of them — must not thereby change which
     * account they land on.
     */
    public static function resolve(SocialIdentity $identity): ?User
    {
        return static::query()
            ->where('provider', $identity->provider->value)
            ->where('provider_user_id', $identity->subject)
            ->first()?->user;
    }
}
