<?php

declare(strict_types=1);

namespace App\Modules\Comments\Http\Resources\Api;

use App\Modules\Comments\Models\FileComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FileComment
 *
 * Every field is listed explicitly, never $comment->toArray(): that would
 * publish whatever the next migration adds. Two columns are held back on
 * purpose:
 *
 *  - `ip_address` is recorded for anonymous comments so repeat spam can be
 *    acted on. It is personal data collected for moderation, and an
 *    integration reading a thread has no business with it.
 *  - `client_context_id` is which client's conversation a comment belongs
 *    to. It is surfaced as `thread` below, and only to tokens whose owner
 *    is staff — which every API token is today, but stating the rule here
 *    means it survives a future client-facing token.
 */
class FileCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();

        return [
            'id' => $this->id,
            'file_id' => $this->file_id,
            'body' => $this->body,
            'visibility' => $this->visibility->value,
            'author' => [
                'id' => $this->author_id,
                'name' => $this->authorName(),
                'type' => $this->author_id === null ? 'guest' : ($this->author?->isStaff() === true ? 'staff' : 'client'),
            ],
            // Whose conversation this is, when it is one client's rather
            // than every client's. Staff only, and null on a comment
            // addressed to all of them.
            'conversation' => $viewer?->isStaff() === true && $this->client_context_id !== null
                ? ['client_id' => $this->client_context_id, 'client_name' => $this->clientContext?->name]
                : null,
            'approved' => ! $this->isPending(),
            'created_at' => $this->created_at?->toIso8601String(),
            'edited_at' => $this->edited_at?->toIso8601String(),
        ];
    }
}
