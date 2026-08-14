import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Timeline } from '@/types/timeline';

export function useTimelineDelete() {
    const [deletingTimeline, setDeletingTimeline] = useState<Timeline | null>(null);
    const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);

    const handleDeleteClick = (timeline: Timeline) => {
        setDeletingTimeline(timeline);
        setIsDeleteDialogOpen(true);
    };

    const handleDeleteConfirm = () => {
        if (!deletingTimeline) return;

        setIsDeleting(true);
        router.delete(`/timelines/${deletingTimeline.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setIsDeleteDialogOpen(false);
                setDeletingTimeline(null);
                setIsDeleting(false);
            },
            onError: () => {
                setIsDeleting(false);
            },
        });
    };

    return {
        deletingTimeline,
        isDeleteDialogOpen,
        isDeleting,
        setIsDeleteDialogOpen,
        handleDeleteClick,
        handleDeleteConfirm,
    };
}
