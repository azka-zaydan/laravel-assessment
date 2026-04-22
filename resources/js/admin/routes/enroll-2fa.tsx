import { ProtectedRoute } from "@/components/protected-route";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import api from "@/lib/api";
import { useQuery } from "@tanstack/react-query";
import { Link, createRoute } from "@tanstack/react-router";
import { QRCodeSVG } from "qrcode.react";
import { type FormEvent, useState } from "react";
import { Route as rootRoute } from "./__root";

interface EnableResponse {
    otpauth_url: string;
    secret_masked?: string;
}

interface ConfirmResponse {
    recovery_codes: string[];
}

interface MeResponse {
    user: {
        id: number;
        name: string;
        email: string;
        two_factor_enabled: boolean;
    };
}

type Step = "password" | "qr" | "done";

function extractSecret(otpauthUrl: string): string {
    try {
        const u = new URL(otpauthUrl);
        return u.searchParams.get("secret") ?? "";
    } catch {
        return "";
    }
}

function formatSecret(secret: string): string {
    return secret.replace(/(.{4})/g, "$1 ").trim();
}

function readApiError(err: unknown, fallback: string): string {
    if (err && typeof err === "object" && "response" in err) {
        const axiosErr = err as {
            response?: {
                data?: {
                    error?: string;
                    message?: string;
                    errors?: Record<string, string[]>;
                };
            };
        };
        const data = axiosErr.response?.data;
        if (data?.errors) {
            const first = Object.values(data.errors)[0];
            if (Array.isArray(first) && first[0]) return first[0];
        }
        if (data?.error) return data.error;
        if (data?.message) return data.message;
    }
    return fallback;
}

function Enroll2FAPage() {
    const {
        data: me,
        isLoading: meLoading,
        refetch: refetchMe,
    } = useQuery<MeResponse>({
        queryKey: ["me"],
        queryFn: async () => {
            const res = await api.get<MeResponse>("/me");
            return res.data;
        },
        staleTime: 0,
    });

    const alreadyEnabled = me?.user.two_factor_enabled === true;

    const [step, setStep] = useState<Step>("password");
    const [password, setPassword] = useState("");
    const [regeneratePassword, setRegeneratePassword] = useState("");
    const [otpauthUrl, setOtpauthUrl] = useState("");
    const [code, setCode] = useState("");
    const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);
    const [error, setError] = useState<string | null>(null);
    const [regenerateError, setRegenerateError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [regenerating, setRegenerating] = useState(false);
    const [copied, setCopied] = useState(false);
    const [secretCopied, setSecretCopied] = useState(false);

    const secret = extractSecret(otpauthUrl);

    async function handleEnableSubmit(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        setError(null);
        setLoading(true);

        try {
            const { data } = await api.post<EnableResponse>("/2fa/enable", { password });
            setOtpauthUrl(data.otpauth_url);
            setStep("qr");
        } catch (err: unknown) {
            setError(readApiError(err, "Failed to enable 2FA. Please check your password."));
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
            void refetchMe();
        } catch (err: unknown) {
            setError(readApiError(err, "Invalid code. Please check your authenticator app."));
        } finally {
            setLoading(false);
        }
    }

    async function handleRegenerateSubmit(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        setRegenerateError(null);
        setRegenerating(true);

        try {
            const { data } = await api.post<ConfirmResponse>("/2fa/recovery-codes/regenerate", {
                password: regeneratePassword,
            });
            setRecoveryCodes(data.recovery_codes);
            setRegeneratePassword("");
        } catch (err: unknown) {
            setRegenerateError(
                readApiError(err, "Failed to regenerate codes. Please check your password.")
            );
        } finally {
            setRegenerating(false);
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

    async function handleCopySecret() {
        try {
            await navigator.clipboard.writeText(secret);
            setSecretCopied(true);
            setTimeout(() => setSecretCopied(false), 2000);
        } catch {
            // clipboard may be unavailable — user can still select text manually
        }
    }

    return (
        <ProtectedRoute>
            <div className="space-y-6 max-w-lg">
                <h1 className="text-2xl font-semibold">
                    Two-Factor Authentication
                    {alreadyEnabled ? " — Settings" : " — Enroll"}
                </h1>

                {meLoading && (
                    <Card>
                        <CardContent className="py-8 text-center text-muted-foreground">
                            Loading…
                        </CardContent>
                    </Card>
                )}

                {/* Already enabled — show management screen (regenerate recovery codes) */}
                {!meLoading && alreadyEnabled && (
                    <Card>
                        <CardHeader>
                            <CardTitle>2FA is active</CardTitle>
                            <CardDescription>
                                Your account is protected by an authenticator app. To disable 2FA,
                                contact an administrator. You can regenerate your recovery codes
                                below.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {recoveryCodes.length > 0 && (
                                <>
                                    <Alert variant="warning">
                                        <AlertTitle>Save these — shown only once</AlertTitle>
                                        <AlertDescription>
                                            Your previous recovery codes are no longer valid. Store
                                            these in a safe place.
                                        </AlertDescription>
                                    </Alert>
                                    <pre className="rounded-md bg-muted p-4 text-xs font-mono leading-relaxed whitespace-pre-wrap select-all">
                                        {recoveryCodes.join("\n")}
                                    </pre>
                                    <Button
                                        variant="outline"
                                        className="w-full"
                                        onClick={handleCopyAll}
                                        type="button"
                                    >
                                        {copied ? "Copied!" : "Copy all"}
                                    </Button>
                                </>
                            )}

                            <form onSubmit={handleRegenerateSubmit} className="space-y-4">
                                {regenerateError && (
                                    <Alert variant="destructive">
                                        <AlertDescription>{regenerateError}</AlertDescription>
                                    </Alert>
                                )}
                                <div className="space-y-1.5">
                                    <Label htmlFor="regen-password">
                                        Confirm password to regenerate codes
                                    </Label>
                                    <Input
                                        id="regen-password"
                                        type="password"
                                        autoComplete="current-password"
                                        value={regeneratePassword}
                                        onChange={(e) => setRegeneratePassword(e.target.value)}
                                        required
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    variant="secondary"
                                    className="w-full"
                                    disabled={regenerating || regeneratePassword.length === 0}
                                >
                                    {regenerating ? "Regenerating…" : "Regenerate recovery codes"}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {/* Step 1: Password confirmation (only when not already enabled) */}
                {!meLoading && !alreadyEnabled && step === "password" && (
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
                {!meLoading && !alreadyEnabled && step === "qr" && (
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
                                <code className="block text-center text-sm font-mono bg-muted rounded px-3 py-2 tracking-widest select-all break-all">
                                    {formatSecret(secret)}
                                </code>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="w-full"
                                    onClick={handleCopySecret}
                                    type="button"
                                    disabled={!secret}
                                >
                                    {secretCopied ? "Copied!" : "Copy secret"}
                                </Button>
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

                {/* Step 3: Recovery codes after fresh enrollment */}
                {!meLoading && !alreadyEnabled && step === "done" && (
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
                                    Store your recovery codes in a safe place. Each code can only be
                                    used once to sign in if you lose access to your authenticator
                                    app.
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
