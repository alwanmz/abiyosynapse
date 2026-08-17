import { ConfirmDialog } from '@/components/confirm-dialog';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { useBreadcrumbs } from '@/hooks/use-breadcrumbs';
import { Head, router, useForm } from '@inertiajs/react';
import { type ReactElement, useState } from 'react';
import { useTranslation } from 'react-i18next';

interface Ncr {
    id: number;
    number: string;
    description: string;
    status: 'open' | 'investigation' | 'disposition' | 'corrective_action' | 'closed';
    disposition: 'rework' | 'scrap' | 'return' | 'use_as_is' | null;
    created_at: string;
    inspection: { id: number; product: { id: number; code: string; name: string } };
    creator: { id: number; name: string } | null;
}

interface PageProps {
    ncrs: Ncr[];
}

const STATUS_VARIANT: Record<Ncr['status'], 'default' | 'outline' | 'secondary'> = {
    open: 'outline',
    investigation: 'secondary',
    disposition: 'secondary',
    corrective_action: 'secondary',
    closed: 'default',
};

function NcrsPage({ ncrs }: PageProps) {
    const { t } = useTranslation('quality');
    const [dispositionTarget, setDispositionTarget] = useState<Ncr | null>(null);
    const [correctiveActionTarget, setCorrectiveActionTarget] = useState<Ncr | null>(null);
    const [closeTarget, setCloseTarget] = useState<Ncr | null>(null);

    useBreadcrumbs([
        { title: t('nav.quality'), href: '#' },
        { title: t('ncr.breadcrumb'), href: '/quality/ncrs' },
    ]);

    const dispositionForm = useForm({ disposition: '', disposition_notes: '' });
    const correctiveActionForm = useForm({ corrective_action: '' });
    const { post: postClose, processing: closing } = useForm({});

    const handleDisposition = (e: React.FormEvent) => {
        e.preventDefault();
        if (!dispositionTarget) return;
        dispositionForm.post(`/quality/ncrs/${dispositionTarget.id}/disposition`, {
            preserveScroll: true,
            onSuccess: () => {
                setDispositionTarget(null);
                dispositionForm.reset();
            },
        });
    };

    const handleCorrectiveAction = (e: React.FormEvent) => {
        e.preventDefault();
        if (!correctiveActionTarget) return;
        correctiveActionForm.post(`/quality/ncrs/${correctiveActionTarget.id}/corrective-action`, {
            preserveScroll: true,
            onSuccess: () => {
                setCorrectiveActionTarget(null);
                correctiveActionForm.reset();
            },
        });
    };

    const handleClose = () => {
        if (!closeTarget) return;
        postClose(`/quality/ncrs/${closeTarget.id}/close`, {
            preserveScroll: true,
            onSuccess: () => setCloseTarget(null),
        });
    };

    return (
        <>
            <Head title={t('ncr.title')} />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold tracking-tight">{t('ncr.title')}</h1>
                    <p className="text-sm text-muted-foreground">{t('ncr.description')}</p>
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader title={t('ncr.list_title')} />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('ncr.table.number')}</TableHead>
                                    <TableHead>{t('ncr.table.product')}</TableHead>
                                    <TableHead>{t('ncr.table.status')}</TableHead>
                                    <TableHead>{t('ncr.table.disposition')}</TableHead>
                                    <TableHead>{t('ncr.table.created_at')}</TableHead>
                                    <TableHead className="text-right">Aksi</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {ncrs.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={6} className="h-24 text-center text-muted-foreground">
                                            {t('ncr.empty')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    ncrs.map((ncr) => (
                                        <TableRow key={ncr.id}>
                                            <TableCell className="font-mono text-sm">{ncr.number}</TableCell>
                                            <TableCell>
                                                <div className="font-medium">{ncr.inspection.product.name}</div>
                                                <div className="font-mono text-xs text-muted-foreground">
                                                    {ncr.inspection.product.code}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant={STATUS_VARIANT[ncr.status]}>
                                                    {t(`ncr.status_${ncr.status}`)}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {ncr.disposition ? t(`ncr.disposition_${ncr.disposition}`) : '—'}
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">{ncr.created_at}</TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end gap-2">
                                                    {ncr.status !== 'closed' && !ncr.disposition && (
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() => setDispositionTarget(ncr)}
                                                        >
                                                            {t('ncr.set_disposition')}
                                                        </Button>
                                                    )}
                                                    {ncr.disposition && ncr.status !== 'closed' && ncr.status !== 'corrective_action' && (
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() => setCorrectiveActionTarget(ncr)}
                                                        >
                                                            {t('ncr.apply_corrective_action')}
                                                        </Button>
                                                    )}
                                                    {ncr.disposition && ncr.status !== 'closed' && (
                                                        <Button variant="outline" size="sm" onClick={() => setCloseTarget(ncr)}>
                                                            {t('ncr.close_ncr')}
                                                        </Button>
                                                    )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>

            <Dialog open={!!dispositionTarget} onOpenChange={(open) => !open && setDispositionTarget(null)}>
                <DialogContent className="sm:max-w-[450px]">
                    <DialogHeader>
                        <DialogTitle>{t('ncr.set_disposition')}</DialogTitle>
                        <DialogDescription>{dispositionTarget?.number}</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleDisposition}>
                        <div className="grid gap-4 py-4">
                            <div className="grid gap-2">
                                <Label>{t('ncr.disposition_label')}</Label>
                                <Select
                                    value={dispositionForm.data.disposition}
                                    onValueChange={(value) => dispositionForm.setData('disposition', value)}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder={t('ncr.disposition_placeholder')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="rework">{t('ncr.disposition_rework')}</SelectItem>
                                        <SelectItem value="scrap">{t('ncr.disposition_scrap')}</SelectItem>
                                        <SelectItem value="return">{t('ncr.disposition_return')}</SelectItem>
                                        <SelectItem value="use_as_is">{t('ncr.disposition_use_as_is')}</SelectItem>
                                    </SelectContent>
                                </Select>
                                {dispositionForm.errors.disposition && (
                                    <p className="text-sm text-destructive">{dispositionForm.errors.disposition}</p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label>{t('ncr.disposition_notes')}</Label>
                                <Textarea
                                    placeholder={t('ncr.disposition_notes_placeholder')}
                                    value={dispositionForm.data.disposition_notes}
                                    onChange={(e) => dispositionForm.setData('disposition_notes', e.target.value)}
                                />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setDispositionTarget(null)}
                                disabled={dispositionForm.processing}
                            >
                                Batal
                            </Button>
                            <Button type="submit" disabled={dispositionForm.processing}>
                                {t('ncr.set_disposition')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={!!correctiveActionTarget} onOpenChange={(open) => !open && setCorrectiveActionTarget(null)}>
                <DialogContent className="sm:max-w-[450px]">
                    <DialogHeader>
                        <DialogTitle>{t('ncr.apply_corrective_action')}</DialogTitle>
                        <DialogDescription>{correctiveActionTarget?.number}</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleCorrectiveAction}>
                        <div className="grid gap-4 py-4">
                            <div className="grid gap-2">
                                <Label>{t('ncr.corrective_action_label')}</Label>
                                <Textarea
                                    placeholder={t('ncr.corrective_action_placeholder')}
                                    value={correctiveActionForm.data.corrective_action}
                                    onChange={(e) => correctiveActionForm.setData('corrective_action', e.target.value)}
                                />
                                {correctiveActionForm.errors.corrective_action && (
                                    <p className="text-sm text-destructive">{correctiveActionForm.errors.corrective_action}</p>
                                )}
                            </div>
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setCorrectiveActionTarget(null)}
                                disabled={correctiveActionForm.processing}
                            >
                                Batal
                            </Button>
                            <Button type="submit" disabled={correctiveActionForm.processing}>
                                {t('ncr.apply_corrective_action')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={!!closeTarget}
                onOpenChange={(open) => !open && setCloseTarget(null)}
                title={t('ncr.close_confirm_title')}
                description={t('ncr.close_confirm_description')}
                confirmLabel={t('ncr.close_ncr')}
                variant="default"
                loading={closing}
                onConfirm={handleClose}
            />
        </>
    );
}

NcrsPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default NcrsPage;
