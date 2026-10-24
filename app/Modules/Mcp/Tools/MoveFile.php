<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools;

use App\Modules\Audit\Action;
use App\Modules\Files\Models\Folder;
use App\Modules\Mcp\Support\Account;
use App\Modules\Mcp\Support\Audit;
use App\Modules\Mcp\Support\Envelope;
use App\Modules\Mcp\Support\Files;
use App\Modules\Mcp\Support\Folders;
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
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('move_file')]
#[Title('Move a file to a folder')]
#[Description('Moves a file into a folder of this site\'s library, or out of every folder to the top level, the way dragging it does on the web. Use it when the person wants a file filed somewhere else; find the destination with list_folders first, or make one with create_folder. Moving changes neither who the file is shared with nor its links or downloads, and a client who sees the destination folder sees the file there. A file in a public folder is published, so moving one there needs the right to publish. Moving a file to where it already is changes nothing. Not available on a free account, which keeps its files in one list.')]
#[IsIdempotent]
class MoveFile extends ToolBase
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
            'file_id' => $schema->integer()->min(1)->required()->description("The file's id, from list_files or a previous response."),
            'folder_id' => $schema->integer()->min(1)->description('The destination folder, from list_folders. Give this or to_top_level.'),
            'to_top_level' => $schema->boolean()->description('True to take the file out of every folder, to the top level of the library. Give this or folder_id.'),
        ];
    }

    protected function run(Request $request, Account $account): Response|ResponseFactory|ToolError
    {
        $validated = $request->validate([
            'file_id' => ['required', 'integer', 'min:1'],
            'folder_id' => ['nullable', 'integer', 'min:1'],
            'to_top_level' => ['nullable', 'boolean'],
        ]);

        $toTop = (bool) ($validated['to_top_level'] ?? false);

        // Two explicit ways to say where, so that leaving folder_id out by
        // mistake never empties a file out of its folder.
        if (isset($validated['folder_id']) === $toTop) {
            return ToolError::validationFailed(['folder_id' => ['Give folder_id, or to_top_level: true to take the file out of every folder — one of the two.']]);
        }

        $file = Files::find($account, (int) $validated['file_id']);

        if ($file === null) {
            return ToolError::notFound('file');
        }

        Files::requireUpdate($file, $account);

        $destination = null;

        if (! $toTop) {
            $destination = Folders::find($account, (int) $validated['folder_id']);

            if ($destination === null) {
                return Folders::notFound();
            }
        }

        $fromId = $file->getAttribute('folder_id') === null ? null : (int) $file->getAttribute('folder_id');
        $toId = $destination === null ? null : (int) $destination->getAttribute('id');
        $from = $fromId === null ? null : Folder::query()->whereKey($fromId)->first();
        $moved = $fromId !== $toId;

        if ($moved) {
            // Asked only when the file goes somewhere new, as FilesController::move
            // does: placing it in a public folder publishes it (GHSA-rxf8-wh8v-jm9j).
            if ($destination !== null) {
                Folders::requirePlaceable($destination, $account);
            }

            $file->update(['folder_id' => $toId]);
            $file->unsetRelation('folder');

            Audit::log(Action::FileUpdated, $account, $file);
        }

        $paths = Folders::paths(array_values(array_filter([$from, $destination], fn (?Model $folder): bool => $folder !== null)));
        $where = $destination === null ? 'the top level of the library' : '"'.(string) $destination->getAttribute('name').'"';

        return Envelope::success(
            [
                'file' => Presenter::file($file, $account),
                'from' => $from instanceof Model ? Presenter::folder($from, $paths) : null,
                'to' => $destination === null ? null : Presenter::folder($destination, $paths),
                'moved' => $moved,
            ],
            $toId === null ? Web::staffFiles() : Web::staffFolder($toId),
            $moved
                ? "Tell the person the file is now in {$where}. Who it is shared with, its links and its downloads did not change."
                : "The file was already in {$where}; nothing changed.",
        );
    }
}
