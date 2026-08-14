import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Project, Timeline } from '@/types/timeline';
import { IconEdit, IconEye, IconTrash } from '@tabler/icons-react';
import { phaseColors, statusColors, typeColors } from '../constants/constants';
import { useTimelineDelete } from '../hooks/use-timeline-delete';
import { useTimelineDialogs } from '../hooks/use-timeline-dialogs';
import { useTimelinePosition } from '../hooks/use-timeline-position';
import { EditTimelineDialog } from './edit-timeline-dialog';
import { ViewTimelineDialog } from './view-timeline-dialog';

interface TimelineRowProps {
    projectData: {
        project: Timeline['project'];
        timelines: Timeline[];
    };
    projects: Project[];
    allTimelines: Timeline[];
    viewStart: Date;
    viewEnd: Date;
    canActions: boolean;
}

export function TimelineRow({
    projectData,
    projects,
    allTimelines,
    viewStart,
    canActions,
}: TimelineRowProps) {
    const { project, timelines } = projectData;

    const {
        viewingTimeline,
        isViewDialogOpen,
        setIsViewDialogOpen,
        handleViewClick,
        editingTimeline,
        isEditDialogOpen,
        setIsEditDialogOpen,
        handleEditClick,
    } = useTimelineDialogs();

    const {
        deletingTimeline,
        isDeleteDialogOpen,
        isDeleting,
        setIsDeleteDialogOpen,
        handleDeleteClick,
        handleDeleteConfirm,
    } = useTimelineDelete();

    const { getTimelinePosition, totalWeeks } = useTimelinePosition(viewStart);

    return (
        <div className="group border-b transition-colors last:border-b-0 hover:bg-muted/30">
            <div className="flex items-center gap-4 border-b bg-muted/20 p-4">
                <div className="flex-1">
                    <div className="flex items-center gap-2">
                        <h3 className="font-semibold">{project.name}</h3>
                        <Badge
                            variant={
                                (statusColors[project.status]
                                    ?.variant as 'default') || 'secondary'
                            }
                            className="text-xs"
                        >
                            {statusColors[project.status]?.label ||
                                project.status}
                        </Badge>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {project.team.name} ({timelines.length} timelines)
                    </p>
                </div>
            </div>

            <div className="p-4">
                <div className="flex flex-col-reverse gap-2">
                    {timelines.map((timeline) => {
                        const position = getTimelinePosition(timeline);
                        if (!position) return null;

                        const phaseColor = timeline.phase
                            ? phaseColors[timeline.phase]
                            : typeColors[timeline.type];

                        return (
                            <div key={timeline.id} className="relative h-10">
                                <div className="absolute inset-0 flex">
                                    {Array.from({ length: totalWeeks }).map(
                                        (_, i) => (
                                            <div
                                                key={i}
                                                className="flex-1 border-r border-dashed border-muted-foreground/10 last:border-r-0"
                                            />
                                        ),
                                    )}
                                </div>
                                <div className="relative h-full">
                                    <div
                                        className="absolute h-full cursor-pointer rounded-md transition-all hover:opacity-80"
                                        style={{
                                            left: `${position.left}%`,
                                            width: `${position.width}%`,
                                        }}
                                        title={`${timeline.title}${timeline.sprint_number ? ` (Sprint ${timeline.sprint_number})` : ''}\n${timeline.start_date} - ${timeline.end_date}\nStatus: ${timeline.status}\n\nClick to view details`}
                                        onClick={() =>
                                            handleViewClick(timeline)
                                        }
                                    >
                                        <div
                                            className={`h-full rounded-md ${phaseColor} ${timeline.status === 'completed'
                                                ? 'opacity-60'
                                                : timeline.status ===
                                                    'pending'
                                                    ? 'opacity-40'
                                                    : timeline.status ===
                                                        'delayed'
                                                        ? 'opacity-70 ring-2 ring-red-500'
                                                        : 'opacity-90'
                                                } flex items-center justify-center px-2`}
                                        >
                                            <span className="truncate text-xs font-medium text-white">
                                                {timeline.title}
                                                {timeline.sprint_number &&
                                                    ` #${timeline.sprint_number}`}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
                <div className="mt-6 flex flex-wrap gap-3">
                    {timelines.map((timeline) => {
                        const phaseColor = timeline.phase
                            ? phaseColors[timeline.phase]
                            : typeColors[timeline.type];

                        return (
                            <div
                                key={timeline.id}
                                className="flex items-center gap-2 text-xs"
                            >
                                <div
                                    className={`h-3 w-3 rounded-sm ${phaseColor} cursor-pointer hover:opacity-80`}
                                    onClick={() => handleViewClick(timeline)}
                                    title="Klik untuk melihat detail"
                                />
                                <span
                                    className="cursor-pointer font-medium hover:text-primary"
                                    onClick={() => handleViewClick(timeline)}
                                    title="Klik untuk melihat detail"
                                >
                                    {timeline.title}:
                                </span>
                                <span className="text-muted-foreground">
                                    {timeline.start_date} - {timeline.end_date}
                                </span>

                                <Badge
                                    variant={
                                        statusColors[timeline.status]
                                            ?.variant as 'outline'
                                    }
                                    className="h-5 text-[10px]"
                                >
                                    {statusColors[timeline.status]?.label}
                                </Badge>

                                <div className="flex items-center gap-1">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="h-5 w-5"
                                        onClick={() =>
                                            handleViewClick(timeline)
                                        }
                                        title="View details"
                                    >
                                        <IconEye className="h-3 w-3" />
                                    </Button>
                                    {canActions && (
                                        <>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="h-5 w-5"
                                                onClick={() =>
                                                    handleEditClick(timeline)
                                                }
                                            >
                                                <IconEdit className="h-3 w-3" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="h-5 w-5 text-destructive hover:text-destructive"
                                                onClick={() =>
                                                    handleDeleteClick(timeline)
                                                }
                                            >
                                                <IconTrash className="h-3 w-3" />
                                            </Button>
                                        </>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>

            <ViewTimelineDialog
                timeline={viewingTimeline}
                open={isViewDialogOpen}
                onOpenChange={setIsViewDialogOpen}
            />

            {editingTimeline && (
                <EditTimelineDialog
                    timeline={editingTimeline}
                    projects={projects}
                    timelines={allTimelines}
                    open={isEditDialogOpen}
                    onOpenChange={setIsEditDialogOpen}
                />
            )}

            <AlertDialog
                open={isDeleteDialogOpen}
                onOpenChange={setIsDeleteDialogOpen}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete Timeline?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to delete &quot;
                            {deletingTimeline?.title}&quot;? This action cannot
                            be undone and will also delete all associated
                            timeline events.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={isDeleting}>
                            Cancel
                        </AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleDeleteConfirm}
                            disabled={isDeleting}
                            className="bg-destructive text-white hover:bg-destructive/90"
                        >
                            {isDeleting ? 'Deleting...' : 'Delete'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
