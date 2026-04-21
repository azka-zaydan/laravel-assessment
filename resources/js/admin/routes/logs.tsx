import { ProtectedRoute } from "@/components/protected-route";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import api from "@/lib/api";
import { useQuery } from "@tanstack/react-query";
import { createRoute } from "@tanstack/react-router";
import { useState } from "react";
import { Route as rootRoute } from "./__root";

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface ApiLogUser {
    id: number;
    name: string;
    email: string;
}

interface ApiLogEntry {
    id: string;
    request_id: string;
    user_id: number | null;
    method: string;
    path: string;
    route_name: string | null;
    ip: string | null;
    user_agent: string | null;
    response_status: number;
    response_size_bytes: number;
    duration_ms: number;
    created_at: string;
    headers: Record<string, string | string[]>;
    body: Record<string, unknown>;
    user?: ApiLogUser;
}

interface LogsMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface LogsLinks {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
}

interface LogsResponse {
    data: ApiLogEntry[];
    meta: LogsMeta;
    links: LogsLinks;
}

interface Filters {
    method: string;
    status: string;
    path: string;
    from: string;
    to: string;
    per_page: string;
    page: number;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function methodBadgeVariant(method: string) {
    switch (method.toUpperCase()) {
        case "GET":
            return "success" as const;
        case "POST":
            return "default" as const;
        case "PUT":
        case "PATCH":
            return "warning" as const;
        case "DELETE":
            return "destructive" as const;
        default:
            return "secondary" as const;
    }
}

function statusBadgeVariant(status: number) {
    if (status >= 500) return "destructive" as const;
    if (status >= 400) return "warning" as const;
    if (status >= 300) return "secondary" as const;
    return "success" as const;
}

function formatTimestamp(iso: string): string {
    try {
        return new Date(iso).toLocaleString();
    } catch {
        return iso;
    }
}

// ---------------------------------------------------------------------------
// Component
// ---------------------------------------------------------------------------

function LogsPage() {
    const [filters, setFilters] = useState<Filters>({
        method: "",
        status: "",
        path: "",
        from: "",
        to: "",
        per_page: "50",
        page: 1,
    });

    const [expandedRow, setExpandedRow] = useState<string | null>(null);

    const queryParams = {
        ...(filters.method ? { "filter[method]": filters.method } : {}),
        ...(filters.status ? { "filter[status]": filters.status } : {}),
        ...(filters.path ? { "filter[path]": filters.path } : {}),
        ...(filters.from ? { "filter[from]": filters.from } : {}),
        ...(filters.to ? { "filter[to]": filters.to } : {}),
        sort: "-created_at",
        per_page: filters.per_page,
        page: filters.page,
    };

    const { data, isLoading, isError, error } = useQuery<LogsResponse>({
        queryKey: ["api-logs", queryParams],
        queryFn: async () => {
            const res = await api.get<LogsResponse>("/admin/api-logs", { params: queryParams });
            return res.data;
        },
    });

    function setFilter<K extends keyof Filters>(key: K, value: Filters[K]) {
        setFilters((prev) => ({
            ...prev,
            [key]: value,
            page: key === "page" ? (value as number) : 1,
        }));
    }

    function handleReset() {
        setFilters({ method: "", status: "", path: "", from: "", to: "", per_page: "50", page: 1 });
    }

    const logs = data?.data ?? [];
    const meta = data?.meta;

    return (
        <ProtectedRoute>
            <div className="space-y-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">API Logs</h1>
                    {meta && (
                        <span className="text-sm text-muted-foreground">
                            {meta.total.toLocaleString()} total entries
                        </span>
                    )}
                </div>

                {/* Filter bar */}
                <div className="rounded-md border bg-card p-4">
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                        <div className="space-y-1">
                            <Label className="text-xs">Method</Label>
                            <Select
                                value={filters.method || "all"}
                                onValueChange={(v) => setFilter("method", v === "all" ? "" : v)}
                            >
                                <SelectTrigger className="h-8 text-xs">
                                    <SelectValue placeholder="All methods" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All methods</SelectItem>
                                    <SelectItem value="GET">GET</SelectItem>
                                    <SelectItem value="POST">POST</SelectItem>
                                    <SelectItem value="PUT">PUT</SelectItem>
                                    <SelectItem value="PATCH">PATCH</SelectItem>
                                    <SelectItem value="DELETE">DELETE</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-1">
                            <Label className="text-xs">Status</Label>
                            <Input
                                className="h-8 text-xs"
                                placeholder="e.g. 200, 4xx"
                                value={filters.status}
                                onChange={(e) => setFilter("status", e.target.value)}
                            />
                        </div>

                        <div className="space-y-1">
                            <Label className="text-xs">Path</Label>
                            <Input
                                className="h-8 text-xs"
                                placeholder="Search path…"
                                value={filters.path}
                                onChange={(e) => setFilter("path", e.target.value)}
                            />
                        </div>

                        <div className="space-y-1">
                            <Label className="text-xs">From</Label>
                            <Input
                                className="h-8 text-xs"
                                type="datetime-local"
                                value={filters.from}
                                onChange={(e) => setFilter("from", e.target.value)}
                            />
                        </div>

                        <div className="space-y-1">
                            <Label className="text-xs">To</Label>
                            <Input
                                className="h-8 text-xs"
                                type="datetime-local"
                                value={filters.to}
                                onChange={(e) => setFilter("to", e.target.value)}
                            />
                        </div>

                        <div className="space-y-1">
                            <Label className="text-xs">Per page</Label>
                            <Select
                                value={filters.per_page}
                                onValueChange={(v) => setFilter("per_page", v)}
                            >
                                <SelectTrigger className="h-8 text-xs">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="25">25</SelectItem>
                                    <SelectItem value="50">50</SelectItem>
                                    <SelectItem value="100">100</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div className="mt-2 flex justify-end">
                        <Button variant="outline" size="sm" onClick={handleReset} type="button">
                            Reset filters
                        </Button>
                    </div>
                </div>

                {/* Error state */}
                {isError && (
                    <Alert variant="destructive">
                        <AlertDescription>
                            {error instanceof Error ? error.message : "Failed to load API logs."}
                        </AlertDescription>
                    </Alert>
                )}

                {/* Table */}
                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Timestamp</TableHead>
                                <TableHead>Method</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Path</TableHead>
                                <TableHead className="text-right">Duration (ms)</TableHead>
                                <TableHead>User</TableHead>
                                <TableHead>Request ID</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {isLoading ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={7}
                                        className="text-center text-muted-foreground py-8"
                                    >
                                        Loading…
                                    </TableCell>
                                </TableRow>
                            ) : logs.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={7}
                                        className="text-center text-muted-foreground py-8"
                                    >
                                        No log entries found.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                logs.flatMap((entry) => [
                                    <TableRow
                                        key={entry.id}
                                        className="cursor-pointer"
                                        onClick={() =>
                                            setExpandedRow(
                                                expandedRow === entry.id ? null : entry.id
                                            )
                                        }
                                    >
                                        <TableCell className="text-xs whitespace-nowrap">
                                            {formatTimestamp(entry.created_at)}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant={methodBadgeVariant(entry.method)}>
                                                {entry.method}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={statusBadgeVariant(entry.response_status)}
                                            >
                                                {entry.response_status}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="font-mono text-xs max-w-xs truncate">
                                            {entry.path}
                                        </TableCell>
                                        <TableCell className="text-right font-mono text-xs">
                                            {entry.duration_ms}
                                        </TableCell>
                                        <TableCell className="text-xs">
                                            {entry.user?.name ?? "-"}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs text-muted-foreground">
                                            {entry.request_id}
                                        </TableCell>
                                    </TableRow>,
                                    expandedRow === entry.id ? (
                                        <TableRow key={`${entry.id}-expanded`}>
                                            <TableCell colSpan={7} className="bg-muted/30 p-4">
                                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                    <div>
                                                        <p className="text-xs font-semibold mb-1 text-muted-foreground uppercase tracking-wide">
                                                            Request Headers
                                                        </p>
                                                        <pre className="text-xs font-mono bg-muted rounded p-3 overflow-x-auto whitespace-pre-wrap">
                                                            {JSON.stringify(entry.headers, null, 2)}
                                                        </pre>
                                                    </div>
                                                    <div>
                                                        <p className="text-xs font-semibold mb-1 text-muted-foreground uppercase tracking-wide">
                                                            Request Body
                                                        </p>
                                                        <pre className="text-xs font-mono bg-muted rounded p-3 overflow-x-auto whitespace-pre-wrap">
                                                            {JSON.stringify(entry.body, null, 2)}
                                                        </pre>
                                                    </div>
                                                </div>
                                                <div className="mt-2 flex flex-wrap gap-4 text-xs text-muted-foreground">
                                                    <span>IP: {entry.ip ?? "-"}</span>
                                                    <span>
                                                        Size: {entry.response_size_bytes} bytes
                                                    </span>
                                                    <span>Route: {entry.route_name ?? "-"}</span>
                                                    <span className="truncate max-w-xs">
                                                        UA: {entry.user_agent ?? "-"}
                                                    </span>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ) : null,
                                ])
                            )}
                        </TableBody>
                    </Table>
                </div>

                {/* Pagination */}
                {meta && meta.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm">
                        <p className="text-muted-foreground">
                            Page {meta.current_page} of {meta.last_page} —{" "}
                            {meta.total.toLocaleString()} total
                        </p>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={meta.current_page <= 1}
                                onClick={() => setFilter("page", meta.current_page - 1)}
                            >
                                Previous
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={meta.current_page >= meta.last_page}
                                onClick={() => setFilter("page", meta.current_page + 1)}
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </ProtectedRoute>
    );
}

export const Route = createRoute({
    getParentRoute: () => rootRoute,
    path: "/admin/logs",
    component: LogsPage,
});
