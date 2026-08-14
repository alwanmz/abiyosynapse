import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Timeline } from '@/types/timeline';
import {
    IconCalendar,
    IconClock,
    IconFileText,
    IconFlag,
    IconListCheck,
} from '@tabler/icons-react';
import {
    phaseColors,
    phaseLabels,
    statusColors,
    typeLabels,
} from '../constants/constants';
import { useTimelineFormatting } from '../hooks/use-timeline-formatting';

interface ViewTimelineDialogProps {
    timeline: Timeline | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

export function ViewTimelineDialog({
    timeline,
    open,
    onOpenChange,
}: ViewTimelineDialogProps) {
    const { formatDate, calculateDuration, phaseColor } =
        useTimelineFormatting(timeline);

    if (!timeline) return null;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] max-w-lg p-0">
                <div
                    className={`${phaseColor} rounded-t-lg px-6 py-8 text-white`}
                >
                    <DialogHeader>
                        <div className="flex items-center gap-6">
                            <div className="flex-1">
                                <DialogTitle className="text-2xl font-bold">
                                    {timeline.title}
                                </DialogTitle>
                                <DialogDescription className="mt-1.5 text-white/90">
                                    {timeline.project.name} •{' '}
                                    {timeline.project.team.name}
                                </DialogDescription>
                            </div>
                            <Badge
                                variant="secondary"
                                className="bg-white/20 text-white hover:bg-white/30"
                            >
                                {typeLabels[timeline.type]}
                                {timeline.sprint_number &&
                                    ` #${timeline.sprint_number}`}
                            </Badge>
                        </div>
                    </DialogHeader>
                </div>

                <ScrollArea className="max-h-[calc(85vh-140px)]">
                    <div className="space-y-6 px-6 py-5">
                        <div className="grid grid-cols-2 gap-4">
                            <div className="rounded-lg border bg-card p-4">
                                <div className="mb-2 flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                    <IconFlag className="h-4 w-4" />
                                    Status
                                </div>
                                <Badge
                                    variant={
                                        statusColors[timeline.status]
                                            ?.variant as 'outline'
                                    }
                                    className="text-sm"
                                >
                                    {statusColors[timeline.status]?.label}
                                </Badge>
                            </div>

                            <div className="rounded-lg border bg-card p-4">
                                <div className="mb-2 flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                    <IconClock className="h-4 w-4" />
                                    Durasi
                                </div>
                                <p className="text-sm font-semibold">
                                    {calculateDuration() || '-'}
                                </p>
                            </div>
                        </div>

                        {timeline.phase && (
                            <div className="rounded-lg border bg-card p-4">
                                <div className="mb-2 flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                    <IconFileText className="h-4 w-4" />
                                    Fase SDLC
                                </div>
                                <div className="flex items-center gap-2">
                                    <div
                                        className={`h-2 w-2 rounded-full ${phaseColor}`}
                                    />
                                    <p className="text-sm font-semibold">
                                        {phaseLabels[timeline.phase]}
                                    </p>
                                </div>
                            </div>
                        )}

                        <div className="rounded-lg border bg-card">
                            <div className="border-b bg-muted/50 px-4 py-3">
                                <div className="flex items-center gap-2 text-sm font-semibold">
                                    <IconCalendar className="h-4 w-4" />
                                    Periode Timeline
                                </div>
                            </div>
                            <div className="grid grid-cols-2 divide-x">
                                <div className="p-4">
                                    <p className="mb-1 text-xs font-medium text-muted-foreground">
                                        Tanggal Mulai
                                    </p>
                                    <p className="text-sm font-medium">
                                        {formatDate(timeline.start_date)}
                                    </p>
                                </div>
                                <div className="p-4">
                                    <p className="mb-1 text-xs font-medium text-muted-foreground">
                                        Tanggal Selesai
                                    </p>
                                    <p className="text-sm font-medium">
                                        {formatDate(timeline.end_date)}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {timeline.description && (
                            <div className="rounded-lg border bg-card">
                                <div className="border-b bg-muted/50 px-4 py-3">
                                    <div className="flex items-center gap-2 text-sm font-semibold">
                                        <IconFileText className="h-4 w-4" />
                                        Deskripsi
                                    </div>
                                </div>
                                <div className="p-4">
                                    <p className="text-sm leading-relaxed whitespace-pre-wrap text-muted-foreground">
                                        {timeline.description}
                                    </p>
                                </div>
                            </div>
                        )}

                        {timeline.deliverables &&
                            timeline.deliverables.length > 0 &&
                            timeline.deliverables[0] !== '' && (
                                <div className="rounded-lg border bg-card">
                                    <div className="border-b bg-muted/50 px-4 py-3">
                                        <div className="flex items-center gap-2 text-sm font-semibold">
                                            <IconListCheck className="h-4 w-4" />
                                            Deliverables
                                            <Badge
                                                variant="secondary"
                                                className="ml-auto"
                                            >
                                                {timeline.deliverables.length}
                                            </Badge>
                                        </div>
                                    </div>
                                    <div className="p-4">
                                        <ul className="space-y-2.5">
                                            {timeline.deliverables.map(
                                                (deliverable, index) => (
                                                    <li
                                                        key={index}
                                                        className="flex items-start gap-3"
                                                    >
                                                        <div className="mt-1.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-primary/10">
                                                            <span className="text-xs font-semibold text-primary">
                                                                {index + 1}
                                                            </span>
                                                        </div>
                                                        <span className="flex-1 text-sm leading-relaxed">
                                                            {deliverable}
                                                        </span>
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    </div>
                                </div>
                            )}
                    </div>
                </ScrollArea>
            </DialogContent>
        </Dialog>
    );
}
