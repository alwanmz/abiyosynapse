import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { getRoleColor } from '@/lib/roles';
import { cn } from '@/lib/utils';
import { UserActivity } from '@/types/analytics';

interface UserActivityTableProps {
    data: UserActivity[];
}

export function UserActivityTable({ data }: UserActivityTableProps) {
    const topUsers = data.slice(0, 10);

    return (
        <Card>
            <CardHeader>
                <CardTitle>Top User Activity</CardTitle>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>User</TableHead>
                            <TableHead>Role</TableHead>
                            <TableHead className="text-right">
                                Assigned
                            </TableHead>
                            <TableHead className="text-right">
                                In Progress
                            </TableHead>
                            <TableHead className="text-right">
                                Completed
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {topUsers.length > 0 ? (
                            topUsers.map((user, index) => (
                                <TableRow key={index}>
                                    <TableCell className="font-medium">
                                        {user.user}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant="outline"
                                            className={cn(
                                                getRoleColor(user.roleName).bg,
                                                getRoleColor(user.roleName).text,
                                                'border-0',
                                            )}
                                        >
                                            {user.role}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {user.assigned}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {user.inProgress}
                                    </TableCell>
                                    <TableCell className="text-right font-semibold text-green-600">
                                        {user.completed}
                                    </TableCell>
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell
                                    colSpan={5}
                                    className="text-center text-muted-foreground"
                                >
                                    No user activity data available
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    );
}
