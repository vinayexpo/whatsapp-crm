import Echo from "laravel-echo";
import Pusher from "pusher-js";
import type { ChannelAuthorizationData } from "pusher-js/types/src/core/auth/options";

declare global {
  interface Window {
    Pusher: typeof Pusher;
  }
}

let echo: Echo<"reverb"> | null = null;

export function getEcho(): Echo<"reverb"> {
  if (echo) return echo;

  window.Pusher = Pusher;

  echo = new Echo({
    broadcaster: "reverb",
    key: "vlpcckeyubabfqhbnwfd",
    wsHost: "localhost",
    wsPort: 8080,
    wssPort: 8080,
    forceTLS: false,
    enabledTransports: ["ws", "wss"],
    authEndpoint: "http://localhost:8000/broadcasting/auth",
    authorizer: (channel: { name: string }) => ({
      authorize: (socketId: string, callback: (error: Error | null, data: ChannelAuthorizationData | null) => void) => {
        const token = typeof window !== "undefined" ? window.localStorage.getItem("auth_token") : null;
        fetch("http://localhost:8000/broadcasting/auth", {
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
