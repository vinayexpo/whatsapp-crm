import { useState } from "react";
import {
  Alert,
  Box,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Paper,
  Stack,
  TextField,
  Typography,
} from "@mui/material";
import { metaAdsApi, type AdCampaign, type MetaObject } from "~/utils/meta-ads-api";
const errorText = (e: unknown) => {
  const x = e as { errors?: Record<string, string[]>; message?: string };
  return (
    Object.values(x.errors ?? {})
      .flat()
      .join(" ") ||
    x.message ||
    "The sync action failed. Refresh and try again."
  );
};
const display = (v: unknown) => (typeof v === "object" ? JSON.stringify(v) : String(v ?? "Missing"));
export function MetaSyncPanel({
  campaign,
  currency,
  onUpdate,
}: {
  campaign: AdCampaign;
  currency: string;
  onUpdate: (c: AdCampaign) => void;
}) {
  const [busy, setBusy] = useState(false),
    [error, setError] = useState(""),
    [editor, setEditor] = useState<{ kind: string; row: MetaObject } | null>(null),
    [push, setPush] = useState(false);
  const pending = Object.entries(campaign.pendingChanges ?? {}),
    conflicts = campaign.syncConflicts ?? [];
  const valueText = (field: string, value: unknown) =>
    field === "daily_budget" && value != null
      ? `${new Intl.NumberFormat("en-IN", { style: "currency", currency }).format(Number(value) / 100)}/day`
      : display(value);
  const queued = ["QUEUED", "APPLYING"].includes(campaign.syncState ?? "");
  async function run(fn: () => Promise<AdCampaign>) {
    setError("");
    setBusy(true);
    try {
      onUpdate(await fn());
      return true;
    } catch (e) {
      setError(errorText(e));
      return false;
    } finally {
      setBusy(false);
    }
  }
  const groups = campaign.metaSnapshot
    ? ([
        ["campaign", [campaign.metaSnapshot.campaign]],
        ["adset", campaign.metaSnapshot.adsets],
        ["ad", campaign.metaSnapshot.ads],
      ] as const)
    : [];
  return (
    <Stack spacing={2}>
      <Stack
        direction={{ xs: "column", sm: "row" }}
        spacing={2}
        sx={{ justifyContent: "space-between", alignItems: { sm: "center" } }}
      >
        <Box>
          <Typography component="h2" variant="h6">
            Two-way Meta sync
          </Typography>
          <Typography variant="body2" color="text.secondary">
            Pull Meta changes, stage app edits, then approve a push.
          </Typography>
        </Box>
        <Button disabled={busy || queued} onClick={() => void run(() => metaAdsApi.pull(campaign.id))}>
          Pull latest from Meta
        </Button>
      </Stack>
      {error && <Alert severity="error">{error}</Alert>}
      {campaign.syncError && <Alert severity="warning">{campaign.syncError}</Alert>}
      {queued && (
        <Alert severity="info">
          Push queued or in progress. Refresh this campaign shortly to confirm Meta’s applied values.
        </Alert>
      )}
      {!campaign.metaSnapshot ? (
        <Alert severity="info">Pull the latest campaign to load its ad sets and ads before editing.</Alert>
      ) : (
        groups.map(([kind, rows]) => (
          <Box key={kind}>
            <Typography variant="subtitle2" sx={{ mb: 1 }}>
              {kind === "campaign" ? "Campaign" : kind === "adset" ? "Ad sets" : "Ads"}
            </Typography>
            <Stack spacing={1}>
              {rows.map((row) => (
                <Paper variant="outlined" key={row.id} sx={{ p: 2 }}>
                  <Stack
                    direction={{ xs: "column", sm: "row" }}
                    spacing={1}
                    sx={{ justifyContent: "space-between", alignItems: { sm: "center" } }}
                  >
                    <Box sx={{ minWidth: 0 }}>
                      <Typography sx={{ fontWeight: 700, overflowWrap: "anywhere" }}>{row.name}</Typography>
                      <Typography variant="body2" color="text.secondary">
                        {row.status} · {row.id}
                        {Number(row.daily_budget ?? 0) > 0
                          ? ` · ${new Intl.NumberFormat("en-IN", { style: "currency", currency }).format(Number(row.daily_budget) / 100)}/day`
                          : ""}
                      </Typography>
                    </Box>
                    <Button
                      disabled={
                        busy ||
                        queued ||
                        campaign.syncState === "MISSING" ||
                        ["ARCHIVED", "DELETED"].includes(row.status ?? "")
                      }
                      onClick={() => setEditor({ kind, row })}
                    >
                      Stage {kind === "adset" ? "ad set" : kind} edits
                    </Button>
                  </Stack>
                </Paper>
              ))}
            </Stack>
          </Box>
        ))
      )}
      {pending.length > 0 && (
        <Box>
          <Typography variant="h6" sx={{ mb: 2 }}>
            Pending app edits
          </Typography>
          <Stack spacing={1}>
            {pending.flatMap(([key, fields]) =>
              Object.entries(fields).map(([field, change]) => (
                <Paper key={key + field} variant="outlined" sx={{ p: 2 }}>
                  <Typography variant="body2" sx={{ fontWeight: 700 }}>
                    {key} · {field}
                  </Typography>
                  <Typography variant="body2" sx={{ overflowWrap: "anywhere" }}>
                    App: {valueText(field, change.value)}
                  </Typography>
                  <Button
                    disabled={busy || queued}
                    onClick={() =>
                      void run(() =>
                        metaAdsApi.resolve(campaign.id, {
                          revision: campaign.syncRevision,
                          key,
                          field,
                          source: "meta",
                        }),
                      )
                    }
                  >
                    Discard app edit
                  </Button>
                </Paper>
              )),
            )}
          </Stack>
        </Box>
      )}
      {conflicts.map((c) => (
        <Alert key={c.key + c.field} severity="warning">
          <Typography sx={{ fontWeight: 700 }}>Both versions changed · {c.field}</Typography>
          <Typography variant="body2" sx={{ overflowWrap: "anywhere" }}>
            Meta: {valueText(c.field, c.meta)}
          </Typography>
          <Typography variant="body2" sx={{ overflowWrap: "anywhere" }}>
            App: {valueText(c.field, c.local)}
          </Typography>
          <Stack direction="row" spacing={1}>
            <Button
              disabled={busy || queued}
              onClick={() =>
                void run(() =>
                  metaAdsApi.resolve(campaign.id, {
                    revision: campaign.syncRevision,
                    key: c.key,
                    field: c.field,
                    source: "meta",
                  }),
                )
              }
            >
              Use Meta
            </Button>
            <Button
              disabled={busy || queued || c.missing}
              onClick={() =>
                void run(() =>
                  metaAdsApi.resolve(campaign.id, {
                    revision: campaign.syncRevision,
                    key: c.key,
                    field: c.field,
                    source: "app",
                  }),
                )
              }
            >
              Keep app edit
            </Button>
          </Stack>
        </Alert>
      ))}
      {pending.length > 0 && (
        <Button
          variant="contained"
          disableElevation
          disabled={busy || queued || campaign.syncState === "MISSING" || conflicts.length > 0}
          onClick={() => setPush(true)}
        >
          Review and push edits
        </Button>
      )}
      <Typography variant="caption" color="text.secondary">
        Names, daily ad-set budgets, end dates and supported country/age targets can be edited here. Other Meta settings
        and creative formats are retained. Sync never activates a campaign.
      </Typography>
      {editor && (
        <ObjectEditor
          kind={editor.kind}
          row={editor.row}
          campaign={campaign}
          currency={currency}
          onClose={() => setEditor(null)}
          onSave={async (changes) => {
            const updated = await metaAdsApi.stage(campaign.id, {
              revision: campaign.syncRevision,
              kind: editor.kind,
              objectId: editor.row.id,
              changes,
            });
            onUpdate(updated);
            setEditor(null);
          }}
        />
      )}
      <Dialog open={push} onClose={() => !busy && setPush(false)} fullWidth maxWidth="sm">
        <DialogTitle>Push reviewed edits to Meta?</DialogTitle>
        <DialogContent>
          <Stack spacing={2}>
            {error && <Alert severity="error">{error}</Alert>}
            <Typography>
              These edits change real Meta objects. Budget, schedule and audience changes may affect active delivery and
              spend. Delivery status stays unchanged.
            </Typography>
            {pending.flatMap(([key, fields]) =>
              Object.entries(fields).map(([field, change]) => (
                <Typography key={key + field} variant="body2" sx={{ overflowWrap: "anywhere" }}>
                  {key} · {field}: {valueText(field, change.value)}
                </Typography>
              )),
            )}
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button disabled={busy} onClick={() => setPush(false)}>
            Cancel
          </Button>
          <Button
            variant="contained"
            disableElevation
            disabled={busy}
            onClick={async () => {
              setBusy(true);
              setError("");
              try {
                await metaAdsApi.push(campaign.id, campaign.syncRevision ?? "");
                onUpdate({ ...campaign, syncState: "QUEUED" });
                setPush(false);
              } catch (e) {
                setError(errorText(e));
              } finally {
                setBusy(false);
              }
            }}
          >
            Approve push
          </Button>
        </DialogActions>
      </Dialog>
    </Stack>
  );
}
function ObjectEditor({
  kind,
  row,
  campaign,
  currency,
  onClose,
  onSave,
}: {
  kind: string;
  row: MetaObject;
  campaign: AdCampaign;
  currency: string;
  onClose: () => void;
  onSave: (v: Record<string, unknown>) => Promise<void>;
}) {
  const [name, setName] = useState(row.name),
    [budget, setBudget] = useState(String(Number(row.daily_budget ?? 0) / 100)),
    [end, setEnd] = useState(""),
    [countries, setCountries] = useState(row.targeting?.geo_locations?.countries?.join(", ") ?? ""),
    [ageMin, setAgeMin] = useState(String(row.targeting?.age_min ?? 18)),
    [ageMax, setAgeMax] = useState(String(row.targeting?.age_max ?? 65)),
    [busy, setBusy] = useState(false),
    [error, setError] = useState("");
  const geo = row.targeting?.geo_locations ?? {},
    canTarget =
      kind === "adset" &&
      !!geo.countries?.length &&
      Object.keys(geo).every((k) => ["countries", "location_types"].includes(k));
  const special = !!campaign.metaSnapshot?.campaign.special_ad_categories?.length;
  return (
    <Dialog open onClose={() => !busy && onClose()} maxWidth="sm" fullWidth>
      <Box
        component="form"
        onSubmit={async (e) => {
          e.preventDefault();
          setBusy(true);
          setError("");
          const changes: Record<string, unknown> = {};
          if (name !== row.name) changes.name = name;
          if (kind === "adset" && row.daily_budget && Number(budget) * 100 !== Number(row.daily_budget))
            changes.daily_budget = Math.round(Number(budget) * 100);
          if (end) changes.end_time = new Date(end).toISOString();
          if (
            canTarget &&
            (countries !== (geo.countries ?? []).join(", ") ||
              Number(ageMin) !== (row.targeting?.age_min ?? 18) ||
              Number(ageMax) !== (row.targeting?.age_max ?? 65))
          )
            changes.targeting = {
              geo_locations: {
                countries: countries
                  .toUpperCase()
                  .split(",")
                  .map((v) => v.trim())
                  .filter(Boolean),
              },
              age_min: Number(ageMin),
              age_max: Number(ageMax),
            };
          if (!Object.keys(changes).length) {
            setError("Change at least one field before staging.");
            setBusy(false);
            return;
          }
          try {
            await onSave(changes);
          } catch (e) {
            setError(errorText(e));
          } finally {
            setBusy(false);
          }
        }}
      >
        <DialogTitle>Stage {kind === "adset" ? "ad set" : kind} edits</DialogTitle>
        <DialogContent>
          <Stack spacing={3} sx={{ pt: 1 }}>
            {error && <Alert severity="error">{error}</Alert>}
            <TextField required label="Name" value={name} onChange={(e) => setName(e.target.value)} />
            {kind === "adset" && (
              <>
                {Number(row.daily_budget ?? 0) > 0 && Number(row.lifetime_budget ?? 0) === 0 ? (
                  <TextField
                    required
                    label={`Daily budget (${currency})`}
                    type="number"
                    value={budget}
                    onChange={(e) => setBudget(e.target.value)}
                    slotProps={{ htmlInput: { min: 1, step: 0.01 } }}
                  />
                ) : (
                  <Alert severity="info">Edit lifetime or campaign-level budgets in Meta Ads Manager.</Alert>
                )}
                <TextField
                  label="New end date (optional, local time)"
                  type="datetime-local"
                  value={end}
                  onChange={(e) => setEnd(e.target.value)}
                  slotProps={{ inputLabel: { shrink: true } }}
                  helperText={
                    row.end_time
                      ? `Current end: ${new Date(row.end_time).toLocaleString()}`
                      : "No end date returned by Meta."
                  }
                />
                {canTarget ? (
                  <>
                    <TextField
                      required
                      label="Country codes"
                      value={countries}
                      onChange={(e) => setCountries(e.target.value)}
                    />
                    <Stack direction="row" spacing={2}>
                      <TextField
                        label="Minimum age"
                        type="number"
                        disabled={special}
                        value={ageMin}
                        onChange={(e) => setAgeMin(e.target.value)}
                        slotProps={{ htmlInput: { min: 18, max: 65 } }}
                        fullWidth
                      />
                      <TextField
                        label="Maximum age (65 means 65+)"
                        type="number"
                        disabled={special}
                        value={ageMax}
                        onChange={(e) => setAgeMax(e.target.value)}
                        slotProps={{ htmlInput: { min: 18, max: 65 } }}
                        fullWidth
                      />
                    </Stack>
                  </>
                ) : (
                  <Typography color="text.secondary" variant="body2">
                    Region, city and advanced targeting remain managed in Meta.
                  </Typography>
                )}
              </>
            )}
            <Alert severity="info">
              Staging saves app edits only. Review conflicts and approve a push before Meta is changed.
            </Alert>
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button disabled={busy} onClick={onClose}>
            Cancel
          </Button>
          <Button variant="contained" disableElevation type="submit" disabled={busy}>
            Stage edits
          </Button>
        </DialogActions>
      </Box>
    </Dialog>
  );
}
