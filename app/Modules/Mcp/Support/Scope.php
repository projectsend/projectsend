<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Support;

use Laravel\Sanctum\PersonalAccessToken;

/**
 * What the credential on this request lets the assistant do — the scope,
 * which only ever subtracts. The ceiling is the person's live permission,
 * checked by each tool against the app's own policies.
 *
 * Three shapes of credential arrive at /mcp, all as Sanctum tokens:
 *
 *   - an OAuth grant to a client on the shared instance: `mcp:own`;
 *   - an OAuth grant to a staff member: `mcp:read` or `mcp:write`;
 *   - a personal API token minted on Settings → API tokens, whose
 *     abilities are permission keys rather than scopes (plan decision 7).
 *
 * The first two never prune `tools/list`: a read-only grant sees the
 * write tools and is told how to reconnect (decision 16, and §8 of the
 * spec). The third does prune, because an API token's abilities are not
 * something a conversation can change.
 */
final class Scope
{
    public const OWN = 'mcp:own';

    public const READ = 'mcp:read';

    public const WRITE = 'mcp:write';

    /**
     * `laravel/mcp` advertises this one and some clients ask for it
     * blindly. Accepted, and treated as whatever was actually granted.
     */
    public const USE = 'mcp:use';

    /**
     * Which permission keys make an API token good for a tool. Any one of
     * the listed abilities is enough, matching how the app's own API
     * routes treat own/others permission pairs. Spec §8, last column.
     *
     * @var array<string, list<string>>
     */
    private const API_ABILITIES = [
        'get_usage' => [],
        'list_files' => ['upload', 'edit_files', 'edit_others_files'],
        'get_file' => ['upload', 'edit_files', 'edit_others_files'],
        'get_web_upload_link' => ['upload'],
        'get_download_status' => ['view_actions_log'],
        'create_upload' => ['upload'],
        'complete_upload' => ['upload'],
        'create_file_from_content' => ['upload'],
        'share_by_link' => ['edit_files', 'edit_others_files'],
        'revoke_link' => ['edit_files', 'edit_others_files'],
        'delete_file' => ['delete_files', 'delete_others_files'],
        'list_clients' => ['manage_clients'],
        'get_client' => ['edit_clients'],
        'create_client' => ['create_clients'],
        'send_file_to_client' => ['edit_files', 'edit_others_files'],
        'list_folders' => ['upload', 'edit_files', 'edit_others_files'],
        'create_folder' => ['create_own_folders'],
        'move_file' => ['edit_files', 'edit_others_files'],
        'share_folder_with_client' => ['edit_files', 'edit_others_files'],
    ];

    /** The tools a read-only grant may not call. */
    private const WRITES = [
        'create_upload', 'complete_upload', 'create_file_from_content',
        'share_by_link', 'revoke_link', 'delete_file',
        'create_client', 'send_file_to_client',
        'create_folder', 'move_file', 'share_folder_with_client',
    ];

    /**
     * The tools a client on the shared instance is gated out of: listed,
     * and answered with what a portal of their own adds (decisions 16 and
     * 17). Client accounts for the first four, folders for the rest.
     */
    public const CLIENT_TOOLS = [
        'list_clients', 'get_client', 'create_client', 'send_file_to_client',
        'list_folders', 'create_folder', 'move_file', 'share_folder_with_client',
    ];

    /**
     * @param  list<string>  $abilities  the token's abilities, verbatim
     */
    private function __construct(
        public readonly string $kind,
        private readonly array $abilities,
    ) {}

    /**
     * From the abilities on the token that authenticated this request.
     *
     * @param  list<string>  $abilities
     */
    public static function fromAbilities(array $abilities): self
    {
        if (in_array(self::OWN, $abilities, true)) {
            return new self('own', $abilities);
        }

        if (in_array(self::WRITE, $abilities, true)) {
            return new self('write', $abilities);
        }

        if (in_array(self::READ, $abilities, true) || in_array(self::USE, $abilities, true)) {
            return new self('read', $abilities);
        }

        return new self('api', $abilities);
    }

    /**
     * Whether a token is one an AI assistant connection holds, rather than
     * a personal API token. The two never mix: the token screen only
     * offers permission keys, and a connection only ever carries these
     * scopes.
     */
    public static function isConnectorToken(mixed $token): bool
    {
        if (! $token instanceof PersonalAccessToken) {
            return false;
        }

        $abilities = array_values(array_filter((array) $token->abilities, 'is_string'));

        return ! self::fromAbilities($abilities)->isApiToken();
    }

    public function isOwn(): bool
    {
        return $this->kind === 'own';
    }

    public function isApiToken(): bool
    {
        return $this->kind === 'api';
    }

    /**
     * Whether this credential may call the tool at all — the scope half
     * of the check, before the person's live permission is asked. Null
     * when it may; the reason when it may not.
     *
     * @return 'scope_insufficient'|'permission_missing'|null
     */
    public function refuses(string $tool): ?string
    {
        return match ($this->kind) {
            'own', 'write' => null,
            'read' => in_array($tool, self::WRITES, true) ? 'scope_insufficient' : null,
            default => $this->apiTokenAllows($tool) ? null : 'permission_missing',
        };
    }

    /**
     * The permission an API token would need for the tool, for the
     * `permission_missing` error to name. The first of the alternatives:
     * the one a person is most likely to hold.
     */
    public function permissionFor(string $tool): string
    {
        return self::API_ABILITIES[$tool][0] ?? $tool;
    }

    /**
     * Whether the tool appears in `tools/list` for this credential. Only
     * an API token prunes; every grant lists them all.
     */
    public function lists(string $tool): bool
    {
        return ! $this->isApiToken() || $this->apiTokenAllows($tool);
    }

    private function apiTokenAllows(string $tool): bool
    {
        if (in_array('*', $this->abilities, true)) {
            return true;
        }

        $needed = self::API_ABILITIES[$tool] ?? null;

        if ($needed === null) {
            return false;
        }

        if ($needed === []) {
            return true;
        }

        return array_intersect($needed, $this->abilities) !== [];
    }
}
