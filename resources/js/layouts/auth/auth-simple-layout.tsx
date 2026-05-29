import { Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-muted/40 p-6 md:p-10">
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-7">
                    <div className="flex flex-col items-center gap-5">
                        <Link
                            href={home()}
                            aria-label="Nexus Scholar home"
                            className="flex items-center gap-3 font-semibold"
                        >
                            <div className="flex size-9 items-center justify-center rounded-lg bg-brand text-brand-foreground shadow-sm">
                                <AppLogoIcon className="size-5" />
                            </div>
                            <span aria-hidden="true">Nexus Scholar</span>
                        </Link>

                        <div className="space-y-2 text-center">
                            <h1 className="text-xl font-semibold">{title}</h1>
                            <p className="text-sm text-balance text-muted-foreground">
                                {description}
                            </p>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
