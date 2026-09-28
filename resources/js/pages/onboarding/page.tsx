import AppLogoIcon from '@/components/app-logo-icon';
import { GeneratingStep } from '@/components/onboarding/generating-step';
import { MappingStep } from '@/components/onboarding/mapping-step';
import { MethodStep } from '@/components/onboarding/method-step';
import { ProfileStep } from '@/components/onboarding/profile-step';
import { ReviewStep } from '@/components/onboarding/review-step';
import { type OnboardingDraft, type ReportLineOption, type RoleOption } from '@/components/onboarding/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { logout } from '@/routes';
import { Form, Head } from '@inertiajs/react';
import { IconCheck, IconLock } from '@tabler/icons-react';

type Step = 'profile' | 'method' | 'generating' | 'review' | 'mapping';

interface OnboardingProps {
    step: Step;
    canManage: boolean;
    company: {
        name: string;
        industry: string | null;
        business_type: string | null;
        business_scale: string | null;
        business_description: string | null;
        uses_inventory: boolean;
        is_pkp: boolean;
    };
    industries: string[];
    scales: string[];
    aiConfigured: boolean;
    draft: OnboardingDraft | null;
    roleOptions: RoleOption[];
    reportLineOptions: ReportLineOption[];
}

const STEPS: { key: Step[]; title: string }[] = [
    { key: ['profile'], title: 'Profil usaha' },
    { key: ['method', 'generating'], title: 'Susun COA' },
    { key: ['review'], title: 'Tinjau akun' },
    { key: ['mapping'], title: 'Mapping akun inti' },
];

const HEADINGS: Record<Step, { title: string; description: string }> = {
    profile: {
        title: 'Ceritakan tentang usaha Anda',
        description: 'Profil ini dipakai untuk menyusun bagan akun (COA) yang pas untuk perusahaan Anda.',
    },
    method: {
        title: 'Bagaimana Anda ingin menyiapkan bagan akun?',
        description: 'Pilih salah satu. Semua hasil bisa Anda tinjau dan ubah sebelum disimpan.',
    },
    generating: {
        title: 'Menyusun bagan akun',
        description: 'Tunggu sebentar, AI sedang bekerja.',
    },
    review: {
        title: 'Tinjau bagan akun',
        description: 'Ubah nama, kode, atau struktur akun sesuai kebutuhan. Akun header dipakai untuk pengelompokan.',
    },
    mapping: {
        title: 'Mapping akun inti',
        description: 'Hubungkan akun Anda dengan jurnal otomatis Nexumi.',
    },
};

export default function Onboarding(props: OnboardingProps) {
    const { step, company } = props;
    const activeIndex = STEPS.findIndex((item) => item.key.includes(step));
    const heading = HEADINGS[step];

    return (
        <div className="min-h-svh bg-nx-navy-50 dark:bg-background">
            <Head title="Pengaturan Awal" />

            <header className="border-b bg-background">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-4">
                    <div className="flex items-center gap-3">
                        <AppLogoIcon className="size-8 fill-current text-nx-navy-700 dark:text-white" />
                        <div>
                            <p className="font-display text-lg leading-tight font-semibold">Pengaturan Awal</p>
                            <p className="text-xs text-muted-foreground">{company.name}</p>
                        </div>
                    </div>
                    <Form {...logout()}>
                        <Button type="submit" variant="ghost" size="sm">
                            Keluar
                        </Button>
                    </Form>
                </div>
            </header>

            <main className="mx-auto max-w-6xl space-y-6 px-6 py-8">
                <ol className="grid grid-cols-2 gap-3 md:grid-cols-4">
                    {STEPS.map((item, index) => {
                        const done = index < activeIndex;
                        const active = index === activeIndex;
                        return (
                            <li
                                key={item.title}
                                className={cn(
                                    'flex items-center gap-3 rounded-lg border bg-background px-4 py-3 text-sm',
                                    active && 'border-nx-cyan-500 ring-1 ring-nx-cyan-500',
                                )}
                            >
                                <span
                                    className={cn(
                                        'flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                                        done && 'bg-nx-andon-run text-white',
                                        active && 'bg-nx-cyan-500 text-white',
                                        !done && !active && 'bg-muted text-muted-foreground',
                                    )}
                                >
                                    {done ? <IconCheck className="size-4" /> : index + 1}
                                </span>
                                <span className={cn(active ? 'font-medium' : 'text-muted-foreground')}>{item.title}</span>
                            </li>
                        );
                    })}
                </ol>

                <Card>
                    <CardHeader>
                        <CardTitle className="font-display text-2xl">{heading.title}</CardTitle>
                        <CardDescription>{heading.description}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {!props.canManage ? (
                            <div className="flex flex-col items-center gap-3 py-12 text-center">
                                <IconLock className="size-8 text-muted-foreground" />
                                <p className="max-w-md text-sm text-muted-foreground">
                                    Pengaturan awal perusahaan ini sedang disiapkan oleh administrator. Silakan kembali setelah
                                    bagan akun selesai dibuat.
                                </p>
                            </div>
                        ) : (
                            <>
                                {step === 'profile' && <ProfileStep company={company} industries={props.industries} scales={props.scales} />}
                                {step === 'method' && <MethodStep aiConfigured={props.aiConfigured} />}
                                {step === 'generating' && <GeneratingStep />}
                                {step === 'review' && props.draft && <ReviewStep draft={props.draft} reportLineOptions={props.reportLineOptions} />}
                                {step === 'mapping' && props.draft && <MappingStep draft={props.draft} roleOptions={props.roleOptions} />}
                            </>
                        )}
                    </CardContent>
                </Card>
            </main>
        </div>
    );
}
