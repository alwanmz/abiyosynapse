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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { Head, useForm } from '@inertiajs/react';
import { IconPlus } from '@tabler/icons-react';
import { Pencil, Trash2 } from 'lucide-react';
import { type ReactElement, useState } from 'react';
import { useTranslation } from 'react-i18next';

interface WorkCenter {
    id: number;
    code: string;
    name: string;
    capacity_per_day_minutes: string;
    cost_rate_per_minute: string;
    is_active: boolean;
}

interface PageProps {
    workCenters: WorkCenter[];
}

function WorkCentersPage({ workCenters }: PageProps) {
    const { t } = useTranslation('manufacturing');
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<WorkCenter | null>(null);
    const [deleting, setDeleting] = useState<WorkCenter | null>(null);

    useBreadcrumbs([
        { title: t('nav.manufacturing'), href: '#' },
        { title: t('work_center.breadcrumb'), href: '/manufacturing/work-centers' },
    ]);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        code: '',
        name: '',
        capacity_per_day_minutes: '480',
        cost_rate_per_minute: '0',
        is_active: true,
    });

    const { delete: destroy, processing: deleting_ } = useForm({});

    const openCreate = () => {
        setEditing(null);
        reset();
        setDialogOpen(true);
    };

    const openEdit = (wc: WorkCenter) => {
        setEditing(wc);
        setData({
            code: wc.code,
            name: wc.name,
            capacity_per_day_minutes: wc.capacity_per_day_minutes,
            cost_rate_per_minute: wc.cost_rate_per_minute,
            is_active: wc.is_active,
        });
        setDialogOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                setDialogOpen(false);
                reset();
            },
        };

        if (editing) {
            put(`/manufacturing/work-centers/${editing.id}`, options);
        } else {
            post('/manufacturing/work-centers', options);
        }
    };

    const handleDelete = () => {
        if (!deleting) return;
        destroy(`/manufacturing/work-centers/${deleting.id}`, {
            preserveScroll: true,
            onSuccess: () => setDeleting(null),
            onError: () => setDeleting(null),
        });
    };

    return (
        <>
            <Head title={t('work_center.title')} />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">{t('work_center.title')}</h1>
                        <p className="text-sm text-muted-foreground">{t('work_center.description')}</p>
                    </div>
                    <Button onClick={openCreate}>
                        <IconPlus className="mr-2 h-4 w-4" />
                        {t('work_center.add')}
                    </Button>
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader title={t('work_center.list_title')} />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Kode</TableHead>
                                    <TableHead>Nama</TableHead>
                                    <TableHead className="text-right">{t('work_center.capacity')}</TableHead>
                                    <TableHead className="text-right">{t('work_center.cost_rate')}</TableHead>
                                    <TableHead className="text-right" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {workCenters.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={5} className="h-24 text-center text-muted-foreground">
                                            {t('work_center.empty')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    workCenters.map((wc) => (
                                        <TableRow key={wc.id}>
                                            <TableCell className="font-mono text-sm">{wc.code}</TableCell>
                                            <TableCell>
                                                {wc.name}{' '}
                                                {!wc.is_active && (
                                                    <Badge variant="outline" className="ml-2">
                                                        Nonaktif
                                                    </Badge>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {wc.capacity_per_day_minutes}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {wc.cost_rate_per_minute}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button variant="ghost" size="icon" onClick={() => openEdit(wc)}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="text-destructive hover:text-destructive"
                                                        onClick={() => setDeleting(wc)}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
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

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle>{editing ? t('work_center.edit_title') : t('work_center.add_title')}</DialogTitle>
                        <DialogDescription>{t('work_center.description')}</DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleSubmit}>
                        <div className="grid gap-4 py-4">
                            <div className="grid gap-2">
                                <Label htmlFor="code">Kode</Label>
                                <Input
                                    id="code"
                                    placeholder={t('work_center.code_placeholder')}
                                    value={data.code}
                                    onChange={(e) => setData('code', e.target.value)}
                                />
                                {errors.code && <p className="text-sm text-destructive">{errors.code}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="name">Nama</Label>
                                <Input
                                    id="name"
                                    placeholder={t('work_center.name_placeholder')}
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                />
                                {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="capacity_per_day_minutes">{t('work_center.capacity')}</Label>
                                    <Input
                                        id="capacity_per_day_minutes"
                                        type="number"
                                        min={0}
                                        value={data.capacity_per_day_minutes}
                                        onChange={(e) => setData('capacity_per_day_minutes', e.target.value)}
                                    />
                                    {errors.capacity_per_day_minutes && (
                                        <p className="text-sm text-destructive">{errors.capacity_per_day_minutes}</p>
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="cost_rate_per_minute">{t('work_center.cost_rate')}</Label>
                                    <Input
                                        id="cost_rate_per_minute"
                                        type="number"
                                        step="0.0001"
                                        min={0}
                                        value={data.cost_rate_per_minute}
                                        onChange={(e) => setData('cost_rate_per_minute', e.target.value)}
                                    />
                                    {errors.cost_rate_per_minute && (
                                        <p className="text-sm text-destructive">{errors.cost_rate_per_minute}</p>
                                    )}
                                </div>
                            </div>
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="cancel"
                                onClick={() => setDialogOpen(false)}
                                disabled={processing}
                            >
                                Batal
                            </Button>
                            <Button type="submit" variant="save" disabled={processing}>
                                {processing ? 'Menyimpan...' : 'Simpan'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={!!deleting}
                onOpenChange={(open) => !open && setDeleting(null)}
                title={t('work_center.delete_title')}
                description={
                    <>
                        {t('work_center.delete_confirm_prefix')}{' '}
                        <strong className="text-foreground">"{deleting?.name}"</strong>?
                    </>
                }
                confirmLabel="Hapus"
                loading={deleting_}
                onConfirm={handleDelete}
            />
        </>
    );
}

WorkCentersPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default WorkCentersPage;
