import AppLogoIcon from '@/components/app-logo-icon';
import { LocaleSwitcher } from '@/components/locale-switcher';
import { PoweredBy } from '@/components/powered-by';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';

interface PublicLayoutCompactProps {
    children: React.ReactNode;
    title: string;
    description?: string;
}

/**
 * The "Compact" public theme: a thin top bar (logo/name left, locale
 * switcher right) and full-width content — no centered hero, denser than
 * the Default theme. A genuinely different structural shell, not just a
 * recolored Default.
 */
export default function PublicLayoutCompact({ children, title, description }: PublicLayoutCompactProps) {
    const { name, branding } = usePage<SharedData>().props;

    return (
        <div className="bg-background flex min-h-svh flex-col">
            <header className="flex items-center justify-between border-b px-4 py-2.5 md:px-8">
                <Link href={route('home')} className="flex items-center gap-2 font-medium">
                    {branding?.logo_url ? (
                        <img src={branding.logo_url} alt={name} className="size-6 object-contain" />
                    ) : (
                        <AppLogoIcon className="text-foreground size-6" />
                    )}
                    <span className="text-foreground text-sm font-semibold">{name}</span>
                </Link>
                <LocaleSwitcher />
            </header>

            <main className="mx-auto w-full max-w-4xl flex-1 px-4 py-6 md:px-8">
                <div className="mb-4">
                    <h1 className="text-lg font-medium">{title}</h1>
                    {description && <p className="text-muted-foreground text-sm">{description}</p>}
                </div>

                {children}
            </main>

            <footer className="border-t px-4 py-2 text-center md:px-8">
                <PoweredBy className="text-muted-foreground/60 hover:text-muted-foreground text-xs transition-colors" />
            </footer>
        </div>
    );
}
