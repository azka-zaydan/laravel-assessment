import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import api from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { createRoute, useNavigate, useSearch } from "@tanstack/react-router";
import { type FormEvent, useState } from "react";
import { Route as rootRoute } from "./__root";

interface Verify2FAResponse {
    access_token: string;
    token_type?: string;
    expires_in?: number;
    user?: { id: number; name: string; email: string };
}

interface SearchParams {
    challenge_token: string;
}

function Verify2FAPage() {
    const { setAccessToken } = useAuth();
    const navigate = useNavigate();
    const { challenge_token } = useSearch({ from: "/admin/2fa/verify" }) as SearchParams;
    const [code, setCode] = useState("");
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);

    async function handleSubmit(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        setError(null);
        setLoading(true);

        try {
            const { data } = await api.post<Verify2FAResponse>("/2fa/verify", {
                challenge_token,
                code,
            });

            setAccessToken(data.access_token);
            void navigate({ to: "/admin/logs" });
        } catch (err: unknown) {
            if (err && typeof err === "object" && "response" in err) {
                const axiosErr = err as { response?: { data?: { message?: string } } };
                setError(
                    axiosErr.response?.data?.message ?? "Invalid or expired code. Please try again."
                );
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
                    <CardTitle className="text-xl">Two-Factor Authentication</CardTitle>
                    <CardDescription>
                        Enter the 6-digit code from your authenticator app.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        {error && (
                            <Alert variant="destructive">
                                <AlertDescription>{error}</AlertDescription>
                            </Alert>
                        )}
                        {!challenge_token && (
                            <Alert variant="destructive">
                                <AlertDescription>
                                    Missing challenge token. Please{" "}
                                    <a href="/admin/login" className="underline">
                                        sign in again
                                    </a>
                                    .
                                </AlertDescription>
                            </Alert>
                        )}
                        <div className="space-y-1.5">
                            <Label htmlFor="code">Authentication Code</Label>
                            <Input
                                id="code"
                                type="text"
                                inputMode="numeric"
                                pattern="[0-9]{6}"
                                maxLength={6}
                                placeholder="000000"
                                autoComplete="one-time-code"
                                value={code}
                                onChange={(e) =>
                                    setCode(e.target.value.replace(/\D/g, "").slice(0, 6))
                                }
                                required
                                disabled={!challenge_token}
                            />
                        </div>
                        <Button
                            type="submit"
                            className="w-full"
                            disabled={loading || !challenge_token || code.length !== 6}
                        >
                            {loading ? "Verifying…" : "Verify"}
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}

export const Route = createRoute({
    getParentRoute: () => rootRoute,
    path: "/admin/2fa/verify",
    component: Verify2FAPage,
});
