<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools;

use App\Models\User;
use App\Modules\Files\Models\ShareLink;
use App\Modules\Groups\Models\Group;
use App\Modules\Mcp\Support\Account;
use App\Modules\Mcp\Support\Envelope;
use App\Modules\Mcp\Support\Files;
use App\Modules\Mcp\Support\Presenter;
use App\Modules\Mcp\Support\ToolError;
use App\Modules\Mcp\Support\Web;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_file')]
#[Title('File details')]
#[Description('Returns everything about one file: its metadata, every active private link, who it is shared with (staff accounts), its version chain, and a download summary. Use it when the person asks about a specific file, before revoking a link (to pick the right one), or to confirm a file exists before sending it. Do not use it to list files (list_files) or for the download history in detail (get_download_status). If the file is a new version of another, the response names the original: sharing happens on that one.')]
#[IsReadOnly]
class GetFile extends ToolBase
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'file_id' => $schema->integer()->min(1)->required()->description("The file's id, from list_files or from a previous response."),
        ];
    }

    protected function run(Request $request, Account $account): Response|ResponseFactory|ToolError
    {
        $validated = $request->validate(['file_id' => ['required', 'integer', 'min:1']]);

        Files::requireLibraryAccess($account);

        $file = Files::find($account, (int) $validated['file_id']);

        if ($file === null) {
            return ToolError::notFound('file');
        }

        $id = (int) $file->getAttribute('id');
        $links = array_values(array_filter(Files::links($file, $account), fn (ShareLink $link): bool => $link->isActive()));
        $downloads = self::downloads($file);

        $uploader = $file->uploader;
        $rootId = $file->getAttribute('version_root_id');

        // The folder comes with Presenter::file, for staff only.
        $detail = Presenter::file($file, $account) + [
            'original_name' => $file->getAttribute('original_name'),
            'checksum' => $file->getAttribute('checksum') === null ? null : 'sha256:'.$file->getAttribute('checksum'),
            'uploaded_by' => $uploader !== null ? ['name' => $uploader->name] : null,
            'is_revision' => $rootId !== null,
            'sharing_root_id' => $rootId === null ? null : (int) $rootId,
            'previous_version' => self::version($file, 'previousVersion'),
            'next_version' => self::version($file, 'nextVersion'),
        ];

        $nextSteps = $links === []
            ? "To hand it over, call share_by_link with file_id {$id}. To see who downloaded and when, call get_download_status with file_id {$id}."
            : sprintf('To stop a link, call revoke_link with its id. To see who downloaded and when, call get_download_status with file_id %d.', $id);

        return Envelope::success(
            [
                'file' => $detail,
                'links' => array_map(fn (Model $link): array => Presenter::link($link), $links),
                'shared_with' => $account->isClient() ? null : self::sharedWith($file),
                'downloads' => $downloads,
            ],
            Web::file($account, $id),
            $nextSteps,
        );
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private static function version(Model $file, string $relation): ?array
    {
        if (! method_exists($file, $relation)) {
            return null;
        }

        $related = $file->getRelationValue($relation);

        return $related instanceof Model
            ? ['id' => (int) $related->getAttribute('id'), 'name' => (string) $related->getAttribute('name')]
            : null;
    }

    /**
     * @return array{total: int, last_at: string|null}
     */
    private static function downloads(Model $file): array
    {
        if (! method_exists($file, 'downloads')) {
            return ['total' => 0, 'last_at' => null];
        }

        $last = $file->downloads()->orderByDesc('created_at')->first();

        return [
            'total' => (int) $file->downloads()->count(),
            'last_at' => $last instanceof Model ? Presenter::iso($last->getAttribute('created_at')) : null,
        ];
    }

    /**
     * Client accounts and groups the file is assigned to, by name.
     *
     * @return array{clients: list<array{id: int, name: string}>, groups: list<array{id: int, name: string}>}
     */
    private static function sharedWith(Model $file): array
    {
        $clients = [];
        $groups = [];

        if (! method_exists($file, 'assignments')) {
            return ['clients' => [], 'groups' => []];
        }

        foreach ($file->assignments()->get() as $assignment) {
            /** @var Model $assignment */
            $type = (string) $assignment->getAttribute('assignable_type');
            $targetId = (int) $assignment->getAttribute('assignable_id');
            $isGroup = str_contains(strtolower($type), 'group');
            $target = $isGroup
                ? Group::query()->whereKey($targetId)->first()
                : User::query()->whereKey($targetId)->first();

            if (! $target instanceof Model) {
                continue;
            }

            $entry = ['id' => $targetId, 'name' => (string) $target->getAttribute('name')];

            if ($isGroup) {
                $groups[] = $entry;
            } else {
                $clients[] = $entry;
            }
        }

        return ['clients' => $clients, 'groups' => $groups];
    }
}
