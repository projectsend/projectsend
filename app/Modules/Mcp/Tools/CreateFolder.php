<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools;

use App\Modules\Audit\Action;
use App\Modules\Files\Folders\FolderService;
use App\Modules\Mcp\Support\Account;
use App\Modules\Mcp\Support\Audit;
use App\Modules\Mcp\Support\Envelope;
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
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('create_folder')]
#[Title('Create a folder')]
#[Description('Creates a folder in this site\'s library, at the top level or inside another folder, the way the person does on the web. Use it when the person wants to organise files, for example one folder per client or per project, before saving or moving files into it. If a folder with the same name already exists in the same place, that one is returned instead of a second being made, so calling it again is safe. After this, save files into it with folder_id, move existing ones with move_file, and give a client everything inside with share_folder_with_client. Not available on a free account, which keeps its files in one list.')]
#[IsIdempotent]
class CreateFolder extends ToolBase
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
            'name' => $schema->string()->max(255)->required()->description('The folder\'s name, as the person should see it in the library.'),
            'parent_id' => $schema->integer()->min(1)->description('Create it inside this folder, from list_folders. Omit it for the top level of the library.'),
        ];
    }

    protected function run(Request $request, Account $account): Response|ResponseFactory|ToolError
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'min:1'],
        ]);

        // What FoldersController::store asks: the folder permission, and
        // upload with it — a folder nobody can put anything in is no use.
        $this->requirePermission($account, 'create_own_folders');
        $this->requirePermission($account, 'upload');

        $name = trim((string) $validated['name']);

        if ($name === '') {
            return ToolError::validationFailed(['name' => ['Give the folder a name.']]);
        }

        $parent = null;

        if (isset($validated['parent_id'])) {
            $parent = Folders::find($account, (int) $validated['parent_id']);

            if ($parent === null) {
                return Folders::notFound();
            }

            // A folder inside a public one is public, so making it there
            // is placing content in a public folder, as FoldersController
            // asks too.
            Folders::requirePlaceable($parent, $account);
        }

        $parentId = $parent === null ? null : (int) $parent->getAttribute('id');

        // The web lets two folders share a name; an assistant retrying a
        // call should not be how a library gets two "Acme" folders.
        $existing = Folders::visibleTo($account)
            ->where('parent_id', $parentId)
            ->where('name', $name)
            ->orderBy('id')
            ->first();

        $created = false;

        if ($existing !== null) {
            $folder = $existing;
        } else {
            // The web's own path: the materialized path, the depth cap
            // (a validation error naming parent_id), and the person as
            // the folder's creator.
            $folder = app(FolderService::class)->create($name, $parent);
            $created = true;

            Audit::log(Action::FolderCreated, $account, $folder);
        }

        $id = (int) $folder->getAttribute('id');

        return Envelope::success(
            [
                'folder' => Presenter::folder($folder, Folders::paths([$folder])),
                'created' => $created,
            ],
            Web::staffFolder($id),
            $created
                ? "Save files into it with folder_id {$id} (create_file_from_content, create_upload or get_web_upload_link), move existing ones with move_file, or give a client everything in it with share_folder_with_client."
                : "A folder with this name was already there, so it was not made twice; use folder_id {$id}. Save files into it, move existing ones with move_file, or share it with share_folder_with_client.",
        );
    }
}
