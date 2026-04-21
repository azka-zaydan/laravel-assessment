import { createContext, useContext, useState, useEffect, createElement, type ReactNode } from "react";
import { registerAccessTokenSetter } from "./authRef";

// ---------------------------------------------------------------------------
// Module-level mutable ref — lets api.ts read the current token synchronously
// without needing React context.
// ---------------------------------------------------------------------------
let _accessToken: string | null = null;

export function getAccessToken(): string | null {
    return _accessToken;
}

// ---------------------------------------------------------------------------
// Auth context
// ---------------------------------------------------------------------------
export interface AuthContextValue {
    accessToken: string | null;
    setAccessToken: (token: string | null) => void;
    logout: () => void;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function useAuth(): AuthContextValue {
    const ctx = useContext(AuthContext);
    if (!ctx) throw new Error("useAuth must be used inside <AuthProvider>");
    return ctx;
}

export function AuthProvider({ children }: { children: ReactNode }) {
    const [accessToken, setAccessTokenState] = useState<string | null>(null);

    const setAccessToken = (token: string | null) => {
        _accessToken = token;
        setAccessTokenState(token);
    };

    const logout = () => {
        setAccessToken(null);
    };

    // Register setter so api.ts can update the token after refresh.
    useEffect(() => {
        registerAccessTokenSetter(setAccessToken);
    }, []);

    // Listen for logout event dispatched by api.ts on refresh failure.
    useEffect(() => {
        const handler = () => logout();
        window.addEventListener("auth:logout", handler);
        return () => window.removeEventListener("auth:logout", handler);
    }, []);

    // Keep the module-level ref in sync on every render (handles HMR edge cases).
    useEffect(() => {
        _accessToken = accessToken;
    }, [accessToken]);

    return createElement(
        AuthContext.Provider,
        { value: { accessToken, setAccessToken, logout } },
        children
    );
}
