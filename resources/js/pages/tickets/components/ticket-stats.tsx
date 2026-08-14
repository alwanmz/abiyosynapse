import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CheckCircle2, Circle, ListTodo, Target } from 'lucide-react';

interface TicketStatsProps {
    totalTickets: number;
    totalStoryPoints: number;
    inProgressTickets: number;
    doneTickets: number;
}

export function TicketStats({
    totalTickets,
    totalStoryPoints,
    inProgressTickets,
    doneTickets,
}: TicketStatsProps) {
    return (
        <div className="mb-6 grid gap-4 md:grid-cols-4">
            <Card className="border-violet-200 bg-violet-50/50 dark:border-violet-900/50 dark:bg-violet-950/20">
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">
                        Total Tiket
                    </CardTitle>
                    <ListTodo className="h-4 w-4 text-violet-500" />
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold text-violet-600 dark:text-violet-400">{totalTickets}</div>
                    <p className="text-xs text-muted-foreground">
                        Sesuai filter & tampilan aktif
                    </p>
                </CardContent>
            </Card>

            <Card className="border-blue-200 bg-blue-50/50 dark:border-blue-900/50 dark:bg-blue-950/20">
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">
                        Story Points
                    </CardTitle>
                    <Target className="h-4 w-4 text-blue-500" />
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold text-blue-600 dark:text-blue-400">{totalStoryPoints}</div>
                    <p className="text-xs text-muted-foreground">
                        Total estimasi effort
                    </p>
                </CardContent>
            </Card>

            <Card className="border-amber-200 bg-amber-50/50 dark:border-amber-900/50 dark:bg-amber-950/20">
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">
                        Sedang Dikerjakan
                    </CardTitle>
                    <Circle className="h-4 w-4 text-amber-500" />
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold text-amber-600 dark:text-amber-400">
                        {inProgressTickets}
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Dari tiket yang tampil
                    </p>
                </CardContent>
            </Card>

            <Card className="border-green-200 bg-green-50/50 dark:border-green-900/50 dark:bg-green-950/20">
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">
                        Selesai
                    </CardTitle>
                    <CheckCircle2 className="h-4 w-4 text-green-500" />
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold text-green-600 dark:text-green-400">{doneTickets}</div>
                    <p className="text-xs text-muted-foreground">
                        Dari tiket yang tampil (done aktif &lt; 30 hari)
                    </p>
                </CardContent>
            </Card>
        </div>
    );
}
