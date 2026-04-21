import { createRoute, Link } from "@tanstack/react-router";
import { type FormEvent, useState } from "react";
import { QRCodeSVG } from "qrcode.react";
import { Route as rootRoute } from "./__root";
import { ProtectedRoute } from "@/components/protected-route";
import api from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";

interface EnableResponse {
    otpauth_url: string;
    secret_masked: string;
}

interface ConfirmResponse {
    recovery_codes: string[];
}

type Step = "password" | "qr" | "done";

function Enroll2FAPage() {
    const [step, setStep] = useState<Step>("password");
    const [password, setPassword] = useState("");
    const [otpauthUrl, setOtpauthUrl] = useState("");
    const [secretMasked, setSecretMasked] = useState("");
    const [code, setCode] = useState("");
    const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [copied, setCopied] = useState(false);

    async function handleEnableSubmit(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        setError(null);
        setLoading(true);

        try {
            const { data } = await api.post<EnableResponse>("/2fa/enable", { password });
            setOtpauthUrl(data.otpauth_url);
            setSecretMasked(data.secret_masked);
            setStep("qr");
        } catch (err: unknown) {
            if (err && typeof err === "object" && "response" in err) {
                const axiosErr = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
                const data = axiosErr.response?.data;
                if (data?.errors?.password) {
                    setError(data.errors.password[0]);
                } else {
                    setError(data?.message ?? "Failed to enable 2FA. Please check your password.");
                }
            } else {
                setError("An unexpected error occurred.");
            }
        } finally {
            setLoading(false);
        }
    }

    async function handleConfirmSubmit(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        setError(null);
        setLoading(true);

        try {
            const { data } = await api.post<ConfirmResponse>("/2fa/confirm", { code });
            setRecoveryCodes(data.recovery_codes);
            setStep("done");
        } catch (err: unknown) {
            if (err && typeof err === "object" && "response" in err) {
                const axiosErr = err as { response?: { data?: { message?: string } } };
                setError(
                    axiosErr.response?.data?.message ??
                        "Invalid code. Please check your authenticator app."
                );
            } else {
                setError("An unexpected error occurred.");
            }
        } finally {
            setLoading(false);
        }
    }

    async function handleCopyAll() {
        try {
            await navigator.clipboard.writeText(recoveryCodes.join("\n"));
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            // fallback: select text in pre
        }
    }

    return (
        <ProtectedRoute>
            <div className="space-y-6 max-w-lg">
                <h1 className="text-2xl font-semibold">Two-Factor Authentication — Enroll</h1>

                {/* Step 1: Password confirmation */}
                {step === "password" && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Confirm Your Password</CardTitle>
                            <CardDescription>
                                Re-enter your password to begin 2FA setup.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleEnableSubmit} className="space-y-4">
                                {error && (
                                    <Alert variant="destructive">
                                        <AlertDescription>{error}</AlertDescription>
                                    </Alert>
                                )}
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
                                <Button type="submit" className="w-full" disabled={loading}>
                                    {loading ? "Enabling…" : "Enable 2FA"}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {/* Step 2: QR code + code confirmation */}
                {step === "qr" && (
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle>Scan QR Code</CardTitle>
                                <CardDescription>
                                    Scan this QR with your authenticator app (e.g., Google
                                    Authenticator, Authy).
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex justify-center">
                                    <QRCodeSVG
                                        value={otpauthUrl}
                                        size={200}
                                        className="rounded-md border p-2 bg-white"
                                    />
                                </div>
                                <p className="text-sm text-muted-foreground text-center">
                                    Can't scan? Enter this code manually:
                                </p>
                                <code className="block text-center text-sm font-mono bg-muted rounded px-3 py-2 tracking-widest">
                                    {secretMasked}
                                </code>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Verify Code</CardTitle>
                                <CardDescription>
                                    Enter the 6-digit code from your authenticator app to confirm
                                    setup.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={handleConfirmSubmit} className="space-y-4">
                                    {error && (
                                        <Alert variant="destructive">
                                            <AlertDescription>{error}</AlertDescription>
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
                                                setCode(
                                                    e.target.value.replace(/\D/g, "").slice(0, 6)
                                                )
                                            }
                                            required
                                        />
                                    </div>
                                    <Button
                                        type="submit"
                                        className="w-full"
                                        disabled={loading || code.length !== 6}
                                    >
                                        {loading ? "Confirming…" : "Confirm & Activate"}
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    </>
                )}

                {/* Step 3: Recovery codes */}
                {step === "done" && (
                    <Card>
                        <CardHeader>
                            <CardTitle>2FA Enabled</CardTitle>
                            <CardDescription>
                                Your two-factor authentication is now active.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <Alert variant="warning">
                                <AlertTitle>Save these — shown only once</AlertTitle>
                                <AlertDescription>
                                    Store your recovery codes in a safe place. Each code can only
                                    be used once to sign in if you lose access to your
                                    authenticator app.
                                </AlertDescription>
                            </Alert>
                            <pre className="rounded-md bg-muted p-4 text-xs font-mono leading-relaxed whitespace-pre-wrap select-all">
                                {recoveryCodes.join("\n")}
                            </pre>
                            <div className="flex gap-2">
                                <Button
                                    variant="outline"
                                    className="flex-1"
                                    onClick={handleCopyAll}
                                    type="button"
                                >
                                    {copied ? "Copied!" : "Copy all"}
                                </Button>
                                <Button asChild className="flex-1">
                                    <Link to="/admin/logs">Back to Logs</Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </ProtectedRoute>
    );
}

export const Route = createRoute({
    getParentRoute: () => rootRoute,
    path: "/admin/2fa/enroll",
    component: Enroll2FAPage,
});
