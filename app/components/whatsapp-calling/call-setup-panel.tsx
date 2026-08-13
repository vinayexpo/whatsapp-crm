import { useEffect, useState } from "react";
import Stack from "@mui/material/Stack";
import Paper from "@mui/material/Paper";
import Typography from "@mui/material/Typography";
import Switch from "@mui/material/Switch";
import FormControlLabel from "@mui/material/FormControlLabel";
import Chip from "@mui/material/Chip";
import Alert from "@mui/material/Alert";
import { apiClient, ApiError } from "~/utils/api-client";
import type { ApiConnection } from "~/data/types";

export function CallSetupPanel() {
  const [connections, setConnections] = useState<ApiConnection[]>([]);
  const [loading, setLoading] = useState(true);
  const [updatingId, setUpdatingId] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    apiClient
      .listApiConnections()
      .then((list) => setConnections(list.filter((c) => c.channel === "whatsapp")))
      .catch(() => {
        // connections list stays empty on failure
      })
      .finally(() => setLoading(false));
  }, []);

  async function handleToggle(connection: ApiConnection, enabled: boolean) {
    setUpdatingId(connection.id);
    setError(null);
    try {
      const updated = await apiClient.toggleWhatsappCalling(connection.id, enabled);
      setConnections((prev) => prev.map((c) => (c.id === updated.id ? updated : c)));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to update calling setting.");
    } finally {
      setUpdatingId(null);
    }
  }

  if (loading) {
    return (
      <Typography variant="body2" sx={{ color: "text.secondary", textAlign: "center", py: 4 }}>
        Loading connections…
      </Typography>
    );
  }

  if (connections.length === 0) {
    return (
      <Paper variant="outlined" sx={{ borderRadius: 3, p: 5, textAlign: "center" }}>
        <Typography variant="body2" sx={{ color: "text.secondary" }}>
          No WhatsApp connections found. Connect a WhatsApp Business account in Settings first.
        </Typography>
      </Paper>
    );
  }

  return (
    <Stack spacing={1.5}>
      {error && <Alert severity="error">{error}</Alert>}

      {connections.map((connection) => (
        <Paper key={connection.id} variant="outlined" sx={{ borderRadius: 3, p: 2.5 }}>
          <Stack direction="row" sx={{ alignItems: "center", justifyContent: "space-between", gap: 2, flexWrap: "wrap" }}>
            <Stack spacing={0.5}>
              <Stack direction="row" sx={{ alignItems: "center", gap: 1 }}>
                <Typography variant="subtitle2">{connection.label}</Typography>
                <Chip
                  label={connection.status === "connected" ? "Connected" : "Disconnected"}
                  size="small"
                  color={connection.status === "connected" ? "success" : "default"}
                  variant={connection.status === "connected" ? "filled" : "outlined"}
                />
              </Stack>
              <Typography variant="caption" sx={{ color: "text.secondary" }}>
                {connection.identifier}
              </Typography>
            </Stack>

            <FormControlLabel
              control={
                <Switch
                  checked={connection.callingEnabled}
                  disabled={updatingId === connection.id || connection.status !== "connected"}
                  onChange={(e) => handleToggle(connection, e.target.checked)}
                />
              }
              label={connection.callingEnabled ? "Calling enabled" : "Calling disabled"}
            />
          </Stack>
        </Paper>
      ))}
    </Stack>
  );
}
