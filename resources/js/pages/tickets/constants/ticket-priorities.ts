export const TICKET_PRIORITIES = {
    highest: {
        label: 'Tertinggi',
        color: 'text-red-600',
        bgColor: 'bg-red-50',
        badgeClassName: 'bg-red-100 text-red-700 border-red-200',
    },
    high: {
        label: 'Tinggi',
        color: 'text-orange-600',
        bgColor: 'bg-orange-50',
        badgeClassName: 'bg-orange-100 text-orange-700 border-orange-200',
    },
    medium: {
        label: 'Sedang',
        color: 'text-yellow-600',
        bgColor: 'bg-yellow-50',
        badgeClassName: 'bg-yellow-100 text-yellow-700 border-yellow-200',
    },
    low: {
        label: 'Rendah',
        color: 'text-green-600',
        bgColor: 'bg-green-50',
        badgeClassName: 'bg-green-100 text-green-700 border-green-200',
    },
    lowest: {
        label: 'Terendah',
        color: 'text-gray-600',
        bgColor: 'bg-gray-50',
        badgeClassName: 'bg-gray-100 text-gray-700 border-gray-200',
    },
} as const;

export type TicketPriority = keyof typeof TICKET_PRIORITIES;

