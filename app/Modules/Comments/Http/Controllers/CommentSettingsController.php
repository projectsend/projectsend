<?php

declare(strict_types=1);

namespace App\Modules\Comments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Comments\CommentAuthors;
use App\Modules\Comments\CommentScope;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Who may comment, and on what. This screen is the whole access model for
 * commenting — there is no per-role equivalent, deliberately (see
 * CommentAuthors for why).
 *
 * The options are sent as data rather than hardcoded in the page so the
 * two enums stay the single source of the vocabulary; a value added to
 * either appears here without the frontend being touched.
 */
class CommentSettingsController extends Controller
{
    public function __construct(
        private readonly Settings $settings,
        private readonly ActivityLogger $activity,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('system/settings/comments', [
            'comments_scope' => $this->settings->get(Setting::CommentsScope),
            'comments_authors' => $this->settings->get(Setting::CommentsAuthors),
            'public_comments_enabled' => $this->settings->get(Setting::PublicCommentsEnabled),
            'comments_guest_moderation' => $this->settings->get(Setting::CommentsGuestModeration),
            'comments_edit_window_minutes' => $this->settings->get(Setting::CommentsEditWindowMinutes),
            'scope_options' => array_map(
                fn (CommentScope $scope): array => ['value' => $scope->value, 'label' => $scope->label()],
                CommentScope::cases(),
            ),
            'author_options' => array_map(
                fn (CommentAuthors $authors): array => ['value' => $authors->value, 'label' => $authors->label()],
                CommentAuthors::cases(),
            ),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'comments_scope' => ['required', Rule::enum(CommentScope::class)],
            'comments_authors' => ['required', Rule::enum(CommentAuthors::class)],
            'public_comments_enabled' => ['required', 'boolean'],
            'comments_guest_moderation' => ['required', 'boolean'],
            'comments_edit_window_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
        ]);

        $this->settings->set(Setting::CommentsScope, $validated['comments_scope']);
        $this->settings->set(Setting::CommentsAuthors, $validated['comments_authors']);
        $this->settings->set(Setting::PublicCommentsEnabled, $validated['public_comments_enabled']);
        $this->settings->set(Setting::CommentsGuestModeration, $validated['comments_guest_moderation']);
        $this->settings->set(Setting::CommentsEditWindowMinutes, (int) $validated['comments_edit_window_minutes']);

        $this->activity->log(Action::SettingsUpdated, context: ['section' => 'comments']);

        return back();
    }
}
