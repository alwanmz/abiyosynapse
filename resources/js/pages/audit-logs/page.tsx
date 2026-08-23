import { ListHeader } from '@/components/list-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useBreadcrumbs } from '@/hooks/use-breadcrumbs';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { IconEye } from '@tabler/icons-react';
import { type FormEvent, type ReactElement, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Pagination } from '@/pages/manage-users/components/pagination';

interface AuditLog {
    id: number;
    event: string;
    auditable_type: string;
    auditable_id: number | null;
    description: string | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    created_at: string;
    user: { id: number; name: string } | null;
}

interface PaginatedLogs {
    data: AuditLog[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface AuditLogsPageProps {
    logs: PaginatedLogs;
    events: string[];
    filters: { event: string; search: string };
}

const eventVariants: Record<string, 'default' | 'outline' | 'secondary' | 'destructive'> = {
    approved: 'default',
    rejected: 'destructive',
    deleted: 'destructive',
    submitted: 'secondary',
    status_changed: 'secondary',
    closed: 'outline',
    created: 'outline',
    updated: 'outline',
};

function AuditLogsPage({ logs, events, filters }: AuditLogsPageProps) {
    const { t } = useTranslation('audit');
    const [search, setSearch] = useState(filters.search);
    const [event, setEvent] = useState(filters.event || 'all');
    const [selectedLog, setSelectedLog] = useState<AuditLog | null>(null);

    useBreadcrumbs([{ title: t('nav.audit_trail'), href: '/audit-logs' }]);

    const locale = document.documentElement.lang || 'id-ID';
    const formatDate = (value: string) => new Intl.DateTimeFormat(locale, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));

    const submitFilters = (formEvent: FormEvent) => {
        formEvent.preventDefault();
        router.get('/audit-logs', {
            search: search || undefined,
            event: event === 'all' ? undefined : event,
        }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const documentName = (type: string) => type.split('\\').pop() ?? type;
    const eventLabel = (value: string) => t(`events.${value}`, { defaultValue: value.replaceAll('_', ' ') });

    return (
        <>
            <Head title={t('title')} />
            <div className="space-y-6 p-6">
                <div>
                    <h1 className="font-display text-2xl font-semibold tracking-tight">{t('title')}</h1>
                    <p className="text-sm text-muted-foreground">{t('description')}</p>
                </div>

                <Card className="overflow-hidden p-0">
                    <CardContent className="p-5">
                        <form onSubmit={submitFilters} className="grid items-end gap-4 md:grid-cols-[minmax(0,1fr)_220px_auto]">
                            <div className="grid gap-2">
                                <Label htmlFor="audit-search">{t('filters.search')}</Label>
                                <Input id="audit-search" value={search} placeholder={t('filters.search_placeholder')} onChange={(e) => setSearch(e.target.value)} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="audit-event">{t('filters.event')}</Label>
                                <Select value={event} onValueChange={setEvent}>
                                    <SelectTrigger id="audit-event"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">{t('filters.all_events')}</SelectItem>
                                        {events.map((item) => <SelectItem key={item} value={item}>{eventLabel(item)}</SelectItem>)}
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button type="submit">{t('filters.apply')}</Button>
                        </form>
                    </CardContent>
                </Card>

                <Card className="overflow-hidden p-0">
                    <ListHeader title={t('title')} />
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('table.date')}</TableHead>
                                        <TableHead>{t('table.user')}</TableHead>
                                        <TableHead>{t('table.event')}</TableHead>
                                        <TableHead>{t('table.document')}</TableHead>
                                        <TableHead>{t('table.description')}</TableHead>
                                        <TableHead className="w-16 text-right">{t('table.details')}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {logs.data.length === 0 ? (
                                        <TableRow><TableCell colSpan={6} className="h-24 text-center text-muted-foreground">{t('table.empty')}</TableCell></TableRow>
                                    ) : logs.data.map((log) => (
                                        <TableRow key={log.id}>
                                            <TableCell className="whitespace-nowrap text-sm text-muted-foreground">{formatDate(log.created_at)}</TableCell>
                                            <TableCell className="whitespace-nowrap">{log.user?.name ?? 'System'}</TableCell>
                                            <TableCell><Badge variant={eventVariants[log.event] ?? 'outline'}>{eventLabel(log.event)}</Badge></TableCell>
                                            <TableCell className="whitespace-nowrap font-mono text-xs">{documentName(log.auditable_type)}{log.auditable_id ? ` #${log.auditable_id}` : ''}</TableCell>
                                            <TableCell className="min-w-64 max-w-xl truncate text-sm text-muted-foreground">{log.description ?? '—'}</TableCell>
                                            <TableCell className="text-right">
                                                <Button type="button" variant="ghost" size="icon" aria-label={t('table.details')} title={t('table.details')} onClick={() => setSelectedLog(log)}>
                                                    <IconEye className="size-4" />
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                        <div className="border-t p-5"><Pagination pagination={logs} /></div>
                    </CardContent>
                </Card>
            </div>

            <Dialog open={!!selectedLog} onOpenChange={(open) => !open && setSelectedLog(null)}>
                <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{t('detail.title')}</DialogTitle>
                        <DialogDescription>{selectedLog?.description ?? '—'}</DialogDescription>
                    </DialogHeader>
                    {selectedLog && (
                        <div className="space-y-5">
                            <div className="grid gap-4 rounded-lg border p-4 sm:grid-cols-3">
                                <DetailValue label={t('detail.document')} value={`${documentName(selectedLog.auditable_type)}${selectedLog.auditable_id ? ` #${selectedLog.auditable_id}` : ''}`} />
                                <DetailValue label={t('detail.actor')} value={selectedLog.user?.name ?? 'System'} />
                                <DetailValue label={t('table.date')} value={formatDate(selectedLog.created_at)} />
                            </div>
                            <div className="grid gap-4 md:grid-cols-2">
                                <ValueTable title={t('detail.old_values')} values={selectedLog.old_values} empty={t('detail.empty')} />
                                <ValueTable title={t('detail.new_values')} values={selectedLog.new_values} empty={t('detail.empty')} />
                            </div>
                        </div>
                    )}
                    <DialogFooter><Button type="button" variant="outline" onClick={() => setSelectedLog(null)}>{t('detail.close')}</Button></DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function DetailValue({ label, value }: { label: string; value: string }) {
    return <div><p className="text-xs text-muted-foreground">{label}</p><p className="mt-1 break-words text-sm font-medium">{value}</p></div>;
}

function ValueTable({ title, values, empty }: { title: string; values: Record<string, unknown> | null; empty: string }) {
    const entries = Object.entries(values ?? {});

    return (
        <div className="overflow-hidden rounded-lg border">
            <div className="border-b bg-muted/30 px-4 py-3 text-sm font-medium">{title}</div>
            {entries.length === 0 ? <p className="p-4 text-sm text-muted-foreground">{empty}</p> : (
                <dl className="divide-y">
                    {entries.map(([key, value]) => (
                        <div key={key} className="grid grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] gap-3 px-4 py-2 text-sm">
                            <dt className="break-words font-mono text-xs text-muted-foreground">{key}</dt>
                            <dd className="break-words">{formatAuditValue(value)}</dd>
                        </div>
                    ))}
                </dl>
            )}
        </div>
    );
}

function formatAuditValue(value: unknown): string {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'object') return JSON.stringify(value);
    return String(value);
}

AuditLogsPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default AuditLogsPage;
