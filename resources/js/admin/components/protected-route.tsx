import { useAuth } from "@/lib/auth";
import { Navigate } from "@tanstack/react-router";
import type { ReactNode } from "react";

interface ProtectedRouteProps {
    children: ReactNode;
}

export function ProtectedRoute({ children }: ProtectedRouteProps) {
    const { accessToken } = useAuth();

    if (!accessToken) {
        return <Navigate to="/admin/login" />;
    }

    return <>{children}</>;
}
