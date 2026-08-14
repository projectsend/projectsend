<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\AuthSource;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Notifications\ResetPasswordNotification;
use App\Modules\Identity\UserType;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property UserType $type
 * @property AuthSource $auth_source
 * @property string|null $ldap_dn
 * @property Carbon|null $ldap_synced_at
 * @property int|null $role_id
 * @property bool $active
 * @property bool $account_requested
 * @property string|null $locale
 * @property string|null $timezone
 * @property int|null $dashboard_columns
 * @property int $storage_quota_mb
 * @property Carbon|null $erase_after
 * @property-read Role|null $role
 */
class User extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'role_id',
        'active',
        'account_requested',
        'name',
        'email',
        'password',
        'locale',
        'timezone',
        'dashboard_columns',
        'storage_quota_mb',
    ];

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Groups this account belongs to (clients only in practice).
     *
     * @return BelongsToMany<Group, $this>
     */
    public function memberOfGroups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_members')->withTimestamps();
    }

    /**
     * The clients a client-scoped staff member manages. Their library
     * scope (what they see and may share) derives from this list.
     *
     * @return BelongsToMany<User, $this>
     */
    public function assignedClients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'staff_client_assignments', 'staff_id', 'client_id')->withTimestamps();
    }

    /**
     * A staff member whose role restricts them to their assigned clients'
     * library content (plus their own uploads).
     */
    public function isClientScoped(): bool
    {
        return $this->isStaff() && $this->role?->client_scoped === true;
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    public function isStaff(): bool
    {
        return $this->type === UserType::Staff;
    }

    public function isClient(): bool
    {
        return $this->type === UserType::Client;
    }

    public function preferredLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    /**
     * The column default only applies on INSERT, so it never reaches an
     * instance the database did not just hand back — and code that asks
     * "does this account have a password of its own?" would then read
     * null and answer wrongly. This makes `local` the answer everywhere.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'auth_source' => 'local',
    ];

    protected function casts(): array
    {
        return [
            'type' => UserType::class,
            // Deliberately absent from $fillable: where an account's
            // credentials live is a security decision, not an attribute a
            // form or an API payload may set. Written with forceFill by
            // the code that provisions the account.
            'auth_source' => AuthSource::class,
            'ldap_synced_at' => 'datetime',
            'active' => 'boolean',
            'account_requested' => 'boolean',
            'erase_after' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
