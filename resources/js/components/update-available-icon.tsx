import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { ArrowUpCircle } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { UpdateReleaseDialog } from '@/components/update-release-dialog';
import { useTranslation } from '@/hooks/use-translation';

export function UpdateAvailableIcon() {
    const { t } = useTranslation();
    const { update_notice } = usePage<SharedData>().props;
    const [open, setOpen] = useState(false);

    if (!update_notice) {
        return null;
    }

    return (
        <>
            <Button
                variant="ghost"
                size="icon"
                className="relative"
                aria-label={t('ProjectSend :version is available', { version: update_notice.version })}
                onClick={() => setOpen(true)}
            >
                <ArrowUpCircle className="size-5" />
                <span className="bg-primary absolute top-0.5 right-0.5 size-2.5 rounded-full" />
            </Button>
            <UpdateReleaseDialog notice={update_notice} open={open} onOpenChange={setOpen} />
        </>
    );
}
