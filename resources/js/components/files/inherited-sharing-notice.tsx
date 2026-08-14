import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { useTranslation } from '@/hooks/use-translation';
import { ExternalLink, History } from 'lucide-react';

export interface SharingRoot {
    id: number;
    name: string;
    url: string;
}

interface InheritedSharingNoticeProps {
    root: SharingRoot;
    canUpdateRoot: boolean;
}

/**
 * Shown wherever a revision's recipients would otherwise be editable.
 *
 * A revision holds no recipients of its own — it is shared with exactly the
 * people the original is shared with — so the Sharing tab replaces its
 * controls with this. The assigned lists stay visible beside it, read-only:
 * hiding who has the file would make the tab useless rather than simpler,
 * and that information is not secret from someone who can already edit the
 * file.
 *
 * The link out is conditional on purpose. The original may be a colleague's
 * upload the viewer cannot edit, or sit outside a client-scoped staffer's
 * library — following the link would land on a 404 with no way back to the
 * file they were actually looking at. Saying so is worse than a link only
 * when there is a link to give.
 */
export function InheritedSharingNotice({ root, canUpdateRoot }: InheritedSharingNoticeProps) {
    const { t } = useTranslation();

    return (
        <Alert>
            <History className="size-4" />
            <AlertTitle>{t('Shared through ":name"', { name: root.name })}</AlertTitle>
            <AlertDescription className="grid gap-2">
                <p>
                    {t(
                        'This file is a new version of ":name", so it is shared with the same people. Changing who can see it means changing that file.',
                        {
                            name: root.name,
                        },
                    )}
                </p>
                {canUpdateRoot ? (
                    <a
                        href={root.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-foreground inline-flex w-fit items-center gap-1 text-sm font-medium underline underline-offset-4"
                    >
                        {t('Edit sharing on ":name"', { name: root.name })}
                        <ExternalLink className="size-3.5" />
                    </a>
                ) : (
                    <p className="text-muted-foreground text-sm">{t("You don't have permission to change who that file is shared with.")}</p>
                )}
            </AlertDescription>
        </Alert>
    );
}
