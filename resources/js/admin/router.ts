import { createRouter } from "@tanstack/react-router";
import { Route as rootRoute } from "./routes/__root";
import { Route as loginRoute } from "./routes/login";
import { Route as logsRoute } from "./routes/logs";
import { Route as enroll2faRoute } from "./routes/enroll-2fa";

// Build the route tree manually (no codegen).
const routeTree = rootRoute.addChildren([loginRoute, logsRoute, enroll2faRoute]);

export const router = createRouter({
    routeTree,
    defaultPreload: "intent",
    // Base path matches Laravel's blade shell mount point.
    basepath: "/",
});

// TypeScript module augmentation for full type safety.
declare module "@tanstack/react-router" {
    interface Register {
        router: typeof router;
    }
}
