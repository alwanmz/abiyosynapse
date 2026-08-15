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
                    <div className="rounded-lg bg-teal-100 p-3 dark:bg-teal-900/20">
                        <IconBell className="h-5 w-5 text-teal-600 dark:text-teal-400" />
                    </div>
                    <div>
                        <p className="text-sm text-muted-foreground">Total</p>
                        <p className="text-2xl font-bold">{total}</p>
                    </div>
                </div>
            </Card>
            <Card className="p-4">
                <div className="flex items-center gap-3">
                    <div className="rounded-lg bg-orange-100 p-3 dark:bg-orange-900/20">
                        <IconBell className="h-5 w-5 text-orange-600 dark:text-orange-400" />
                    </div>
                    <div>
                        <p className="text-sm text-muted-foreground">Belum Dibaca</p>
                        <p className="text-2xl font-bold">{unread}</p>
                    </div>
                </div>
            </Card>
            <Card className="p-4">
                <div className="flex items-center gap-3">
                    <div className="rounded-lg bg-green-100 p-3 dark:bg-green-900/20">
                        <IconChecks className="h-5 w-5 text-green-600 dark:text-green-400" />
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
