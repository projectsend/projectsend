import AppLogoIcon from '@/components/app-logo-icon';
import { LocaleSwitcher } from '@/components/locale-switcher';
import { PoweredBy } from '@/components/powered-by';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';

interface PublicLayoutGalleryProps {
    children: React.ReactNode;
    title: string;
    description?: string;
}

/**
 * The "Gallery" public theme: a bold, full-bleed banner (rather than
 * Default's centered card header or Compact's thin bar) framing a wide
 * grid body — the structural shell the index/group/file pages below
 * build on.
 */
export default function PublicLayoutGallery({ children, title, description }: PublicLayoutGalleryProps) {
    const { name, branding } = usePage<SharedData>().props;

    return (
        <div className="bg-background flex min-h-svh flex-col">
            <div className="from-violet-700 to-violet-900 bg-gradient-to-br px-6 py-14 text-center text-white md:px-10">
                <Link href={route('home')} className="inline-flex flex-col items-center gap-3">
                    {branding?.logo_url ? (
                        <img src={branding.logo_url} alt={name} className="h-14 w-auto object-contain brightness-0 invert" />
                    ) : (
                        <AppLogoIcon className="h-14 w-auto text-white" />
                    )}
                    <span className="text-sm font-semibold tracking-wide text-white/80 uppercase">{name}</span>
                </Link>
                <h1 className="mt-6 text-2xl font-semibold">{title}</h1>
                {description && <p className="mx-auto mt-2 max-w-xl text-sm text-white/80">{description}</p>}
            </div>

            <main className="w-full flex-1 px-6 py-10 md:px-10">{children}</main>

            <footer className="flex flex-col items-center gap-3 border-t px-4 py-6">
                <LocaleSwitcher />
                <PoweredBy className="text-muted-foreground/60 hover:text-muted-foreground text-xs transition-colors" />
            </footer>
        </div>
    );
}
