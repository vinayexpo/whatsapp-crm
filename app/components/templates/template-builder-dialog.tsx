import { useEffect, useState } from "react";
import Dialog from "@mui/material/Dialog";
import DialogTitle from "@mui/material/DialogTitle";
import DialogContent from "@mui/material/DialogContent";
import DialogActions from "@mui/material/DialogActions";
import Stack from "@mui/material/Stack";
import TextField from "@mui/material/TextField";
import MenuItem from "@mui/material/MenuItem";
import Button from "@mui/material/Button";
import Alert from "@mui/material/Alert";
import Typography from "@mui/material/Typography";
import Paper from "@mui/material/Paper";
import Divider from "@mui/material/Divider";
import { apiClient, ApiError } from "~/utils/api-client";
import type { WhatsappTemplate } from "~/data/types";

interface TemplateBuilderDialogProps {
  open: boolean;
  connectionId: string | null;
  template: WhatsappTemplate | null;
  onClose: () => void;
  onSaved: (template: WhatsappTemplate) => void;
}

const CATEGORIES = ["utility", "marketing", "authentication"];

export function TemplateBuilderDialog({ open, connectionId, template, onClose, onSaved }: TemplateBuilderDialogProps) {
  const [name, setName] = useState("");
  const [language, setLanguage] = useState("en_US");
  const [category, setCategory] = useState("utility");
  const [headerText, setHeaderText] = useState("");
  const [bodyText, setBodyText] = useState("");
  const [footerText, setFooterText] = useState("");
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!open) return;
    if (template) {
      setName(template.name);
      setLanguage(template.language);
      setCategory(template.category);
      setBodyText(template.body);
      setHeaderText(template.components?.find((c) => c.type === "HEADER")?.text ?? "");
      setFooterText(template.components?.find((c) => c.type === "FOOTER")?.text ?? "");
    } else {
      setName("");
      setLanguage("en_US");
      setCategory("utility");
      setHeaderText("");
      setBodyText("");
      setFooterText("");
    }
    setError(null);
  }, [open, template]);

  const variables = Array.from(new Set(Array.from(bodyText.matchAll(/\{\{\s*(\w+)\s*\}\}/g)).map((m) => m[1])));

  function buildComponents() {
    const components = [];
    if (headerText.trim()) components.push({ type: "HEADER" as const, format: "TEXT" as const, text: headerText.trim() });
    components.push({ type: "BODY" as const, text: bodyText.trim() });
    if (footerText.trim()) components.push({ type: "FOOTER" as const, text: footerText.trim() });
    return components;
  }

  async function handleSave() {
    if (!bodyText.trim() || !name.trim()) return;
    setSaving(true);
    setError(null);
    try {
      const input = {
        name: name.trim(),
        language,
        category,
        body: bodyText.trim(),
        variables,
        components: buildComponents(),
      };
      const saved = template
        ? await apiClient.updateTemplate(template.id, input)
        : connectionId
          ? await apiClient.createTemplate(connectionId, input)
          : null;
      if (saved) onSaved(saved);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to save template.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <Dialog open={open} onClose={onClose} maxWidth="md" fullWidth>
      <DialogTitle>{template ? "Edit draft template" : "New WhatsApp template"}</DialogTitle>
      <DialogContent>
        <Stack direction={{ xs: "column", md: "row" }} spacing={3} sx={{ mt: 1 }}>
          <Stack spacing={2} sx={{ flex: 1.4, minWidth: 0 }}>
            {error && <Alert severity="error">{error}</Alert>}

            <TextField
              label="Template name"
              value={name}
              onChange={(e) => setName(e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, "_"))}
              helperText="Lowercase letters, numbers, underscores only (Meta requirement)"
              fullWidth
            />

            <Stack direction="row" spacing={2}>
              <TextField
                select
                label="Language"
                value={language}
                onChange={(e) => setLanguage(e.target.value)}
                sx={{ flex: 1 }}
              >
                <MenuItem value="en_US">English (US)</MenuItem>
                <MenuItem value="en_GB">English (UK)</MenuItem>
                <MenuItem value="es_ES">Spanish</MenuItem>
                <MenuItem value="pt_BR">Portuguese (BR)</MenuItem>
                <MenuItem value="hi_IN">Hindi</MenuItem>
              </TextField>
              <TextField
                select
                label="Category"
                value={category}
                onChange={(e) => setCategory(e.target.value)}
                sx={{ flex: 1 }}
              >
                {CATEGORIES.map((c) => (
                  <MenuItem key={c} value={c}>
                    {c.charAt(0).toUpperCase() + c.slice(1)}
                  </MenuItem>
                ))}
              </TextField>
            </Stack>

            <TextField
              label="Header (optional)"
              value={headerText}
              onChange={(e) => setHeaderText(e.target.value)}
              fullWidth
            />

            <TextField
              label="Body"
              value={bodyText}
              onChange={(e) => setBodyText(e.target.value)}
              multiline
              minRows={4}
              helperText="Use {{1}}, {{2}}, etc. for variables"
              fullWidth
            />

            <TextField
              label="Footer (optional)"
              value={footerText}
              onChange={(e) => setFooterText(e.target.value)}
              fullWidth
            />
          </Stack>

          <Stack spacing={1} sx={{ flex: 1, minWidth: 0 }}>
            <Typography variant="subtitle2">Preview</Typography>
            <Paper
              variant="outlined"
              sx={{
                borderRadius: 3,
                p: 2,
                bgcolor: "rgba(37, 211, 102, 0.06)",
                minHeight: 160,
              }}
            >
              <Stack spacing={1}>
                {headerText.trim() && (
                  <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>
                    {headerText}
                  </Typography>
                )}
                <Typography variant="body2" sx={{ whiteSpace: "pre-wrap" }}>
                  {bodyText || "Message body will appear here…"}
                </Typography>
                {footerText.trim() && (
                  <Typography variant="caption" sx={{ color: "text.secondary" }}>
                    {footerText}
                  </Typography>
                )}
              </Stack>
            </Paper>
            {variables.length > 0 && (
              <Typography variant="caption" sx={{ color: "text.secondary" }}>
                Variables detected: {variables.join(", ")}
              </Typography>
            )}
            <Divider sx={{ my: 1 }} />
            <Typography variant="caption" sx={{ color: "text.secondary" }}>
              Drafts are only visible to your team until submitted for Meta review.
            </Typography>
          </Stack>
        </Stack>
      </DialogContent>
      <DialogActions sx={{ px: 3, pb: 2 }}>
        <Button onClick={onClose}>Cancel</Button>
        <Button variant="contained" onClick={handleSave} disabled={saving || !name.trim() || !bodyText.trim()}>
          {template ? "Save changes" : "Save draft"}
        </Button>
      </DialogActions>
    </Dialog>
  );
}
