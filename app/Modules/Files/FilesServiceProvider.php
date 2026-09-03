<?php

declare(strict_types=1);

namespace App\Modules\Files;

use App\Modules\Files\Access\ClientIdentityScope;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Notifications\FileShareDigestNotification;
use App\Modules\Files\Notifications\FileSharedNotification;
use App\Modules\Files\Notifications\NewVersionAvailableNotification;
use App\Modules\Files\Notifications\NewVersionDigestNotification;
use App\Modules\Files\Thumbnails\Events\ImageRenderingChanged;
use App\Modules\Files\Thumbnails\RenderedImageCache;
use App\Modules\Notifications\NotificationTypeDefinition;
use App\Modules\Notifications\NotificationTypeRegistry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class FilesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped rather than transient: the library query it builds costs
        // several lookups per assigned client, and the policies ask for it
        // once per row on a listing — Gate resolves a fresh policy for
        // every check, so without this the instance memo would never be
        // reached twice. Scoped rather than a singleton so a long-lived
        // queue worker starts each job with an empty memo.
        $this->app->scoped(StaffLibraryScope::class);

        // Same lifetime, same reason: the identity rule memoises a roster
        // per viewer and the file listings ask it once per row.
        $this->app->scoped(ClientIdentityScope::class);
    }

    public function boot(): void
    {
        Gate::policy(File::class, FilePolicy::class);
        Gate::policy(Folder::class, FolderPolicy::class);

        // Cached renditions are written once and never revisited, so
        // whoever changes how they render has to say so — otherwise the
        // change is invisible on every file anyone has already looked at.
        // See ImageRenderingChanged for why it carries no payload.
        Event::listen(ImageRenderingChanged::class, function (): void {
            $this->app->make(RenderedImageCache::class)->flush();
        });

        // Emailed by the debounced digest rather than by Notifier, so a
        // burst of shares is one message — hence digestMail rather than
        // mailNotification; setting both would send twice. Defaulted on,
        // which is what it has always done, but it is now a type the
        // recipient can switch off for themselves: before this it emailed
        // through a pipeline the preference screen could not see.
        $this->app->make(NotificationTypeRegistry::class)->register(new NotificationTypeDefinition(
            key: 'file_shared',
            label: 'A file or folder was shared with you',
            template: ':itemName was shared with you',
            defaultEmailEnabled: true,
            digestMail: FileSharedNotification::class,
            digestMailMany: FileShareDigestNotification::class,
        ));

        // In-app only, same reasoning as file_shared above — email for
        // this event is already sent separately, to whatever raw
        // addresses Setting::AdminNotificationEmails lists (which may
        // not even correspond to real staff accounts), via
        // AdminClientUploadedNotification. Routing it through Notifier's
        // mail dispatch too would risk double-emailing any staff member
        // who happens to also be in that address list.
        $this->app->make(NotificationTypeRegistry::class)->register(new NotificationTypeDefinition(
            key: 'client_uploaded',
            label: 'A client uploaded a file',
            template: ':clientName uploaded ":itemName"',
            url: fn (array $data) => route('files.edit', $data['fileId']),
        ));

        // Digested rather than sent one-per-event, same reasoning as
        // file_shared: staff re-issuing a whole set of drawings at once is
        // the ordinary case for this type, not the exception.
        //
        // Its recipients are the people who could already see BOTH files
        // before the link was made — resolved in FileVersions::link(), and
        // resolved there BEFORE the assignment merge, so that anyone newly
        // reached by the merge gets file_shared and not both.
        $this->app->make(NotificationTypeRegistry::class)->register(new NotificationTypeDefinition(
            key: 'file_new_version',
            label: 'A newer version of a file I have is available',
            template: 'A newer version of ":previousName" is available: :itemName',
            defaultEmailEnabled: true,
            digestMail: NewVersionAvailableNotification::class,
            digestMailMany: NewVersionDigestNotification::class,
            url: fn (array $data): string => route('my-files.index'),
        ));

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\PurgeStaleUploadsCommand::class,
                Console\PurgeZipDownloadsCommand::class,
                Console\PurgeExpiredFilesCommand::class,
                Console\PurgeOrphanFilesCommand::class,
                Console\CheckFileVersionsCommand::class,
            ]);
        }
    }
}
