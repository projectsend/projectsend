import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';

import { NotificationPanelContent } from '@/components/notification-panel-content';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useNotificationPoll } from '@/hooks/use-notification-poll';
import { useTranslation } from '@/hooks/use-translation';

export function NotificationBell() {
    const { t } = useTranslation();
    const { pending } = usePage<SharedData>().props;
    const { count, setCount } = useNotificationPoll(pending.notifications_unread ?? 0);

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="relative" aria-label={t('Notifications')}>
                    <Bell className="size-5" />
                    {count > 0 && (
                        <span className="bg-primary text-primary-foreground absolute top-0.5 right-0.5 flex size-4 items-center justify-center rounded-full text-[10px] font-semibold">
                            {count > 9 ? '9+' : count}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="p-0">
                <NotificationPanelContent onUnreadCountChange={(delta) => setCount((current) => Math.max(0, current + delta))} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
