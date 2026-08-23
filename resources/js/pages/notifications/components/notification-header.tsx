import { Button } from '@/components/ui/button';
import { IconBellOff, IconCheck } from '@tabler/icons-react';

interface NotificationHeaderProps {
    unreadCount: number;
    totalCount: number;
    onMarkAllAsRead: () => void;
    onClearAll: () => void;
    isProcessing: boolean;
}

export function NotificationHeader({
    unreadCount,
    totalCount,
    onMarkAllAsRead,
    onClearAll,
    isProcessing,
}: NotificationHeaderProps) {
    return (
        <div className="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 className="text-2xl font-semibold tracking-tight">
                    Notifikasi
                </h1>
                <p className="text-sm text-muted-foreground">
                    Tetap update dengan aktivitas terbaru Anda
                </p>
            </div>
            <div className="flex items-center gap-2">
                <Button
                    variant="default"
                    size="sm"
                    onClick={onMarkAllAsRead}
                    disabled={unreadCount === 0 || isProcessing}
                >
                    <IconCheck className="mr-2 h-4 w-4" />
                    Tandai semua sudah dibaca
                </Button>
                <Button
                    variant="destructive"
                    size="sm"
                    onClick={onClearAll}
                    disabled={totalCount === 0 || isProcessing}
                >
                    <IconBellOff className="mr-2 h-4 w-4" />
                    Hapus semua
                </Button>
            </div>
        </div>
    );
}
