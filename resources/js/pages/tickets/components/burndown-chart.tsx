import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { useEffect, useState } from 'react';
import {
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
    CartesianGrid,
    Legend
} from 'recharts';

interface BurndownData {
    day: string;
    actual: number;
    ideal: number;
}

interface BurndownResponse {
    timeline: string;
    totalPoints: number;
    series: BurndownData[];
}

export function BurndownChart({ timelineId }: { timelineId: string | null }) {
    const [data, setData] = useState<BurndownResponse | null>(null);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!timelineId || timelineId === 'all') {
            setData(null);
            return;
        }

        setLoading(true);
        fetch(`/analytics/burndown?timeline_id=${timelineId}`)
            .then((res) => res.json())
            .then((data) => {
                setData(data);
                setLoading(false);
            })
            .catch((err) => {
                console.error('Failed to fetch burndown data:', err);
                setLoading(false);
            });
    }, [timelineId]);

    if (!timelineId || timelineId === 'all') return null;

    return (
        <Card className="mb-6">
            <CardHeader className="pb-2">
                <div className="flex items-center justify-between">
                    <CardTitle className="text-lg font-semibold">
                        Sprint Burndown: {data?.timeline || '...'}
                    </CardTitle>
                    {data && (
                        <div className="text-sm text-muted-foreground">
                            Total: <span className="font-bold text-foreground">{data.totalPoints} SP</span>
                        </div>
                    )}
                </div>
            </CardHeader>
            <CardContent>
                <div className="h-[250px] w-full">
                    {loading ? (
                        <Skeleton className="h-full w-full rounded-lg" />
                    ) : data ? (
                        <ResponsiveContainer width="100%" height="100%">
                            <LineChart data={data.series}>
                                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#f0f0f0" />
                                <XAxis 
                                    dataKey="day" 
                                    fontSize={10} 
                                    tickLine={false} 
                                    axisLine={false}
                                    tick={{ fill: '#888' }}
                                />
                                <YAxis 
                                    fontSize={10} 
                                    tickLine={false} 
                                    axisLine={false}
                                    tick={{ fill: '#888' }}
                                    label={{ value: 'Points', angle: -90, position: 'insideLeft', fontSize: 10 }}
                                />
                                <Tooltip 
                                    contentStyle={{ borderRadius: '8px', border: 'none', boxShadow: '0 4px 12px rgba(0,0,0,0.1)' }}
                                />
                                <Legend verticalAlign="top" height={36} iconType="circle" />
                                <Line
                                    type="monotone"
                                    dataKey="ideal"
                                    stroke="#cbd5e1"
                                    strokeDasharray="5 5"
                                    dot={false}
                                    name="Ideal Burndown"
                                    strokeWidth={2}
                                />
                                <Line
                                    type="stepAfter"
                                    dataKey="actual"
                                    stroke="#22c55e"
                                    strokeWidth={3}
                                    dot={{ r: 4, strokeWidth: 2, fill: '#fff' }}
                                    activeDot={{ r: 6 }}
                                    name="Actual Remaining"
                                />
                            </LineChart>
                        </ResponsiveContainer>
                    ) : (
                        <div className="flex h-full items-center justify-center text-sm text-muted-foreground">
                            No data available for this timeline.
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
