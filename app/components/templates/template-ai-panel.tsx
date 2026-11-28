import { useState } from "react";
import { Alert, Box, Button, MenuItem, Paper, Stack, TextField, Typography } from "@mui/material";
import AutoAwesomeRounded from "@mui/icons-material/AutoAwesomeRounded";
import { apiClient, ApiError, type AiTemplateProposal } from "~/utils/api-client";

export function TemplateAiPanel({
  connectionId,
  language,
  category,
  onApply,
}: {
  connectionId: string;
  language: string;
  category: string;
  onApply: (draft: AiTemplateProposal) => void;
}) {
  const [brief, setBrief] = useState("");
  const [tone, setTone] = useState("friendly");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [result, setResult] = useState<Awaited<ReturnType<typeof apiClient.generateTemplateDraft>> | null>(null);
  return (
    <Paper variant="outlined" sx={{ p: 2 }}>
      <Stack spacing={2}>
        <Box>
          <Typography component="h3" variant="subtitle1" sx={{ fontWeight: 700 }}>
            Start with a brief
          </Typography>
          <Typography variant="body2" color="text.secondary">
            AI uses the language and category selected above. Review the suggestion before replacing your editor
            content.
          </Typography>
        </Box>
        {error && <Alert severity="error">{error}</Alert>}
        {category === "authentication" ? (
          <Alert severity="info">
            AI drafting supports utility and marketing templates. Authentication templates need Meta’s prescribed
            format.
          </Alert>
        ) : (
          <>
            <TextField
              label="What should this message say?"
              value={brief}
              onChange={(e) => setBrief(e.target.value)}
              multiline
              minRows={3}
              fullWidth
              disabled={busy}
              helperText="Describe your business, purpose and exact details. Leave out real customer data."
              slotProps={{ htmlInput: { maxLength: 5000 }, inputLabel: { shrink: true } }}
            />
            <TextField
              select
              label="Tone"
              value={tone}
              onChange={(e) => setTone(e.target.value)}
              fullWidth
              disabled={busy}
            >
              <MenuItem value="friendly">Friendly</MenuItem>
              <MenuItem value="professional">Professional</MenuItem>
              <MenuItem value="concise">Concise</MenuItem>
            </TextField>
            <Button
              variant="outlined"
              startIcon={<AutoAwesomeRounded />}
              disabled={busy || brief.trim().length < 15}
              onClick={async () => {
                setBusy(true);
                setError("");
                setResult(null);
                try {
                  setResult(
                    await apiClient.generateTemplateDraft(connectionId, {
                      brief: brief.trim(),
                      language,
                      category,
                      tone,
                    }),
                  );
                } catch (e) {
                  setError(e instanceof ApiError ? e.message : "Could not generate a template. Try again.");
                } finally {
                  setBusy(false);
                }
              }}
            >
              {busy ? "Generating suggestion…" : "Generate suggestion"}
            </Button>
          </>
        )}
        {busy && (
          <Typography role="status" variant="body2">
            Your company’s AI provider is preparing a draft.
          </Typography>
        )}
        {result && (
          <Stack spacing={1.5}>
            <Typography variant="subtitle2">Suggested template · {result.proposal.name}</Typography>
            {result.proposal.headerText && (
              <Typography sx={{ fontWeight: 700 }}>{result.proposal.headerText}</Typography>
            )}
            <Typography variant="body2" sx={{ whiteSpace: "pre-wrap", overflowWrap: "anywhere" }}>
              {result.proposal.body}
            </Typography>
            {result.proposal.footerText && <Typography variant="caption">{result.proposal.footerText}</Typography>}
            <Typography variant="body2" color="text.secondary">
              {result.rationale}
            </Typography>
            {result.warnings.map((w) => (
              <Alert severity="info" key={w}>
                {w}
              </Alert>
            ))}
            <Button variant="contained" disableElevation onClick={() => onApply(result.proposal)}>
              Use this draft
            </Button>
          </Stack>
        )}
        <Typography variant="caption" color="text.secondary">
          Uses your company’s AI Assistant settings. Generation does not save or submit a template.
        </Typography>
      </Stack>
    </Paper>
  );
}
