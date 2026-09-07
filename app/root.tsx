import { useEffect } from "react";
import { Links, Meta, Outlet, Scripts, ScrollRestoration } from "react-router";

import type { Route } from "./+types/root";
import colorSchemeApi from "@dazl/color-scheme/client?url";
import { ErrorBoundary as ErrorBoundaryRoot } from "~/components/error-boundary/error-boundary";
import { MuiProvider } from "./mui-provider";
import { CrmStoreProvider } from "~/hooks/use-crm-store";
import { AuthProvider } from "~/hooks/use-auth";

import "./styles/reset.css";
import "./styles/global.css";
import "./styles/theme.css";
import { useColorScheme } from "@dazl/color-scheme/react";
import favicon from "/favicon.svg";

export const links: Route.LinksFunction = () => [
  {
    rel: "icon",
    href: favicon,
    type: "image/svg+xml",
  },
  {
    rel: "apple-touch-icon",
    href: "/icons/apple-touch-icon.png",
  },
  {
    rel: "manifest",
    href: "/manifest.webmanifest",
  },
  { rel: "preconnect", href: "https://fonts.googleapis.com" },
  {
    rel: "preconnect",
    href: "https://fonts.gstatic.com",
    crossOrigin: "anonymous",
  },
  {
    rel: "stylesheet",
    href: "https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap",
  },
];

export function Layout({ children }: { children: React.ReactNode }) {
  const { rootCssClass, resolvedScheme } = useColorScheme();
  return (
    <html lang="en" suppressHydrationWarning className={rootCssClass} style={{ colorScheme: resolvedScheme }}>
      <head>
        <script async src="https://www.googletagmanager.com/gtag/js?id=G-JYJVR9YZW2"></script>
        <script
          dangerouslySetInnerHTML={{
            __html: `window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', 'G-JYJVR9YZW2');`,
          }}
        />
        <meta charSet="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="theme-color" content="#00A884" />
        <meta name="mobile-web-app-capable" content="yes" />
        <meta name="apple-mobile-web-app-capable" content="yes" />
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
        <meta name="apple-mobile-web-app-title" content="Creative Connects" />
        <Meta />
        <script src={colorSchemeApi} data-light-class="light-theme" data-dark-class="dark-theme"></script>
        <Links />
      </head>
      <body>
        {children}
        <ScrollRestoration />
        <Scripts />
      </body>
    </html>
  );
}

export default function App() {
  useEffect(() => {
    if ("serviceWorker" in navigator) {
      navigator.serviceWorker.register("/push-worker.js").catch(() => {
        // Service worker registration failed; PWA install and offline shell are unavailable, but the app still works online.
      });
    }
  }, []);

  return (
    <MuiProvider>
      <AuthProvider>
        <CrmStoreProvider>
          <Outlet />
        </CrmStoreProvider>
      </AuthProvider>
    </MuiProvider>
  );
}

export const ErrorBoundary = ErrorBoundaryRoot;
