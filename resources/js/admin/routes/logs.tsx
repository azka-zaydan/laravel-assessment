import { createRoute } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { Route as rootRoute } from "./__root";
import { ProtectedRoute } from "@/components/protected-route";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";

interface LogEntry {
    id: string;
    timestamp: string;
    method: string;
    path: string;
    status: number;
    user: string;
    duration_ms: number;
}

interface LogsResponse {
    data: LogEntry[];
    meta: Record<string, unknown>;
}

function LogsPage() {
    const { data, isLoading } = useQuery<LogsResponse>({
        queryKey: ["api-logs"],
        queryFn: async () => ({ data: [], meta: {} }),
    });

    return (
        <ProtectedRoute>
            <div className="space-y-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">API Logs</h1>
                </div>

                {/* Filter placeholder */}
                <div className="rounded-md border bg-card p-3 text-sm text-muted-foreground">
                    Filters coming soon…
                </div>

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Timestamp</TableHead>
                                <TableHead>Method</TableHead>
                                <TableHead>Path</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>User</TableHead>
                                <TableHead className="text-right">Duration (ms)</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {isLoading ? (
                                <TableRow>
                                    <TableCell colSpan={6} className="text-center text-muted-foreground">
                                        Loading…
                                    </TableCell>
                                </TableRow>
                            ) : (data?.data ?? []).length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} className="text-center text-muted-foreground">
                                        No log entries found.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                data?.data.map((entry) => (
                                    <TableRow key={entry.id}>
                                        <TableCell>{entry.timestamp}</TableCell>
                                        <TableCell>
                                            <span className="font-mono text-xs">{entry.method}</span>
                                        </TableCell>
                                        <TableCell className="font-mono text-xs">{entry.path}</TableCell>
                                        <TableCell>{entry.status}</TableCell>
                                        <TableCell>{entry.user}</TableCell>
                                        <TableCell className="text-right">{entry.duration_ms}</TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </ProtectedRoute>
    );
}

export const Route = createRoute({
    getParentRoute: () => rootRoute,
    path: "/admin/logs",
    component: LogsPage,
});
