import { Button } from '@/components/ui/button';
import { IconUserPlus } from '@tabler/icons-react';
import { useTranslation } from 'react-i18next';

interface PageHeaderProps {
    onAddUser: () => void;
}

export function PageHeader({ onAddUser }: PageHeaderProps) {
    const { t } = useTranslation('manage-users');

    return (
        <div className="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 className="text-2xl font-semibold tracking-tight">
                    {t('page_title')}
                </h1>
                <p className="text-sm text-muted-foreground">
                    {t('page_description')}
                </p>
            </div>

            <div className="flex items-center gap-2">
                <Button onClick={onAddUser}>
                    <IconUserPlus className="mr-2 h-4 w-4" />
                    {t('add_user')}
                </Button>
            </div>
        </div>
    );
}
