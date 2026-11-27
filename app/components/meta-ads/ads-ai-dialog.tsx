import { useState } from "react";
import {
  Alert,
  Box,
  Button,
  Checkbox,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControlLabel,
  Stack,
  TextField,
  Typography,
} from "@mui/material";
import { metaAdsApi, type AdAccount, type AdSpec } from "~/utils/meta-ads-api";
const errorText = (e: unknown) => {
  const x = e as { errors?: Record<string, string[]>; message?: string };
  return (
    Object.values(x.errors ?? {})
      .flat()
      .join(" ") ||
    x.message ||
    "AI could not generate a draft. Try again."
  );
};
export function AdsAiDialog({
  account,
  onClose,
  onDraft,
}: {
  account: AdAccount;
  onClose: () => void;
  onDraft: (spec: AdSpec) => void;
}) {
  const [brief, setBrief] = useState(""),
    [destination, setDestination] = useState("website"),
    [budget, setBudget] = useState("500"),
    [countries, setCountries] = useState("IN"),
    [days, setDays] = useState("7"),
    [website, setWebsite] = useState(""),
    [image, setImage] = useState(""),
    [placements, setPlacements] = useState(["facebook"]),
    [busy, setBusy] = useState(false),
    [error, setError] = useState("");
  const [result, setResult] = useState<{ proposal: AdSpec; rationale: string; warnings: string[] } | null>(null);
  return (
    <Dialog open onClose={() => !busy && onClose()} maxWidth="sm" fullWidth>
      <Box
        component="form"
        onSubmit={async (e) => {
          e.preventDefault();
          setError("");
          setBusy(true);
          try {
            setResult(
              await metaAdsApi.aiDraft({
                accountId: account.id,
                brief,
                destination,
                dailyBudget: Math.round(Number(budget) * 100),
                countries: countries
                  .toUpperCase()
                  .split(",")
                  .map((v) => v.trim())
                  .filter(Boolean),
                durationDays: Number(days),
                placements,
                websiteUrl: website || null,
                imageUrl: image || null,
              }),
            );
          } catch (e) {
            setError(errorText(e));
          } finally {
            setBusy(false);
          }
        }}
      >
        <DialogTitle>Create campaign with AI</DialogTitle>
        <DialogContent>
          <Stack spacing={3} sx={{ pt: 1 }}>
            {error && <Alert severity="error">{error}</Alert>}
            {!result ? (
              <>
                <Typography color="text.secondary">
                  Describe your business and offer. AI prepares a draft using your company’s configured AI provider.
                </Typography>
                <TextField
                  label="Business, offer and campaign brief"
                  required
                  multiline
                  minRows={4}
                  value={brief}
                  onChange={(e) => setBrief(e.target.value)}
                  helperText="Include your audience, offer and tone. Do not include customer data or access tokens."
                />
                <TextField
                  select
                  label="Goal"
                  value={destination}
                  onChange={(e) => setDestination(e.target.value)}
                  slotProps={{ select: { native: true }, inputLabel: { shrink: true } }}
                  sx={{ "& .MuiNativeSelect-select": { pr: "16px" } }}
                >
                  <option value="website">Website visits</option>
                  <option value="whatsapp" disabled={!account.whatsappNumber}>
                    WhatsApp conversations
                  </option>
                </TextField>
                <Stack direction={{ xs: "column", sm: "row" }} spacing={2}>
                  <TextField
                    required
                    label={`Daily budget (${account.currency})`}
                    type="number"
                    value={budget}
                    onChange={(e) => setBudget(e.target.value)}
                    slotProps={{ htmlInput: { min: 1, step: 0.01 } }}
                    fullWidth
                  />
                  <TextField
                    required
                    label="Duration (days)"
                    type="number"
                    value={days}
                    onChange={(e) => setDays(e.target.value)}
                    slotProps={{ htmlInput: { min: 1, max: 90 } }}
                    fullWidth
                  />
                </Stack>
                <TextField
                  required
                  label="Country codes"
                  value={countries}
                  onChange={(e) => setCountries(e.target.value)}
                  helperText="For example IN, AE. AI cannot change these countries or your budget."
                />
                <Box>
                  <Typography>Feed placements</Typography>
                  {["facebook", "instagram"].map((p) => (
                    <FormControlLabel
                      key={p}
                      label={p === "facebook" ? "Facebook" : "Instagram"}
                      control={
                        <Checkbox
                          disabled={p === "instagram" && !account.instagramId}
                          checked={placements.includes(p)}
                          onChange={(_, on) =>
                            setPlacements(on ? [...placements, p] : placements.filter((x) => x !== p))
                          }
                        />
                      }
                    />
                  ))}
                </Box>
                {destination === "website" && (
                  <TextField
                    required
                    type="url"
                    label="Website URL"
                    value={website}
                    onChange={(e) => setWebsite(e.target.value)}
                  />
                )}
                <TextField
                  type="url"
                  label="Public image URL (optional)"
                  value={image}
                  onChange={(e) => setImage(e.target.value)}
                  helperText="Provide your own image here or add one in the draft editor."
                />
                <Alert severity="info">
                  AI never publishes or activates ads. You review the draft, special ad category and budget first.
                </Alert>
              </>
            ) : (
              <>
                <Typography variant="h6">{result.proposal.name}</Typography>
                <Typography>{result.proposal.headline}</Typography>
                <Typography sx={{ whiteSpace: "pre-wrap" }}>{result.proposal.message}</Typography>
                <Typography color="text.secondary">{result.rationale}</Typography>
                {result.warnings.map((w) => (
                  <Alert severity="info" key={w}>
                    {w}
                  </Alert>
                ))}
                <Typography variant="body2">
                  Budget:{" "}
                  {new Intl.NumberFormat("en-IN", { style: "currency", currency: account.currency ?? "INR" }).format(
                    result.proposal.dailyBudget / 100,
                  )}
                  /day · {result.proposal.countries.join(", ")} · {days} days
                </Typography>
              </>
            )}
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button disabled={busy} onClick={onClose}>
            Cancel
          </Button>
          {result ? (
            <>
              <Button onClick={() => setResult(null)}>Edit brief</Button>
              <Button variant="contained" disableElevation onClick={() => onDraft(result.proposal)}>
                Review draft
              </Button>
            </>
          ) : (
            <Button type="submit" variant="contained" disableElevation disabled={busy}>
              {busy ? "Generating draft…" : "Generate draft"}
            </Button>
          )}
        </DialogActions>
      </Box>
    </Dialog>
  );
}
