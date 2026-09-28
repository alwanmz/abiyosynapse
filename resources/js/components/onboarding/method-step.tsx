import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { IconArrowLeft, IconDownload, IconFileSpreadsheet, IconListDetails, IconSparkles } from '@tabler/icons-react';
import { useState } from 'react';
import { collectErrors } from './types';

export function MethodStep({ aiConfigured }: { aiConfigured: boolean }) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const [pending, setPending] = useState<'ai' | 'standard' | null>(null);
    const upload = useForm<{ file: File | null }>({ file: null });
    const uploadErrors = collectErrors(errors, 'file');

    const generate = (method: 'ai' | 'standard') => {
        setPending(method);
        router.post('/onboarding/generate', { method }, { onFinish: () => setPending(null) });
    };

    return (
        <div className="space-y-6">
            <div className="grid gap-4 lg:grid-cols-3">
                <Card className="relative border-nx-cyan-500 ring-1 ring-nx-cyan-500">
                    <Badge className="absolute -top-2.5 left-4 bg-nx-cyan-500 text-white">Direkomendasikan</Badge>
                    <CardHeader>
                        <IconSparkles className="mb-2 size-7 text-nx-cyan-600" />
                        <CardTitle>Susun dengan AI</CardTitle>
                        <CardDescription>
                            AI menyusun bagan akun sesuai industri dan jenis usaha Anda, lengkap dengan akun khususnya.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        <Button className="w-full" disabled={pending !== null} onClick={() => generate('ai')}>
                            {pending === 'ai' && <Spinner />}
                            Buat COA dengan AI
                        </Button>
                        {!aiConfigured && (
                            <p className="text-xs text-muted-foreground">
                                AI belum aktif di server ini; COA standar akan dipakai sebagai gantinya.
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <IconFileSpreadsheet className="mb-2 size-7 text-nx-navy-600" />
                        <CardTitle>Upload template</CardTitle>
                        <CardDescription>
                            Sudah punya COA sendiri? Isi template Excel lalu upload di sini.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <Button variant="outline" className="w-full" asChild>
                            <a href="/onboarding/coa-template">
                                <IconDownload />
                                Download template
                            </a>
                        </Button>
                        <form
                            className="space-y-2"
                            onSubmit={(event) => {
                                event.preventDefault();
                                upload.post('/onboarding/coa-upload', { forceFormData: true });
                            }}
                        >
                            <Input
                                type="file"
                                accept=".xlsx,.xls,.csv"
                                onChange={(event) => upload.setData('file', event.target.files?.[0] ?? null)}
                            />
                            <Button type="submit" variant="secondary" className="w-full" disabled={!upload.data.file || upload.processing}>
                                {upload.processing && <Spinner />}
                                Upload COA
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <IconListDetails className="mb-2 size-7 text-nx-navy-600" />
                        <CardTitle>COA standar</CardTitle>
                        <CardDescription>
                            Mulai dari bagan akun umum Nexumi (31 akun), lalu sesuaikan sendiri.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Button variant="outline" className="w-full" disabled={pending !== null} onClick={() => generate('standard')}>
                            {pending === 'standard' && <Spinner />}
                            Pakai COA standar
                        </Button>
                    </CardContent>
                </Card>
            </div>

            {uploadErrors.length > 0 && (
                <div className="rounded-lg border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive">
                    <p className="mb-2 font-medium">File belum bisa dipakai:</p>
                    <ul className="list-disc space-y-1 pl-5">
                        {uploadErrors.map((message) => (
                            <li key={message}>{message}</li>
                        ))}
                    </ul>
                </div>
            )}

            <Button variant="ghost" asChild>
                <Link href="/onboarding?step=profile">
                    <IconArrowLeft />
                    Ubah profil usaha
                </Link>
            </Button>
        </div>
    );
}
