<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\InAppNotification;
use App\Modules\Notifications\NotificationTypeRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class NotificationsController extends Controller
{
    public function __construct(
        private readonly NotificationTypeRegistry $types,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        assert($user !== null);

        $entries = InAppNotification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(25);

        return Inertia::render('notifications/index', [
            'entries' => $entries->getCollection()->map(fn (InAppNotification $entry): array => $this->present($entry))->all(),
            'pagination' => [
                'page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'prev' => $entries->previousPageUrl(),
                'next' => $entries->nextPageUrl(),
                'total' => $entries->total(),
            ],
        ]);
    }

    /**
     * Plain JSON for the bell dropdown — a widget mounted across every
     * page, not owned by one, so it fetches directly rather than riding
     * an Inertia page visit.
     */
    public function recent(Request $request): JsonResponse
    {
        $user = $request->user();
        assert($user !== null);

        $entries = InAppNotification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        return response()->json([
            'entries' => $entries->map(fn (InAppNotification $entry): array => $this->present($entry))->all(),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        assert($user !== null);

        $count = InAppNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return response()->json(['count' => $count]);
    }

    public function markRead(Request $request, InAppNotification $notification): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $notification);

        $notification->update(['read_at' => now()]);

        return $request->wantsJson() ? response()->json(['ok' => true]) : back();
    }

    public function markUnread(Request $request, InAppNotification $notification): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $notification);

        $notification->update(['read_at' => null]);

        return $request->wantsJson() ? response()->json(['ok' => true]) : back();
    }

    public function markAllRead(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        assert($user !== null);

        InAppNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $request->wantsJson() ? response()->json(['ok' => true]) : back();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(InAppNotification $entry): array
    {
        $type = $this->types->get($entry->type);
        $data = $entry->data ?? [];

        return [
            'id' => $entry->id,
            'created_at' => $entry->created_at->toIso8601String(),
            'read_at' => $entry->read_at?->toIso8601String(),
            'template' => $type->template ?? $entry->type,
            'replacements' => $this->replacements($data),
            'url' => $type?->url !== null ? ($type->url)($data) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function replacements(array $data): array
    {
        $replacements = [];

        foreach ($data as $key => $value) {
            if (is_scalar($value)) {
                $replacements[$key] = (string) $value;
            }
        }

        return $replacements;
    }
}
