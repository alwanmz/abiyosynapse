import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { statusColors, statusDescriptions } from '../constants';

export function ProjectStatusLegend() {
    return (
        <Card className="mt-6">
            <CardHeader>
                <CardTitle className="text-base">Status Proyek</CardTitle>
            </CardHeader>
            <CardContent>
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    {Object.entries(statusColors).map(([key, status]) => (
                        <div
                            key={key}
                            className="flex items-center gap-3 rounded-lg border p-3"
                        >
                            <div
                                className={`h-8 w-8 rounded ${status.bgColor}`}
                            />
                            <div>
                                <div className="font-medium">
                                    {status.label}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {statusDescriptions[key]}
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}
