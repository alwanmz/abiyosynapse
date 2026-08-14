export interface Notification {
    id: string;
    type: 'role_assigned' | 'team_assigned' | 'team_removed' | 'ticket_assigned';
    title: string;
    message: string;
    actor: string;
    read: boolean;
    created_at: string;
    data: NotificationData;
}

export interface NotificationData {
    type: string;
    title: string;
    message: string;
    old_role?: string;
    new_role?: string;
    updated_by?: string;
    team_id?: number;
    team_name?: string;
    added_by?: string;
    removed_by?: string;
    ticket_id?: number;
    ticket_number?: string;
    ticket_title?: string;
    assigned_by?: string;
}

export interface NotificationsPageProps {
    notifications: Notification[];
}
