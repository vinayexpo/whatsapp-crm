import Echo from "laravel-echo";
import Pusher from "pusher-js";
import type { ChannelAuthorizationData } from "pusher-js/types/src/core/auth/options";

declare global {
  interface Window {
    Pusher: typeof Pusher;
  }
}

let echo: Echo<"reverb"> | null = null;

// VITE_REVERB_* are build-time Vite vars — they must be set on the Railway
// frontend service so the production build points at the deployed Reverb
// server instead of localhost.
const REVERB_HOST = import.meta.env.VITE_REVERB_HOST ?? "localhost";
const REVERB_PORT = Number(import.meta.env.VITE_REVERB_PORT ?? 8080);
const REVERB_SCHEME = import.meta.env.VITE_REVERB_SCHEME ?? "http";
const REVERB_APP_KEY = import.meta.env.VITE_REVERB_APP_KEY ?? "vlpcckeyubabfqhbnwfd";
const REVERB_FORCE_TLS = REVERB_SCHEME === "https";
const AUTH_ENDPOINT = `${import.meta.env.VITE_API_URL ?? "http://localhost:8000"}/broadcasting/auth`;

export function getEcho(): Echo<"reverb"> {
  if (echo) return echo;

  window.Pusher = Pusher;

  echo = new Echo({
    broadcaster: "reverb",
    key: REVERB_APP_KEY,
    wsHost: REVERB_HOST,
    wsPort: REVERB_PORT,
    wssPort: REVERB_PORT,
    wsPath: import.meta.env.VITE_REVERB_PATH ?? "",
    forceTLS: REVERB_FORCE_TLS,
    enabledTransports: ["ws", "wss"],
    authEndpoint: AUTH_ENDPOINT,
    authorizer: (channel: { name: string }) => ({
      authorize: (socketId: string, callback: (error: Error | null, data: ChannelAuthorizationData | null) => void) => {
        const token = typeof window !== "undefined" ? window.localStorage.getItem("auth_token") : null;
        fetch(AUTH_ENDPOINT, {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
            Accept: "application/json",
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
          },
          body: new URLSearchParams({ socket_id: socketId, channel_name: channel.name }),
        })
          .then((res) => (res.ok ? res.json() : Promise.reject(res)))
          .then((data: ChannelAuthorizationData) => callback(null, data))
          .catch(() => callback(new Error("Broadcast auth failed"), null));
      },
    }),
  });

  return echo;
}

export function disconnectEcho(): void {
  echo?.disconnect();
  echo = null;
}
