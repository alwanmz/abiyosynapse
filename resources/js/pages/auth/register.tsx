import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { login } from '@/routes';
import { store } from '@/routes/register';
import { Form, Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export default function Register() {
    const { t } = useTranslation('auth');

    return (
        <AuthLayout
            title={t('register.title')}
            description={t('register.description')}
        >
            <Head title={t('register.head_title')} />

            <Form
                {...store()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">{t('register.full_name')}</Label>
                                <Input
                                    id="name"
                                    type="text"
                                    name="name"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="name"
                                    placeholder={t('register.full_name_placeholder')}
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="company_name">{t('register.company_name')}</Label>
                                <Input
                                    id="company_name"
                                    type="text"
                                    name="company_name"
                                    required
                                    tabIndex={2}
                                    autoComplete="organization"
                                    placeholder={t('register.company_name_placeholder')}
                                />
                                <InputError message={errors.company_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="username">{t('register.username')}</Label>
                                <Input
                                    id="username"
                                    type="text"
                                    name="username"
                                    required
                                    tabIndex={3}
                                    autoComplete="username"
                                    placeholder="username"
                                />
                                <InputError message={errors.username} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">{t('register.email')}</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    tabIndex={4}
                                    autoComplete="email"
                                    placeholder="email@contoh.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">{t('register.password')}</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    name="password"
                                    required
                                    tabIndex={5}
                                    autoComplete="new-password"
                                    placeholder={t('register.password')}
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    {t('register.password_confirmation')}
                                </Label>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    name="password_confirmation"
                                    required
                                    tabIndex={6}
                                    autoComplete="new-password"
                                    placeholder={t('register.password_confirmation_placeholder')}
                                />
                                <InputError message={errors.password_confirmation} />
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 w-full"
                                tabIndex={7}
                                disabled={processing}
                                data-test="register-button"
                            >
                                {processing && <Spinner />}
                                {t('register.submit')}
                            </Button>
                        </div>

                        <div className="text-center text-sm text-muted-foreground">
                            {t('register.have_account')}{' '}
                            <TextLink href={login()} tabIndex={8}>
                                {t('register.login')}
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
