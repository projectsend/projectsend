<?php

declare(strict_types=1);

namespace App\Modules\Groups\Models;

use App\Models\User;
use App\Modules\Identity\UserType;
use App\Support\Concerns\HasUniqueSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A group of clients files can be assigned to collectively. Membership
 * is clients-only, enforced at the application layer.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $public
 * @property-read int $members_count
 */
class Group extends Model
{
    use HasUniqueSlug;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'public' => 'boolean',
        ];
    }

    protected static function slugFallback(): string
    {
        return 'group';
    }

    /**
     * The clients in this group.
     *
     * Filtered to clients rather than trusting the pivot to only ever hold
     * them. Membership is a clients-only concept enforced at the
     * application layer, so for most of this app's life the filter is a
     * no-op — but an account can change type (see AccountConversion), and
     * a staff member left in a group is not merely untidy: this relation
     * is what FileSharing::recipients(), ResolvesShareTargets and
     * FileVersions expand a group into, so a stale row would keep sending
     * "a file was shared with you" to somebody who is no longer a
     * recipient of anything.
     *
     * Rows are deliberately left in place when an account converts, so
     * converting back restores the membership; this is what makes them
     * genuinely inert in the meantime.
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_members')
            ->where('users.type', UserType::Client)
            ->withTimestamps();
    }
}
