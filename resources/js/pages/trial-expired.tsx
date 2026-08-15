import { AndonBadge } from '@/components/ui/andon-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import company from '@/routes/company';
import { logout } from '@/routes';
import { type SharedData } from '@/types';
import { Form, Head, router, usePage } from '@inertiajs/react';
import { IconClockPause } from '@tabler/icons-react';

interface TrialExpiredProps {
    companyName: string | null;
    trialEndedAt: string | null;
}

export default function TrialExpired({ companyName, trialEndedAt }: TrialExpiredProps) {
    const { companies, currentCompany } = usePage<SharedData>().props;
    const otherCompanies = companies.filter((c) => c.id !== currentCompany?.id);

    return (
        <div className="flex min-h-svh items-center justify-center bg-nx-navy-50 p-6 dark:bg-background">
            <Head title="Trial Berakhir" />

            <Card className="w-full max-w-md">
                <CardHeader className="text-center">
                    <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-nx-hanko-50">
                        <IconClockPause className="h-7 w-7 text-nx-hanko-500" />
                    </div>
                    <CardTitle className="font-display text-2xl">
                        Masa Trial Telah Berakhir
                    </CardTitle>
                    <CardDescription>
                        {companyName
                            ? `Trial gratis untuk "${companyName}" sudah habis.`
                            : 'Trial gratis perusahaan ini sudah habis.'}
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-5">
                    <div className="flex items-center justify-center">
                        <AndonBadge variant="stop">
                            {trialEndedAt
                                ? `Berakhir ${new Date(trialEndedAt).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })}`
                                : 'Trial berakhir'}
                        </AndonBadge>
                    </div>

                    <p className="text-center text-sm text-muted-foreground">
                        Hubungi tim kami untuk melanjutkan berlangganan dan mendapatkan
                        kembali akses penuh ke perusahaan ini.
                    </p>

                    {otherCompanies.length > 0 && (
                        <div className="space-y-2 border-t border-border pt-4">
                            <p className="text-xs font-medium text-muted-foreground">
                                Atau beralih ke perusahaan lain:
                            </p>
                            <div className="flex flex-col gap-2">
                                {otherCompanies.map((c) => (
                                    <Button
                                        key={c.id}
                                        variant="secondary"
                                        className="justify-start"
                                        onClick={() =>
                                            router.post(company.switch.url(), {
                                                company_id: c.id,
                                            })
                                        }
                                    >
                                        {c.name}
                                    </Button>
                                ))}
                            </div>
                        </div>
                    )}

                    <Form {...logout()} className="pt-2">
                        <Button type="submit" variant="ghost" className="w-full">
                            Keluar
                        </Button>
                    </Form>
                </CardContent>
            </Card>
        </div>
    );
}
