<?php

declare(strict_types=1);

namespace App\Modules\Clients;

use App\Modules\Notifications\NotificationTypeDefinition;
use App\Modules\Notifications\NotificationTypeRegistry;
use Illuminate\Support\ServiceProvider;

class ClientsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // In-app only, the same reasoning client_uploaded gives: email for
        // this event is already sent separately, to whatever raw addresses
        // Setting::AdminNotificationEmails lists, via
        // AdminClientRegisteredNotification. Routing it through Notifier's
        // mail dispatch as well would risk double-emailing any staff member
        // who also appears in that list.
        //
        // One type for both doors, deliberately. A client arriving through
        // the public form and one arriving through an invitation are the
        // same event to the person being told — an account now exists that
        // did not — and a second type would buy nothing: preferences here
        // govern email only (see NotificationPreferences), so it could not
        // be switched off separately, and which door it came through is one
        // click away in the activity log and on the invitations screen.
        $this->app->make(NotificationTypeRegistry::class)->register(new NotificationTypeDefinition(
            key: 'client_registered',
            label: 'A new client registered an account',
            template: ':clientName (:clientEmail) registered a client account',
            // The list, filtered to this address — not clients.edit, which
            // is gated by edit_clients while the recipients below are chosen
            // by manage_clients. A notification that refuses the person it
            // was sent to is worse than one that lands a click short.
            url: fn (array $data): string => route('clients.index', ['search' => $data['clientEmail']]),
        ));
    }
}
