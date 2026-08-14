import AppLogoIcon from '@/components/app-logo-icon';
import { LocaleSwitcher } from '@/components/locale-switcher';
import { PoweredBy } from '@/components/powered-by';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';

interface PublicLayoutDriveProps {
    children: React.ReactNode;
    title: string;
    description?: string;
}

/**
 * The "Drive" public theme: a Google Drive-inspired shell — a clean
 * white top bar with a thin border and a blue accent, full-width list
 * content below. Has a matching email theme under the same 'drive' key
 * (see ThemeRegistry's docblock on theme-pair parity) — this is the
 * public/portal half of that pair.
 */
export default function PublicLayoutDrive({ children, title, description }: PublicLayoutDriveProps) {
    const { name, branding } = usePage<SharedData>().props;

    return (
        <div className="flex min-h-svh flex-col bg-white dark:bg-neutral-950">
            <header className="flex items-center justify-between border-b border-neutral-200 px-4 py-3 md:px-8 dark:border-neutral-800">
                <Link href={route('home')} className="flex items-center gap-2 font-medium">
                    {branding?.logo_url ? (
                        <img src={branding.logo_url} alt={name} className="size-7 object-contain" />
                    ) : (
                        <AppLogoIcon className="size-7 text-blue-600" />
                    )}
                    <span className="text-sm font-medium text-neutral-800 dark:text-neutral-200">{name}</span>
                </Link>
                <LocaleSwitcher />
            </header>

            <main className="mx-auto w-full max-w-4xl flex-1 px-4 py-8 md:px-8">
                <div className="mb-5">
                    <h1 className="text-xl font-medium text-neutral-800 dark:text-neutral-200">{title}</h1>
                    {description && <p className="text-sm text-neutral-500 dark:text-neutral-400">{description}</p>}
                </div>

                {children}
            </main>

            <footer className="border-t border-neutral-200 px-4 py-3 text-center dark:border-neutral-800">
                <PoweredBy className="text-xs text-neutral-400 transition-colors hover:text-neutral-600 dark:hover:text-neutral-300" />
            </footer>
        </div>
    );
}
