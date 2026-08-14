import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { TeamWorkload } from '@/types/analytics';
import { Bar, BarChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

interface TeamWorkloadChartProps {
    data: TeamWorkload[];
}

export function TeamWorkloadChart({ data }: TeamWorkloadChartProps) {
    const chartData = data.map(item => ({
        team: item.team.length > 15 ? item.team.substring(0, 15) + '...' : item.team,
        tickets: item.tickets,
        members: item.members,
    }));

    return (
        <Card>
            <CardHeader>
                <CardTitle>Team Workload</CardTitle>
            </CardHeader>
            <CardContent>
                <ResponsiveContainer width="100%" height={300}>
                    <BarChart data={chartData}>
                        <CartesianGrid strokeDasharray="3 3" />
                        <XAxis dataKey="team" />
                        <YAxis />
                        <Tooltip />
                        <Legend />
                        <Bar dataKey="tickets" fill="#3b82f6" name="Tickets" />
                        <Bar dataKey="members" fill="#10b981" name="Anggota" />
                    </BarChart>
                </ResponsiveContainer>
            </CardContent>
        </Card>
    );
}
