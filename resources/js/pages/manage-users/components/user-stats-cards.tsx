import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { BadgeCheck, CheckCircle2, Shield, Users } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface UserStatsCardsProps {
    stats: {
        total: number;
        admins: number;
        roles: number;
        verified: number;
    };
}

/**
 * 4-up stats strip on the User Management header.
 * Grid switches from 2 cols on tablet to 4 cols on desktop so the cards
 * never go skinnier than ~220px.
 */
export function UserStatsCards({ stats }: UserStatsCardsProps) {
    const { t } = useTranslation('manage-users');

    const verifiedRatio =
        stats.total > 0
            ? Math.round((stats.verified / stats.total) * 100)
            : 0;

    return (
        <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card>
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">
                        {t('stats.total_users')}
                    </CardTitle>
                    <Users className="h-4 w-4 text-nx-navy-500" />
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold tabular-nums">{stats.total}</div>
                    <p className="text-xs text-muted-foreground">
                        {t('stats.total_users_hint')}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">
                        {t('stats.administrators')}
                    </CardTitle>
                    <Shield className="h-4 w-4 text-nx-cyan-700" />
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold tabular-nums">{stats.admins}</div>
                    <p className="text-xs text-muted-foreground">
                        {t('stats.administrators_hint')}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">
                        {t('stats.total_roles')}
                    </CardTitle>
                    <CheckCircle2 className="h-4 w-4 text-nx-navy-500" />
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold tabular-nums">{stats.roles}</div>
                    <p className="text-xs text-muted-foreground">
                        {t('stats.total_roles_hint')}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">
                        {t('stats.verified')}
                    </CardTitle>
                    <BadgeCheck className="h-4 w-4 text-nx-andon-run" />
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold tabular-nums">{stats.verified}</div>
                    <p className="text-xs text-muted-foreground">
                        {stats.total > 0
                            ? `${verifiedRatio}${t('stats.verified_hint')}`
                            : t('stats.verified_hint_empty')}
                    </p>
                </CardContent>
            </Card>
        </div>
    );
}
