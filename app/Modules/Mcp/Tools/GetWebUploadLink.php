<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools;

use App\Modules\Mcp\Support\Account;
use App\Modules\Mcp\Support\Envelope;
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

#[Name('get_web_upload_link')]
#[Title('Upload from the web')]
#[Description('Returns the URL of this account\'s own upload page, for a file that is on the person\'s computer and that you cannot read. Use it when the person says "I have it here", "it\'s on my laptop", or attaches something you cannot access. Do not use it for content you wrote (create_file_from_content) or bytes you hold (create_upload). Tell the person to open the URL and upload there; when they say it is done, call list_files with the `since` value from this response to find the file, then share or send it.')]
#[IsReadOnly]
class GetWebUploadLink extends ToolBase
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'folder_id' => $schema->integer()->min(1)->description('Staff libraries only: preselect this folder, from list_folders, on the upload page.'),
        ];
    }

    protected function run(Request $request, Account $account): Response|ResponseFactory|ToolError
    {
        $validated = $request->validate(['folder_id' => ['nullable', 'integer', 'min:1']]);

        $this->requirePermission($account, 'upload');

        $folderId = $account->isClient() ? null : (isset($validated['folder_id']) ? (int) $validated['folder_id'] : null);
        $url = Web::upload($account, $folderId);
        $since = now()->toIso8601String();

        return Envelope::success(
            [
                'upload_url' => $url,
                'since' => $since,
                'say_to_person' => "Upload the file at {$url} — you may need to sign in — and tell me when it is done.",
            ],
            $url,
            "When the person confirms, call list_files with since \"{$since}\" to find the file. Then share_by_link or send_file_to_client with its id.",
        );
    }
}
