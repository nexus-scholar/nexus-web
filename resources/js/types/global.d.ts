import type { Auth } from '@/types/auth';
import type { WorkspaceContext } from '@/types/workspace';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            workspace: WorkspaceContext;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
