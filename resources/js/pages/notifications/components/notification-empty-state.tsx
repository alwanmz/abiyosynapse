import { Card } from '@/components/ui/card';
import { IconBell } from '@tabler/icons-react';

export function NotificationEmptyState() {
    return (
        <Card className="p-12">
            <div className="flex flex-col items-center justify-center text-center">
                <div className="rounded-full bg-muted p-6">
                    <IconBell className="h-12 w-12 text-muted-foreground" />
                </div>
                <h3 className="mt-4 text-lg font-semibold">Belum ada notifikasi</h3>
                <p className="mt-2 text-sm text-muted-foreground">
                    Saat Anda mendapat notifikasi, akan muncul di sini
                </p>
            </div>
        </Card>
    );
}
