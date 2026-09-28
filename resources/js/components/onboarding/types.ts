export type AccountType = 'asset' | 'liability' | 'equity' | 'revenue' | 'expense';

export interface DraftAccount {
    code: string;
    name: string;
    type: AccountType;
    normal_balance: 'debit' | 'credit';
    is_postable: boolean;
    parent_code: string | null;
    report_line: string | null;
}

export interface RoleOption {
    value: string;
    label: string;
    description: string;
    is_parent_role: boolean;
    allowed_types: AccountType[];
}

export interface ReportLineOption {
    value: string;
    label: string;
    report: 'balance_sheet' | 'profit_loss';
}

export interface OnboardingDraft {
    accounts: DraftAccount[];
    mapping: Record<string, string>;
    source: 'ai' | 'upload' | 'standard';
    notice: string | null;
}

export const TYPE_LABELS: Record<AccountType, string> = {
    asset: 'Aset',
    liability: 'Kewajiban',
    equity: 'Modal',
    revenue: 'Pendapatan',
    expense: 'Beban',
};

export function collectErrors(errors: Record<string, string>, prefix: string): string[] {
    return Object.entries(errors)
        .filter(([key]) => key === prefix || key.startsWith(`${prefix}.`))
        .map(([, message]) => message);
}
