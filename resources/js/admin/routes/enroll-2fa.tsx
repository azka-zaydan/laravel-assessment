import { createRoute } from "@tanstack/react-router";
import { Route as rootRoute } from "./__root";
import { ProtectedRoute } from "@/components/protected-route";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

// Placeholder recovery codes — real data wiring comes later.
const PLACEHOLDER_CODES = [
    "ABCD-1234-EFGH",
    "IJKL-5678-MNOP",
    "QRST-9012-UVWX",
    "YZ01-3456-ABCD",
    "EFGH-7890-IJKL",
    "MNOP-1234-QRST",
    "UVWX-5678-YZ01",
    "ABCD-9012-EFGH",
];

function Enroll2FAPage() {
    return (
        <ProtectedRoute>
            <div className="space-y-6 max-w-lg">
                <h1 className="text-2xl font-semibold">Two-Factor Authentication — Enroll</h1>

                <Card>
                    <CardHeader>
                        <CardTitle>Scan QR Code</CardTitle>
                        <CardDescription>
                            Scan this QR with your authenticator app (e.g., Google Authenticator,
                            Authy).
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {/* QR placeholder — replace with <QRCodeSVG value={otpauthUri} /> once
                            data wiring is complete and qrcode.react is installed. */}
                        <div className="bg-slate-200 aspect-square max-w-xs rounded-md flex items-center justify-center text-slate-500 text-sm">
                            QR Code placeholder
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Recovery Codes</CardTitle>
                        <CardDescription>
                            Store these codes somewhere safe. Each can only be used once.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <pre className="rounded-md bg-muted p-4 text-xs font-mono leading-relaxed whitespace-pre-wrap">
                            {PLACEHOLDER_CODES.join("\n")}
                        </pre>
                    </CardContent>
                </Card>
            </div>
        </ProtectedRoute>
    );
}

export const Route = createRoute({
    getParentRoute: () => rootRoute,
    path: "/admin/2fa/enroll",
    component: Enroll2FAPage,
});
