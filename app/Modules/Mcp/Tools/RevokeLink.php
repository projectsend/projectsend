<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools;

use App\Modules\Audit\Action;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\ShareLink;
use App\Modules\Mcp\Support\Account;
use App\Modules\Mcp\Support\Audit;
use App\Modules\Mcp\Support\Envelope;
use App\Modules\Mcp\Support\Files;
use App\Modules\Mcp\Support\Presenter;
use App\Modules\Mcp\Support\ToolError;
use App\Modules\Mcp\Support\Web;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('revoke_link')]
#[Title('Revoke a link')]
#[Description('Stops a private link immediately: the URL stops working for everyone who has it. Use it when the person wants to take a link back or sent it to the wrong person. It does not delete the file or affect client accounts the file is shared with; share_by_link mints a fresh link afterwards if needed. Destructive: the client will ask the person to confirm before this runs, so call it once. Revoking a link that is already revoked returns success.')]
#[IsDestructive]
class RevokeLink extends ToolBase
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'link_id' => $schema->integer()->min(1)->description('From get_file, list_files or share_by_link. Give this or link_url.'),
            'link_url' => $schema->string()->max(2048)->description('The full URL, if that is what you have.'),
        ];
    }

    protected function run(Request $request, Account $account): Response|ResponseFactory|ToolError
    {
        $validated = $request->validate([
            'link_id' => ['nullable', 'integer', 'min:1'],
            'link_url' => ['nullable', 'string', 'max:2048'],
        ]);

        if (! isset($validated['link_id']) && ! isset($validated['link_url'])) {
            return ToolError::validationFailed(['link_id' => ['Give link_id or link_url.']]);
        }

        $link = self::findLink($validated, $account);

        if ($link === null) {
            return ToolError::notFound('link', action: 'Nothing to do: there is no such link in this account. A link that was already revoked no longer exists.');
        }

        $file = Files::find($account, (int) $link->getAttribute('shareable_id'));

        if ($file === null) {
            return ToolError::notFound('link', action: 'Nothing to do: there is no such link in this account.');
        }

        Files::requireUpdate($file, $account);

        $presented = Presenter::link($link);
        $link->delete();

        Audit::log(Action::ShareLinkRevoked, $account, $file);

        $fileId = (int) $file->getAttribute('id');

        return Envelope::success(
            [
                'revoked' => true,
                'already_revoked' => false,
                'link' => ['id' => $presented['id'], 'url' => $presented['url'], 'revoked_at' => now()->toIso8601String()],
                'file' => ['id' => $fileId, 'name' => (string) $file->getAttribute('name')],
            ],
            Web::file($account, $fileId),
            "Tell the person the link no longer works. If they need a new one, share_by_link with file_id {$fileId}.",
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private static function findLink(array $validated, Account $account): ?ShareLink
    {
        $query = ShareLink::query()->where('shareable_type', (new File)->getMorphClass());

        if (isset($validated['link_id'])) {
            $query->whereKey((int) $validated['link_id']);
        } else {
            $token = self::tokenFromUrl((string) $validated['link_url']);

            if ($token === null) {
                return null;
            }

            $query->where('token', $token);
        }

        $link = $query->first();

        if ($link === null) {
            return null;
        }

        // A link of a file this account cannot see is, to this account,
        // no link at all.
        return Files::find($account, (int) $link->getAttribute('shareable_id')) === null ? null : $link;
    }

    private static function tokenFromUrl(string $url): ?string
    {
        $path = parse_url(trim($url), PHP_URL_PATH);

        if (! is_string($path)) {
            return null;
        }

        $segments = array_values(array_filter(explode('/', $path), static fn (string $s): bool => $s !== ''));

        // `/s/{token}` or `/s/{token}/download`.
        $at = array_search('s', $segments, true);

        if ($at === false || ! isset($segments[$at + 1])) {
            return null;
        }

        $token = $segments[$at + 1];

        return preg_match('/^[A-Za-z0-9_-]{12,64}$/', $token) === 1 ? $token : null;
    }
}
