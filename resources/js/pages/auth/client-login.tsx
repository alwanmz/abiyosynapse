import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { Form, Head } from '@inertiajs/react';
import { Eye, EyeOff, ShieldCheck } from 'lucide-react';
import { useState } from 'react';

interface ClientLoginProps {
    status?: string;
}

export default function ClientLogin({ status }: ClientLoginProps) {
    const [showPassword, setShowPassword] = useState(false);

    return (
        <AuthLayout
            title="Portal Klien"
            description="Masuk untuk memantau progres tiket Anda"
        >
            <Head title="Portal Klien" />

            <Form
                action="/portal/login"
                method="post"
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="username">Username</Label>
                                <Input
                                    id="username"
                                    type="text"
                                    name="username"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="username"
                                    placeholder="username Anda (atau email)"
                                />
                                <InputError message={errors.username} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Kata Sandi</Label>
                                <div className="relative">
                                    <Input
                                        id="password"
                                        type={
                                            showPassword ? 'text' : 'password'
                                        }
                                        name="password"
                                        required
                                        tabIndex={2}
                                        autoComplete="current-password"
                                        placeholder="Kata Sandi"
                                        className="pr-10"
                                    />
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setShowPassword((v) => !v)
                                        }
                                        tabIndex={-1}
                                        aria-label={
                                            showPassword
                                                ? 'Sembunyikan kata sandi'
                                                : 'Tampilkan kata sandi'
                                        }
                                        className="absolute inset-y-0 right-0 flex items-center px-3 text-muted-foreground hover:text-foreground"
                                    >
                                        {showPassword ? (
                                            <EyeOff className="h-4 w-4" />
                                        ) : (
                                            <Eye className="h-4 w-4" />
                                        )}
                                    </button>
                                </div>
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                />
                                <Label htmlFor="remember">Ingat saya</Label>
                            </div>

                            <Button
                                type="submit"
                                className="mt-4 w-full"
                                tabIndex={4}
                                disabled={processing}
                                data-test="client-login-button"
                            >
                                {processing && <Spinner />}
                                Masuk
                            </Button>
                        </div>

                        <div className="text-center text-sm text-muted-foreground">
                            Hubungi tim kami jika Anda belum memiliki akun.
                        </div>
                    </>
                )}
            </Form>

            {/* Cross-portal link lives OUTSIDE the <Form> so form/autofill never
                intercepts the navigation; a full-page <a> guarantees the switch. */}
            <div className="flex flex-col gap-6">
                <div className="relative">
                    <div className="absolute inset-0 flex items-center">
                        <span className="w-full border-t" />
                    </div>
                    <div className="relative flex justify-center text-xs uppercase">
                        <span className="bg-blue-50/60 px-2 text-muted-foreground dark:bg-[#0a1326]">
                            atau
                        </span>
                    </div>
                </div>

                <a
                    href="/login"
                    className="flex w-full items-center justify-center gap-2 rounded-md border border-input bg-background px-4 py-2 text-sm font-medium shadow-sm transition-colors hover:bg-accent hover:text-accent-foreground"
                >
                    <ShieldCheck className="h-4 w-4" />
                    Login sebagai PT Sistem Kesehatan Indonesia
                </a>
            </div>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}
        </AuthLayout>
    );
}
