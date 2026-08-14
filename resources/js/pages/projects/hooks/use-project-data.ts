import { Project } from '@/types/project';

export function useProjectFilters(
    projects: Project[],
    searchQuery: string,
    selectedStatus: string,
) {
    return projects.filter((project) => {
        const matchSearch =
            searchQuery === '' ||
            project.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            project.description
                ?.toLowerCase()
                .includes(searchQuery.toLowerCase());
        const matchStatus =
            selectedStatus === 'all' || project.status === selectedStatus;
        return matchSearch && matchStatus;
    });
}

export function useProjectStats(projects: Project[]) {
    const totalProjects = projects.length;
    const inProgressProjects = projects.filter(
        (p) => p.status === 'in_progress',
    ).length;
    const completedProjects = projects.filter(
        (p) => p.status === 'completed',
    ).length;
    const totalMembers = new Set(
        projects.flatMap((p) => p.team.members.map((m) => m.id)),
    ).size;

    return {
        totalProjects,
        inProgressProjects,
        completedProjects,
        totalMembers,
    };
}
