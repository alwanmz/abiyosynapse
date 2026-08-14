import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { AssigneeCount } from '@/types/analytics';
import { Bar, BarChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

interface TopAssigneesChartProps {
    data: AssigneeCount[];
}

export function TopAssigneesChart({ data }: TopAssigneesChartProps) {
    const chartData = data.map(item => ({
        user: item.user.length > 15 ? item.user.substring(0, 15) + '...' : item.user,
        tickets: item.count,
    }));

    return (
        <Card>
            <CardHeader>
                <CardTitle>Top 10 Assignees</CardTitle>
            </CardHeader>
            <CardContent>
                <ResponsiveContainer width="100%" height={300}>
                    <BarChart data={chartData} layout="vertical">
                        <CartesianGrid strokeDasharray="3 3" />
                        <XAxis type="number" />
                        <YAxis dataKey="user" type="category" width={100} />
                        <Tooltip />
                        <Legend />
                        <Bar dataKey="tickets" fill="#8b5cf6" />
                    </BarChart>
                </ResponsiveContainer>
            </CardContent>
        </Card>
    );
}
