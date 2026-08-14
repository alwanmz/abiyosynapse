import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Ticket } from '@/types/ticket';
import { TICKET_STATUSES } from '../constants/ticket-statuses';
import { cn } from '@/lib/utils';
import { useMoveStatus } from '../hooks/use-move-status';

interface MoveStatusDialogProps {
    ticket: Ticket;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

export function MoveStatusDialog({
    ticket,
    open,
    onOpenChange,
}: MoveStatusDialogProps) {
    const {
        selectedStatus,
        setSelectedStatus,
        reviewNotes,
        setReviewNotes,
        isSubmitting,
        handleSubmit,
        canSubmit,
    } = useMoveStatus({
        ticket,
        onSuccess: () => onOpenChange(false),
    });

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md" onPointerDown={(e) => e.stopPropagation()}>
                <DialogHeader>
                    <DialogTitle>Pindahkan Tiket</DialogTitle>
                    <DialogDescription>
                        Ubah status dari{' '}
                        <span className="font-medium text-foreground">
                            {ticket.ticket_number}
                        </span>
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-2 py-4">
                    {TICKET_STATUSES.map((status) => {
                        const Icon = status.icon;
                        const isSelected = selectedStatus === status.id;
                        const isCurrent = ticket.status === status.id;
                        const shouldHighlight = isSelected || (!selectedStatus && isCurrent);

                        return (
                            <button
                                key={status.id}
                                type="button"
                                onClick={() => setSelectedStatus(status.id)}
                                disabled={isSubmitting}
                                className={cn(
                                    'flex items-center gap-3 rounded-lg border p-3 text-left transition-all',
                                    shouldHighlight
                                        ? 'border-primary bg-primary/5'
                                        : 'border-border hover:border-primary/50 hover:bg-muted/50',
                                    isSubmitting && 'opacity-50 cursor-not-allowed'
                                )}
                            >
                                <div
                                    className={cn(
                                        'flex h-10 w-10 items-center justify-center rounded-md',
                                        status.color,
                                        'text-white'
                                    )}
                                >
                                    <Icon className="h-5 w-5" />
                                </div>
                                <div className="flex-1">
                                    <div className="flex items-center gap-2">
                                        <p className="font-medium">{status.label}</p>
                                        {isCurrent && (
                                            <span className="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                                                Saat Ini
                                            </span>
                                        )}
                                    </div>
                                </div>
                                {isSelected && (
                                    <div className="h-4 w-4 rounded-full border-2 border-primary bg-primary" />
                                )}
                            </button>
                        );
                    })}
                </div>

                {selectedStatus === 'not-appropriate' && (
                    <div className="grid gap-2">
                        <Label htmlFor="review-notes" className="text-sm">
                            Keterangan Review
                        </Label>
                        <Textarea
                            id="review-notes"
                            placeholder="Jelaskan kenapa hasil QC belum sesuai..."
                            rows={3}
                            value={reviewNotes}
                            onChange={(e) => setReviewNotes(e.target.value)}
                        />
                    </div>
                )}

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={isSubmitting}
                    >
                        Batal
                    </Button>
                    <Button
                        type="button"
                        onClick={handleSubmit}
                        disabled={!canSubmit}
                    >
                        {isSubmitting ? 'Memindahkan...' : 'Pindahkan Tiket'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
