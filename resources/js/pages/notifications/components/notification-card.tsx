import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Notification } from '@/types/notification';
import { useNotificationStyle } from '../hooks/use-notification-style';
import { formatTimestamp } from '../utils/format-timestamp';

interface NotificationCardProps {
    notification: Notification;
    onMarkAsRead: (id: string) => void;
    onDelete: (id: string) => void;
    isProcessing: boolean;
}

export function NotificationCard({
    notification,
    onMarkAsRead,
    onDelete,
    isProcessing,
}: NotificationCardProps) {
    const style = useNotificationStyle(notification.type);
    const Icon = style.icon;

    return (
        <div
            className={`flex gap-4 p-4 transition-colors hover:bg-muted/50 ${!notification.read ? style.bgColor : ''
                }`}
        >
            <div className="flex-shrink-0">
                <div className={`rounded-full bg-background p-2 ${style.iconColor}`}>
                    <Icon className="h-5 w-5" />
                </div>
            </div>

            <div className="flex-1 space-y-1">
                <div className="flex items-start justify-between gap-2">
                    <div className="flex-1">
                        <div className="flex items-center gap-2">
                            <h3 className="text-sm font-semibold">
                                {notification.title}
                            </h3>
                            {!notification.read && (
                                <Badge variant="default" className="h-5 px-2 text-xs">
                                    New
                                </Badge>
                            )}
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {notification.message}
                        </p>
                        <div className="mt-2 flex items-center gap-2 text-xs text-muted-foreground">
                            <Avatar className="h-5 w-5">
                                <AvatarFallback className="text-xs">
                                    {notification.actor.charAt(0)}
                                </AvatarFallback>
                            </Avatar>
                            <span>{notification.actor}</span>
                            <span>•</span>
                            <span>{formatTimestamp(notification.created_at)}</span>
                        </div>
                    </div>
                    <div className="flex items-center gap-1">
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-8"
                            onClick={() => onMarkAsRead(notification.id)}
                            disabled={isProcessing}
                        >
                            {notification.read ? 'Unread' : 'Read'}
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-8 text-destructive hover:text-destructive"
                            onClick={() => onDelete(notification.id)}
                            disabled={isProcessing}
                        >
                            Delete
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    );
}
