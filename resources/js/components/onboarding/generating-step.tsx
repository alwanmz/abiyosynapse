import { Spinner } from '@/components/ui/spinner';
import { router } from '@inertiajs/react';
import { IconSparkles } from '@tabler/icons-react';
import { useEffect, useState } from 'react';

const MESSAGES = [
    'Membaca profil usaha Anda…',
    'Menyusun kelompok Aset, Kewajiban, dan Modal…',
    'Menambahkan akun khusus industri…',
    'Memetakan akun ke pos laporan keuangan…',
    'Menyiapkan saran mapping akun inti…',
];

export function GeneratingStep() {
    const [index, setIndex] = useState(0);

    useEffect(() => {
        const poll = window.setInterval(() => router.reload({ only: ['step', 'draft'] }), 4000);
        const rotate = window.setInterval(() => setIndex((current) => (current + 1) % MESSAGES.length), 3000);

        return () => {
            window.clearInterval(poll);
            window.clearInterval(rotate);
        };
    }, []);

    return (
        <div className="flex flex-col items-center gap-4 py-16 text-center">
            <div className="flex size-16 items-center justify-center rounded-full bg-nx-cyan-50 dark:bg-nx-cyan-500/10">
                <IconSparkles className="size-8 animate-pulse text-nx-cyan-600" />
            </div>
            <div className="space-y-1">
                <p className="text-lg font-medium">AI sedang menyusun bagan akun Anda</p>
                <p className="text-sm text-muted-foreground">{MESSAGES[index]}</p>
            </div>
            <Spinner />
            <p className="max-w-sm text-xs text-muted-foreground">
                Biasanya selesai dalam 30–90 detik. Halaman ini akan berlanjut otomatis.
            </p>
        </div>
    );
}
