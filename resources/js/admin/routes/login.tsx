import { createRoute, useNavigate } from "@tanstack/react-router";
import { type FormEvent, useState } from "react";
import { Route as rootRoute } from "./__root";
import { useAuth } from "@/lib/auth";
import api from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

interface LoginResponse {
    access_token: string;
    two_factor_required?: boolean;
    challenge_token?: string;
}

function LoginPage() {
    const { setAccessToken } = useAuth();
    const navigate = useNavigate();
    const [email, setEmail] = useState("");
    const [password, setPassword] = useState("");
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);

    async function handleSubmit(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        setError(null);
        setLoading(true);

        try {
            const { data } = await api.post<LoginResponse>("/login", { email, password });

            if (data.two_factor_required && data.challenge_token) {
                // Redirect to 2FA verify page (not built yet — query param carries challenge)
                void navigate({
                    to: "/admin/2fa/verify" as never,
                    search: { challenge_token: data.challenge_token } as never,
                });
                return;
            }

            setAccessToken(data.access_token);
            void navigate({ to: "/admin/logs" });
        } catch (err: unknown) {
            const message =
                err instanceof Error ? err.message : "Invalid credentials. Please try again.";
            setError(message);
        } finally {
            setLoading(false);
        }
    }

    return (
        <div className="flex items-center justify-center min-h-full">
            <Card className="w-full max-w-sm">
                <CardHeader>
                    <CardTitle className="text-xl">Sign in</CardTitle>
                    <CardDescription>Enter your credentials to access the admin panel.</CardDescription>
                </CardHeader>
                <CardContent>
                    <form onSubmit={handleSubmit} className="space-y-4">
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
                            />
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
                            />
                        </div>
                        {error && (
                            <p className="text-sm text-destructive" role="alert">
                                {error}
                            </p>
                        )}
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
