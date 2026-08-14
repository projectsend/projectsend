<?php

declare(strict_types=1);

namespace App\Modules\Files\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\ShareLink;
use App\Modules\Platform\Localization\LocalDay;
use App\Modules\Platform\Localization\TimezoneRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Public share links for a file — same "can share this" gate as
 * assigning to a client or group (Gate::update), not a dedicated
 * permission. The expiry/download-limit fields are separately gated by
 * the set_file_expiration_date/limit_downloads permissions: without
 * them the field is simply absent, not a 403.
 */
class ShareLinksController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly TimezoneRegistry $timezones,
    ) {}

    public function store(Request $request, File $file): RedirectResponse
    {
        Gate::authorize('update', $file);

        $validated = $request->validate([
            // Deliberately not `after:now`: that rule reads the bare
            // YYYY-MM-DD the picker posts as midnight UTC, so a creator
            // far enough east would be told today's date is in the past
            // while it is plainly still today where they are. The check
            // moves below, onto the instant the date actually resolves to.
            'expires_at' => ['nullable', 'date'],
            'max_downloads' => ['nullable', 'integer', 'min:1'],
            // A custom token is optional — leave blank for a random one,
            // same as before. Must not collide with the file's own
            // public slug: the two live in different URL namespaces
            // (/s/{token} vs the public group listing), but sharing the
            // same string between them is confusing enough to reject.
            // The token IS the authorization for /s/{token} — there is no
            // second factor behind it — so it has to be long enough not to
            // be guessable. 6 chars of [A-Za-z0-9_-] is ~36 bits, within
            // reach of sustained guessing (the route's 30/min IP throttle
            // is not a bound when the attacker has many IPs). Random tokens
            // are Str::random(32); 12 is the floor for a chosen one.
            'token' => ['nullable', 'string', 'min:12', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('share_links', 'token')],
        ]);

        if (($validated['token'] ?? null) !== null && $validated['token'] === $file->slug) {
            throw ValidationException::withMessages([
                'token' => __('This link cannot match the file\'s own public URL slug.'),
            ]);
        }

        $user = $request->user();
        assert($user !== null);

        // End of that day in the creator's own zone — a link "expiring on
        // the 12th" stays usable through the 12th, which is what they
        // will have told the recipient.
        $expiresAt = ($validated['expires_at'] ?? null) === null
            ? null
            : LocalDay::end($validated['expires_at'], $this->timezones->resolve($user));

        if ($expiresAt !== null && $expiresAt->isPast()) {
            throw ValidationException::withMessages([
                'expires_at' => __('The expiry date must be in the future.'),
            ]);
        }

        ShareLink::query()->create([
            'shareable_type' => $file->getMorphClass(),
            'shareable_id' => $file->id,
            'token' => $validated['token'] ?? Str::random(32),
            'created_by' => $user->id,
            'expires_at' => $user->can('set_file_expiration_date') ? $expiresAt : null,
            'max_downloads' => $user->can('limit_downloads') ? $validated['max_downloads'] ?? null : null,
        ]);

        $this->activity->log(Action::ShareLinkCreated, subject: $file);

        return back()->with('success', __('Public link created.'));
    }

    public function destroy(ShareLink $shareLink): RedirectResponse
    {
        $file = $shareLink->shareable;
        abort_unless($file instanceof File, 404);

        Gate::authorize('update', $file);

        $shareLink->delete();

        $this->activity->log(Action::ShareLinkRevoked, subject: $file);

        return back()->with('success', __('Public link revoked.'));
    }
}
