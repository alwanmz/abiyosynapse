import { useMemo } from 'react';
import { Timeline } from '@/types/timeline';

export function useExistingSprints(
    timelines: Timeline[],
    projectId: string
) {
    const existingSprints = useMemo(() => {
        if (!projectId) return [];

        const projectTimelines = timelines.filter(
            (t) => t.project.id.toString() === projectId
        );

        const sprints = projectTimelines
            .map((t) => t.sprint_number)
            .filter((num): num is number => num !== null && num !== undefined);

        return Array.from(new Set(sprints)).sort((a, b) => a - b);
    }, [timelines, projectId]);

    return existingSprints;
}
