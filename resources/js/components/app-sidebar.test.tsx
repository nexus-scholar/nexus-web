import type * as InertiaReact from '@inertiajs/react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { AppSidebar } from '@/components/app-sidebar';
import { SidebarProvider } from '@/components/ui/sidebar';
import { TooltipProvider } from '@/components/ui/tooltip';

const postMock = vi.hoisted(() => vi.fn());
const flushAllMock = vi.hoisted(() => vi.fn());
const mockPage = vi.hoisted(() => ({
    url: '/dashboard',
    props: {
        auth: {
            user: {
                id: 1,
                name: 'Dr. Lina Haddad',
                email: 'owner@nexusscholar.test',
                avatar: '',
                is_operator: false,
            },
        },
        workspace: {
            current: {
                id: 'shared-workspace',
                name: 'Evidence Synthesis Lab',
                role: 'owner',
                type: 'shared',
                suspended_at: null,
                settings_url: '/workspaces/shared-workspace/settings',
                members_url: '/workspaces/shared-workspace/members',
            },
            memberships: [
                {
                    id: 1,
                    role: 'owner',
                    role_label: 'Owner',
                    workspace: {
                        id: 'shared-workspace',
                        name: 'Evidence Synthesis Lab',
                        role: 'owner',
                        type: 'shared',
                        suspended_at: null,
                        settings_url: '/workspaces/shared-workspace/settings',
                        members_url: '/workspaces/shared-workspace/members',
                    },
                },
                {
                    id: 2,
                    role: 'owner',
                    role_label: 'Owner',
                    workspace: {
                        id: 'personal-workspace',
                        name: "Dr. Lina Haddad's Workspace",
                        role: 'owner',
                        type: 'personal',
                        suspended_at: null,
                        settings_url: '/workspaces/personal-workspace/settings',
                        members_url: '/workspaces/personal-workspace/members',
                    },
                },
            ],
        },
    },
}));

type MockLinkProps = {
    as?: string;
    children: ReactNode;
    className?: string;
    href: string | { url: string };
    onClick?: () => void;
    'data-test'?: string;
};

function toHref(href: MockLinkProps['href']): string {
    return typeof href === 'string' ? href : href.url;
}

vi.mock('@inertiajs/react', async (importOriginal) => {
    const actual = await importOriginal<typeof InertiaReact>();

    return {
        ...actual,
        Link: ({
            as,
            children,
            className,
            href,
            onClick,
            'data-test': dataTest,
        }: MockLinkProps) => {
            if (as === 'button') {
                return (
                    <button
                        className={className}
                        data-test={dataTest}
                        onClick={onClick}
                        type="button"
                    >
                        {children}
                    </button>
                );
            }

            return (
                <a
                    className={className}
                    data-test={dataTest}
                    href={toHref(href)}
                    onClick={onClick}
                >
                    {children}
                </a>
            );
        },
        router: {
            ...actual.router,
            flushAll: flushAllMock,
            post: postMock,
        },
        usePage: () => mockPage,
    };
});

function renderSidebar() {
    return render(
        <TooltipProvider>
            <SidebarProvider>
                <AppSidebar />
            </SidebarProvider>
        </TooltipProvider>,
    );
}

describe('AppSidebar', () => {
    beforeEach(() => {
        mockPage.url = '/dashboard';
        mockPage.props.auth.user.is_operator = false;
        postMock.mockClear();
        flushAllMock.mockClear();
    });

    it('renders Nexus product navigation without starter-kit footer links', () => {
        renderSidebar();

        expect(
            screen.getByRole('link', {
                name: /Nexus Scholar\s*Evidence reviews/i,
            }),
        ).toHaveAttribute('href', '/dashboard');
        expect(screen.queryByText('Repository')).not.toBeInTheDocument();
        expect(screen.queryByText('Documentation')).not.toBeInTheDocument();
        expect(screen.queryByText(/github/i)).not.toBeInTheDocument();
    });

    it('shows static workspaces and direct account actions', () => {
        renderSidebar();

        expect(
            screen.getByRole('button', {
                name: /Evidence Synthesis Lab\s*Owner/i,
            }),
        ).toBeDisabled();
        expect(
            screen.getByRole('button', {
                name: /Dr\. Lina Haddad's Workspace\s*Owner/i,
            }),
        ).toBeEnabled();
        expect(
            screen.getByRole('link', { name: /Dr\. Lina Haddad/i }),
        ).toHaveAttribute('href', '/settings/profile');
        expect(
            screen.getByRole('link', { name: /^Settings$/i }),
        ).toHaveAttribute('href', '/settings/profile');
        expect(
            screen.getByRole('button', { name: /Log out/i }),
        ).toBeInTheDocument();
    });

    it('shows operator navigation only to operator users', () => {
        const { rerender } = renderSidebar();

        expect(screen.queryByText('Operator users')).not.toBeInTheDocument();
        expect(
            screen.queryByText('Operator workspaces'),
        ).not.toBeInTheDocument();

        mockPage.props.auth.user.is_operator = true;
        rerender(
            <TooltipProvider>
                <SidebarProvider>
                    <AppSidebar />
                </SidebarProvider>
            </TooltipProvider>,
        );

        expect(screen.getByText('Operator users')).toBeInTheDocument();
        expect(screen.getByText('Operator workspaces')).toBeInTheDocument();
    });

    it('clears cached Inertia state before logout', async () => {
        const user = userEvent.setup();

        renderSidebar();
        await user.click(screen.getByRole('button', { name: /Log out/i }));

        expect(flushAllMock).toHaveBeenCalledOnce();
    });
});
