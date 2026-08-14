import { Project } from '@/types/ticket';
import { useMemo } from 'react';

interface TeamMember {
    id: number;
    name: string;
}

export function useProjectTeamMembers(
    projects: Project[],
    projectId: string | undefined,
) {
    const selectedProject = useMemo(() => {
        if (!projectId) return null;
        return projects.find((p) => p.id.toString() === projectId) || null;
    }, [projects, projectId]);

    const teamMembers = useMemo<TeamMember[]>(() => {
        return selectedProject?.team?.users || [];
    }, [selectedProject]);

    return {
        selectedProject,
        teamMembers,
    };
}
