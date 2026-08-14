import { send } from '@/routes/verification';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

import { AvatarUploadField } from '@/components/avatar-upload-field';
import DeleteUser from '@/components/delete-user';
import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { edit } from '@/routes/profile';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Pengaturan Profil',
        href: edit().url,
    },
];

export default function Profile({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const { auth } = usePage<SharedData>().props;
    const [savedAt, setSavedAt] = useState<number | null>(null);

    // Manual useForm so we can send a file with `_method=patch`. Inertia's
    // patch()/put() helpers don't accept multipart bodies.
    const { data, setData, post, processing, errors } = useForm<{
        _method: string;
        name: string;
        email: string;
        avatar: File | null;
        remove_avatar: boolean;
    }>({
        _method: 'patch',
        name: auth.user.name,
        email: auth.user.email,
        avatar: null,
        remove_avatar: false,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post(edit().url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setSavedAt(Date.now());
                setData('avatar', null);
                setData('remove_avatar', false);
            },
        });
    };

    // Show "Saved" for 2s after a successful save.
    const recentlySuccessful = savedAt !== null && Date.now() - savedAt < 2000;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pengaturan Profil" />

            <h1 className="sr-only">Pengaturan Profil</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Informasi Profil"
                        description="Perbarui nama, email, dan avatar Anda"
                    />

                    <form onSubmit={handleSubmit} className="space-y-6">
                        <AvatarUploadField
                            name={data.name}
                            currentUrl={typeof auth.user.avatar_url === 'string' ? auth.user.avatar_url : null}
                            onChange={({ file, remove }) => {
                                setData('avatar', file);
                                setData('remove_avatar', remove);
                            }}
                        />
                        {errors.avatar && (
                            <p className="-mt-3 text-sm text-destructive">
                                {errors.avatar}
                            </p>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="name">Nama</Label>
                            <Input
                                id="name"
                                className="mt-1 block w-full"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                required
                                autoComplete="name"
                                placeholder="Nama lengkap"
                            />
                            <InputError className="mt-2" message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="email">Alamat email</Label>
                            <Input
                                id="email"
                                type="email"
                                className="mt-1 block w-full"
                                value={data.email}
                                onChange={(e) =>
                                    setData('email', e.target.value)
                                }
                                required
                                autoComplete="username"
                                placeholder="Alamat email"
                            />
                            <InputError
                                className="mt-2"
                                message={errors.email}
                            />
                        </div>

                        {mustVerifyEmail &&
                            auth.user.email_verified_at === null && (
                                <div>
                                    <p className="-mt-4 text-sm text-muted-foreground">
                                        Alamat email Anda belum terverifikasi.{' '}
                                        <Link
                                            href={send()}
                                            as="button"
                                            className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                        >
                                            Klik di sini untuk mengirim ulang email verifikasi.
                                        </Link>
                                    </p>

                                    {status === 'verification-link-sent' && (
                                        <div className="mt-2 text-sm font-medium text-green-600">
                                            Tautan verifikasi baru telah dikirim ke alamat email Anda.
                                        </div>
                                    )}
                                </div>
                            )}

                        <div className="flex items-center gap-4">
                            <Button
                                disabled={processing}
                                data-test="update-profile-button"
                            >
                                Simpan
                            </Button>

                            {recentlySuccessful && (
                                <p className="text-sm text-neutral-600">
                                    Tersimpan
                                </p>
                            )}
                        </div>
                    </form>
                </div>

                <DeleteUser />
            </SettingsLayout>
        </AppLayout>
    );
}
