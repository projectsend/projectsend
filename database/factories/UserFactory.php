<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Identity\Permissions\EnsureSystemRoles;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Identity\UserType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => UserType::Staff,
            'active' => true,
            'account_requested' => false,
            'role_id' => fn (): ?int => self::roleId(SystemRole::SystemAdministrator),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * A client account — the recipient files are shared with.
     */
    public function client(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => UserType::Client,
            'role_id' => self::roleId(SystemRole::Client),
        ]);
    }

    /**
     * A self-registered client awaiting approval.
     */
    public function pendingClient(): static
    {
        return $this->client()->state(fn (array $attributes) => [
            'active' => false,
            'account_requested' => true,
        ]);
    }

    /**
     * A staff account with one of the built-in roles.
     */
    public function role(SystemRole $role): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $role === SystemRole::Client ? UserType::Client : UserType::Staff,
            'role_id' => self::roleId($role),
        ]);
    }

    private static function roleId(SystemRole $role): ?int
    {
        // Materialize on demand so tests can use any SystemRole — including
        // the legacy Uploader, which fresh installs no longer seed.
        return app(EnsureSystemRoles::class)->materialize($role)->id;
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
