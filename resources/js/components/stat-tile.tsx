import { Link } from '@inertiajs/react';
import { type LucideIcon } from 'lucide-react';

interface StatTileProps {
    label: string;
    value: string;
    hint?: string;
    icon: LucideIcon;
    accentClassName: string;
    iconClassName: string;
    href?: string;
    /** 0–100. Renders a slim usage bar under the hint, red past 90%. */
    progress?: number;
}

export function StatTile({ label, value, hint, icon: Icon, accentClassName, iconClassName, href, progress }: StatTileProps) {
    const content = (
        <>
            <div className="flex items-center gap-3">
                <div className={`flex size-9 shrink-0 items-center justify-center rounded-lg ${iconClassName}`}>
                    <Icon className="size-5" />
                </div>
                <div className="min-w-0">
                    <p className="text-muted-foreground text-sm">{label}</p>
                    <p className="text-2xl leading-tight font-semibold">{value}</p>
                </div>
            </div>
            {hint && <p className="text-muted-foreground mt-2 text-xs">{hint}</p>}
            {progress !== undefined && (
                <div className="bg-muted mt-2 h-1.5 w-full overflow-hidden rounded-full">
                    <div
                        className={`h-full rounded-full ${progress >= 90 ? 'bg-destructive' : 'bg-primary'}`}
                        style={{ width: `${Math.min(100, progress)}%` }}
                    />
                </div>
            )}
        </>
    );

    if (href) {
        return (
            <Link href={href} className={`block rounded-lg border p-4 transition hover:brightness-95 dark:hover:brightness-110 ${accentClassName}`}>
                {content}
            </Link>
        );
    }

    return <div className={`rounded-lg border p-4 ${accentClassName}`}>{content}</div>;
}
