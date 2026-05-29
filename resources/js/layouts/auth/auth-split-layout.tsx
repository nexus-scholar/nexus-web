import { Link, usePage } from '@inertiajs/react';
import authEvidenceWorkspace from '@/assets/auth-evidence-workspace.webp';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div
            className="grid min-h-svh bg-background lg:grid-cols-2"
            data-test="auth-split-layout"
        >
            <div className="flex flex-col gap-4 p-6 md:p-10">
                <div className="flex justify-center gap-2 md:justify-start">
                    <Link
                        href={home()}
                        aria-label="Nexus Scholar home"
                        className="flex items-center gap-3 font-semibold"
                    >
                        <span className="flex size-9 items-center justify-center rounded-lg bg-brand text-brand-foreground shadow-sm">
                            <AppLogoIcon className="size-5" />
                        </span>
                        <span aria-hidden="true">{name}</span>
                    </Link>
                </div>

                <div className="flex flex-1 items-center justify-center">
                    <div className="w-full max-w-sm space-y-6">
                        <div className="space-y-2 text-center">
                            <h1 className="text-xl font-semibold">{title}</h1>
                            <p className="text-sm text-balance text-muted-foreground">
                                {description}
                            </p>
                        </div>
                        {children}
                    </div>
                </div>
            </div>

            <div className="relative hidden overflow-hidden border-l bg-muted lg:block">
                <img
                    src={authEvidenceWorkspace}
                    alt=""
                    data-test="auth-visual-image"
                    className="absolute inset-0 h-full w-full object-cover"
                    aria-hidden="true"
                />
                <div className="absolute inset-0 bg-background/10" />
            </div>
        </div>
    );
}
