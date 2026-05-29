import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { SidebarProvider } from '@/components/ui/sidebar';
import { TooltipProvider } from '@/components/ui/tooltip';
import { WorkspaceSwitcher } from '@/components/workspace-switcher';

const postMock = vi.hoisted(() => vi.fn());
const mockPage = vi.hoisted(() => ({
    props: {
        workspace: {
            current: {
                id: 'shared-workspace',
                name: 'Evidence Synthesis Lab',
                role: 'admin',
                type: 'shared',
            },
            memberships: [
                {
                    id: 1,
                    role_label: 'Admin',
                    workspace: {
                        id: 'shared-workspace',
                        name: 'Evidence Synthesis Lab',
                        type: 'shared',
                    },
                },
                {
                    id: 2,
                    role_label: 'Owner',
                    workspace: {
                        id: 'personal-workspace',
                        name: 'Personal Workspace',
                        type: 'personal',
                    },
                },
            ],
        },
    },
}));

vi.mock('@inertiajs/react', () => ({
    router: {
        post: postMock,
    },
    usePage: () => mockPage,
}));

function renderWithSidebar(children: ReactNode) {
    return render(
        <TooltipProvider>
            <SidebarProvider>{children}</SidebarProvider>
        </TooltipProvider>,
    );
}

describe('WorkspaceSwitcher', () => {
    it('renders the active workspace and role', () => {
        renderWithSidebar(<WorkspaceSwitcher />);

        expect(screen.getByText('Evidence Synthesis Lab')).toBeInTheDocument();
        expect(screen.getByText('Admin')).toBeInTheDocument();
    });

    it('posts the selected workspace when the user switches context', async () => {
        const user = userEvent.setup();

        renderWithSidebar(<WorkspaceSwitcher />);

        await user.click(
            screen.getByRole('button', { name: /Personal Workspace/i }),
        );

        expect(postMock).toHaveBeenCalledWith(
            '/workspaces/switch',
            { workspace_id: 'personal-workspace' },
            { preserveScroll: true },
        );
    });
});
