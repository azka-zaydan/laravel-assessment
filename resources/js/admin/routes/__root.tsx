import { Link, Outlet, createRootRoute } from "@tanstack/react-router";
import { LogOut, QrCode, ScrollText } from "lucide-react";
import { useAuth } from "@/lib/auth";
import { Button } from "@/components/ui/button";

function RootLayout() {
    const { accessToken, logout } = useAuth();

    return (
        <div className="flex min-h-screen bg-background">
            {/* Sidebar */}
            <aside className="w-56 shrink-0 border-r bg-card flex flex-col">
                <div className="p-4 border-b">
                    <span className="font-semibold text-sm tracking-tight">Admin Panel</span>
                </div>
                <nav className="flex-1 p-3 space-y-1">
                    {accessToken ? (
                        <>
                            <Link
                                to="/admin/logs"
                                className="flex items-center gap-2 px-3 py-2 rounded-md text-sm hover:bg-accent hover:text-accent-foreground transition-colors [&.active]:bg-accent [&.active]:font-medium"
                            >
                                <ScrollText className="h-4 w-4" />
                                Logs
                            </Link>
                            <Link
                                to="/admin/2fa/enroll"
                                className="flex items-center gap-2 px-3 py-2 rounded-md text-sm hover:bg-accent hover:text-accent-foreground transition-colors [&.active]:bg-accent [&.active]:font-medium"
                            >
                                <QrCode className="h-4 w-4" />
                                2FA Enroll
                            </Link>
                        </>
                    ) : (
                        <Link
                            to="/admin/login"
                            className="flex items-center gap-2 px-3 py-2 rounded-md text-sm hover:bg-accent hover:text-accent-foreground transition-colors [&.active]:bg-accent [&.active]:font-medium"
                        >
                            Login
                        </Link>
                    )}
                </nav>
                {accessToken && (
                    <div className="p-3 border-t">
                        <Button
                            variant="ghost"
                            size="sm"
                            className="w-full justify-start gap-2"
                            onClick={logout}
                        >
                            <LogOut className="h-4 w-4" />
                            Sign out
                        </Button>
                    </div>
                )}
            </aside>

            {/* Main content */}
            <main className="flex-1 overflow-y-auto p-6">
                <Outlet />
            </main>
        </div>
    );
}

export const Route = createRootRoute({
    component: RootLayout,
});
