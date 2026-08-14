import { useCallback, useState } from 'react';

interface UseTimelineNavigationProps {
    totalWeeks: number;
    initialDate?: Date;
}

export function useTimelineNavigation({
    totalWeeks,
    initialDate = new Date(),
}: UseTimelineNavigationProps) {
    const [viewStart, setViewStart] = useState(() => {
        const date = new Date(initialDate);
        date.setHours(0, 0, 0, 0);
        return date;
    });

    const navigatePrevious = useCallback(() => {
        setViewStart((prev) => {
            const newDate = new Date(prev);
            newDate.setDate(newDate.getDate() - totalWeeks * 7);
            return newDate;
        });
    }, [totalWeeks]);

    const navigateNext = useCallback(() => {
        setViewStart((prev) => {
            const newDate = new Date(prev);
            newDate.setDate(newDate.getDate() + totalWeeks * 7);
            return newDate;
        });
    }, [totalWeeks]);

    const navigateToToday = useCallback(() => {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const dayOfWeek = today.getDay();
        const diff = dayOfWeek === 0 ? -6 : 1 - dayOfWeek;
        today.setDate(today.getDate() + diff);
        setViewStart(today);
    }, []);

    return {
        viewStart,
        navigatePrevious,
        navigateNext,
        navigateToToday,
    };
}
