import { AppearanceSwitcher } from '@/components/appearance-switcher';
import { LocaleSwitcher } from '@/components/locale-switcher';
import { PoweredBy } from '@/components/powered-by';
import ProjectSendLogo from '@/components/projectsend-logo';
import { Link } from '@inertiajs/react';

interface AuthLayoutProps {
    children: React.ReactNode;
    name?: string;
    title?: string;
    description?: string;
}

export default function AuthSimpleLayout({ children, title, description }: AuthLayoutProps) {
    return (
        <div className="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4">
                        <Link href={route('home')} className="flex flex-col items-center gap-2 font-medium">
                            <ProjectSendLogo className="text-foreground mb-1 h-12 w-auto" />
                            <span className="sr-only">{title}</span>
                        </Link>

                        <div className="space-y-2 text-center">
                            <h1 className="text-xl font-medium">{title}</h1>
                            <p className="text-muted-foreground text-center text-sm">{description}</p>
                        </div>
                    </div>
                    {children}

                    <div className="flex flex-col items-center gap-3">
                        <div className="flex items-center gap-1">
                            <LocaleSwitcher />
                            <AppearanceSwitcher />
                        </div>
                        <PoweredBy className="text-muted-foreground/60 hover:text-muted-foreground text-xs transition-colors" />
                    </div>
                </div>
            </div>
        </div>
    );
}
