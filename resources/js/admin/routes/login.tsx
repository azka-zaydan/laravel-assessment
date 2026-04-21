import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import api from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { createRoute, useNavigate } from "@tanstack/react-router";
import { type FormEvent, useState } from "react";
import { Route as rootRoute } from "./__root";

interface LoginResponse {
    access_token: string;
    token_type?: string;
    expires_in?: number;
    user?: { id: number; name: string; email: string };
    two_factor_required?: boolean;
    challenge_token?: string;
}

interface ValidationError {
    message: string;
    errors?: Record<string, string[]>;
}

function LoginPage() {
    const { setAccessToken } = useAuth();
    const navigate = useNavigate();
    const [email, setEmail] = useState("");
    const [password, setPassword] = useState("");
    const [error, setError] = useState<string | null>(null);
    const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
    const [loading, setLoading] = useState(false);

    async function handleSubmit(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        setError(null);
        setFieldErrors({});
        setLoading(true);

        try {
            const { data } = await api.post<LoginResponse>("/login", { email, password });

            if (data.two_factor_required && data.challenge_token) {
                void navigate({
                    to: "/admin/2fa/verify",
                    search: { challenge_token: data.challenge_token },
                });
                return;
            }

            setAccessToken(data.access_token);
            void navigate({ to: "/admin/logs" });
        } catch (err: unknown) {
            if (err && typeof err === "object" && "response" in err) {
                const axiosErr = err as { response?: { status?: number; data?: ValidationError } };
                const status = axiosErr.response?.status;
                const data = axiosErr.response?.data;

                if (status === 422 && data?.errors) {
                    const flat: Record<string, string> = {};
                    for (const [field, messages] of Object.entries(data.errors)) {
                        flat[field] = Array.isArray(messages) ? messages[0] : String(messages);
                    }
                    setFieldErrors(flat);
                } else {
                    setError(data?.message ?? "Invalid credentials. Please try again.");
                }
            } else {
                setError("An unexpected error occurred. Please try again.");
            }
        } finally {
            setLoading(false);
        }
    }

    return (
        <div className="flex items-center justify-center min-h-full">
            <Card className="w-full max-w-sm">
                <CardHeader className="text-center">
                    <div className="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-lg bg-primary text-primary-foreground text-lg font-bold">
                        CB
                    </div>
                    <CardTitle className="text-xl">Culinary Bot Admin</CardTitle>
                    <CardDescription>Sign in to your admin account</CardDescription>
                </CardHeader>
                <CardContent>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        {error && (
                            <Alert variant="destructive">
                                <AlertDescription>{error}</AlertDescription>
                            </Alert>
                        )}
                        <div className="space-y-1.5">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                type="email"
                                placeholder="admin@example.com"
                                autoComplete="email"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                required
                                aria-invalid={!!fieldErrors.email}
                            />
                            {fieldErrors.email && (
                                <p className="text-xs text-destructive" role="alert">
                                    {fieldErrors.email}
                                </p>
                            )}
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="password">Password</Label>
                            <Input
                                id="password"
                                type="password"
                                autoComplete="current-password"
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                required
                                aria-invalid={!!fieldErrors.password}
                            />
                            {fieldErrors.password && (
                                <p className="text-xs text-destructive" role="alert">
                                    {fieldErrors.password}
                                </p>
                            )}
                        </div>
                        <Button type="submit" className="w-full" disabled={loading}>
                            {loading ? "Signing in…" : "Sign in"}
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}

export const Route = createRoute({
    getParentRoute: () => rootRoute,
    path: "/admin/login",
    component: LoginPage,
});
