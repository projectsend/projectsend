<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools;

use App\Modules\Mcp\Support\Account;
use App\Modules\Mcp\Support\Envelope;
use App\Modules\Mcp\Support\Files;
use App\Modules\Mcp\Support\Folders;
use App\Modules\Mcp\Support\Presenter;
use App\Modules\Mcp\Support\ToolError;
use App\Modules\Mcp\Support\Web;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_files')]
#[Title('List files')]
#[Description('Lists the files this account can see, newest first: name, size, when it was added, its private link if it has one, and how many times it was downloaded. Use it to find a file before sharing, sending, revoking or deleting it, to find what the person just uploaded from the web (pass the `since` you were given), or to see what is taking space (sort by size). Do not use it to watch for downloads (use get_download_status) or on a timer: pass `since` with the last `updated_at` you saw instead. Each file\'s id is what the other tools take.')]
#[IsReadOnly]
class ListFiles extends ToolBase
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->max(120)->description('Matches the file name and description. Leave empty to list everything.'),
            'since' => $schema->string()->description('Only files created or updated at or after this moment (ISO 8601). Use the `since` value from get_web_upload_link to find what the person just uploaded.'),
            'folder_id' => $schema->integer()->min(1)->description('Only files in this folder, from list_folders. Staff libraries only; ignored on a free account.'),
            'sort' => $schema->string()->enum(['newest', 'name', 'size'])->default('newest')->description('Order: newest first, by name, or largest first (useful when freeing space).'),
            'limit' => $schema->integer()->min(1)->max(50)->default(20)->description('How many files to return, 1 to 50.'),
            'cursor' => $schema->string()->description("From the previous response's next_cursor."),
        ];
    }

    protected function run(Request $request, Account $account): Response|ResponseFactory|ToolError
    {
        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:120'],
            'since' => ['nullable', 'date'],
            'folder_id' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'in:newest,name,size'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'cursor' => ['nullable', 'string', 'max:512'],
        ]);

        Files::requireLibraryAccess($account);

        $query = Files::visibleTo($account);

        if (isset($validated['query']) && $validated['query'] !== '') {
            $term = '%'.$validated['query'].'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('description', 'like', $term));
        }

        if (isset($validated['since'])) {
            $query->where('updated_at', '>=', Carbon::parse($validated['since']));
        }

        if (isset($validated['folder_id']) && ! $account->isClient()) {
            if (Folders::find($account, (int) $validated['folder_id']) === null) {
                return Folders::notFound();
            }

            $query->where('folder_id', (int) $validated['folder_id']);
        }

        match ($validated['sort'] ?? 'newest') {
            'name' => $query->orderBy('name'),
            'size' => $query->orderByDesc('size'),
            default => $query->orderByDesc('created_at'),
        };
        $query->orderBy('id');

        $query->withCount('downloads')->with('shareLinks');

        // Each file says which folder it is in and how many it is shared
        // with; staff only (Presenter::file).
        if (! $account->isClient()) {
            $query->withCount('assignments')->with('folder');
        }

        $total = (clone $query)->count();
        $limit = (int) ($validated['limit'] ?? 20);
        $page = $query->cursorPaginate($limit, ['*'], 'cursor', $validated['cursor'] ?? null);

        $files = [];

        foreach ($page->items() as $file) {
            $link = Presenter::activeLink($file->shareLinks);

            $files[] = Presenter::file($file, $account) + [
                'link' => $link === null ? null : Presenter::link($link),
                'downloads_count' => Files::downloadsCount($file),
                'shared_with_count' => $account->isClient() ? 0 : (int) ($file->getAttribute('assignments_count') ?? 0),
            ];
        }

        $warnings = array_filter([$account->storageWarning()]);

        return Envelope::success(
            ['files' => $files, 'next_cursor' => $page->nextCursor()?->encode(), 'total' => $total],
            Web::files($account),
            "Use a file's id with share_by_link, send_file_to_client, get_download_status, revoke_link, move_file or delete_file. If next_cursor is not null, there are more.",
            $warnings,
        );
    }
}
