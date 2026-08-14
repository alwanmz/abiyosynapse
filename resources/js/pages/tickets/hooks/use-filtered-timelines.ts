import { Timeline } from '@/types/ticket';
import { useMemo } from 'react';

export function useFilteredTimelines(
    timelines: Timeline[],
    projectId: string | undefined,
) {
    return useMemo(() => {
        if (!projectId) return [];
        return timelines.filter((t) => t.project_id.toString() === projectId);
    }, [timelines, projectId]);
}
