import { Timeline } from '@/types/timeline';

interface TimelinePosition {
    left: number;
    width: number;
}

export function useTimelinePosition(viewStart: Date, totalWeeks: number = 12) {
    const weekWidth = 100 / totalWeeks;

    const getTimelinePosition = (timeline: Timeline): TimelinePosition | null => {
        if (!timeline.start_date || !timeline.end_date) return null;

        const start = new Date(timeline.start_date);
        const end = new Date(timeline.end_date);

        const startWeek =
            Math.max(
                0,
                Math.floor(
                    (start.getTime() - viewStart.getTime()) /
                    (7 * 24 * 60 * 60 * 1000),
                ),
            ) + 1;
        const endWeek =
            Math.min(
                totalWeeks,
                Math.ceil(
                    (end.getTime() - viewStart.getTime()) /
                    (7 * 24 * 60 * 60 * 1000),
                ),
            ) + 1;

        const duration = Math.max(0.5, endWeek - startWeek);

        return {
            left: (startWeek - 1) * weekWidth,
            width: duration * weekWidth,
        };
    };

    return {
        getTimelinePosition,
        weekWidth,
        totalWeeks,
    };
}
