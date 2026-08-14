import { useState } from 'react';

export function useTicketCardDialogs() {
    const [showDetail, setShowDetail] = useState(false);
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
    const [showEditDialog, setShowEditDialog] = useState(false);
    const [showMoveStatus, setShowMoveStatus] = useState(false);

    return {
        showDetail,
        setShowDetail,
        showDeleteConfirm,
        setShowDeleteConfirm,
        showEditDialog,
        setShowEditDialog,
        showMoveStatus,
        setShowMoveStatus,
    };
}
