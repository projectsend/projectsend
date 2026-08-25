<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Resources\Api;

use App\Modules\Audit\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One entry in the activity log, as an integration reads it.
 *
 * Deliberately not ActivityPresenter's shape. That one exists to render a
 * sentence, so it hands back a template and the words to slot into it —
 * right for a screen, useless to a caller that wants to branch on what
 * happened. Here the action is a key, the subject is an object, and the
 * specifics stay in `context`.
 *
 * @mixin ActivityLog
 */
class ActivityResource extends JsonResource
{
    /**
     * Class names are internal structure and must never reach the wire:
     * moving a model between namespaces would otherwise be a breaking API
     * change, and `/api/v1` is a frozen contract. These strings are the
     * contract instead — add to this map when a new kind of thing becomes
     * a subject, and never rename an entry in it.
     *
     * @var array<class-string, string>
     */
    private const SUBJECTS = [
        \App\Models\User::class => 'user',
        \App\Modules\Files\Models\File::class => 'file',
        \App\Modules\Files\Models\Folder::class => 'folder',
        \App\Modules\Files\Models\Category::class => 'category',
        \App\Modules\Groups\Models\Group::class => 'group',
        \App\Modules\Identity\Models\Role::class => 'role',
        \App\Modules\Clients\Models\ClientCustomField::class => 'client_custom_field',
    ];

    /**
     * The map, for the controller's reverse lookup.
     *
     * @return array<class-string, string>
     */
    public static function subjects(): array
    {
        return self::SUBJECTS;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action->value,
            'created_at' => $this->created_at->toIso8601String(),

            // Snapshots, not joins. The actor may since have been deleted,
            // and the entry still has to say who it was.
            'actor' => $this->actor_id === null && $this->actor_name === null ? null : [
                'id' => $this->actor_id,
                'name' => $this->actor_name,
                'type' => $this->actor_type,
            ],

            // How it arrived: a person in the browser, an integration, a
            // visitor with no account, or the installation itself.
            'origin' => $this->origin->value,

            'subject' => $this->subject_type === null ? null : [
                'type' => self::SUBJECTS[$this->subject_type] ?? 'other',
                'id' => $this->subject_id,
                'name' => $this->subject_name,
            ],

            // Whatever the action recorded beyond its subject — who a file
            // was shared with, how many files a cascade removed. Shape
            // varies by action and is documented per action rather than
            // here.
            'context' => $this->context ?? [],

            // ip_address is deliberately absent. It is stored for some
            // actions and shown on the activity screen, but handing a
            // client's IP to an automation tool is a privacy expansion
            // with no matching use — see docs/api-todo.md.
        ];
    }
}
