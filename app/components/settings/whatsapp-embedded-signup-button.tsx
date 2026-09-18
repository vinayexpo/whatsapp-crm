import { useCallback, useEffect, useRef, useState } from "react";
import Button from "@mui/material/Button";
import Alert from "@mui/material/Alert";
import Stack from "@mui/material/Stack";
import CircularProgress from "@mui/material/CircularProgress";
import WhatsAppIcon from "@mui/icons-material/WhatsApp";
import { useFacebookSdk } from "~/hooks/use-facebook-sdk";

const META_APP_ID = import.meta.env.VITE_META_APP_ID as string | undefined;
const META_COEXISTENCE_CONFIG_ID = import.meta.env.VITE_META_COEXISTENCE_CONFIG_ID as string | undefined;

interface WhatsAppSignupSessionInfo {
  wabaId?: string;
  phoneNumberId?: string;
  waBusinessAppPhoneNumber?: string;
}

interface WhatsAppEmbeddedSignupButtonProps {
  disabled?: boolean;
  onComplete: (payload: {
    code: string;
    wabaId: string;
    phoneNumberId: string;
    waBusinessAppPhoneNumber?: string;
  }) => Promise<void>;
}

export function WhatsAppEmbeddedSignupButton({ disabled, onComplete }: WhatsAppEmbeddedSignupButtonProps) {
  const { ready } = useFacebookSdk(META_APP_ID);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const sessionInfoRef = useRef<WhatsAppSignupSessionInfo>({});

  useEffect(() => {
    function handleMessage(event: MessageEvent) {
      if (event.origin !== "https://www.facebook.com" && event.origin !== "https://web.facebook.com") return;

      try {
        const data = typeof event.data === "string" ? JSON.parse(event.data) : event.data;
        if (data?.type !== "WA_EMBEDDED_SIGNUP") return;

        if (data.event === "FINISH" || data.event === "FINISH_ONBOARDING") {
          const info = data.data ?? {};
          sessionInfoRef.current = {
            wabaId: info.waba_id ?? sessionInfoRef.current.wabaId,
            phoneNumberId: info.phone_number_id ?? sessionInfoRef.current.phoneNumberId,
            waBusinessAppPhoneNumber: info.wa_business_app_phone_number ?? sessionInfoRef.current.waBusinessAppPhoneNumber,
          };
        } else if (data.event === "CANCEL" || data.event === "ERROR") {
          setError("WhatsApp signup was cancelled or failed before it could complete. Please try again.");
        }
      } catch {
        // ignore unrelated postMessage traffic
      }
    }

    window.addEventListener("message", handleMessage);
    return () => window.removeEventListener("message", handleMessage);
  }, []);

  const handleClick = useCallback(() => {
    if (!window.FB || !META_COEXISTENCE_CONFIG_ID) {
      setError("WhatsApp embedded signup isn't configured for this environment.");
      return;
    }

    setError(null);
    sessionInfoRef.current = {};
    setLoading(true);

    window.FB.login(
      (response) => {
        const code = response.authResponse?.code;
        if (!code) {
          setLoading(false);
          if (response.status !== "unknown") {
            setError("WhatsApp signup didn't complete. Please try again.");
          }
          return;
        }

        const { wabaId, phoneNumberId, waBusinessAppPhoneNumber } = sessionInfoRef.current;
        if (!wabaId || !phoneNumberId) {
          setLoading(false);
          setError("Couldn't confirm the linked WhatsApp Business account. Please try again.");
          return;
        }

        onComplete({ code, wabaId, phoneNumberId, waBusinessAppPhoneNumber })
          .catch((err: unknown) => {
            const apiError = err as { errors?: Record<string, string[]>; message?: string };
            const firstFieldError = apiError.errors ? Object.values(apiError.errors)[0]?.[0] : undefined;
            setError(firstFieldError ?? apiError.message ?? "Couldn't complete the WhatsApp signup. Please try again.");
          })
          .finally(() => setLoading(false));
      },
      {
        config_id: META_COEXISTENCE_CONFIG_ID,
        response_type: "code",
        override_default_response_type: true,
        extras: {
          feature: "whatsapp_embedded_signup",
          setup: {},
        },
      },
    );
  }, [onComplete]);

  return (
    <Stack spacing={1} sx={{ alignItems: "flex-start" }}>
      <Button
        variant="outlined"
        startIcon={loading ? <CircularProgress size={16} /> : <WhatsAppIcon />}
        onClick={handleClick}
        disabled={disabled || loading || !ready || !META_APP_ID || !META_COEXISTENCE_CONFIG_ID}
      >
        Link via WhatsApp Business App (Coexistence)
      </Button>
      {error && <Alert severity="error">{error}</Alert>}
    </Stack>
  );
}
