import { useMemo } from 'react';

interface UseTimelineViewProps {
    viewStart: Date;
    totalWeeks: number;
}

interface TimelineViewInfo {
    viewEnd: Date;
    weekLabels: string[];
    months: number;
    weeks: number;
    startMonth: string;
    endMonth: string;
    startWeek: number;
    endWeek: number;
    quarterText: string;
    dateRange: string;
}

const MONTH_NAMES = [
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

function getWeekInMonth(date: Date): number {
    const firstDayOfMonth = new Date(date.getFullYear(), date.getMonth(), 1);
    const daysDiff = Math.floor(
        (date.getTime() - firstDayOfMonth.getTime()) / (1000 * 60 * 60 * 24),
    );
    return Math.ceil((daysDiff + 1) / 7);
}

export function useTimelineView({
    viewStart,
    totalWeeks,
}: UseTimelineViewProps): TimelineViewInfo {
    const viewEnd = useMemo(() => {
        const end = new Date(viewStart);
        end.setDate(end.getDate() + totalWeeks * 7);
        return end;
    }, [viewStart, totalWeeks]);

    const weekLabels = useMemo(() => {
        const labels: string[] = [];
        for (let i = 0; i < totalWeeks; i++) {
            const weekDate = new Date(viewStart);
            weekDate.setDate(weekDate.getDate() + i * 7);
            const month = MONTH_NAMES[weekDate.getMonth()];
            const day = weekDate.getDate();
            labels.push(`${month} ${day}`);
        }
        return labels;
    }, [viewStart, totalWeeks]);

    const timelineInfo = useMemo(() => {
        const months = Math.ceil(totalWeeks / 4);
        const startMonth = viewStart.toLocaleDateString('en-US', {
            month: 'short',
        });
        const endMonth = viewEnd.toLocaleDateString('en-US', {
            month: 'short',
        });

        const startWeek = getWeekInMonth(viewStart);
        const endWeek = getWeekInMonth(viewEnd);
        const startYear = viewStart.getFullYear();
        const endYear = viewEnd.getFullYear();

        const startQuarter = Math.ceil((viewStart.getMonth() + 1) / 3);
        const endQuarter = Math.ceil((viewEnd.getMonth() + 1) / 3);

        const quarterText =
            startQuarter === endQuarter
                ? `Q${startQuarter} ${startYear}`
                : startYear === endYear
                  ? `Q${startQuarter}-Q${endQuarter} ${startYear}`
                  : `Q${startQuarter} ${startYear} - Q${endQuarter} ${endYear}`;

        const dateRange = `${startMonth} W${startWeek} - ${endMonth} W${endWeek} (${quarterText})`;

        return {
            months,
            weeks: totalWeeks,
            startMonth,
            endMonth,
            startWeek,
            endWeek,
            quarterText,
            dateRange,
        };
    }, [viewStart, viewEnd, totalWeeks]);

    return {
        viewEnd,
        weekLabels,
        ...timelineInfo,
    };
}
