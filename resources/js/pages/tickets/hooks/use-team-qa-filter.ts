import { useMemo } from 'react';
import { usePage } from '@inertiajs/react';
import { User } from '@/types/user';
import { Project } from '@/types/ticket';

interface UseTeamQaFilterProps {
    projectId: number;
    projects: Project[];
}

export function useTeamQaFilter({ projectId, projects }: UseTeamQaFilterProps) {
    const { allUsers } = usePage<{ allUsers?: User[] }>().props;

    const teamQaTesters = useMemo(() => {
        const project = projects.find((p) => p.id === projectId);
        const teamUserIds = project?.team?.users?.map((u) => u.id) || [];

        return (
            allUsers?.filter(
                (user) =>
                    user.role?.name === 'quality_assurance' &&
                    teamUserIds.includes(user.id)
            ) || []
        );
    }, [projectId, projects, allUsers]);

    return teamQaTesters;
}
