<?php

declare(strict_types=1);

namespace App\Modules\Comments;

use Illuminate\Http\Request;

/**
 * Which comments this visitor wrote, remembered in their session.
 *
 * A visitor has no account, which is why their held comment used to
 * disappear the moment they posted it: the thread reloads, the comment is
 * not approved yet, and nothing could tell "the person who just wrote
 * this" from any other stranger. Posting then looked like it had failed.
 *
 * The session is the identity. It is weak on purpose — it lasts as long
 * as the browser session and no longer, and it is only ever used to widen
 * what somebody sees of *their own* writing, never to grant anything.
 * Losing it shows them one fewer pending comment, which is the harmless
 * direction to fail in.
 *
 * Read by Access\VisibleCommentScope, written by the public controller —
 * the only place a comment can be posted without an account.
 */
class GuestCommentIdentity
{
    private const KEY = 'comments.own';

    /**
     * How many ids to keep. A visitor cannot post faster than the route's
     * rate limit allows, so this is a bound on a long session rather than
     * on a burst; the oldest are dropped, and dropping one only means that
     * comment stops showing while it waits.
     */
    private const LIMIT = 50;

    public function __construct(
        private readonly Request $request,
    ) {}

    public function remember(int $commentId): void
    {
        if (! $this->request->hasSession()) {
            return;
        }

        $ids = [...$this->ownCommentIds(), $commentId];

        $this->request->session()->put(self::KEY, array_slice(array_unique($ids), -self::LIMIT));
    }

    /**
     * @return list<int>
     */
    public function ownCommentIds(): array
    {
        if (! $this->request->hasSession()) {
            return [];
        }

        $stored = $this->request->session()->get(self::KEY, []);

        if (! is_array($stored)) {
            return [];
        }

        return array_values(array_map(intval(...), array_filter($stored, is_numeric(...))));
    }
}
