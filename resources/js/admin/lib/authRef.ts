// Tiny bridge module that holds a setter ref so api.ts can update the auth
// context after a successful token refresh without creating circular imports.
// AuthProvider registers this setter on mount.

let _setAccessToken: ((token: string | null) => void) | null = null;

export function registerAccessTokenSetter(fn: (token: string | null) => void) {
    _setAccessToken = fn;
}

export function setAccessTokenRef(token: string | null) {
    _setAccessToken?.(token);
}
