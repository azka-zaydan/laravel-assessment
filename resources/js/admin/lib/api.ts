import axios, { type AxiosRequestConfig } from "axios";
import { getAccessToken } from "./auth";

const api = axios.create({
    baseURL: "/api",
    withCredentials: true, // needed for httpOnly refresh cookie
    headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
    },
});

// ---------------------------------------------------------------------------
// Request interceptor — attach Bearer token if available
// ---------------------------------------------------------------------------
api.interceptors.request.use((config) => {
    const token = getAccessToken();
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

// ---------------------------------------------------------------------------
// Response interceptor — handle 401 with a single refresh attempt
// ---------------------------------------------------------------------------
let isRefreshing = false;
let refreshSubscribers: Array<(token: string | null) => void> = [];

function subscribeTokenRefresh(cb: (token: string | null) => void) {
    refreshSubscribers.push(cb);
}

function onRefreshResolved(token: string | null) {
    refreshSubscribers.forEach((cb) => cb(token));
    refreshSubscribers = [];
}

api.interceptors.response.use(
    (response) => response,
    async (error) => {
        const originalRequest = error.config as AxiosRequestConfig & { _retry?: boolean };

        if (error.response?.status !== 401 || originalRequest._retry) {
            return Promise.reject(error);
        }

        // Avoid multiple simultaneous refresh calls
        if (isRefreshing) {
            return new Promise((resolve, reject) => {
                subscribeTokenRefresh((token) => {
                    if (!token) return reject(error);
                    originalRequest.headers = {
                        ...originalRequest.headers,
                        Authorization: `Bearer ${token}`,
                    };
                    resolve(api(originalRequest));
                });
            });
        }

        originalRequest._retry = true;
        isRefreshing = true;

        try {
            // The refresh endpoint reads the httpOnly cookie automatically.
            const { data } = await api.post<{ access_token: string }>("/auth/refresh");
            const newToken = data.access_token;

            // Update the module-level ref; AuthProvider will pick it up on next
            // re-render via its own setState, but we import the setter lazily to
            // avoid circular deps at module parse time.
            const { setAccessTokenRef } = await import("./authRef");
            setAccessTokenRef(newToken);

            onRefreshResolved(newToken);
            originalRequest.headers = {
                ...originalRequest.headers,
                Authorization: `Bearer ${newToken}`,
            };
            return api(originalRequest);
        } catch {
            onRefreshResolved(null);
            // Trigger global logout by dispatching a custom event — AuthProvider
            // listens for it so we avoid a direct circular import.
            window.dispatchEvent(new Event("auth:logout"));
            return Promise.reject(error);
        } finally {
            isRefreshing = false;
        }
    }
);

export default api;
