<?php

declare(strict_types=1);

namespace App\Modules\Files;

use App\Modules\Files\Access\ClientIdentityScope;
use App\Models\User;
use App\Modules\Files\Events\FileBecameAvailable;
use App\Modules\Files\Folders\ClientHomeFolders;
use App\Modules\Files\Events\FileWasStored;
use App\Modules\Files\Listeners\AnnounceAvailableFile;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Files\Models\File;
use App\Modules\Files\Models\Folder;
use App\Modules\Files\Jobs\ScanFileJob;
use App\Modules\Files\Notifications\FileShareDigestNotification;
use App\Modules\Files\Notifications\FileSharedNotification;
use App\Modules\Files\Notifications\NewVersionAvailableNotification;
use App\Modules\Files\Notifications\NewVersionDigestNotification;
use App\Modules\Files\Scanning\ClamAvScanner;
use App\Modules\Files\Scanning\ScanningConfig;
use App\Modules\Files\Scanning\ScanStatus;
use App\Modules\Files\Scanning\VirusScanner;
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

        // Scoped, so the settings screen and the scanner it resolves share
        // one instance: that is what lets the Test button try the address
        // being typed rather than the one on file. Scoped rather than a
        // singleton so a queue worker starts each job with a clean one.
        $this->app->scoped(ScanningConfig::class);

        // One implementation ships, and the interface exists so the test
        // suite can state a verdict instead of producing a file that
        // provokes one — and so a commercial engine can be added later
        // without touching the job or the policy.
        $this->app->bind(VirusScanner::class, ClamAvScanner::class);
    }

    public function boot(): void
    {
        Gate::policy(File::class, FilePolicy::class);
        Gate::policy(Folder::class, FolderPolicy::class);

        $this->keepClientHomeFolders();

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

        // Two audiences, two types, because they need different words and
        // different links. Staff get a queue to act on; the person who
        // uploaded gets told their file did not go through.
        $registry = $this->app->make(NotificationTypeRegistry::class);

        $registry->register(new NotificationTypeDefinition(
            key: 'file_quarantined',
            label: 'A file was quarantined by the virus scanner',
            template: 'A virus was found in ":itemName", uploaded by :uploaderName',
            url: fn (array $data): string => route('files.quarantine'),
        ));

        $registry->register(new NotificationTypeDefinition(
            key: 'upload_blocked',
            label: 'One of your uploads was blocked',
            template: 'Your file ":itemName" was blocked: :threat',
            // Their own files list. Deliberately not the quarantine
            // screen, which they cannot open.
            url: fn (array $data): string => route('my-files.index'),
        ));

        // The other half of holding an announcement back while a file is
        // being checked — see FileSharing::assign and
        // AnnounceAvailableFile.
        Event::listen(FileBecameAvailable::class, AnnounceAvailableFile::class);

        // Every upload path converges on FileWasStored, so this is the
        // one place a scan is started from. Dispatched rather than run
        // inline: a 5 GB file takes minutes to read, and an upload must
        // not wait for it — the file is already withheld until the
        // verdict arrives.
        Event::listen(FileWasStored::class, function (FileWasStored $event): void {
            if ($event->file->scan_status === ScanStatus::Pending) {
                ScanFileJob::dispatch($event->file->id);
            }
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\ScanFilesCommand::class,
                Console\CheckMissingFilesCommand::class,
                Console\PurgeStaleUploadsCommand::class,
                Console\PurgeZipDownloadsCommand::class,
                Console\PurgeExpiredFilesCommand::class,
                Console\PurgeOrphanFilesCommand::class,
                Console\CheckFileVersionsCommand::class,
            ]);
        }
    }

    /**
     * Give a new client their home folder, and keep its name in step.
     *
     * On model events rather than in the handful of services that create
     * and rename clients, because there are more of those than anyone
     * remembers: ClientAccounts for the staff screens, the API and the
     * control plane; ClientProvisioning for self-registration, LDAP,
     * social sign-in and invitation redemption; the profile screen and two
     * update endpoints for a rename; AccountConversion for a staff member
     * becoming a client. A rule that had to be repeated in nine places
     * would be missing from the tenth.
     *
     * Here rather than in User::booted() so the identity model does not
     * have to know the files module exists -- the dependency points one
     * way, and this is the end that cares.
     *
     * Both listeners are cheap when the feature is off: `created` asks the
     * setting and returns, and `updated` asks whether the name actually
     * changed before it asks anything else.
     */
    private function keepClientHomeFolders(): void
    {
        User::created(function (User $user): void {
            if ($user->isClient()) {
                $this->app->make(ClientHomeFolders::class)->ensureFor($user);
            }
        });

        User::updated(function (User $user): void {
            // wasChanged, not isDirty: by `updated` the write has happened
            // and isDirty is empty. A save that did not touch the name --
            // which is most of them, every sign-in timestamp included --
            // costs one array lookup and stops here.
            if ($user->isClient() && $user->wasChanged('name')) {
                $this->app->make(ClientHomeFolders::class)->syncName($user);
            }
        });
    }
}
