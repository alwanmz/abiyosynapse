import { router } from '@inertiajs/react';
import { useState } from 'react';

export function useNotificationActions() {
    const [isProcessing, setIsProcessing] = useState(false);

    const markAsRead = (id: string) => {
        setIsProcessing(true);
        router.post(`/notifications/${id}/read`, {}, {
            preserveScroll: true,
            onFinish: () => setIsProcessing(false),
        });
    };

    const markAllAsRead = () => {
        setIsProcessing(true);
        router.post('/notifications/read-all', {}, {
            preserveScroll: true,
            onFinish: () => setIsProcessing(false),
        });
    };

    /**
     * Performs the actual deletion. Wrap the trigger with <ConfirmDialog>
     * in the consumer to ask the user before calling this.
     */
    const deleteNotification = (id: string) => {
        setIsProcessing(true);
        router.delete(`/notifications/${id}`, {
            preserveScroll: true,
            onFinish: () => setIsProcessing(false),
        });
    };

    /**
     * Performs clear-all. Wrap the trigger with <ConfirmDialog>
     * in the consumer to ask the user before calling this.
     */
    const clearAll = () => {
        setIsProcessing(true);
        router.delete('/notifications/clear-all', {
            preserveScroll: true,
            onFinish: () => setIsProcessing(false),
        });
    };

    return {
        isProcessing,
        markAsRead,
        markAllAsRead,
        deleteNotification,
        clearAll,
    };
}
