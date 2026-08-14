import { UserAvatar } from '@/components/user-avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Separator } from '@/components/ui/separator';
import { userHasPermission } from '@/lib/permissions';
import { SharedData } from '@/types';
import { Client, Project, Ticket, Timeline, UserSummary } from '@/types/ticket';
import { usePage } from '@inertiajs/react';
import {
    IconArchive,
    IconArchiveOff,
    IconArrowRight,
    IconChevronDown,
    IconChevronRight,
    IconDotsVertical,
    IconEdit,
    IconFlag,
    IconMessageCircle,
    IconPaperclip,
    IconTrash,
} from '@tabler/icons-react';
import { TICKET_PRIORITIES } from '../constants/ticket-priorities';
import { TicketDetailDialog } from './ticket-detail-dialog';
import { EditTicketDialog } from './edit-ticket-dialog';
import { MoveStatusDialog } from './move-status-dialog';
import { useTicketCardDialogs } from '../hooks/use-ticket-card-dialogs';
import { useTicketActions } from '../hooks/use-ticket-actions';
import { toggleCard, useCardExpanded } from '../hooks/use-card-expansion';

interface TicketCardProps {
    ticket: Ticket;
    projects: Project[];
    clients: Client[];
    timelines: Timeline[];
    allUsers?: UserSummary[];
}

export function TicketCard({ ticket, projects, clients, timelines, allUsers = [] }: TicketCardProps) {
    const priorityConfig = TICKET_PRIORITIES[ticket.priority];
    const expanded = useCardExpanded(ticket.id);

    const {
        showDetail,
        setShowDetail,
        showDeleteConfirm,
        setShowDeleteConfirm,
        showEditDialog,
        setShowEditDialog,
        showMoveStatus,
        setShowMoveStatus,
    } = useTicketCardDialogs();

    const { handleDelete, handleArchive, handleUnarchive } = useTicketActions({
        ticket,
        onDeleteSuccess: () => setShowDeleteConfirm(false),
    });
    const isDone = ticket.status === 'done';
    const isArchived = !!ticket.archived_at;

    const { auth } = usePage<SharedData>().props;
    const canEdit =
        userHasPermission(auth, 'tickets.edit') ||
        userHasPermission(auth, 'tickets.update') ||
        userHasPermission(auth, 'tickets.update-any');
    const canDelete = userHasPermission(auth, 'tickets.delete');

    const requiresApproval = ticket.request_type === 'berbayar';
    const isApproved = !!ticket.approved_at;
    const isRejected = !!ticket.rejected_at && !isApproved;
    const pendingApproval = requiresApproval && !isApproved && !isRejected;

    const cardClass = isRejected
        ? 'border-red-300/60 bg-red-50/30 dark:border-red-700/40 dark:bg-red-950/10'
        : pendingApproval
        ? 'border-amber-300/60 bg-amber-50/30 dark:border-amber-700/40 dark:bg-amber-950/10'
        : '';

    return (
        <>
            <Card
                className={`group cursor-pointer transition-all hover:shadow-sm ${cardClass}`}
                onClick={() => setShowDetail(true)}
            >
                <CardHeader className="p-4">
                    <div className="mb-2 flex items-start justify-between">
                        <div className="flex items-center gap-1.5">
                            <Button
                                variant="ghost"
                                size="icon"
                                className="h-5 w-5 shrink-0 text-muted-foreground"
                                title={expanded ? 'Ringkas' : 'Perluas'}
                                onPointerDown={(e) => e.stopPropagation()}
                                onClick={(e) => {
                                    e.stopPropagation();
                                    toggleCard(ticket.id);
                                }}
                            >
                                {expanded ? (
                                    <IconChevronDown className="h-4 w-4" />
                                ) : (
                                    <IconChevronRight className="h-4 w-4" />
                                )}
                            </Button>
                            <Badge variant="outline" className="font-mono text-xs">
                                {ticket.ticket_number}
                            </Badge>
                            {isRejected && (
                                <Badge variant="outline" className="border-red-400 bg-red-100 text-[9px] text-red-700 dark:border-red-600 dark:bg-red-900/30 dark:text-red-400">
                                    Ditolak
                                </Badge>
                            )}
                            {pendingApproval && (
                                <Badge variant="outline" className="border-amber-400 bg-amber-100 text-[9px] text-amber-700 dark:border-amber-600 dark:bg-amber-900/30 dark:text-amber-400">
                                    Menunggu Approval
                                </Badge>
                            )}
                        </div>
                        {(canEdit || (!isDone && canDelete)) && (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="h-6 w-6 opacity-0 transition-opacity group-hover:opacity-100"
                                        onClick={(e) => e.stopPropagation()}
                                    >
                                        <IconDotsVertical className="h-4 w-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    {canEdit && !isDone && (
                                        <>
                                            <DropdownMenuItem
                                                onSelect={(e) => {
                                                    e.preventDefault();
                                                    setShowEditDialog(true);
                                                }}
                                                onClick={(e) => e.stopPropagation()}
                                            >
                                                <IconEdit className="mr-2 h-4 w-4" />
                                                Edit Ticket
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                onSelect={(e) => {
                                                    e.preventDefault();
                                                    setShowMoveStatus(true);
                                                }}
                                                onClick={(e) => e.stopPropagation()}
                                            >
                                                <IconArrowRight className="mr-2 h-4 w-4" />
                                                Move to...
                                            </DropdownMenuItem>
                                        </>
                                    )}

                                    {canEdit && isDone && !isArchived && (
                                        <DropdownMenuItem
                                            onSelect={(e) => {
                                                e.preventDefault();
                                                handleArchive();
                                            }}
                                            onClick={(e) => e.stopPropagation()}
                                        >
                                            <IconArchive className="mr-2 h-4 w-4" />
                                            Arsipkan
                                        </DropdownMenuItem>
                                    )}
                                    {canEdit && isDone && isArchived && (
                                        <DropdownMenuItem
                                            onSelect={(e) => {
                                                e.preventDefault();
                                                handleUnarchive();
                                            }}
                                            onClick={(e) => e.stopPropagation()}
                                        >
                                            <IconArchiveOff className="mr-2 h-4 w-4" />
                                            Batal Arsip
                                        </DropdownMenuItem>
                                    )}

                                    {canDelete && !isDone && (
                                        <>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                variant="destructive"
                                                className="text-destructive"
                                                onSelect={(e) => {
                                                    e.preventDefault();
                                                    setShowDeleteConfirm(true);
                                                }}
                                                onClick={(e) => e.stopPropagation()}
                                            >
                                                <IconTrash className="mr-2 h-4 w-4" />
                                                Delete
                                            </DropdownMenuItem>
                                        </>
                                    )}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}
                    </div>

                    <CardTitle className="text-sm leading-tight font-semibold">
                        {ticket.title}
                    </CardTitle>
                    <div className="mt-1 flex items-center gap-3 text-xs text-muted-foreground">
                        {!!(ticket.comments_count || ticket.comments?.length) && (
                            <span className="flex items-center gap-1">
                                <IconMessageCircle className="h-3.5 w-3.5" />
                                {ticket.comments_count ?? ticket.comments?.length}
                            </span>
                        )}
                        {!!(ticket.attachments?.length) && (
                            <span className="flex items-center gap-1">
                                <IconPaperclip className="h-3.5 w-3.5" />
                                {ticket.attachments.length}
                            </span>
                        )}
                    </div>
                    {expanded && (
                        <CardDescription className="line-clamp-2 text-xs">
                            {ticket.description}
                        </CardDescription>
                    )}
                </CardHeader>

                {expanded && (
                <CardContent className="p-4">
                    <div className="space-y-2">
                        {/* Priority */}
                        <Badge variant="outline" className={`text-[10px] ${priorityConfig.badgeClassName}`}>
                            <IconFlag className="mr-1 h-2.5 w-2.5" />
                            {priorityConfig.label}
                        </Badge>

                        <Separator />

                        <div className="space-y-2">
                            <div className="flex items-center justify-between gap-3">
                                <div className="min-w-0 space-y-1">
                                    <p className="text-[10px] uppercase tracking-wide text-muted-foreground">Submitter</p>
                                    <div className="flex items-center gap-1.5">
                                        <UserAvatar
                                            user={ticket.reporter}
                                            className="h-6 w-6"
                                            fallbackClassName="text-[10px]"
                                        />
                                        <span className="truncate text-xs text-muted-foreground">
                                            {ticket.reporter.name}
                                        </span>
                                    </div>
                                </div>

                                <Badge
                                    variant="secondary"
                                    className="h-5 min-w-5 rounded-full px-1.5 text-[10px]"
                                >
                                    {ticket.story_points ?? 0}
                                </Badge>
                            </div>

                            <div className="grid gap-2 text-xs text-muted-foreground">
                                <div className="flex items-center gap-1.5">
                                    <span className="w-16 shrink-0 text-[10px] uppercase tracking-wide">Pelaksana</span>
                                    {ticket.assignees && ticket.assignees.length > 0 ? (
                                        <span className="truncate">
                                            {ticket.assignees[0].name}
                                            {ticket.assignees.length > 1 ? ` +${ticket.assignees.length - 1}` : ''}
                                        </span>
                                    ) : (
                                        <span>Belum ditugaskan</span>
                                    )}
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <span className="w-16 shrink-0 text-[10px] uppercase tracking-wide">Approval</span>
                                    {ticket.request_type === 'berbayar' ? (
                                        ticket.approved_at ? (
                                            <span className="truncate text-green-700">
                                                {ticket.approver ? ticket.approver.name : 'Disetujui'}
                                            </span>
                                        ) : ticket.rejected_at ? (
                                            <span className="truncate text-red-700">
                                                {ticket.rejecter ? ticket.rejecter.name : 'Ditolak'}
                                            </span>
                                        ) : (
                                            <span>Menunggu approval</span>
                                        )
                                    ) : (
                                        <span>Tidak perlu approval</span>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
                )}
            </Card>

            {/* Ticket Detail Dialog */}
            <TicketDetailDialog
                ticket={ticket}
                open={showDetail}
                onOpenChange={setShowDetail}
            />

            {/* Edit Ticket Dialog */}
            <EditTicketDialog
                ticket={ticket}
                projects={projects}
                clients={clients}
                timelines={timelines}
                allUsers={allUsers}
                open={showEditDialog}
                onOpenChange={setShowEditDialog}
            />

            {/* Move Status Dialog */}
            <MoveStatusDialog
                ticket={ticket}
                open={showMoveStatus}
                onOpenChange={setShowMoveStatus}
            />

            {/* Delete Confirmation Dialog */}
            <AlertDialog open={showDeleteConfirm} onOpenChange={setShowDeleteConfirm}>
                <AlertDialogContent onPointerDown={(e) => e.stopPropagation()}>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Apakah Anda yakin?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Ini akan menghapus tiket <strong>{ticket.ticket_number}</strong> ({ticket.title}) secara permanen.
                            Tindakan ini tidak dapat dibatalkan.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Batal</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleDelete}
                            className="bg-destructive hover:bg-destructive/90"
                        >
                            Hapus
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
