import { lazy, Suspense } from 'react';
import type { AuthLayoutProps } from '@/types';

const AuthSimpleLayout = lazy(
    () => import('@/layouts/auth/auth-simple-layout'),
);
const AuthSplitLayout = lazy(() => import('@/layouts/auth/auth-split-layout'));

export default function AuthLayout({
    title = '',
    description = '',
    variant = 'simple',
    children,
}: AuthLayoutProps) {
    const AuthLayoutTemplate =
        variant === 'split' ? AuthSplitLayout : AuthSimpleLayout;

    return (
        <Suspense fallback={null}>
            <AuthLayoutTemplate title={title} description={description}>
                {children}
            </AuthLayoutTemplate>
        </Suspense>
    );
}
