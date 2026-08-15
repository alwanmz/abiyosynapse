import { Card } from '@/components/ui/card';
import { IconBell, IconChecks } from '@tabler/icons-react';

interface NotificationStatsProps {
    total: number;
    unread: number;
    read: number;
}

export function NotificationStats({ total, unread, read }: NotificationStatsProps) {
    return (
        <div className="mb-6 grid gap-4 md:grid-cols-3">
            <Card className="p-4">
                <div className="flex items-center gap-3">
                    <div className="rounded-lg bg-nx-andon-info-bg p-3">
                        <IconBell className="h-5 w-5 text-nx-andon-info" />
                    </div>
                    <div>
                        <p className="text-sm text-muted-foreground">Total</p>
                        <p className="text-2xl font-bold">{total}</p>
                    </div>
                </div>
            </Card>
            <Card className="p-4">
                <div className="flex items-center gap-3">
                    <div className="rounded-lg bg-nx-andon-caution-bg p-3">
                        <IconBell className="h-5 w-5 text-nx-andon-caution" />
                    </div>
                    <div>
                        <p className="text-sm text-muted-foreground">Belum Dibaca</p>
                        <p className="text-2xl font-bold">{unread}</p>
                    </div>
                </div>
            </Card>
            <Card className="p-4">
                <div className="flex items-center gap-3">
                    <div className="rounded-lg bg-nx-andon-run-bg p-3">
                        <IconChecks className="h-5 w-5 text-nx-andon-run" />
                    </div>
                    <div>
                        <p className="text-sm text-muted-foreground">Sudah Dibaca</p>
                        <p className="text-2xl font-bold">{read}</p>
                    </div>
                </div>
            </Card>
        </div>
    );
}
