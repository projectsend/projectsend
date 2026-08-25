<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Api\Support\PollingQuery;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Audit\ActivityLogScope;
use App\Modules\Audit\Http\Resources\Api\ActivityResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * What has happened in this installation.
 *
 * The endpoint automation tools actually need. Every other list here
 * answers "what is there now"; a caller that wants to *react* — post to
 * Slack when a file is shared, add a row when a client downloads one —
 * needs to know that something happened, and the shape of the thing
 * afterwards does not say. Sharing a file writes an assignment row and
 * never touches the file, so polling the file list cannot see it at all.
 *
 * One feed rather than one endpoint per event, because the log already
 * records every one of them and a caller filtering by `action` gets any
 * event the application ever grows without waiting for an endpoint.
 */
class ActivityController extends Controller
{
    public function __construct(
        private readonly PollingQuery $polling,
        private readonly ActivityLogScope $scope,
    ) {}

    /**
     * List activity, newest first.
     *
     * Filter by `action` — repeat the parameter for more than one, as
     * `?action[]=file.assigned&action[]=file.downloaded`. `subject_type`
     * narrows to one kind of thing (`file`, `user`, `group`, …).
     *
     * Entries are never edited, so `updated_since` walks the moment each
     * one was recorded. Everything else about polling is the shape every
     * list endpoint here shares.
     *
     * Scoped to what the caller may read: a staff member limited to their
     * assigned clients sees entries about their own library and their own
     * actions, never the whole installation's.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate($this->polling->rules() + [
            'action' => ['nullable', 'array'],
            'action.*' => [Rule::enum(Action::class)],
            'subject_type' => ['nullable', 'string', 'max:64'],
        ]);

        $viewer = $request->user();
        assert($viewer !== null);

        $query = $this->scope->apply(ActivityLog::query(), $viewer);

        if (($filters['action'] ?? []) !== []) {
            $query->whereIn('action', $filters['action']);
        }

        if (($filters['subject_type'] ?? null) !== null) {
            $query->where('subject_type', $this->subjectClass($filters['subject_type']));
        }

        // created_at, not updated_at: the log is appended to and never
        // edited, and has no updated_at column to walk.
        return ActivityResource::collection(
            $this->polling->paginate($request, $query, 'activity_log', 'created_at')
        );
    }

    /**
     * The public name for a kind of subject, back to the class the column
     * actually holds. An unknown name matches nothing rather than
     * everything — a filter that silently ignores what it was given would
     * hand back the whole log to a caller who asked for one slice of it.
     */
    private function subjectClass(string $type): string
    {
        return array_search($type, ActivityResource::subjects(), true) ?: '__no_such_subject__';
    }
}
