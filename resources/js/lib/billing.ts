export type BillingCycle = 'monthly' | 'yearly' | 'lifetime';

export const CYCLE_LABELS: Record<BillingCycle, string> = { monthly: 'Bulanan', yearly: 'Tahunan', lifetime: 'Selamanya' };

export const ORDER_STATUS: Record<string, { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }> = {
    paid: { label: 'Lunas', variant: 'default' },
    pending: { label: 'Menunggu pembayaran', variant: 'secondary' },
    failed: { label: 'Gagal', variant: 'destructive' },
    expired: { label: 'Kedaluwarsa', variant: 'outline' },
};

export function formatMoney(value: string | number, currency = 'IDR') {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency, maximumFractionDigits: 0 }).format(Number(value));
}

export function formatDate(value: string | null, withTime = false) {
    if (!value) return '-';
    return new Date(value).toLocaleString('id-ID', withTime ? { dateStyle: 'medium', timeStyle: 'short' } : { dateStyle: 'long' });
}
