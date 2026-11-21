import { createInertiaApp } from "@inertiajs/react";
import { Toaster } from "@/components/ui/sonner";
import { TooltipProvider } from "@/components/ui/tooltip";
import { initializeAccessibility } from "@/hooks/use-accessibility";
import AppLayout from "@/layouts/app-layout";
import AuthLayout from "@/layouts/auth-layout";
import PublicLayout from "@/layouts/public-layout";
import SettingsLayout from "@/layouts/settings/layout";
import { configureEcho } from "@laravel/echo-react";

configureEcho({
    broadcaster: "reverb",
});

const appName = import.meta.env.VITE_APP_NAME || "LexLink";

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name, page) => {
        const loggedIn = Boolean(
            (page?.props as { auth?: { user?: unknown } } | undefined)?.auth
                ?.user,
        );

        switch (true) {
            // Public shell (utility bar + header + footer, no sidebar).
            case name === "welcome":
            case name === "help":
                return PublicLayout;
            case name === "legal-map":
                return loggedIn ? AppLayout : PublicLayout;
            case name.startsWith("auth/"):
                return [PublicLayout, AuthLayout];
            // client/* (ClientLayout), onboarding/* and invites/*
            // (OnboardingShell) set their own `.layout`, which wraps PublicLayout.
            case name.startsWith("client/"):
            case name.startsWith("onboarding/"):
            case name.startsWith("invites/"):
                return PublicLayout;
            case name.startsWith("settings/"):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: "#0a5bd8",
    },
});

initializeAccessibility();
