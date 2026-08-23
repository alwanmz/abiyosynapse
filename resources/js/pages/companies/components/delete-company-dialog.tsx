import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import companiesRoutes from '@/routes/companies';
import { useForm } from '@inertiajs/react';
import { IconAlertTriangle } from '@tabler/icons-react';
import { useTranslation } from 'react-i18next';

interface DeleteCompanyDialogProps {
    company: { id: number; name: string } | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

export function DeleteCompanyDialog({
    company,
    open,
    onOpenChange,
}: DeleteCompanyDialogProps) {
    const { t } = useTranslation('companies');
    const { delete: destroy, processing } = useForm({});

    const handleDelete = () => {
        if (!company) return;

        destroy(companiesRoutes.destroy(company.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-destructive">
                        <IconAlertTriangle className="h-5 w-5" />
                        {t('dialog.delete.title')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('dialog.delete.description')}{' '}
                        <span className="font-semibold">{company?.name}</span>?
                        {' '}{t('dialog.delete.description_suffix')}
                    </DialogDescription>
                </DialogHeader>

                <div className="rounded-lg border border-destructive/20 bg-destructive/10 p-4">
                    <div className="flex gap-3">
                        <IconAlertTriangle className="h-5 w-5 text-destructive" />
                        <div className="flex-1 space-y-1">
                            <p className="text-sm font-medium text-destructive">
                                {t('dialog.delete.warning_title')}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {t('dialog.delete.warning_body')}
                            </p>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="cancel"
                        onClick={() => onOpenChange(false)}
                        disabled={processing}
                    >
                        {t('dialog.delete.cancel')}
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={handleDelete}
                        disabled={processing}
                    >
                        {processing ? t('dialog.delete.submitting') : t('dialog.delete.submit')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
