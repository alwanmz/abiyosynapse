import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    TICKET_PRIORITIES,
    type TicketPriority,
} from '@/pages/tickets/constants/ticket-priorities';
import { PriorityCount } from '@/types/analytics';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

interface TicketPriorityChartProps {
    data: PriorityCount[];
}

// Board priority palette, keyed by id; ordered highest → lowest so the bars
// read in a meaningful sequence instead of DB group order.
const PRIORITY_META: { id: TicketPriority; hex: string }[] = [
    { id: 'highest', hex: '#dc2626' },
    { id: 'high', hex: '#ea580c' },
    { id: 'medium', hex: '#ca8a04' },
    { id: 'low', hex: '#16a34a' },
    { id: 'lowest', hex: '#4b5563' },
];

export function TicketPriorityChart({ data }: TicketPriorityChartProps) {
    // The API sends "Highest", "Medium", … — normalise to the id then order.
    const counts = new Map(
        data.map((item) => [item.priority.toLowerCase(), item.count]),
    );

    const chartData = PRIORITY_META.map(({ id, hex }) => ({
        label: TICKET_PRIORITIES[id]?.label ?? id,
        count: counts.get(id) ?? 0,
        fill: hex,
    }));

    return (
        <Card>
            <CardHeader>
                <CardTitle>Tiket per Prioritas</CardTitle>
            </CardHeader>
            <CardContent>
                <ResponsiveContainer width="100%" height={300}>
                    <BarChart data={chartData}>
                        <CartesianGrid
                            strokeDasharray="3 3"
                            stroke="var(--border)"
                            vertical={false}
                        />
                        <XAxis
                            dataKey="label"
                            fontSize={12}
                            stroke="var(--muted-foreground)"
                        />
                        <YAxis
                            allowDecimals={false}
                            fontSize={12}
                            stroke="var(--muted-foreground)"
                        />
                        <Tooltip
                            cursor={{ fill: 'var(--muted)' }}
                            formatter={(value) => [
                                `${value ?? 0} tiket`,
                                'Jumlah',
                            ]}
                        />
                        <Bar dataKey="count" radius={[4, 4, 0, 0]}>
                            {chartData.map((entry) => (
                                <Cell key={entry.label} fill={entry.fill} />
                            ))}
                        </Bar>
                    </BarChart>
                </ResponsiveContainer>
            </CardContent>
        </Card>
    );
}
