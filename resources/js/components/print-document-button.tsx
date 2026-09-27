import { Button } from '@/components/ui/button';
import { FileSpreadsheet, Printer } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface PrintDocumentButtonProps {
    type: string;
    documentId: number;
    compact?: boolean;
}

export function PrintDocumentButton({ type, documentId, compact = false }: PrintDocumentButtonProps) {
    const { t } = useTranslation('common');
    const size = compact ? 'icon' : 'sm';

    return (
        <div className="flex flex-wrap items-center gap-2">
            <Button
                type="button"
                variant="print"
                size={size}
                title={t('action.print')}
                aria-label={t('action.print')}
                onClick={() => window.open(`/documents/${type}/${documentId}/print`, '_blank', 'noopener,noreferrer')}
            >
                <Printer className="size-4" />
                {!compact && t('action.print')}
            </Button>
            <Button
                type="button"
                variant="save"
                size={size}
                title={t('action.export_excel')}
                aria-label={t('action.export_excel')}
                onClick={() => window.open(`/documents/${type}/${documentId}/xlsx`, '_blank', 'noopener,noreferrer')}
            >
                <FileSpreadsheet className="size-4" />
                {!compact && t('action.export_excel')}
            </Button>
        </div>
    );
}
