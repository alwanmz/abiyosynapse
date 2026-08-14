import { Card, CardContent } from '@/components/ui/card';
import { LucideIcon } from 'lucide-react';

interface StatCardProps {
    title: string;
    value: number | string;
    icon: LucideIcon;
    iconColor: string;
    iconBgColor: string;
    subtitle?: string;
}

export function StatCard({
    title,
    value,
    icon: Icon,
    iconColor,
    iconBgColor,
    subtitle,
}: StatCardProps) {
    return (
        <Card>
            <CardContent className="p-6">
                <div className="flex items-center gap-4">
                    <div className={`rounded-lg p-3 ${iconBgColor}`}>
                        <Icon className={`h-6 w-6 ${iconColor}`} />
                    </div>
                    <div className="flex-1">
                        <p className="text-sm font-medium text-muted-foreground">{title}</p>
                        <p className="text-2xl font-bold">{value}</p>
                        {subtitle && (
                            <p className="text-xs text-muted-foreground mt-1">{subtitle}</p>
                        )}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
