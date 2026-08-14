import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { FolderRoot } from 'lucide-react';

interface EmptyStateProps {
    onCreateClick: () => void;
    canCreate?: boolean;
}

export function EmptyState({
    onCreateClick,
    canCreate = true,
}: EmptyStateProps) {
    return (
        <Card className="py-12">
            <CardContent className="flex flex-col items-center justify-center text-center">
                <FolderRoot className="mb-4 h-12 w-12 text-muted-foreground" />
                <h3 className="mb-2 text-lg font-semibold">
                    Proyek tidak ditemukan
                </h3>
                <p className="mb-4 text-sm text-muted-foreground">
                    Coba sesuaikan filter Anda
                    {canCreate ? ' atau buat proyek baru' : ''}
                </p>
                {canCreate && (
                    <Button onClick={onCreateClick}>
                        Buat Proyek Pertama
                    </Button>
                )}
            </CardContent>
        </Card>
    );
}
