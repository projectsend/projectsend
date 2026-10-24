<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools;

use App\Modules\Files\Models\Folder;
use App\Modules\Mcp\Support\Account;
use App\Modules\Mcp\Support\Envelope;
use App\Modules\Mcp\Support\Files;
use App\Modules\Mcp\Support\Folders;
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
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_folders')]
#[Title('List folders')]
#[Description('Lists the folders of this site\'s library with their path, how many files and folders each holds, and whether it is public or a client\'s own home folder. Use it to find a folder\'s id before saving a file into it (folder_id on create_file_from_content, create_upload or get_web_upload_link), before move_file, create_folder or share_folder_with_client, or when the person asks how the library is organised. Without arguments it returns the top level; parent_id opens one folder, and query searches names across the whole tree. To see the files inside a folder, use list_files with its folder_id. Not available on a free account, which keeps its files in one list.')]
#[IsReadOnly]
class ListFolders extends ToolBase
{
    protected function gatedForClients(): bool
    {
        return true;
    }

    protected function gateFeature(): string
    {
        return 'folders';
    }

    /** No file is shared by asking for a folder: the gate's alternative says what to do instead. */
    protected function gateNextCall(Request $request): ?array
    {
        return null;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'parent_id' => $schema->integer()->min(1)->description('Only the folders directly inside this one. Omit it for the top level of the library.'),
            'query' => $schema->string()->max(120)->description('Matches folder names anywhere in the library; parent_id is ignored when it is given.'),
            'limit' => $schema->integer()->min(1)->max(50)->default(20)->description('How many folders to return, 1 to 50.'),
            'cursor' => $schema->string()->description("From the previous response's next_cursor."),
        ];
    }

    protected function run(Request $request, Account $account): Response|ResponseFactory|ToolError
    {
        $validated = $request->validate([
            'parent_id' => ['nullable', 'integer', 'min:1'],
            'query' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'cursor' => ['nullable', 'string', 'max:512'],
        ]);

        // FolderPolicy::view asks what the library page asks.
        Files::requireLibraryAccess($account);

        $query = Folders::visibleTo($account);
        $parent = null;
        $term = trim((string) ($validated['query'] ?? ''));

        if ($term !== '') {
            $query->where('name', 'like', '%'.$term.'%');
        } elseif (isset($validated['parent_id'])) {
            $parent = Folders::find($account, (int) $validated['parent_id']);

            if ($parent === null) {
                return Folders::notFound();
            }

            $query->where('parent_id', (int) $parent->getAttribute('id'));
        } else {
            // The top level, as the library page shows it.
            $query->whereNull('parent_id');
        }

        $query->withCount(['children', 'files', 'assignments'])->orderBy('name')->orderBy('id');

        $total = (clone $query)->count();
        $limit = (int) ($validated['limit'] ?? 20);
        $page = $query->cursorPaginate($limit, ['*'], 'cursor', $validated['cursor'] ?? null);

        /** @var list<Folder> $items */
        $items = array_values($page->items());
        $paths = Folders::paths($parent === null ? $items : [...$items, $parent]);

        $folders = array_map(fn (Folder $folder): array => Presenter::folder($folder, $paths) + [
            'folders_count' => (int) ($folder->getAttribute('children_count') ?? 0),
            'files_count' => (int) ($folder->getAttribute('files_count') ?? 0),
            'shared_with_count' => (int) ($folder->getAttribute('assignments_count') ?? 0),
        ], $items);

        return Envelope::success(
            [
                'parent' => $parent === null ? null : Presenter::folder($parent, $paths),
                'folders' => $folders,
                'next_cursor' => $page->nextCursor()?->encode(),
                'total' => $total,
            ],
            $parent === null ? Web::staffFiles() : Web::staffFolder((int) $parent->getAttribute('id')),
            'Use a folder\'s id as folder_id to save files into it or to list its files, as parent_id to open it or create a folder inside it, with move_file to file something there, or with share_folder_with_client. If next_cursor is not null, there are more.',
        );
    }
}
