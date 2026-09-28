import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { router, usePage } from '@inertiajs/react';
import { IconArrowLeft, IconCircleCheck } from '@tabler/icons-react';
import { useState } from 'react';
import { type OnboardingDraft, type RoleOption } from './types';

interface MappingStepProps {
    draft: OnboardingDraft;
    roleOptions: RoleOption[];
}

export function MappingStep({ draft, roleOptions }: MappingStepProps) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const [mapping, setMapping] = useState<Record<string, string>>(draft.mapping);
    const [processing, setProcessing] = useState(false);
    const missing = roleOptions.filter((role) => !mapping[role.value]).length;

    const submit = () => {
        setProcessing(true);
        router.post('/onboarding/complete', { mapping }, { preserveScroll: true, preserveState: true, onFinish: () => setProcessing(false) });
    };

    return (
        <div className="space-y-4">
            <p className="text-sm text-muted-foreground">
                Nexumi membuat jurnal otomatis (penjualan, pembelian, persediaan, kurs). Tentukan akun mana yang dipakai untuk
                setiap peran di bawah. Saran sudah kami isi; Anda bisa mengubahnya nanti di Master Data &gt; Akun.
            </p>

            <div className="divide-y rounded-lg border">
                {roleOptions.map((role) => {
                    const candidates = draft.accounts.filter(
                        (account) => role.allowed_types.includes(account.type) && (role.is_parent_role || account.is_postable),
                    );
                    const error = errors[`mapping.${role.value}`];
                    return (
                        <div key={role.value} className="grid gap-2 p-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] md:items-center">
                            <div>
                                <p className="flex items-center gap-1.5 font-medium">
                                    {mapping[role.value] && !error && <IconCircleCheck className="size-4 text-nx-andon-run" />}
                                    {role.label}
                                </p>
                                <p className="text-xs text-muted-foreground">{role.description}</p>
                            </div>
                            <div>
                                <Select value={mapping[role.value] || undefined} onValueChange={(value) => setMapping((current) => ({ ...current, [role.value]: value }))}>
                                    <SelectTrigger className={error ? 'border-destructive' : undefined}>
                                        <SelectValue placeholder="Pilih akun" />
                                    </SelectTrigger>
                                    <SelectContent className="max-h-72">
                                        {candidates.map((account) => (
                                            <SelectItem key={account.code} value={account.code}>
                                                {account.code} — {account.name}
                                                {!account.is_postable && ' (header)'}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={error} className="mt-1" />
                            </div>
                        </div>
                    );
                })}
            </div>

            <div className="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                <Button variant="ghost" onClick={() => router.post('/onboarding/draft/review')}>
                    <IconArrowLeft />
                    Kembali ke daftar akun
                </Button>
                <div className="flex items-center gap-3">
                    {missing > 0 && <span className="text-sm text-muted-foreground">{missing} peran belum dipilih</span>}
                    <Button variant="save" onClick={submit} disabled={processing || missing > 0}>
                        {processing && <Spinner />}
                        Simpan & mulai pakai Nexumi
                    </Button>
                </div>
            </div>
        </div>
    );
}
