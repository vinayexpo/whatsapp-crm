import { useState } from "react";
import Box from "@mui/material/Box";
import Paper from "@mui/material/Paper";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Chip from "@mui/material/Chip";
import Button from "@mui/material/Button";
import IconButton from "@mui/material/IconButton";
import Collapse from "@mui/material/Collapse";
import Tooltip from "@mui/material/Tooltip";
import CircularProgress from "@mui/material/CircularProgress";
import Dialog from "@mui/material/Dialog";
import DialogTitle from "@mui/material/DialogTitle";
import DialogContent from "@mui/material/DialogContent";
import DialogActions from "@mui/material/DialogActions";
import TextField from "@mui/material/TextField";
import Alert from "@mui/material/Alert";
import SyncRoundedIcon from "@mui/icons-material/SyncRounded";
import ContentCopyRoundedIcon from "@mui/icons-material/ContentCopyRounded";
import ExpandMoreRoundedIcon from "@mui/icons-material/ExpandMoreRounded";
import CheckRoundedIcon from "@mui/icons-material/CheckRounded";
import { ChannelIcon } from "~/components/channel-icon/channel-icon";
import type { ApiConnection } from "~/data/types";
import { formatDate } from "~/utils/format";
import { apiClient } from "~/utils/api-client";

interface ConnectionCardProps {
  connection: ApiConnection;
  onToggle: (connectionId: string) => void;
  onConnect: (
    connectionId: string,
    credentials: {
      accessToken: string;
      wabaId?: string;
      phoneNumberId?: string;
      instagramAccountId?: string;
      twilioAccountSid?: string;
      twilioPhoneNumber?: string;
    },
  ) => Promise<void>;
  onSaveVerifyToken?: (connectionId: string, verifyToken: string) => Promise<void>;
  disabled?: boolean;
}

export function ConnectionCard({ connection, onToggle, onConnect, onSaveVerifyToken, disabled = false }: ConnectionCardProps) {
  const isConnected = connection.status === "connected";
  const [syncing, setSyncing] = useState(false);
  const [syncResult, setSyncResult] = useState<{ count: number; syncedAt: string } | null>(null);
  const [syncError, setSyncError] = useState<string | null>(null);

  const [flowSyncing, setFlowSyncing] = useState(false);
  const [flowSyncResult, setFlowSyncResult] = useState<{ count: number; syncedAt: string } | null>(null);
  const [flowSyncError, setFlowSyncError] = useState<string | null>(null);

  const [dialogOpen, setDialogOpen] = useState(false);
  const [accessToken, setAccessToken] = useState("");
  const [wabaId, setWabaId] = useState("");
  const [phoneNumberId, setPhoneNumberId] = useState("");
  const [instagramAccountId, setInstagramAccountId] = useState("");
  const [twilioAccountSid, setTwilioAccountSid] = useState("");
  const [twilioPhoneNumber, setTwilioPhoneNumber] = useState("");
  const [connecting, setConnecting] = useState(false);
  const [connectError, setConnectError] = useState<string | null>(null);

  const [webhooksOpen, setWebhooksOpen] = useState(false);
  const [copiedValue, setCopiedValue] = useState<string | null>(null);
  const [verifyTokenInput, setVerifyTokenInput] = useState(connection.webhooks[0]?.verifyToken ?? "");
  const [savingVerifyToken, setSavingVerifyToken] = useState(false);
  const [verifyTokenSaved, setVerifyTokenSaved] = useState(false);
  const [verifyTokenError, setVerifyTokenError] = useState<string | null>(null);

  async function handleCopy(value: string) {
    try {
      await navigator.clipboard.writeText(value);
      setCopiedValue(value);
      setTimeout(() => setCopiedValue((current) => (current === value ? null : current)), 1500);
    } catch {
      // Clipboard access denied; nothing to fall back to.
    }
  }

  async function handleSaveVerifyToken() {
    if (!onSaveVerifyToken) return;
    setSavingVerifyToken(true);
    setVerifyTokenError(null);
    setVerifyTokenSaved(false);
    try {
      await onSaveVerifyToken(connection.id, verifyTokenInput.trim());
      setVerifyTokenSaved(true);
      setTimeout(() => setVerifyTokenSaved(false), 1500);
    } catch {
      setVerifyTokenError("Couldn't save the verify token. Try again.");
    } finally {
      setSavingVerifyToken(false);
    }
  }

  async function handleSyncTemplates() {
    setSyncing(true);
    setSyncError(null);
    try {
      const templates = await apiClient.syncTemplates(connection.id);
      setSyncResult({ count: templates.length, syncedAt: new Date().toISOString() });
    } catch {
      setSyncError("Couldn't sync templates from Meta. Try again.");
    } finally {
      setSyncing(false);
    }
  }

  async function handleSyncFlows() {
    setFlowSyncing(true);
    setFlowSyncError(null);
    try {
      const flows = await apiClient.syncFlows(connection.id);
      setFlowSyncResult({ count: flows.length, syncedAt: new Date().toISOString() });
    } catch {
      setFlowSyncError("Couldn't sync flows from Meta. Try again.");
    } finally {
      setFlowSyncing(false);
    }
  }

  function openDialog() {
    setAccessToken("");
    setWabaId(connection.wabaId ?? "");
    setPhoneNumberId(connection.phoneNumberId ?? "");
    setInstagramAccountId(connection.instagramAccountId ?? "");
    setTwilioAccountSid(connection.twilioAccountSid ?? "");
    setTwilioPhoneNumber(connection.twilioPhoneNumber ?? "");
    setConnectError(null);
    setDialogOpen(true);
  }

  async function handleConnectSubmit() {
    setConnecting(true);
    setConnectError(null);
    try {
      await onConnect(connection.id, {
        accessToken,
        wabaId: connection.channel === "whatsapp" ? wabaId : undefined,
        phoneNumberId: connection.channel === "whatsapp" ? phoneNumberId : undefined,
        instagramAccountId: connection.channel === "instagram" ? instagramAccountId : undefined,
        twilioAccountSid: connection.channel === "voice" ? twilioAccountSid : undefined,
        twilioPhoneNumber: connection.channel === "voice" ? twilioPhoneNumber : undefined,
      });
      setDialogOpen(false);
    } catch (error) {
      const apiError = error as { errors?: Record<string, string[]>; message?: string };
      const fieldMessage = apiError.errors ? Object.values(apiError.errors)[0]?.[0] : undefined;
      setConnectError(fieldMessage ?? apiError.message ?? "Couldn't connect. Check your credentials and try again.");
    } finally {
      setConnecting(false);
    }
  }

  const canSubmit =
    accessToken.trim().length > 0 &&
    (connection.channel === "whatsapp"
      ? wabaId.trim().length > 0 && phoneNumberId.trim().length > 0
      : connection.channel === "voice"
        ? twilioAccountSid.trim().length > 0 && twilioPhoneNumber.trim().length > 0
        : instagramAccountId.trim().length > 0);

  return (
    <Paper variant="outlined" sx={{ p: 2.5, borderRadius: 3 }}>
      <Stack direction="row" sx={{ alignItems: "center", justifyContent: "space-between", gap: 2, flexWrap: "wrap" }}>
        <Stack direction="row" sx={{ alignItems: "center", gap: 1.5 }}>
          <ChannelIcon channel={connection.channel} size={36} />
          <Box>
            <Typography variant="body1" sx={{ fontWeight: 700 }}>
              {connection.label}
            </Typography>
            <Typography variant="body2" sx={{ color: "text.secondary" }}>
              {connection.accountName} &middot; {connection.identifier}
            </Typography>
            <Typography variant="caption" sx={{ color: "text.secondary" }}>
              {isConnected && connection.connectedAt
                ? `Connected on ${formatDate(connection.connectedAt)}`
                : "Not connected"}
            </Typography>
            {syncResult && (
              <Typography variant="caption" sx={{ color: "success.main", display: "block" }}>
                Synced {syncResult.count} template{syncResult.count === 1 ? "" : "s"} from Meta.
              </Typography>
            )}
            {syncError && (
              <Typography variant="caption" sx={{ color: "error.main", display: "block" }}>
                {syncError}
              </Typography>
            )}
            {flowSyncResult && (
              <Typography variant="caption" sx={{ color: "success.main", display: "block" }}>
                Synced {flowSyncResult.count} flow{flowSyncResult.count === 1 ? "" : "s"} from Meta.
              </Typography>
            )}
            {flowSyncError && (
              <Typography variant="caption" sx={{ color: "error.main", display: "block" }}>
                {flowSyncError}
              </Typography>
            )}
          </Box>
        </Stack>
        <Stack direction="row" sx={{ alignItems: "center", gap: 1.5 }}>
          <Chip
            label={isConnected ? "Connected" : "Disconnected"}
            size="small"
            color={isConnected ? "success" : "default"}
            variant={isConnected ? "filled" : "outlined"}
          />
          {connection.channel === "whatsapp" && isConnected && (
            <Button
              variant="outlined"
              size="small"
              startIcon={syncing ? <CircularProgress size={14} /> : <SyncRoundedIcon fontSize="small" />}
              onClick={handleSyncTemplates}
              disabled={disabled || syncing}
            >
              Sync Templates
            </Button>
          )}
          {connection.channel === "whatsapp" && isConnected && (
            <Button
              variant="outlined"
              size="small"
              startIcon={flowSyncing ? <CircularProgress size={14} /> : <SyncRoundedIcon fontSize="small" />}
              onClick={handleSyncFlows}
              disabled={disabled || flowSyncing}
            >
              Sync Flows
            </Button>
          )}
          <Button
            variant={isConnected ? "outlined" : "contained"}
            color={isConnected ? "error" : "primary"}
            size="small"
            onClick={() => (isConnected ? onToggle(connection.id) : openDialog())}
            disabled={disabled}
          >
            {isConnected ? "Disconnect" : "Connect"}
          </Button>
        </Stack>
      </Stack>

      {connection.webhooks.length > 0 && (
        <Box sx={{ mt: 2 }}>
          <Button
            size="small"
            onClick={() => setWebhooksOpen((open) => !open)}
            endIcon={
              <ExpandMoreRoundedIcon
                fontSize="small"
                sx={{ transform: webhooksOpen ? "rotate(180deg)" : "none", transition: "transform 0.15s" }}
              />
            }
            sx={{ textTransform: "none", color: "text.secondary" }}
          >
            Webhook setup
          </Button>
          <Collapse in={webhooksOpen}>
            <Stack spacing={1.5} sx={{ mt: 1, p: 2, borderRadius: 2, bgcolor: "action.hover" }}>
              <Typography variant="caption" sx={{ color: "text.secondary" }}>
                Paste these URLs into the Meta/Twilio developer dashboard so {connection.label} can notify this app
                of incoming events.
              </Typography>
              {connection.webhooks.map((webhook) => (
                <Box key={webhook.url}>
                  <Typography variant="caption" sx={{ fontWeight: 600, display: "block" }}>
                    {webhook.label}
                  </Typography>
                  <Stack direction="row" sx={{ alignItems: "center", gap: 0.5 }}>
                    <TextField
                      value={webhook.url}
                      size="small"
                      fullWidth
                      slotProps={{ input: { readOnly: true, sx: { fontFamily: "monospace", fontSize: 13 } } }}
                    />
                    <Tooltip title={copiedValue === webhook.url ? "Copied!" : "Copy URL"}>
                      <IconButton size="small" onClick={() => handleCopy(webhook.url)}>
                        {copiedValue === webhook.url ? (
                          <CheckRoundedIcon fontSize="small" color="success" />
                        ) : (
                          <ContentCopyRoundedIcon fontSize="small" />
                        )}
                      </IconButton>
                    </Tooltip>
                  </Stack>
                  {(connection.channel === "whatsapp" || connection.channel === "instagram") &&
                    webhook.label.toLowerCase().includes("messages") && (
                      <Stack sx={{ mt: 0.5, gap: 0.5 }}>
                        <Stack direction="row" sx={{ alignItems: "center", gap: 0.5 }}>
                          <TextField
                            label="Verify token"
                            value={verifyTokenInput}
                            onChange={(e) => setVerifyTokenInput(e.target.value)}
                            size="small"
                            fullWidth
                            placeholder="Set a verify token to enter on Meta's webhook setup"
                            disabled={savingVerifyToken}
                            slotProps={{ input: { sx: { fontFamily: "monospace", fontSize: 13 } } }}
                          />
                          {verifyTokenInput && (
                            <Tooltip title={copiedValue === verifyTokenInput ? "Copied!" : "Copy token"}>
                              <IconButton size="small" onClick={() => handleCopy(verifyTokenInput)}>
                                {copiedValue === verifyTokenInput ? (
                                  <CheckRoundedIcon fontSize="small" color="success" />
                                ) : (
                                  <ContentCopyRoundedIcon fontSize="small" />
                                )}
                              </IconButton>
                            </Tooltip>
                          )}
                          <Button
                            variant="outlined"
                            size="small"
                            onClick={handleSaveVerifyToken}
                            disabled={
                              savingVerifyToken ||
                              !onSaveVerifyToken ||
                              verifyTokenInput.trim() === (webhook.verifyToken ?? "")
                            }
                            startIcon={savingVerifyToken ? <CircularProgress size={14} /> : undefined}
                          >
                            Save
                          </Button>
                        </Stack>
                        {verifyTokenSaved && (
                          <Typography variant="caption" sx={{ color: "success.main" }}>
                            Verify token saved.
                          </Typography>
                        )}
                        {verifyTokenError && (
                          <Typography variant="caption" sx={{ color: "error.main" }}>
                            {verifyTokenError}
                          </Typography>
                        )}
                      </Stack>
                    )}
                </Box>
              ))}
            </Stack>
          </Collapse>
        </Box>
      )}

      <Dialog open={dialogOpen} onClose={() => (!connecting ? setDialogOpen(false) : undefined)} fullWidth maxWidth="sm">
        <DialogTitle>Connect {connection.label}</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ mt: 1 }}>
            <Typography variant="body2" sx={{ color: "text.secondary" }}>
              {connection.channel === "voice"
                ? "Enter your Twilio Account SID, Auth Token, and voice-capable phone number from the Twilio Console. We'll verify these against the Twilio API before saving."
                : `Paste a Meta System User access token and the ${
                    connection.channel === "whatsapp" ? "WhatsApp Business Account" : "Instagram Business Account"
                  } ID from Meta Business Manager. We'll verify these against the Graph API before saving.`}
            </Typography>
            {connectError && <Alert severity="error">{connectError}</Alert>}
            <TextField
              label={connection.channel === "voice" ? "Auth Token" : "Access Token"}
              value={accessToken}
              onChange={(e) => setAccessToken(e.target.value)}
              fullWidth
              multiline
              minRows={2}
              disabled={connecting}
              autoFocus
            />
            {connection.channel === "whatsapp" ? (
              <>
                <TextField
                  label="WhatsApp Business Account ID (WABA ID)"
                  value={wabaId}
                  onChange={(e) => setWabaId(e.target.value)}
                  fullWidth
                  disabled={connecting}
                />
                <TextField
                  label="Phone Number ID"
                  value={phoneNumberId}
                  onChange={(e) => setPhoneNumberId(e.target.value)}
                  fullWidth
                  disabled={connecting}
                />
              </>
            ) : connection.channel === "voice" ? (
              <>
                <TextField
                  label="Twilio Account SID"
                  value={twilioAccountSid}
                  onChange={(e) => setTwilioAccountSid(e.target.value)}
                  fullWidth
                  disabled={connecting}
                />
                <TextField
                  label="Twilio Phone Number"
                  value={twilioPhoneNumber}
                  onChange={(e) => setTwilioPhoneNumber(e.target.value)}
                  fullWidth
                  disabled={connecting}
                  placeholder="+15551234567"
                />
              </>
            ) : (
              <TextField
                label="Instagram Business Account ID"
                value={instagramAccountId}
                onChange={(e) => setInstagramAccountId(e.target.value)}
                fullWidth
                disabled={connecting}
              />
            )}
          </Stack>
        </DialogContent>
        <DialogActions sx={{ px: 3, pb: 2 }}>
          <Button onClick={() => setDialogOpen(false)} disabled={connecting}>
            Cancel
          </Button>
          <Button
            variant="contained"
            onClick={handleConnectSubmit}
            disabled={!canSubmit || connecting}
            startIcon={connecting ? <CircularProgress size={14} /> : undefined}
          >
            Connect
          </Button>
        </DialogActions>
      </Dialog>
    </Paper>
  );
}
