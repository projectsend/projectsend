<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools;

use App\Modules\Audit\Action;
use App\Modules\Files\Models\ShareLink;
use App\Modules\Mcp\Support\Account;
use App\Modules\Mcp\Support\Audit;
use App\Modules\Mcp\Support\Bytes;
use App\Modules\Mcp\Support\Envelope;
use App\Modules\Mcp\Support\Files;
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

#[Name('delete_file')]
#[Title('Delete a file')]
#[Description('Deletes a file permanently, revoking its links and removing it from every client it was shared with. Use it when the person asks to remove a file or to free space (list_files sorted by size shows what to offer). Not for taking a link back while keeping the file (revoke_link) or for unsharing from one client (not available in this connector; point to web_url). Destructive: the client will ask the person to confirm, so call it once. Deleting a file that is already gone returns success.')]
#[IsDestructive]
class DeleteFile extends ToolBase
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'file_id' => $schema->integer()->min(1)->required()->description("The file's id, from list_files or a previous response."),
        ];
    }

    protected function run(Request $request, Account $account): Response|ResponseFactory|ToolError
    {
        $validated = $request->validate(['file_id' => ['required', 'integer', 'min:1']]);

        $file = Files::find($account, (int) $validated['file_id']);

        if ($file === null) {
            return ToolError::notFound('file', action: 'Nothing to do: there is no such file in this account. A file that was already deleted no longer exists.');
        }

        Files::requireDelete($file, $account);

        $id = (int) $file->getAttribute('id');
        $name = (string) $file->getAttribute('name');
        $size = (int) $file->getAttribute('size');
        $linksRevoked = count(array_filter(Files::links($file), fn (ShareLink $link): bool => $link->isActive()));

        // A soft delete, as the web does: File's own hooks clean the
        // bytes up on commit, and the row stays for version chains, the
        // activity log and the erasure grace period.
        $file->delete();

        Audit::log(Action::FileDeleted, $account, null, ['name' => $name]);

        $storage = $account->refreshedStorage();

        return Envelope::success(
            [
                'deleted' => true,
                'already_deleted' => false,
                'file' => ['id' => $id, 'name' => $name, 'size_bytes' => $size, 'size' => Bytes::human($size)],
                'links_revoked' => $linksRevoked,
            ],
            Web::files($account),
            $storage === null
                ? 'Confirm to the person what was deleted.'
                : "Confirm to the person what was deleted and how much space is free now ({$storage['remaining']}).",
            array_filter([$account->storageWarning()]),
            $storage,
        );
    }
}
