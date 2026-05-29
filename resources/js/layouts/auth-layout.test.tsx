import { render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import AuthLayout from '@/layouts/auth-layout';

vi.mock('@inertiajs/react', () => ({
    Link: ({
        children,
        href,
        ...props
    }: {
        children: ReactNode;
        href: string;
    }) => (
        <a href={href} {...props}>
            {children}
        </a>
    ),
    usePage: () => ({
        props: {
            name: 'Nexus Scholar',
        },
    }),
}));

vi.mock('@/routes', () => ({
    home: () => '/',
}));

describe('AuthLayout', () => {
    it('uses the simple auth shell by default', async () => {
        render(
            <AuthLayout title="Reset password" description="Choose a password.">
                <div>Reset form</div>
            </AuthLayout>,
        );

        expect(await screen.findByText('Reset password')).toBeInTheDocument();
        expect(
            screen.queryByTestId('auth-split-layout'),
        ).not.toBeInTheDocument();
    });

    it('uses the split auth shell when requested', async () => {
        const { container } = render(
            <AuthLayout
                title="Create your Nexus Scholar account"
                description="Start a workspace for your review team."
                variant="split"
            >
                <div>Register form</div>
            </AuthLayout>,
        );

        expect(
            await screen.findByTestId('auth-split-layout'),
        ).toBeInTheDocument();
        expect(
            container.querySelector('[data-test="auth-visual-image"]'),
        ).toBeInTheDocument();
    });
});
