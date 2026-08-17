import { ConfirmDialog } from "@/components/confirm-dialog";
import AppLayout from "@/layouts/app-layout";
import { useBreadcrumbs } from "@/hooks/use-breadcrumbs";
import { NotificationsPageProps } from "@/types/notification";
import { Head } from "@inertiajs/react";
import { type ReactElement, useState } from "react";
import { NotificationEmptyState } from "./components/notification-empty-state";
import { NotificationHeader } from "./components/notification-header";
import { NotificationList } from "./components/notification-list";
import { NotificationStats } from "./components/notification-stats";
import { useNotificationActions } from "./hooks/use-notification-actions";

function NotificationsPage({ notifications }: NotificationsPageProps) {
    const unreadCount = notifications.filter(n => !n.read).length;
    const readCount = notifications.length - unreadCount;

    const {
        isProcessing,
        markAsRead,
        markAllAsRead,
        deleteNotification,
        clearAll,
    } = useNotificationActions();

    const [deletingId, setDeletingId] = useState<string | null>(null);
    const [showClearAll, setShowClearAll] = useState(false);

    useBreadcrumbs([
        {
            title: 'Notifikasi',
            href: "#"
        },
    ]);

    return (
        <>
            <Head title="Notifikasi" />

            <div className="p-6">
                <NotificationHeader
                    unreadCount={unreadCount}
                    totalCount={notifications.length}
                    onMarkAllAsRead={markAllAsRead}
                    onClearAll={() => setShowClearAll(true)}
                    isProcessing={isProcessing}
                />

                <NotificationStats
                    total={notifications.length}
                    unread={unreadCount}
                    read={readCount}
                />

                {notifications.length > 0 ? (
                    <NotificationList
                        notifications={notifications}
                        onMarkAsRead={markAsRead}
                        onDelete={(id) => setDeletingId(id)}
                        isProcessing={isProcessing}
                    />
                ) : (
                    <NotificationEmptyState />
                )}
            </div>

            <ConfirmDialog
                open={deletingId !== null}
                onOpenChange={(o) => !o && setDeletingId(null)}
                title="Hapus notifikasi?"
                description="Notifikasi ini akan dihapus permanen."
                confirmLabel="Ya, Hapus"
                loading={isProcessing}
                onConfirm={() => {
                    if (deletingId) {
                        deleteNotification(deletingId);
                        setDeletingId(null);
                    }
                }}
            />

            <ConfirmDialog
                open={showClearAll}
                onOpenChange={setShowClearAll}
                title="Hapus semua notifikasi?"
                description="Semua notifikasi (yang dibaca maupun belum) akan dihapus permanen."
                confirmLabel="Ya, Hapus Semua"
                loading={isProcessing}
                onConfirm={() => {
                    clearAll();
                    setShowClearAll(false);
                }}
            />
        </>
    );
}

NotificationsPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default NotificationsPage;
