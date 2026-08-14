import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Calendar, Clock } from 'lucide-react';

interface TimelineStatsProps {
    stats: {
        activeProjects: number;
        completedTimelines: number;
        inDevelopment: number;
        planningSprints: number;
    };
}

export function TimelineStats({ stats }: TimelineStatsProps) {
    return (
        <div className="mb-6 grid gap-4 md:grid-cols-4">
            <Card className="border-blue-200 bg-blue-50/50 dark:border-blue-900/50 dark:bg-blue-950/20">
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">
                        Active Projects
                    </CardTitle>
                    <Calendar className="h-4 w-4 text-blue-500" />
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold text-blue-600 dark:text-blue-400">
                        {stats.activeProjects}
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Currently running
                    </p>
                </CardContent>
            </Card>
            <Card className="border-green-200 bg-green-50/50 dark:border-green-900/50 dark:bg-green-950/20">
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">
                        Completed Timelines
                    </CardTitle>
                    <Clock className="h-4 w-4 text-green-500" />
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold text-green-600 dark:text-green-400">
                        {stats.completedTimelines}
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Successfully finished
                    </p>
                </CardContent>
            </Card>
            <Card className="border-amber-200 bg-amber-50/50 dark:border-amber-900/50 dark:bg-amber-950/20">
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">
                        In Development
                    </CardTitle>
                    <div className="h-3 w-3 rounded-full bg-amber-500" />
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold text-amber-600 dark:text-amber-400">
                        {stats.inDevelopment}
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Development phase
                    </p>
                </CardContent>
            </Card>
            <Card className="border-slate-200 bg-slate-50/50 dark:border-slate-800 dark:bg-slate-900/30">
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">
                        Pending Sprints
                    </CardTitle>
                    <div className="h-3 w-3 rounded-full bg-slate-500" />
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold text-slate-600 dark:text-slate-300">
                        {stats.planningSprints}
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Not yet started
                    </p>
                </CardContent>
            </Card>
        </div>
    );
}
