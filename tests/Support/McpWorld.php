<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Models\ShareLink;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Mcp\Events\ResolvingConnectorPlan;
use App\Modules\Mcp\ProjectSendServer;
use App\Modules\Mcp\Support\McpContext;
use Closure;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Mcp\Server\Testing\TestResponse;

/**
 * The MCP tests' shared vocabulary: the accounts that call the tools,
 * the things they act on, a way to call a tool and read back the JSON it
 * answered, and the hosted edition's free plan, stood in for by a
 * listener on ResolvingConnectorPlan.
 */
final class McpWorld
{
    /**
     * The plain text of each token minted here, by user id, for the
     * tests that send it as a bearer token over HTTP.
     *
     * @var array<int, string>
     */
    public static array $plainTokens = [];

    public const ALL_STAFF_PERMISSIONS = [
        'upload', 'edit_files', 'edit_others_files', 'delete_files', 'delete_others_files',
        'manage_clients', 'create_clients', 'edit_clients', 'view_actions_log',
        'set_file_expiration_date', 'limit_downloads', 'create_own_folders',
    ];

    /**
     * A staff member holding the given permissions, carrying a real
     * Sanctum token whose abilities say what the connection may do.
     *
     * @param  list<string>  $permissions
     * @param  list<string>  $tokenAbilities
     */
    public static function staff(array $permissions = self::ALL_STAFF_PERMISSIONS, array $tokenAbilities = ['mcp:write'], string $tokenName = 'AI assistant · Claude'): User
    {
        $user = User::factory()->create([
            'name' => 'Ada Lovelace',
            'role_id' => self::role($permissions)->id,
        ]);

        return self::withToken($user, $tokenName, $tokenAbilities);
    }

    /**
     * A client of the free plan: the installation says clients may
     * connect (see freePlan()), and this one carries an `mcp:own` grant.
     */
    public static function client(string $email = 'grace@example.com', string $name = 'Grace Hopper'): User
    {
        self::freePlan();

        $user = User::factory()->client()->create([
            'name' => $name,
            'email' => $email,
            'role_id' => self::role(['upload', 'edit_files', 'delete_files'])->id,
        ]);

        return self::withToken($user, 'AI assistant · Claude', ['mcp:own']);
    }

    /** A client account created on the web: somebody files are sent to. */
    public static function clientAccount(string $email = 'ops@acme.example', string $name = 'Acme Ltd'): User
    {
        return User::factory()->client()->create(['name' => $name, 'email' => $email]);
    }

    /**
     * Make a staff member client-scoped: their role reaches only their
     * own uploads and the clients assigned to them, these.
     */
    public static function scopeTo(User $staff, User ...$clients): void
    {
        $staff->role?->forceFill(['client_scoped' => true])->save();
        $staff->assignedClients()->syncWithoutDetaching(array_map(fn (User $client): int => $client->id, $clients));
        $staff->unsetRelation('role')->unsetRelation('assignedClients');

        // StaffLibraryScope keeps what it worked out for the request.
        app()->forgetScopedInstances();
    }

    /**
     * Answer ResolvingConnectorPlan the way the hosted edition does on its
     * shared instance: clients may connect, they are a free plan, and the
     * way out of it is a paid plan with a sign-up link.
     *
     * @param  (Closure(ResolvingConnectorPlan): void)|null  $more
     */
    public static function freePlan(?Closure $more = null): void
    {
        Event::listen(ResolvingConnectorPlan::class, static function (ResolvingConnectorPlan $event) use ($more): void {
            $event->clientsMayConnect = true;
            $event->upgradePlan = ['code' => 'pro', 'name' => 'Pro', 'price' => 'from $19/month'];
            $event->upgradeAdds = ['client accounts and team seats', 'links with an expiry date or a download cap'];
            $event->upgradeNote = 'We move their files across when they upgrade.';
            $event->upgradeUrl = static fn (string $tool, string $connector): string => "https://portal.example/register?plan=pro&utm_content={$tool}&mcp_client={$connector}";
            $event->plansUrl = static fn (string $tool, string $connector): string => "https://example.com/pricing?utm_content={$tool}";
            $event->portalUrl = 'https://portal.example/';

            if ($more !== null) {
                $more($event);
            }
        });

        // The answer is kept for the request; a test that changes it
        // mid-way starts the next call cold.
        app()->forgetScopedInstances();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function file(User $owner, array $attributes = []): File
    {
        return File::factory()->create(array_merge([
            'name' => 'informe-q3',
            'original_name' => 'informe-q3.pdf',
            'mime_type' => 'application/pdf',
            'size' => 3355443,
            'checksum' => str_repeat('a', 64),
            'uploaded_by' => $owner->id,
        ], $attributes));
    }

    /**
     * A folder of the staff library, inside $parent when one is given,
     * made by the real service so its materialized path holds.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function folder(string $name, ?Folder $parent = null, ?User $creator = null, array $attributes = []): Folder
    {
        $folder = makeFolder($name, $parent);

        $folder->forceFill(array_merge(['created_by' => $creator?->id], $attributes))->save();

        return $folder;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function link(File $file, array $attributes = []): ShareLink
    {
        return ShareLink::query()->create(array_merge([
            'shareable_type' => $file->getMorphClass(),
            'shareable_id' => $file->id,
            'token' => Str::random(32),
            'created_by' => $file->uploaded_by,
            'downloads_count' => 0,
        ], $attributes));
    }

    /**
     * Call a tool as the user and get the JSON it answered, success or
     * error, as an array.
     *
     * @param  class-string  $tool
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public static function call(string $tool, array $arguments, User $user): array
    {
        McpContext::set(null);

        // The server takes the token off the user for the request (see
        // ProjectSendServer::boot); a real request resolves a fresh user
        // from the token each time, so the test puts it back afterwards.
        $token = $user->currentAccessToken();

        $response = ProjectSendServer::actingAs($user)->tool($tool, $arguments);

        $user->withAccessToken($token);
        McpContext::set(null);

        $text = self::firstText($response);
        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : ['raw' => $text];
    }

    /**
     * The `error` object of an answer, or null when it succeeded.
     *
     * @param  array<string, mixed>  $answer
     * @return array<string, mixed>|null
     */
    public static function error(array $answer): ?array
    {
        return isset($answer['error']) && is_array($answer['error']) ? $answer['error'] : null;
    }

    /**
     * @param  list<string>  $abilities
     */
    private static function withToken(User $user, string $name, array $abilities): User
    {
        $token = $user->createToken($name, $abilities);
        self::$plainTokens[$user->id] = $token->plainTextToken;

        return $user->withAccessToken($token->accessToken);
    }

    /**
     * @param  list<string>  $permissions
     */
    private static function role(array $permissions): Role
    {
        $role = Role::query()->create(['name' => 'Role '.Str::random(6)]);

        foreach ($permissions as $permission) {
            RolePermission::query()->create(['role_id' => $role->id, 'permission' => $permission]);
        }

        return $role;
    }

    private static function firstText(TestResponse $response): string
    {
        // The testing response keeps the tool's content behind a
        // protected method; read it the way its own assertions do.
        $reader = Closure::bind(function (): array {
            /** @var array<int, string> $content */
            $content = $this->content();

            if ($content !== []) {
                return $content;
            }

            // No content: the server refused the call itself, as a
            // JSON-RPC error — a tool the token cannot see, for one.
            /** @var array<int, string> $errors */
            $errors = $this->errors();

            return [json_encode(['error' => ['code' => 'jsonrpc', 'message' => $errors[0] ?? 'unknown']])];
        }, $response, $response::class);

        $content = $reader();

        return (string) ($content[0] ?? json_encode(['raw' => 'no text content']));
    }
}
