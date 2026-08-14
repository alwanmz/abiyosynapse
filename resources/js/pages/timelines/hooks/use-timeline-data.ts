import { Timeline } from '@/types/timeline';
import { useMemo } from 'react';

export function useTimelineStats(timelines: Timeline[]) {
    return useMemo(() => {
        const activeProjects = new Set(
            timelines
                .filter((t) => t.status === 'in_progress')
                .map((t) => t.project.id),
        ).size;

        const completedTimelines = timelines.filter(
            (t) => t.status === 'completed',
        ).length;

        const inDevelopment = timelines.filter(
            (t) => t.phase === 'development' && t.status === 'in_progress',
        ).length;

        const planningSprints = timelines.filter(
            (t) => t.type === 'sprint' && t.status === 'pending',
        ).length;

        return {
            activeProjects,
            completedTimelines,
            inDevelopment,
            planningSprints,
        };
    }, [timelines]);
}

export function useGroupedTimelines(timelines: Timeline[]) {
    return useMemo(() => {
        const grouped = new Map<
            number,
            { project: Timeline['project']; timelines: Timeline[] }
        >();

        timelines.forEach((timeline) => {
            const projectId = timeline.project.id;
            if (!grouped.has(projectId)) {
                grouped.set(projectId, {
                    project: timeline.project,
                    timelines: [],
                });
            }
            grouped.get(projectId)!.timelines.push(timeline);
        });

        return Array.from(grouped.values());
    }, [timelines]);
}

export function useWeekLabels(totalWeeks: number = 12) {
    return useMemo(() => {
        const labels: string[] = [];
        const startDate = new Date(2026, 0, 1);
        const monthNames = [
            'Jan',
            'Feb',
            'Mar',
            'Apr',
            'May',
            'Jun',
            'Jul',
            'Aug',
            'Sep',
            'Oct',
            'Nov',
            'Dec',
        ];

        for (let i = 0; i < totalWeeks; i++) {
            const weekDate = new Date(startDate);
            weekDate.setDate(startDate.getDate() + i * 7);
            const month = monthNames[weekDate.getMonth()];
            const weekOfMonth = Math.ceil(weekDate.getDate() / 7);
            labels.push(`${month} W${weekOfMonth}`);
        }

        return labels;
    }, [totalWeeks]);
}
