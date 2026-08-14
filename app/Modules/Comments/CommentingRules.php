<?php

declare(strict_types=1);

namespace App\Modules\Comments;

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Platform\Captcha\Captcha;
use App\Modules\Platform\Captcha\CaptchaForm;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Support\Facades\Gate;

/**
 * The settings half of commenting: may this file be commented on, may
 * this person comment, and which visibilities may they choose.
 *
 * Every surface asks this class — nothing else reads the comment
 * settings directly. That matters because the answer is a conjunction of
 * four separate switches plus the file's own state, and a surface that
 * checked three of them would look correct right up until the fourth one
 * mattered.
 *
 * Note the division of labour with Access\VisibleCommentScope: this class
 * answers "may you write", that one answers "what may you read". They
 * share no logic, deliberately — reading a file's public comments is not
 * evidence you may add one, and being allowed to write does not widen what
 * you can see.
 */
class CommentingRules
{
    public function __construct(
        private readonly Settings $settings,
    ) {}

    /**
     * Both readers fall back to the enum's own safe value rather than
     * throwing on a stored string that is no longer a case — the same
     * shape UploadExtensionPolicy uses, so a hand-edited settings row
     * degrades instead of taking the page down.
     */
    public function scope(): CommentScope
    {
        $value = $this->settings->get(Setting::CommentsScope);

        return (is_string($value) ? CommentScope::tryFrom($value) : null) ?? CommentScope::AllFiles;
    }

    public function authors(): CommentAuthors
    {
        $value = $this->settings->get(Setting::CommentsAuthors);

        return (is_string($value) ? CommentAuthors::tryFrom($value) : null) ?? CommentAuthors::StaffAndClients;
    }

    /**
     * Whether the feature exists at all on this install. Surfaces use this
     * to decide whether to render any comment affordance whatsoever.
     */
    public function enabled(): bool
    {
        return $this->scope() !== CommentScope::None;
    }

    /**
     * Whether this particular file accepts new comments. Existing comments
     * on a file that no longer qualifies stay readable — turning the scope
     * down hides the composer, not the history. Only CommentScope::None
     * removes the feature's UI entirely.
     */
    public function enabledFor(File $file): bool
    {
        return $this->scope()->allows($file);
    }

    public function publicCommentsEnabled(): bool
    {
        return $this->settings->get(Setting::PublicCommentsEnabled) === true;
    }

    /**
     * Whether this author has to solve a security check before posting.
     *
     * Only a visitor with no account: somebody signed in has already been
     * identified, is subject to everything else that governs an account,
     * and asking them to prove they are human on a page that already knows
     * who they are is friction with nothing behind it.
     *
     * The decision lives here because this class is where every surface
     * asks what commenting policy is; the machinery behind it belongs to
     * the Captcha module and stays there.
     */
    public function captchaRequiredFor(?User $viewer): bool
    {
        return $viewer === null && app(Captcha::class)->protects(CaptchaForm::Comment);
    }

    /**
     * Whether anonymous comments wait for a moderator. Only reachable when
     * the author setting is `everyone`; an authenticated comment is never
     * held.
     */
    public function moderatesGuests(): bool
    {
        return $this->settings->get(Setting::CommentsGuestModeration) === true;
    }

    public function editWindowMinutes(): int
    {
        return (int) $this->settings->get(Setting::CommentsEditWindowMinutes);
    }

    /**
     * Whether $viewer may post on $file. Null means an anonymous visitor.
     */
    public function canPost(?User $viewer, File $file): bool
    {
        return $this->postingBlockedReason($viewer, $file) === null;
    }

    /**
     * Why this person may not write here, in a sentence addressed to them —
     * or null when they may, which is what canPost() above is.
     *
     * The permission answer and the explanation are the same walk on
     * purpose. They were briefly two: the composer simply vanished when
     * posting was refused, and the thread underneath said "No comments
     * yet", which reads as *nobody has commented* rather than *you cannot*.
     * A visitor on a public file with commenting closed had no way to tell
     * the difference. Deriving one from the other means a new reason to
     * refuse cannot ship without words for it.
     *
     * English text, also the translation key — same convention as the enum
     * labels in this module.
     *
     * The file-visibility check happens here rather than in the caller: an
     * authenticated writer must pass FilePolicy::view, and an anonymous one
     * requires the file to be reachable without logging in at all.
     */
    public function postingBlockedReason(?User $viewer, File $file): ?string
    {
        if (! $this->enabledFor($file)) {
            return 'Comments are closed on this file.';
        }

        if (! $this->authors()->allows($viewer?->type)) {
            return match ($this->authors()) {
                CommentAuthors::Staff => 'Only staff can comment here.',
                CommentAuthors::Clients => 'Only clients can comment here.',
                // StaffAndClients, whose only refusal is of a visitor.
                // Everyone refuses nobody and never reaches this.
                default => 'Only people who are signed in can comment here.',
            };
        }

        if ($viewer === null) {
            // An anonymous author has exactly one possible visibility, so
            // if that visibility is switched off they cannot write at all.
            // Signing in is still a route to commenting, which is why the
            // wording points there rather than saying it is impossible.
            return $this->publicCommentsEnabled() && $file->isEffectivelyPublic()
                ? null
                : 'Only people who are signed in can comment here.';
        }

        // FilePolicy::view is about assignment, which is the wrong question
        // for a public file: a logged-in client browsing the public listing
        // reaches it exactly as a visitor does, and being signed in should
        // not take away what being signed out allows. Their comment still
        // lands in their own thread, so staff see who asked.
        return Gate::forUser($viewer)->allows('view', $file) || $file->isEffectivelyPublic()
            ? null
            : 'You cannot comment on this file.';
    }

    /**
     * Which visibilities the composer may offer, in the order they should
     * be listed. Empty means the composer must not be rendered.
     *
     * @return list<CommentVisibility>
     */
    public function allowedVisibilities(?User $viewer, File $file): array
    {
        if (! $this->canPost($viewer, $file)) {
            return [];
        }

        $everyone = $this->publicCommentsEnabled() && $file->isEffectivelyPublic();

        if ($viewer === null) {
            return $everyone ? [CommentVisibility::Everyone] : [];
        }

        // Narrowest audience first — a list is easier to scan when it runs
        // one way. The default is chosen separately (see defaultVisibility)
        // rather than falling out of this order.
        $allowed = array_values(array_filter(
            [CommentVisibility::OnlyMe, CommentVisibility::StaffOnly, CommentVisibility::Clients],
            fn (CommentVisibility $visibility): bool => $viewer->isStaff() || $visibility->availableToClients(),
        ));

        if ($everyone) {
            $allowed[] = CommentVisibility::Everyone;
        }

        return $allowed;
    }

    /**
     * Every audience the composer shows, including the ones this file or
     * this installation does not currently allow.
     *
     * An unavailable option is shown disabled with the reason rather than
     * left out. Silently omitting it is indistinguishable from the feature
     * not existing — which is exactly how it read to the first person who
     * looked for "Everyone" and could not find it. Saying why also says
     * what to change.
     *
     * This is presentation only. allowedVisibilities() above stays the
     * authorization list, and FileComments::post still refuses anything
     * absent from it.
     *
     * @return list<array{visibility: CommentVisibility, available: bool, reason: string|null}>
     */
    public function visibilityOptions(?User $viewer, File $file): array
    {
        $allowed = $this->allowedVisibilities($viewer, $file);

        if ($allowed === [] || $viewer === null) {
            // A visitor gets Everyone or nothing at all, and no explanation
            // is any use to them — there is nothing they could change.
            return array_map(
                fn (CommentVisibility $visibility): array => ['visibility' => $visibility, 'available' => true, 'reason' => null],
                $allowed,
            );
        }

        $offered = array_values(array_filter(
            CommentVisibility::cases(),
            fn (CommentVisibility $visibility): bool => $viewer->isStaff() || $visibility->availableToClients(),
        ));

        return array_map(fn (CommentVisibility $visibility): array => [
            'visibility' => $visibility,
            'available' => in_array($visibility, $allowed, true),
            'reason' => in_array($visibility, $allowed, true) ? null : $this->unavailableReason($visibility, $file),
        ], $offered);
    }

    /**
     * Why an audience is not on offer, in words that name the thing to
     * change. Only Everyone is ever conditional today.
     */
    private function unavailableReason(CommentVisibility $visibility, File $file): ?string
    {
        if ($visibility !== CommentVisibility::Everyone) {
            return null;
        }

        // The site switch first: it is the more general cause, and turning
        // it on is the step that unblocks every file at once.
        return $this->publicCommentsEnabled()
            ? 'Only a file that is publicly visible can have public comments.'
            : 'Public comments are turned off for this site.';
    }

    /**
     * Which audience the composer starts on.
     *
     * The conversational one, not the narrowest. A comment box on a shared
     * file is for talking to the people it is shared with, and defaulting
     * to a private note has a silent failure behind it: a client asks a
     * question, never notices the dropdown, and writes it to themselves —
     * so nobody answers, and nobody knows there was anything to answer.
     * The reverse mistake is louder and easier to undo.
     */
    public function defaultVisibility(?User $viewer, File $file): ?CommentVisibility
    {
        $allowed = $this->allowedVisibilities($viewer, $file);

        if ($allowed === []) {
            return null;
        }

        return in_array(CommentVisibility::Clients, $allowed, true)
            ? CommentVisibility::Clients
            : $allowed[0];
    }
}
