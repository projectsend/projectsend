<?php

declare(strict_types=1);

namespace App\Modules\Audit\Listeners;

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLogger;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Events\Dispatcher;
use ProjectSend\CommunityModules\Modules\CustomAssets\Events\CustomAssetCreated;
use ProjectSend\CommunityModules\Modules\CustomAssets\Events\CustomAssetDeleted;
use ProjectSend\CommunityModules\Modules\CustomAssets\Events\CustomAssetToggled;
use ProjectSend\CommunityModules\Modules\CustomAssets\Events\CustomAssetUpdated;

/**
 * Translates the community-modules Custom Assets package's own events
 * into real ActivityLog entries. The package can't do this itself: its
 * events carry no dependency on this app's Action enum or
 * ActivityLogger (see CustomAssetCreated's docblock) so it stays
 * testable standalone — this listener is the other half of that
 * contract, living here instead.
 */
class LogCustomAssetActivity
{
    public function __construct(
        private readonly ActivityLogger $activity,
    ) {}

    public function handleCreated(CustomAssetCreated $event): void
    {
        $this->activity->log(Action::CustomAssetCreated, $this->actor($event->actor), $event->asset);
    }

    public function handleUpdated(CustomAssetUpdated $event): void
    {
        $this->activity->log(Action::CustomAssetUpdated, $this->actor($event->actor), $event->asset);
    }

    public function handleToggled(CustomAssetToggled $event): void
    {
        $this->activity->log(
            $event->enabled ? Action::CustomAssetEnabled : Action::CustomAssetDisabled,
            $this->actor($event->actor),
            $event->asset,
        );
    }

    public function handleDeleted(CustomAssetDeleted $event): void
    {
        $this->activity->log(Action::CustomAssetDeleted, $this->actor($event->actor), context: ['name' => $event->assetTitle]);
    }

    private function actor(?Authenticatable $actor): ?User
    {
        return $actor instanceof User ? $actor : null;
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            CustomAssetCreated::class => 'handleCreated',
            CustomAssetUpdated::class => 'handleUpdated',
            CustomAssetToggled::class => 'handleToggled',
            CustomAssetDeleted::class => 'handleDeleted',
        ];
    }
}
