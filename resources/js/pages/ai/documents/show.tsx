import { AndonBadge } from '@/components/ui/andon-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useBreadcrumbs } from '@/hooks/use-breadcrumbs';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, FileScan, RotateCcw, X } from 'lucide-react';
import { type ReactElement, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

interface DocumentLine { product_code: string | null; description: string | null; quantity: string; unit_price: string; tax_code: string | null; confidence: number; }
interface NormalizedPayload { document_type: string; supplier_code: string | null; customer_code: string | null; document_number: string | null; document_date: string | null; due_date: string | null; currency_code: string; warehouse_code: string | null; purchase_order_number: string | null; sales_order_number: string | null; lines: DocumentLine[]; subtotal: string | null; tax_total: string | null; total: string | null; }
interface AiDocument { id: number; original_filename: string; document_type: string; status: 'uploaded' | 'processing' | 'review' | 'accepted' | 'rejected' | 'failed'; mime_type: string; storage_path: string; created_at: string; error_message: string | null; normalized_payload: NormalizedPayload | null; confidence_payload: Record<string, number> | null; }

const statusTone: Record<AiDocument['status'], 'run' | 'caution' | 'stop' | 'info' | 'idle'> = { uploaded: 'info', processing: 'caution', review: 'caution', accepted: 'run', rejected: 'stop', failed: 'stop' };

function AiDocumentShow({ document }: { document: AiDocument }) {
    const { t } = useTranslation('ai-documents');
    const initial = useMemo<NormalizedPayload>(() => document.normalized_payload ?? { document_type: document.document_type, supplier_code: null, customer_code: null, document_number: null, document_date: null, due_date: null, currency_code: 'IDR', warehouse_code: null, purchase_order_number: null, sales_order_number: null, lines: [], subtotal: null, tax_total: null, total: null }, [document]);
    const [payload, setPayload] = useState(initial);

    useBreadcrumbs([{ title: t('title'), href: '/ai/documents' }, { title: t('review'), href: `/ai/documents/${document.id}` }]);

    const setField = (field: keyof NormalizedPayload, value: string) => setPayload((current) => ({ ...current, [field]: value }));
    const updateLine = (index: number, field: keyof DocumentLine, value: string) => setPayload((current) => ({ ...current, lines: current.lines.map((line, lineIndex) => lineIndex === index ? { ...line, [field]: field === 'confidence' ? Number(value) : value } : line) }));

    const retry = () => router.post(`/ai/documents/${document.id}/process`, {}, { preserveScroll: true });
    const reject = () => router.post(`/ai/documents/${document.id}/reject`, {}, { preserveScroll: true });
    const accept = () => router.post(`/ai/documents/${document.id}/accept`, { payload: payload as unknown as Record<string, string> }, { preserveScroll: true });

    return (
        <>
            <Head title={`${t('review')} — ${document.original_filename}`} />
            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex min-w-0 items-center gap-3"><Button asChild variant="ghost" size="icon" aria-label={t('back')}><Link href="/ai/documents"><ArrowLeft /></Link></Button><div className="min-w-0"><h1 className="truncate font-display text-2xl font-semibold tracking-tight">{t('review')}</h1><p className="truncate text-sm text-muted-foreground">{document.original_filename}</p></div></div>
                    <AndonBadge variant={statusTone[document.status]}>{t(`status.${document.status}`)}</AndonBadge>
                </div>
                <p className="text-sm text-muted-foreground">{t('review_description')}</p>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
                    <Card className="overflow-hidden"><CardHeader><CardTitle className="text-base">{t('source_file')}</CardTitle><CardDescription>{document.mime_type}</CardDescription></CardHeader><CardContent className="p-0"><iframe title={document.original_filename} src={`/ai/documents/${document.id}/file`} className="h-[32rem] w-full border-t bg-muted/20" /></CardContent></Card>
                    <Card><CardHeader><CardTitle className="text-base">{t('manual_review')}</CardTitle><CardDescription>{document.error_message ?? (document.normalized_payload ? t('review_description') : t('not_ready'))}</CardDescription></CardHeader><CardContent className="space-y-5">
                        <div className="grid gap-4 sm:grid-cols-2">
                            {([['document_number', 'number'], ['supplier_code', 'supplier'], ['customer_code', 'customer'], ['document_date', 'date'], ['due_date', 'due_date'], ['currency_code', 'currency'], ['warehouse_code', 'warehouse'], ['purchase_order_number', 'po'], ['sales_order_number', 'so'], ['subtotal', 'subtotal'], ['tax_total', 'tax_total'], ['total', 'total']] as const).map(([field, label]) => <div key={field} className="grid gap-2"><Label htmlFor={`ai-${field}`}>{t(`field.${label}`)}</Label><Input id={`ai-${field}`} value={(payload[field] ?? '') as string} onChange={(event) => setField(field, event.target.value)} /></div>)}
                        </div>
                        <div><h2 className="mb-3 text-sm font-semibold">{t('lines.title')}</h2><div className="overflow-x-auto border"><Table><TableHeader><TableRow><TableHead>{t('lines.product')}</TableHead><TableHead>{t('lines.description')}</TableHead><TableHead>{t('lines.quantity')}</TableHead><TableHead>{t('lines.unit_price')}</TableHead><TableHead>{t('lines.tax')}</TableHead><TableHead>{t('lines.confidence')}</TableHead></TableRow></TableHeader><TableBody>{payload.lines.length === 0 ? <TableRow><TableCell colSpan={6} className="h-16 text-center text-muted-foreground">{t('lines.empty')}</TableCell></TableRow> : payload.lines.map((line, index) => <TableRow key={index}><TableCell><Input value={line.product_code ?? ''} onChange={(event) => updateLine(index, 'product_code', event.target.value)} /></TableCell><TableCell><Input value={line.description ?? ''} onChange={(event) => updateLine(index, 'description', event.target.value)} /></TableCell><TableCell><Input value={line.quantity} onChange={(event) => updateLine(index, 'quantity', event.target.value)} /></TableCell><TableCell><Input value={line.unit_price} onChange={(event) => updateLine(index, 'unit_price', event.target.value)} /></TableCell><TableCell><Input value={line.tax_code ?? ''} onChange={(event) => updateLine(index, 'tax_code', event.target.value)} /></TableCell><TableCell className={line.confidence < 0.7 ? 'font-medium text-nx-andon-caution' : 'text-muted-foreground'}>{line.confidence < 0.7 ? t('confidence_low') : `${Math.round(line.confidence * 100)}%`}</TableCell></TableRow>)}</TableBody></Table></div></div>
                        <div className="flex flex-wrap justify-end gap-2"><Button type="button" variant="cancel" onClick={reject} disabled={!['review', 'failed'].includes(document.status)}><X className="size-4" />{t('reject')}</Button>{['failed', 'uploaded'].includes(document.status) && <Button type="button" variant="default" onClick={retry}><RotateCcw className="size-4" />{t('retry')}</Button>}<Button type="button" variant="save" disabled={document.status !== 'review' || payload.lines.length === 0} onClick={accept}><FileScan className="size-4" />{t('create_draft')}</Button></div>
                    </CardContent></Card>
                </div>
            </div>
        </>
    );
}

AiDocumentShow.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default AiDocumentShow;
