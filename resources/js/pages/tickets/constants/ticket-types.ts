import {
    IconBug,
    IconBulb,
    IconChecklist,
    IconFileText,
    IconSparkles,
} from '@tabler/icons-react';

export const TICKET_TYPES = {
    bug: {
        label: 'Bug',
        icon: IconBug,
        className: 'bg-red-100 text-red-700 border-red-200',
    },
    feature: {
        label: 'Fitur',
        icon: IconSparkles,
        className: 'bg-purple-100 text-purple-700 border-purple-200',
    },
    task: {
        label: 'Tugas',
        icon: IconChecklist,
        className: 'bg-blue-100 text-blue-700 border-blue-200',
    },
    improvement: {
        label: 'Peningkatan',
        icon: IconBulb,
        className: 'bg-yellow-100 text-yellow-700 border-yellow-200',
    },
    documentation: {
        label: 'Dokumentasi',
        icon: IconFileText,
        className: 'bg-gray-100 text-gray-700 border-gray-200',
    },
} as const;

export type TicketType = keyof typeof TICKET_TYPES;
