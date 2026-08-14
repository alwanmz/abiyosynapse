import { Timeline } from '@/types/timeline';
import { phaseColors, typeColors } from '../constants/constants';

export function useTimelineFormatting(timeline: Timeline | null) {
    const formatDate = (date: string | null) => {
        if (!date) return '-';
        return new Date(date).toLocaleDateString('id-ID', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    };

    const calculateDuration = () => {
        if (!timeline?.start_date || !timeline?.end_date) return null;

        const start = new Date(timeline.start_date);
        const end = new Date(timeline.end_date);
        const diffTime = Math.abs(end.getTime() - start.getTime());
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        const weeks = Math.floor(diffDays / 7);
        const days = diffDays % 7;

        if (weeks > 0 && days > 0) {
            return `${weeks} minggu ${days} hari`;
        } else if (weeks > 0) {
            return `${weeks} minggu`;
        } else {
            return `${days} hari`;
        }
    };

    const getPhaseColor = () => {
        if (!timeline) return '';
        return timeline.phase
            ? phaseColors[timeline.phase]
            : typeColors[timeline.type];
    };

    return {
        formatDate,
        calculateDuration,
        phaseColor: getPhaseColor(),
    };
}
