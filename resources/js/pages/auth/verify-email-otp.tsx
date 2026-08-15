import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { InputOTP, InputOTPGroup, InputOTPSlot } from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { logout } from '@/routes';
import { send, verify } from '@/routes/verification';
import { Form, Head, usePage } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useState } from 'react';

interface VerifyEmailOtpProps {
    email: string;
}

export default function VerifyEmailOtp({ email }: VerifyEmailOtpProps) {
    const { flash } = usePage<{ flash: { success: string | null } }>().props;
    const [code, setCode] = useState('');

    return (
        <AuthLayout
            title="Verifikasi Email"
            description={`Masukkan kode 6 digit yang baru saja kami kirim ke ${email}`}
        >
            <Head title="Verifikasi Email" />

            {flash?.success && (
                <div className="mb-4 text-center text-sm font-medium text-nx-andon-run">
                    {flash.success}
                </div>
            )}

            <Form
                {...verify()}
                resetOnSuccess={false}
                className="space-y-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="flex flex-col items-center gap-3">
                            <InputOTP
                                maxLength={6}
                                pattern={REGEXP_ONLY_DIGITS}
                                value={code}
                                onChange={setCode}
                                name="code"
                                autoFocus
                            >
                                <InputOTPGroup>
                                    <InputOTPSlot index={0} />
                                    <InputOTPSlot index={1} />
                                    <InputOTPSlot index={2} />
                                    <InputOTPSlot index={3} />
                                    <InputOTPSlot index={4} />
                                    <InputOTPSlot index={5} />
                                </InputOTPGroup>
                            </InputOTP>
                            <InputError message={errors.code} />
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing || code.length !== 6}
                        >
                            {processing && <Spinner />}
                            Verifikasi
                        </Button>
                    </>
                )}
            </Form>

            <Form {...send()} className="mt-4 text-center">
                {({ processing }) => (
                    <button
                        type="submit"
                        disabled={processing}
                        className="text-sm text-muted-foreground underline decoration-neutral-300 underline-offset-4 hover:decoration-current disabled:opacity-50"
                    >
                        {processing ? 'Mengirim...' : 'Kirim ulang kode'}
                    </button>
                )}
            </Form>

            <TextLink href={logout()} className="mx-auto mt-4 block text-center text-sm">
                Keluar
            </TextLink>
        </AuthLayout>
    );
}
