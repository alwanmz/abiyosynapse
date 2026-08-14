import { router } from '@inertiajs/react';
import { useState } from 'react';

export function useDeleteComment(commentId: number) {
    const [isDeleting, setIsDeleting] = useState(false);

    /**
     * Performs the actual deletion. Wrap the trigger with <ConfirmDialog>
     * in the consumer to ask the user before calling this.
     */
    const handleDelete = () => {
        setIsDeleting(true);

        router.delete(`/tickets/comments/${commentId}`, {
            preserveScroll: true,
            onSuccess: () => {
                setIsDeleting(false);
            },
            onError: () => {
                setIsDeleting(false);
            },
        });
    };

    return {
        isDeleting,
        handleDelete,
    };
}
