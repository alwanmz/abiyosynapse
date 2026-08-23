import { AndonBadge } from '@/components/ui/andon-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useBreadcrumbs } from '@/hooks/use-breadcrumbs';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FileScan, Plus } from 'lucide-react';
import { type FormEvent, type ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

interface AiDocument {
    id: number;
    original_filename: string;
    document_type: string;
    status: 'uploaded' | 'processing' | 'review' | 'accepted' | 'rejected' | 'failed';
    mime_type: string;
    file_size: number;
    created_at: string;
    uploader?: { id: number; name: string } | null;
}

interface PageProps {
    documents: AiDocument[];
    documentTypes: string[];
}

const statusTone: Record<AiDocument['status'], 'run' | 'caution' | 'stop' | 'info' | 'idle'> = {
    uploaded: 'info',
    processing: 'caution',
    review: 'caution',
    accepted: 'run',
    rejected: 'stop',
    failed: 'stop',
};

function formatDate(value: string): string {
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

function formatSize(bytes: number): string {
    if (bytes < 1024 * 1024) return `${Math.ceil(bytes / 1024)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function AiDocumentsPage({ documents, documentTypes }: PageProps) {
    const { t } = useTranslation('ai-documents');
    const form = useForm<{ document_type: string; file: File | null }>({ document_type: 'unknown', file: null });

    useBreadcrumbs([{ title: t('title'), href: '/ai/documents' }]);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/ai/documents', { forceFormData: true, preserveScroll: true });
    };

    return (
        <>
            <Head title={t('title')} />
            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <div className="mb-2 flex items-center gap-2 text-primary"><FileScan className="size-5" /><span className="text-xs font-medium uppercase tracking-wide">AI</span></div>
                        <h1 className="font-display text-2xl font-semibold tracking-tight">{t('title')}</h1>
                        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">{t('description')}</p>
                    </div>
                    <Button onClick={() => document.getElementById('ai-document-upload')?.scrollIntoView({ behavior: 'smooth', block: 'center' })}>
                        <Plus className="size-4" />{t('upload')}
                    </Button>
                </div>

                <Card id="ai-document-upload">
                    <CardHeader><CardTitle className="text-base">{t('upload_title')}</CardTitle><CardDescription>{t('manual_review')}</CardDescription></CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="grid gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] md:items-end">
                            <div className="grid gap-2">
                                <Label htmlFor="ai-document-type">{t('document_type')}</Label>
                                <Select value={form.data.document_type} onValueChange={(value) => form.setData('document_type', value)}>
                                    <SelectTrigger id="ai-document-type"><SelectValue /></SelectTrigger>
                                    <SelectContent>{documentTypes.map((type) => <SelectItem key={type} value={type}>{t(`types.${type}`)}</SelectItem>)}</SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="ai-document-file">{t('file')}</Label>
                                <Input id="ai-document-file" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" onChange={(event) => form.setData('file', event.target.files?.[0] ?? null)} />
                                {form.errors.file && <p className="text-sm text-destructive">{form.errors.file}</p>}
                            </div>
                            <Button type="submit" variant="save" disabled={form.processing || !form.data.file}>{form.processing ? t('processing') : t('submit')}</Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle className="text-base">{t('title')}</CardTitle></CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader><TableRow><TableHead>{t('table.file')}</TableHead><TableHead>{t('table.type')}</TableHead><TableHead>{t('table.status')}</TableHead><TableHead>{t('table.uploaded_by')}</TableHead><TableHead>{t('table.date')}</TableHead><TableHead className="text-right">{t('table.actions')}</TableHead></TableRow></TableHeader>
                                <TableBody>
                                    {documents.length === 0 ? <TableRow><TableCell colSpan={6} className="h-24 text-center text-muted-foreground">{t('empty')}</TableCell></TableRow> : documents.map((document) => (
                                        <TableRow key={document.id}>
                                            <TableCell><Link href={`/ai/documents/${document.id}`} className="flex max-w-xs items-center gap-2 font-medium hover:text-primary"><FileScan className="size-4 shrink-0 text-muted-foreground" /><span className="truncate">{document.original_filename}</span><span className="shrink-0 text-xs text-muted-foreground">{formatSize(document.file_size)}</span></Link></TableCell>
                                            <TableCell>{t(`types.${document.document_type}`)}</TableCell>
                                            <TableCell><AndonBadge variant={statusTone[document.status]}>{t(`status.${document.status}`)}</AndonBadge></TableCell>
                                            <TableCell>{document.uploader?.name ?? '—'}</TableCell>
                                            <TableCell className="whitespace-nowrap text-sm text-muted-foreground">{formatDate(document.created_at)}</TableCell>
                                            <TableCell className="text-right"><Button asChild variant="outline" size="sm"><Link href={`/ai/documents/${document.id}`}>{t('review')}</Link></Button></TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AiDocumentsPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default AiDocumentsPage;
