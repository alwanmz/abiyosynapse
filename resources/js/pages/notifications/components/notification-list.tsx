import { Card } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Notification } from '@/types/notification';
import { NotificationCard } from './notification-card';

interface NotificationListProps {
    notifications: Notification[];
    onMarkAsRead: (id: string) => void;
    onDelete: (id: string) => void;
    isProcessing: boolean;
}

export function NotificationList({
    notifications,
    onMarkAsRead,
    onDelete,
    isProcessing,
}: NotificationListProps) {
    return (
        <Card>
            <div className="p-6">
                <h2 className="mb-4 text-lg font-semibold">Recent Notifications</h2>
                <div className="space-y-3">
                    {notifications.map((notification, index) => (
                        <div key={notification.id}>
                            <NotificationCard
                                notification={notification}
                                onMarkAsRead={onMarkAsRead}
                                onDelete={onDelete}
                                isProcessing={isProcessing}
                            />
                            {index < notifications.length - 1 && <Separator />}
                        </div>
                    ))}
                </div>
            </div>
        </Card>
    );
}
