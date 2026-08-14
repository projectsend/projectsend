<?php

declare(strict_types=1);

namespace App\Modules\Clients\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use App\Modules\Clients\Notifications\ClientAccountApprovedNotification;
use App\Modules\Clients\Notifications\ClientAccountDeniedNotification;
use App\Modules\Identity\UserType;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The approval queue for self-registered clients (v1's
 * clients-requests.php): approve activates the account, deny deletes it.
 */
class AccountRequestsController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly Settings $settings,
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $filters = ['search' => $validated['search'] ?? null];

        $requests = User::query()
            ->where('type', UserType::Client)
            ->where('account_requested', true)
            ->when($filters['search'], fn (Builder $query, string $search) => $query->where(fn (Builder $q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('created_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $client): array => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'created_at' => $client->created_at?->toIso8601String(),
            ]);

        return Inertia::render('clients/requests', [
            'requests' => $requests->items(),
            'pagination' => Pagination::meta($requests),
            'filters' => $filters,
        ]);
    }

    public function approve(User $client): RedirectResponse
    {
        abort_unless($client->isClient() && $client->account_requested, 404);

        $client->forceFill([
            'active' => true,
            'account_requested' => false,
        ])->save();

        $this->activity->log(Action::ClientApproved, subject: $client);

        if ($this->settings->get(Setting::EmailNotificationsEnabled) === true) {
            $client->notify(new ClientAccountApprovedNotification);
        }

        return back()->with('success', __('Account request approved.'));
    }

    public function deny(User $client): RedirectResponse
    {
        abort_unless($client->isClient() && $client->account_requested, 404);

        $name = $client->name;
        $email = $client->email;
        $locale = $client->locale;
        // A denied request is removed outright (not soft-deleted) so the
        // person can register again with the same email address.
        $client->forceDelete();

        $this->activity->log(Action::ClientDenied, context: ['name' => $name]);

        if ($this->settings->get(Setting::EmailNotificationsEnabled) === true) {
            Notification::route('mail', $email)->notify(
                (new ClientAccountDeniedNotification($name))->locale($locale ?? app()->getLocale())
            );
        }

        return back()->with('success', __('Account request denied.'));
    }
}
