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
                iconColor: 'text-nx-andon-info',
                bgColor: 'bg-nx-andon-info-bg',
            };
        case 'team_assigned':
            return {
                icon: IconUsers,
                iconColor: 'text-nx-andon-run',
                bgColor: 'bg-nx-andon-run-bg',
            };
        case 'team_removed':
            return {
                icon: IconUsers,
                iconColor: 'text-nx-andon-stop',
                bgColor: 'bg-nx-andon-stop-bg',
            };
        case 'ticket_assigned':
            return {
                icon: IconTicket,
                iconColor: 'text-nx-andon-info',
                bgColor: 'bg-nx-andon-info-bg',
            };
        default:
            return {
                icon: IconBell,
                iconColor: 'text-nx-andon-idle',
                bgColor: 'bg-nx-andon-idle-bg',
            };
    }
}
