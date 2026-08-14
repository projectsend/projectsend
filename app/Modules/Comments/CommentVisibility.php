<?php

declare(strict_types=1);

namespace App\Modules\Comments;

/**
 * Who a comment is addressed to.
 *
 * Four fixed audiences, and the list never changes — it does not grow with
 * the number of clients a file is shared with. An earlier version made
 * staff pick a recipient from a dropdown of every client on the file
 * before they could write, which is not how anybody answers a message and
 * reached 51 entries on a file shared with a 50-client group. Answering
 * one client is a reply now (see FileComments::post's $replyTo), so the
 * audience of a reply is inherited from the comment it answers rather than
 * chosen.
 *
 * **`Clients` includes staff.** The name says who it *adds* — the team can
 * already see everything on a file they can open. Read it as "the team and
 * the clients", never as "clients instead of staff"; the label a person
 * sees says so out loud, because the short name did not and somebody
 * reasonably concluded there was no staff-and-clients option at all.
 *
 * It is one channel seen from two ends, which is why its label depends on
 * who is reading: staff address "Staff and clients", a client addresses
 * "Staff". That asymmetry is real, not cosmetic — a client's comment
 * carries their own conversation and never fans out to the other clients,
 * so for them the audience genuinely is the team alone. Narrowing to a
 * single client is `client_context_id`, not a separate case: see
 * Access\VisibleCommentScope.
 *
 * The case names avoid `Private`/`Public`: those words already mean
 * something else here (a *file's* public flag, and CommentScope's
 * `public_files`), and reading `CommentVisibility::Public` next to
 * `$file->public` invites exactly the confusion this feature cannot afford.
 */
enum CommentVisibility: string
{
    case OnlyMe = 'only_me';
    case StaffOnly = 'staff_only';
    case Clients = 'clients';
    case Everyone = 'everyone';

    /**
     * English label — also the translation key.
     *
     * @param  bool  $forStaff  Whose end of the conversation is reading.
     */
    public function label(bool $forStaff = true): string
    {
        return match ($this) {
            self::OnlyMe => 'Only me',
            self::StaffOnly => 'Staff only',
            self::Clients => $forStaff ? 'Staff and clients' : 'Staff',
            self::Everyone => 'Everyone',
        };
    }

    /**
     * The one-line explanation shown under the option in the composer.
     * English text — also the translation key.
     */
    public function description(bool $forStaff = true): string
    {
        return match ($this) {
            self::OnlyMe => 'A private note. Nobody else can read it, staff included.',
            self::StaffOnly => 'The team working on this file. No client ever sees it.',
            self::Clients => $forStaff
                ? 'The team, and everyone this file is shared with. Clients cannot see each other.'
                : 'The team behind this file. No other client can see it.',
            self::Everyone => 'Everyone above, plus anyone who opens this file without logging in.',
        };
    }

    /**
     * Whether a client may choose this audience. Staff-only notes are not
     * theirs to write, and there is no third party for them to address.
     */
    public function availableToClients(): bool
    {
        return $this !== self::StaffOnly;
    }
}
