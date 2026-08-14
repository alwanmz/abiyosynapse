import {
    IconBell,
    IconTicket,
    IconUserPlus,
    IconUsers,
} from '@tabler/icons-react';

export interface NotificationStyle {
    icon: typeof IconBell;
    iconColor: string;
    bgColor: string;
}

export function useNotificationStyle(type: string): NotificationStyle {
    switch (type) {
        case 'role_assigned':
            return {
                icon: IconUserPlus,
                iconColor: 'text-blue-500',
                bgColor: 'bg-blue-50 dark:bg-blue-950/20',
            };
        case 'team_assigned':
            return {
                icon: IconUsers,
                iconColor: 'text-green-500',
                bgColor: 'bg-green-50 dark:bg-green-950/20',
            };
        case 'team_removed':
            return {
                icon: IconUsers,
                iconColor: 'text-red-500',
                bgColor: 'bg-red-50 dark:bg-red-950/20',
            };
        case 'ticket_assigned':
            return {
                icon: IconTicket,
                iconColor: 'text-purple-500',
                bgColor: 'bg-purple-50 dark:bg-purple-950/20',
            };
        default:
            return {
                icon: IconBell,
                iconColor: 'text-gray-500',
                bgColor: 'bg-gray-50 dark:bg-gray-950/20',
            };
    }
}
