export type WorkspaceSummary = {
    id: string;
    name: string;
    slug: string;
    type: 'personal' | 'shared';
    role: 'owner' | 'admin' | 'member' | null;
    suspended_at: string | null;
    settings_url: string;
    members_url: string;
};

export type WorkspaceMembershipSummary = {
    id: number;
    role: 'owner' | 'admin' | 'member';
    role_label: string;
    workspace: WorkspaceSummary;
};

export type WorkspaceContext = {
    current: WorkspaceSummary | null;
    memberships: WorkspaceMembershipSummary[];
};
