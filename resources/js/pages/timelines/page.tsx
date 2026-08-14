import { ListHeader } from '@/components/list-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { TimelinePageProps } from '@/types/timeline';
import { Head } from '@inertiajs/react';
import {
    IconCalendar,
    IconChevronLeft,
    IconChevronRight,
} from '@tabler/icons-react';
import { CreateTimelineDialog } from './components/create-timeline-dialog';
import { TimelineLegend } from './components/timeline-legend';
import { TimelineRow } from './components/timeline-row';
import { TimelineStats } from './components/timeline-stats';
import {
    TIMELINE_BREADCRUMBS,
    TIMELINE_INITIAL_DATE,
    TIMELINE_TOTAL_WEEKS,
} from './constants/timeline-config';
import {
    useGroupedTimelines,
    useTimelineStats,
} from './hooks/use-timeline-data';
import { useTimelineNavigation } from './hooks/use-timeline-navigation';
import { useTimelinePermissions } from './hooks/use-timeline-permissions';
import { useTimelineView } from './hooks/use-timeline-view';



export default function TimelinePage({
    timelines,
    projects,
}: TimelinePageProps) {
    const { canManageTimelines } = useTimelinePermissions();

    const { viewStart, navigatePrevious, navigateNext, navigateToToday } =
        useTimelineNavigation({
            totalWeeks: TIMELINE_TOTAL_WEEKS,
            initialDate: TIMELINE_INITIAL_DATE,
        });

    const { viewEnd, weekLabels, ...timelineInfo } = useTimelineView({
        viewStart,
        totalWeeks: TIMELINE_TOTAL_WEEKS,
    });

    const stats = useTimelineStats(timelines);
    const groupedTimelines = useGroupedTimelines(timelines);

    return (
        <AppLayout breadcrumbs={TIMELINE_BREADCRUMBS}>
            <Head title="Timeline" />
            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Timeline Proyek
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Kelola dan visualisasikan jadwal proyek dan fase SDLC
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        {canManageTimelines && (
                            <CreateTimelineDialog
                                projects={projects}
                                timelines={timelines}
                            />
                        )}
                    </div>
                </div>

                <TimelineStats stats={stats} />

                <Card className="mb-6 overflow-hidden p-0">
                    <ListHeader
                        title="Tampilan Timeline"
                        description={`${timelineInfo.months} bulan gambaran timeline proyek (${timelineInfo.weeks} minggu)`}
                    />

                    <CardContent className="p-5">
                        <div className="mb-4 flex items-center justify-end gap-2">
                            <Button
                                variant="outline"
                                size="icon"
                                onClick={navigatePrevious}
                            >
                                <IconChevronLeft className="h-4 w-4" />
                            </Button>
                            <div
                                className="flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 transition-colors hover:bg-accent"
                                onClick={navigateToToday}
                                title="Jump to current week"
                            >
                                <IconCalendar className="h-4 w-4 text-muted-foreground" />
                                <span className="text-sm font-medium">
                                    {timelineInfo.dateRange}
                                </span>
                            </div>
                            <Button
                                variant="outline"
                                size="icon"
                                onClick={navigateNext}
                            >
                                <IconChevronRight className="h-4 w-4" />
                            </Button>
                        </div>

                        <div className="mb-4 flex border-b">
                            {weekLabels.map((label, i) => (
                                <div
                                    key={i}
                                    className="flex-1 border-r border-dashed py-2 text-center text-xs font-medium text-muted-foreground last:border-r-0"
                                    title={`Week ${i + 1}: ${label}`}
                                >
                                    {label}
                                </div>
                            ))}
                        </div>
                        <div className="space-y-0 overflow-hidden rounded-lg border">
                            {groupedTimelines.length > 0 ? (
                                groupedTimelines.map((projectData) => (
                                    <TimelineRow
                                        key={projectData.project.id}
                                        projectData={projectData}
                                        projects={projects}
                                        allTimelines={timelines}
                                        viewStart={viewStart}
                                        viewEnd={viewEnd}
                                        canActions={canManageTimelines}
                                    />
                                ))
                            ) : (
                                <div className="p-8 text-center text-muted-foreground">
                                    Belum ada timeline. Buat timeline proyek pertama Anda untuk memulai.
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                <TimelineLegend timelines={timelines} />
            </div>
        </AppLayout>
    );
}
