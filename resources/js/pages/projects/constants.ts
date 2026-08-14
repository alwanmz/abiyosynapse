export const statusColors: Record<
    string,
    { label: string; color: string; bgColor: string }
> = {
    planning: {
        label: 'Perencanaan',
        color: 'text-purple-700',
        bgColor: 'bg-purple-100',
    },
    in_progress: {
        label: 'Sedang Berjalan',
        color: 'text-blue-700',
        bgColor: 'bg-blue-100',
    },
    on_hold: {
        label: 'Ditunda',
        color: 'text-orange-700',
        bgColor: 'bg-orange-100',
    },
    completed: {
        label: 'Selesai',
        color: 'text-green-700',
        bgColor: 'bg-green-100',
    },
    cancelled: {
        label: 'Dibatalkan',
        color: 'text-gray-700',
        bgColor: 'bg-gray-100',
    },
};

export const statusDescriptions: Record<string, string> = {
    planning: 'Fase persiapan proyek',
    in_progress: 'Pengembangan aktif',
    on_hold: 'Dijeda sementara',
    completed: 'Berhasil diselesaikan',
    cancelled: 'Proyek dibatalkan',
};
