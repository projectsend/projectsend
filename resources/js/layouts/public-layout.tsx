import AppLogoIcon from '@/components/app-logo-icon';
import { LocaleSwitcher } from '@/components/locale-switcher';
import { PoweredBy } from '@/components/powered-by';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';

interface PublicLayoutProps {
    children: React.ReactNode;
    title: string;
    description?: string;
}

/**
 * Guest layout for the public group listing — unlike auth-layout's narrow
 * single-card frame (built for one link/button), this needs a wider body
 * to hold a list of groups/files. Same guest header treatment as
 * auth-simple-layout, reused rather than duplicated.
 */
export default function PublicLayout({ children, title, description }: PublicLayoutProps) {
    const { name, branding } = usePage<SharedData>().props;

    return (
        <div className="bg-background flex min-h-svh flex-col items-center gap-8 p-6 md:p-10">
            <div className="flex w-full max-w-3xl flex-col gap-8">
                <div className="flex flex-col items-center gap-4">
                    <Link href={route('home')} className="flex flex-col items-center gap-2 font-medium">
                        {branding?.logo_url ? (
                            <img src={branding.logo_url} alt={name} className="h-12 w-auto object-contain" />
                        ) : (
                            <AppLogoIcon className="text-foreground h-12 w-auto" />
                        )}
                        <span className="text-foreground text-lg font-semibold">{name}</span>
                        <span className="sr-only">{title}</span>
                    </Link>

                    <div className="space-y-2 text-center">
                        <h1 className="text-xl font-medium">{title}</h1>
                        {description && <p className="text-muted-foreground text-center text-sm">{description}</p>}
                    </div>
                </div>

                {children}

                <div className="flex flex-col items-center gap-3">
                    <LocaleSwitcher />
                    <PoweredBy className="text-muted-foreground/60 hover:text-muted-foreground text-xs transition-colors" />
                </div>
            </div>
        </div>
    );
}
