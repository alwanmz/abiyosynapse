import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    TICKET_STATUSES,
    type TicketStatusId,
} from '@/pages/tickets/constants/ticket-statuses';
import { StatusCount } from '@/types/analytics';
import {
    Cell,
    Legend,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
} from 'recharts';

interface TicketStatusChartProps {
    data: StatusCount[];
}

// Same palette as the Kanban board so status colors read consistently across
// the app. Keyed by status id; unknown (e.g. backlog) falls back to slate.
const STATUS_HEX: Record<string, string> = {
    todo: '#64748b',
    backlog: '#94a3b8',
    pending: '#eab308',
    inprogress: '#3b82f6',
    'qa-ready': '#a855f7',
    'qa-test': '#f97316',
    review: '#6366f1',
    'not-appropriate': '#f43f5e',
    done: '#22c55e',
};

/** The API sends "Qa-ready", "Inprogress", … — normalise back to the status id. */
function toId(name: string): string {
    return name.toLowerCase();
}

function labelFor(name: string): string {
    const id = toId(name);
    return (
        TICKET_STATUSES.find((s) => s.id === (id as TicketStatusId))?.label ??
        name
    );
}

interface Slice {
    name: string;
    label: string;
    value: number;
    color: string;
}

export function TicketStatusChart({ data }: TicketStatusChartProps) {
    const chartData: Slice[] = data
        .filter((item) => item.count > 0)
        .map((item) => ({
            name: item.status,
            label: labelFor(item.status),
            value: item.count,
            color: STATUS_HEX[toId(item.status)] ?? '#64748b',
        }));

    const total = chartData.reduce((sum, s) => sum + s.value, 0);

    return (
        <Card>
            <CardHeader>
                <CardTitle>Tiket per Status</CardTitle>
            </CardHeader>
            <CardContent>
                {total === 0 ? (
                    <div className="flex h-[300px] items-center justify-center text-sm text-muted-foreground">
                        Belum ada data tiket.
                    </div>
                ) : (
                    <ResponsiveContainer width="100%" height={300}>
                        <PieChart>
                            <Pie
                                data={chartData}
                                cx="50%"
                                cy="50%"
                                innerRadius={65}
                                outerRadius={100}
                                paddingAngle={2}
                                dataKey="value"
                                nameKey="label"
                                stroke="var(--background)"
                                strokeWidth={2}
                            >
                                {chartData.map((entry) => (
                                    <Cell key={entry.name} fill={entry.color} />
                                ))}
                            </Pie>
                            <Tooltip
                                formatter={(value, _name, item) => {
                                    const v = Number(value ?? 0);
                                    const pct = total
                                        ? ((v / total) * 100).toFixed(0)
                                        : 0;
                                    return [
                                        `${v} tiket (${pct}%)`,
                                        (item?.payload as Slice)?.label,
                                    ];
                                }}
                            />
                            <Legend
                                formatter={(_value, entry) =>
                                    (entry?.payload as unknown as Slice)?.label
                                }
                            />
                        </PieChart>
                    </ResponsiveContainer>
                )}
            </CardContent>
        </Card>
    );
}
