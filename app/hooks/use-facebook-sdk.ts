import { useEffect, useState } from "react";

declare global {
  interface Window {
    FB?: {
      init: (options: {
        appId: string;
        version: string;
        cookie?: boolean;
        xfbml?: boolean;
      }) => void;
      login: (
        callback: (response: { authResponse?: { code?: string } | null; status?: string }) => void,
        options: {
          config_id: string;
          response_type: string;
          override_default_response_type?: boolean;
          extras?: Record<string, unknown>;
        },
      ) => void;
    };
    fbAsyncInit?: () => void;
  }
}

const FACEBOOK_SDK_SRC = "https://connect.facebook.net/en_US/sdk.js";
const FACEBOOK_SDK_VERSION = "v20.0";

let sdkLoadPromise: Promise<void> | null = null;

function loadFacebookSdk(appId: string): Promise<void> {
  if (sdkLoadPromise) return sdkLoadPromise;

  sdkLoadPromise = new Promise((resolve) => {
    window.fbAsyncInit = () => {
      window.FB?.init({ appId, version: FACEBOOK_SDK_VERSION, cookie: true, xfbml: false });
      resolve();
    };

    if (document.getElementById("facebook-jssdk")) {
      window.fbAsyncInit?.();
      return;
    }

    const script = document.createElement("script");
    script.id = "facebook-jssdk";
    script.src = FACEBOOK_SDK_SRC;
    script.async = true;
    script.defer = true;
    document.body.appendChild(script);
  });

  return sdkLoadPromise;
}

export function useFacebookSdk(appId: string | undefined) {
  const [ready, setReady] = useState(false);

  useEffect(() => {
    if (!appId) return;
    let cancelled = false;
    loadFacebookSdk(appId).then(() => {
      if (!cancelled) setReady(true);
    });
    return () => {
      cancelled = true;
    };
  }, [appId]);

  return { ready };
}
