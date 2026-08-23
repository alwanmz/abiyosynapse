import { Button } from '@/components/ui/button';
import { Printer } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export function PrintDocumentButton({ type, documentId }: { type: string; documentId: number }) {
    const { t } = useTranslation('common');

    return (
        <Button type="button" variant="print" size="sm" onClick={() => window.open(`/documents/${type}/${documentId}/print`, '_blank')}>
            <Printer className="size-4" />
            {t('action.print')}
        </Button>
    );
}
