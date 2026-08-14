import ClientPortalLayout from '@/layouts/client-portal-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import TicketDetailBody from '../components/ticket-detail-body';
import { type PortalTicketDetail } from './types';

export default function ClientTicketShow({
    ticket,
}: {
    ticket: PortalTicketDetail;
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Tiket Saya', href: '/portal/tickets' },
        { title: ticket.ticket_number, href: `/portal/tickets/${ticket.id}` },
    ];

    return (
        <ClientPortalLayout breadcrumbs={breadcrumbs}>
            <Head title={`${ticket.ticket_number} — ${ticket.title}`} />

            <div className="mx-auto flex w-full max-w-4xl flex-col gap-4 p-4 md:p-6">
                <Link
                    href="/portal/tickets"
                    className="text-sm text-muted-foreground hover:underline"
                >
                    ← Kembali ke daftar tiket
                </Link>
                <TicketDetailBody ticket={ticket} />
            </div>
        </ClientPortalLayout>
    );
}
