import { useState } from 'react';
import { Timeline } from '@/types/timeline';

export function useTimelineDialogs() {
    const [editingTimeline, setEditingTimeline] = useState<Timeline | null>(null);
    const [isEditDialogOpen, setIsEditDialogOpen] = useState(false);
    const [viewingTimeline, setViewingTimeline] = useState<Timeline | null>(null);
    const [isViewDialogOpen, setIsViewDialogOpen] = useState(false);

    const handleViewClick = (timeline: Timeline) => {
        setViewingTimeline(timeline);
        setIsViewDialogOpen(true);
    };

    const handleEditClick = (timeline: Timeline) => {
        setEditingTimeline(timeline);
        setIsEditDialogOpen(true);
    };

    return {
        // View dialog
        viewingTimeline,
        isViewDialogOpen,
        setIsViewDialogOpen,
        handleViewClick,

        // Edit dialog
        editingTimeline,
        isEditDialogOpen,
        setIsEditDialogOpen,
        handleEditClick,
    };
}
