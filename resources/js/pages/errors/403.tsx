import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { IconShieldLock } from '@tabler/icons-react';
import { type ReactElement } from 'react';

function Error403() {
    return (
        <>
            <Head title="403 - Akses Ditolak" />

            <div className="flex min-h-[calc(100vh-4rem)] items-center justify-center p-6">
                <Card className="w-full max-w-md">
                    <CardHeader className="text-center">
                        <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-destructive/10">
                            <IconShieldLock className="h-8 w-8 text-destructive" />
                        </div>
                        <CardTitle className="text-2xl">
                            Akses Ditolak
                        </CardTitle>
                        <CardDescription>
                            Anda tidak memiliki izin untuk mengakses halaman ini
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="rounded-lg border border-destructive/20 bg-destructive/10 p-4">
                            <p className="text-sm text-muted-foreground">
                                Halaman ini hanya dapat diakses oleh administrator.
                                Jika Anda merasa seharusnya memiliki akses, silakan
                                hubungi administrator sistem Anda.
                            </p>
                        </div>

                        <div className="flex justify-center gap-2">
                            <Button asChild>
                                <Link href="/dashboard">
                                    Kembali ke Dasbor
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Error403.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default Error403;
