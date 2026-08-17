import { ListHeader } from '@/components/list-header';
import { ConfirmDialog } from '@/components/confirm-dialog';
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
import { type ReactElement, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Pencil, Trash2 } from 'lucide-react';

interface UnitOfMeasure {
    id: number;
    code: string;
    name: string;
    is_active: boolean;
}

interface PageProps {
    unitOfMeasures: UnitOfMeasure[];
}

function UnitOfMeasuresPage({ unitOfMeasures }: PageProps) {
    const { t } = useTranslation('master');
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<UnitOfMeasure | null>(null);
    const [deleting, setDeleting] = useState<UnitOfMeasure | null>(null);

    useBreadcrumbs([
        { title: t('nav.master_data'), href: '#' },
        { title: t('uom.title'), href: '/master/unit-of-measures' },
    ]);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        code: '',
        name: '',
        is_active: true,
    });

    const { delete: destroy, processing: deleting_ } = useForm({});

    const openCreate = () => {
        setEditing(null);
        reset();
        setDialogOpen(true);
    };

    const openEdit = (uom: UnitOfMeasure) => {
        setEditing(uom);
        setData({ code: uom.code, name: uom.name, is_active: uom.is_active });
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
            put(`/master/unit-of-measures/${editing.id}`, options);
        } else {
            post('/master/unit-of-measures', options);
        }
    };

    const handleDelete = () => {
        if (!deleting) return;
        destroy(`/master/unit-of-measures/${deleting.id}`, {
            preserveScroll: true,
            onSuccess: () => setDeleting(null),
            onError: () => setDeleting(null),
        });
    };

    return (
        <>
            <Head title={t('uom.title')} />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">{t('uom.title')}</h1>
                        <p className="text-sm text-muted-foreground">{t('uom.description')}</p>
                    </div>
                    <Button onClick={openCreate}>
                        <IconPlus className="mr-2 h-4 w-4" />
                        {t('uom.add')}
                    </Button>
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader title={t('uom.list_title')} />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('common.code')}</TableHead>
                                    <TableHead>{t('common.name')}</TableHead>
                                    <TableHead>{t('common.status')}</TableHead>
                                    <TableHead className="text-right">{t('common.actions')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {unitOfMeasures.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={4} className="h-24 text-center text-muted-foreground">
                                            {t('uom.empty')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    unitOfMeasures.map((uom) => (
                                        <TableRow key={uom.id}>
                                            <TableCell className="font-mono text-sm">{uom.code}</TableCell>
                                            <TableCell>{uom.name}</TableCell>
                                            <TableCell>
                                                <Badge variant={uom.is_active ? 'default' : 'outline'}>
                                                    {uom.is_active ? t('common.active') : t('common.inactive')}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button variant="ghost" size="icon" onClick={() => openEdit(uom)}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="text-destructive hover:text-destructive"
                                                        onClick={() => setDeleting(uom)}
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
                        <DialogTitle>{editing ? t('uom.edit_title') : t('uom.add_title')}</DialogTitle>
                        <DialogDescription>{t('uom.form_description')}</DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleSubmit}>
                        <div className="grid gap-4 py-4">
                            <div className="grid gap-2">
                                <Label htmlFor="code">{t('common.code')}</Label>
                                <Input
                                    id="code"
                                    placeholder={t('uom.code_placeholder')}
                                    value={data.code}
                                    onChange={(e) => setData('code', e.target.value)}
                                />
                                {errors.code && <p className="text-sm text-destructive">{errors.code}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="name">{t('common.name')}</Label>
                                <Input
                                    id="name"
                                    placeholder={t('uom.name_placeholder')}
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                />
                                {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                            </div>
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setDialogOpen(false)}
                                disabled={processing}
                            >
                                {t('common.cancel')}
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? t('common.saving') : t('common.save')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={!!deleting}
                onOpenChange={(open) => !open && setDeleting(null)}
                title={t('uom.delete_title')}
                description={
                    <>
                        {t('uom.delete_confirm_prefix')}{' '}
                        <strong className="text-foreground">"{deleting?.name}"</strong>?
                    </>
                }
                confirmLabel={t('common.delete')}
                loading={deleting_}
                onConfirm={handleDelete}
            />
        </>
    );
}

UnitOfMeasuresPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default UnitOfMeasuresPage;
