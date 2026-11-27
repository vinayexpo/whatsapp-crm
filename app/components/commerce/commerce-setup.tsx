import { useEffect, useState, type FormEvent } from "react";
import {
  Alert,
  Box,
  Button,
  Chip,
  Divider,
  FormControlLabel,
  MenuItem,
  Paper,
  Stack,
  Checkbox,
  TextField,
  Typography,
} from "@mui/material";
import OpenInNewRounded from "@mui/icons-material/OpenInNewRounded";
import { apiClient } from "~/utils/api-client";
import { commerceApi, type CommerceSetup } from "~/utils/commerce-api";
import type { ApiConnection, CommerceSetting } from "~/data/types";
import { useAuth } from "~/hooks/use-auth";

export function CommerceSetupPanel({
  settings,
  onSaved,
}: {
  settings: CommerceSetting;
  onSaved: (s: CommerceSetting) => void;
}) {
  const { user } = useAuth();
  const [form, setForm] = useState<CommerceSetup>({});
  const [connections, setConnections] = useState<ApiConnection[]>([]);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState<{ severity: "error" | "success" | "info"; text: string } | null>(null);
  useEffect(() => {
    setForm({
      apiConnectionId: settings.apiConnectionId ?? "",
      metaCatalogId: settings.metaCatalogId ?? "",
      currency: "INR",
      syncEnabled: settings.syncEnabled ?? false,
      paymentConfigurationName: settings.paymentConfigurationName ?? "",
      whatsappPayEnabled: settings.whatsappPayEnabled ?? false,
      orderNumberPrefix: settings.orderNumberPrefix ?? "ORD",
      settings: {
        triggerKeywords: settings.settings.trigger_keywords ?? [],
        welcomeMessage: settings.settings.welcome_message ?? "",
      },
    });
  }, [settings]);
  useEffect(() => {
    let alive = true;
    apiClient
      .listApiConnections()
      .then((data) => {
        if (alive) setConnections(data.filter((x) => x.channel === "whatsapp"));
      })
      .catch(() => {
        if (alive) setMessage({ severity: "error", text: "Could not load WhatsApp connections. Reload to retry." });
      });
    return () => {
      alive = false;
    };
  }, []);
  const field = (key: keyof CommerceSetup, value: unknown) => setForm((f) => ({ ...f, [key]: value }));
  async function save(event: FormEvent) {
    event.preventDefault();
    setBusy(true);
    setMessage(null);
    try {
      const clean = {
        ...form,
        apiConnectionId: form.apiConnectionId || null,
        metaCatalogId: form.metaCatalogId || null,
        paymentConfigurationName: form.paymentConfigurationName || null,
      };
      for (const key of ["razorpayKeyId", "razorpayKeySecret", "razorpayWebhookSecret"] as const)
        if (!clean[key]) delete clean[key];
      const saved = await commerceApi.saveSetup(clean);
      onSaved(saved);
      setMessage({ severity: "success", text: "Commerce settings saved. Use Check connections to verify access." });
    } catch (e) {
      setMessage({ severity: "error", text: e instanceof Error ? e.message : "Could not save commerce settings." });
    } finally {
      setBusy(false);
    }
  }
  async function check() {
    setBusy(true);
    setMessage(null);
    try {
      const r = await commerceApi.check();
      setMessage({
        severity: r.paymentConfigurationActive && r.razorpayVerified ? "success" : "info",
        text: `Catalog: ${r.catalog}. Razorpay API: ${r.razorpayVerified ? "verified" : "not verified"}. WhatsApp payment configuration: ${r.paymentConfigurationActive ? "active" : "not active or not configured"}.`,
      });
    } catch (e) {
      setMessage({ severity: "error", text: e instanceof Error ? e.message : "Connection check failed." });
    } finally {
      setBusy(false);
    }
  }
  return (
    <Box component="form" onSubmit={save} sx={{ maxWidth: 1000 }}>
      {message && (
        <Alert severity={message.severity} sx={{ mb: 3 }}>
          {message.text}
        </Alert>
      )}
      <Stack spacing={3}>
        <Paper variant="outlined" sx={{ p: { xs: 2, md: 3 }, borderRadius: 2 }}>
          <Stack direction="row" spacing={2} sx={{ alignItems: "center", justifyContent: "space-between", mb: 3 }}>
            <Typography variant="h6">Meta catalog</Typography>
            <Chip
              size="small"
              label={settings.metaCatalogId ? "Catalog selected" : "Not connected"}
              variant="outlined"
            />
          </Stack>
          <Box sx={{ display: "grid", gridTemplateColumns: { xs: "1fr", md: "1fr 1fr" }, gap: 3 }}>
            <TextField
              select
              label="WhatsApp connection"
              value={form.apiConnectionId ?? ""}
              onChange={(e) => field("apiConnectionId", e.target.value)}
              slotProps={{ select: { native: true }, inputLabel: { shrink: true } }}
              sx={{ "& .MuiNativeSelect-select": { paddingRight: "16px" } }}
              fullWidth
            >
              <option value="">Choose a connection</option>
              {connections.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.label}
                </option>
              ))}
            </TextField>
            <TextField
              label="Meta catalog ID"
              value={form.metaCatalogId ?? ""}
              onChange={(e) => field("metaCatalogId", e.target.value)}
              helperText="Find this in Meta Commerce Manager."
              fullWidth
            />
          </Box>
          <FormControlLabel
            sx={{ mt: 2 }}
            control={
              <Checkbox checked={form.syncEnabled ?? false} onChange={(e) => field("syncEnabled", e.target.checked)} />
            }
            label="Sync automatically every five minutes"
          />
          <Typography variant="body2" color="text.secondary" sx={{ mt: 1 }}>
            Products, prices and availability sync both ways. Conflicting edits need your choice. Deleting a synced
            product also removes it from Meta on the next sync.
          </Typography>
          <Button
            component="a"
            href="https://business.facebook.com/commerce"
            target="_blank"
            rel="noopener noreferrer"
            endIcon={<OpenInNewRounded />}
            sx={{ mt: 2 }}
          >
            Open Commerce Manager
          </Button>
        </Paper>
        <Paper variant="outlined" sx={{ p: { xs: 2, md: 3 }, borderRadius: 2 }}>
          <Stack direction="row" sx={{ justifyContent: "space-between", alignItems: "center", mb: 2 }}>
            <Typography variant="h6">WhatsApp payments</Typography>
            <Chip label="India · Razorpay · INR" size="small" variant="outlined" />
          </Stack>
          <Typography variant="body2" color="text.secondary" sx={{ mb: 3 }}>
            Link Razorpay in WhatsApp Manager, then enter its payment configuration name. Meta must activate the
            account.
          </Typography>
          <Box sx={{ display: "grid", gridTemplateColumns: { xs: "1fr", md: "1fr 1fr" }, gap: 3 }}>
            <TextField
              label="Payment configuration name"
              value={form.paymentConfigurationName ?? ""}
              onChange={(e) => field("paymentConfigurationName", e.target.value)}
              fullWidth
            />
            <TextField
              label="Razorpay key ID"
              value={form.razorpayKeyId ?? ""}
              placeholder={settings.razorpayConfigured ? "Saved · enter to replace" : "rzp_live_…"}
              onChange={(e) => field("razorpayKeyId", e.target.value)}
              autoComplete="off"
              fullWidth
            />
            <TextField
              label="Razorpay key secret"
              type="password"
              value={form.razorpayKeySecret ?? ""}
              helperText={
                settings.razorpayConfigured
                  ? "Saved securely. Leave blank to keep it."
                  : "Stored encrypted and never returned to the browser."
              }
              onChange={(e) => field("razorpayKeySecret", e.target.value)}
              autoComplete="new-password"
              fullWidth
            />
            <TextField
              label="Razorpay webhook secret"
              type="password"
              value={form.razorpayWebhookSecret ?? ""}
              helperText={
                settings.razorpayWebhookConfigured
                  ? "Saved securely. Leave blank to keep it."
                  : "Use the same secret in your Razorpay webhook settings."
              }
              onChange={(e) => field("razorpayWebhookSecret", e.target.value)}
              autoComplete="new-password"
              fullWidth
            />
          </Box>
          <TextField
            sx={{ mt: 3 }}
            fullWidth
            label="Razorpay webhook URL"
            multiline
            minRows={2}
            value={`https://creativeconnects.web.id/api/webhooks/commerce-payment/razorpay/${user?.companyId ?? ""}`}
            slotProps={{ input: { readOnly: true } }}
            helperText="Add this URL in Razorpay and subscribe to payment.captured. Use the webhook secret above."
          />
          <FormControlLabel
            sx={{ mt: 2 }}
            control={
              <Checkbox
                checked={form.whatsappPayEnabled ?? false}
                onChange={(e) => field("whatsappPayEnabled", e.target.checked)}
              />
            }
            label="Offer Razorpay checkout inside WhatsApp"
          />
          <Button
            component="a"
            href="https://business.facebook.com/wa/manage/direct-pay/"
            target="_blank"
            rel="noopener noreferrer"
            endIcon={<OpenInNewRounded />}
            sx={{ mt: 2 }}
          >
            Open WhatsApp Manager
          </Button>
        </Paper>
        <Paper variant="outlined" sx={{ p: { xs: 2, md: 3 }, borderRadius: 2 }}>
          <Typography variant="h6" sx={{ mb: 3 }}>
            Ordering defaults
          </Typography>
          <Stack spacing={3}>
            <TextField
              label="Order prefix"
              value={form.orderNumberPrefix ?? ""}
              onChange={(e) => field("orderNumberPrefix", e.target.value)}
              slotProps={{ htmlInput: { maxLength: 16 } }}
            />
            <TextField
              label="Ordering keywords"
              helperText="Separate keywords with commas. Customers send a keyword to start ordering."
              value={Array.isArray(form.settings?.triggerKeywords) ? form.settings.triggerKeywords.join(", ") : ""}
              onChange={(e) =>
                field("settings", { ...form.settings, triggerKeywords: e.target.value.split(",").map((x) => x.trim()) })
              }
            />
            <TextField
              label="Welcome message"
              multiline
              minRows={2}
              value={String(form.settings?.welcomeMessage ?? "")}
              onChange={(e) => field("settings", { ...form.settings, welcomeMessage: e.target.value })}
            />
          </Stack>
        </Paper>
        <Divider />
        <Stack direction={{ xs: "column", sm: "row" }} spacing={2}>
          <Button type="submit" variant="contained" disableElevation disabled={busy}>
            Save commerce setup
          </Button>
          <Button variant="outlined" onClick={check} disabled={busy}>
            Check connections
          </Button>
        </Stack>
      </Stack>
    </Box>
  );
}
