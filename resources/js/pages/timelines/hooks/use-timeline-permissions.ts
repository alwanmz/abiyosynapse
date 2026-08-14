import { usePage } from '@inertiajs/react';

const TIMELINE_MANAGER_ROLES = [
    'super_admin',
    'project_manager',
    'admin',
];

export function useTimelinePermissions() {
    const { auth } = usePage().props as any;

    const canManageTimelines = TIMELINE_MANAGER_ROLES.includes(
        auth?.user?.role?.name
    );

    return {
        canManageTimelines,
    };
}
