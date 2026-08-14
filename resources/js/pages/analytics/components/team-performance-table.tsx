import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Progress } from '@/components/ui/progress';
import { TeamCompletionRate } from '@/types/analytics';

interface TeamPerformanceTableProps {
    data: TeamCompletionRate[];
}

export function TeamPerformanceTable({ data }: TeamPerformanceTableProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Team Completion Rates</CardTitle>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Team</TableHead>
                            <TableHead>Progress</TableHead>
                            <TableHead className="text-right">Completed</TableHead>
                            <TableHead className="text-right">Total</TableHead>
                            <TableHead className="text-right">Rate</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {data.length > 0 ? (
                            data.map((team, index) => (
                                <TableRow key={index}>
                                    <TableCell className="font-medium">{team.team}</TableCell>
                                    <TableCell>
                                        <div className="flex items-center gap-2">
                                            <Progress value={team.rate} className="w-24" />
                                            <span className="text-xs text-muted-foreground">{team.rate}%</span>
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-right">{team.completed}</TableCell>
                                    <TableCell className="text-right">{team.total}</TableCell>
                                    <TableCell className="text-right font-semibold">{team.rate}%</TableCell>
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell colSpan={5} className="text-center text-muted-foreground">
                                    No team data available
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    );
}
