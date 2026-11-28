import { useCallback, useEffect, useState, type ReactNode } from "react";
import {
  Alert,
  Box,
  Button,
  Checkbox,
  Chip,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Divider,
  FormControlLabel,
  Pagination,
  Paper,
  Stack,
  Step,
  StepLabel,
  Stepper,
  Tab,
  Tabs,
  TextField,
  Typography,
} from "@mui/material";
import AddRounded from "@mui/icons-material/AddRounded";
import SyncRounded from "@mui/icons-material/SyncRounded";
import OpenInNewRounded from "@mui/icons-material/OpenInNewRounded";
import AdsClickRounded from "@mui/icons-material/AdsClickRounded";
import AutoAwesomeRounded from "@mui/icons-material/AutoAwesomeRounded";
import { AdsAiDialog } from "~/components/meta-ads/ads-ai-dialog";
import { MetaSyncPanel } from "~/components/meta-ads/meta-sync-panel";
import { AppLayout } from "~/components/app-layout/app-layout";
import { RoleGuard } from "~/components/role-guard/role-guard";
import {
  metaAdsApi,
  type AdAccount,
  type AdCampaign,
  type AdSpec,
  type AdHierarchy,
  type AdsPage,
} from "~/utils/meta-ads-api";
export function meta() {
  return [{ title: "Meta Ads — Creative Connects" }];
}
const money = (n: number, currency = "INR") =>
  new Intl.NumberFormat("en-IN", { style: "currency", currency }).format(n);
const ctaLabel = (key: string) =>
  key
    .toLowerCase()
    .replaceAll("_", " ")
    .replace(/^./, (value) => value.toUpperCase());
const errorText = (e: unknown) => {
  const x = e as { errors?: Record<string, string[]>; message?: string };
  return (
    Object.values(x.errors ?? {})
      .flat()
      .join(" ") ||
    x.message ||
    "This action failed. Refresh and try again."
  );
};
const when = (s: string | null) => (s ? new Date(s).toLocaleString() : "Not synced yet");
const metaLink = (a: AdAccount, c?: AdCampaign) =>
  `https://adsmanager.facebook.com/adsmanager/manage/campaigns?act=${a.metaAccountId}${c?.remoteId ? `&selected_campaign_ids=${c.remoteId}` : ""}`;
const selectProps = { select: { native: true }, inputLabel: { shrink: true } } as const;
function SelectField({
  label,
  value,
  onChange,
  children,
}: {
  label: string;
  value: string;
  onChange: (v: string) => void;
  children: ReactNode;
}) {
  return (
    <TextField
      fullWidth
      select
      label={label}
      value={value}
      onChange={(e) => onChange(e.target.value)}
      slotProps={selectProps}
      sx={{ minWidth: 0, "& .MuiNativeSelect-select": { pr: "16px" } }}
    >
      {children}
    </TextField>
  );
}
type Confirmation = { title: string; body: string; label: string; run: () => Promise<unknown> };

export default function MetaAds() {
  return (
    <AppLayout>
      <RoleGuard allow={["admin", "manager"]}>
        <AdsWorkspace />
      </RoleGuard>
    </AppLayout>
  );
}
function AdsWorkspace() {
  const [aiOpen, setAiOpen] = useState(false),
    [proposal, setProposal] = useState<AdSpec | null>(null);
  const [accounts, setAccounts] = useState<AdAccount[]>([]),
    [accountId, setAccountId] = useState("");
  const [tab, setTab] = useState(0),
    [page, setPage] = useState(1),
    [search, setSearch] = useState(""),
    [query, setQuery] = useState("");
  const [data, setData] = useState<AdsPage | null>(null),
    [loading, setLoading] = useState(true),
    [busy, setBusy] = useState(false),
    [error, setError] = useState(""),
    [notice, setNotice] = useState("");
  const [accountEditor, setAccountEditor] = useState<AdAccount | null | undefined>(),
    [draft, setDraft] = useState<AdCampaign | null | undefined>(),
    [selected, setSelected] = useState<AdCampaign | null>(null),
    [detail, setDetail] = useState<AdHierarchy | null>(null),
    [detailBusy, setDetailBusy] = useState(false),
    [confirm, setConfirm] = useState<Confirmation | null>(null);
  const account = accounts.find((a) => a.id === accountId);
  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const rows = await metaAdsApi.accounts();
      setAccounts(rows);
      const chosen = rows.find((a) => a.id === accountId)?.id || rows[0]?.id || "";
      if (chosen !== accountId) {
        setAccountId(chosen);
        return;
      }
      const loaded = chosen ? await metaAdsApi.campaigns(chosen, page, query) : null;
      setData(loaded);
      setSelected((previous) => (previous ? (loaded?.data.find((c) => c.id === previous.id) ?? previous) : null));
    } catch (e) {
      setError(errorText(e));
    } finally {
      setLoading(false);
    }
  }, [accountId, page, query]);
  useEffect(() => {
    void load();
  }, [load]);
  useEffect(() => {
    if (
      !data?.data.some(
        (c) => ["QUEUED", "PUBLISHING"].includes(c.state) || ["QUEUED", "APPLYING"].includes(c.syncState ?? ""),
      )
    )
      return;
    const t = setInterval(() => void load(), 10000);
    return () => clearInterval(t);
  }, [data, load]);
  async function action(run: () => Promise<unknown>, message: string) {
    setBusy(true);
    setError("");
    setNotice("");
    try {
      await run();
      setNotice(message);
      await load();
      if (selected?.remoteId) setDetail(await metaAdsApi.details(selected.id));
      return true;
    } catch (e) {
      setError(errorText(e));
      return false;
    } finally {
      setBusy(false);
    }
  }
  async function show(c: AdCampaign) {
    setSelected(c);
    setDetail(null);
    setDetailBusy(true);
    try {
      setDetail(await metaAdsApi.details(c.id));
    } catch (e) {
      setError(errorText(e));
    } finally {
      setDetailBusy(false);
    }
  }
  function campaignAction(c: AdCampaign, status: string) {
    setConfirm({
      title:
        status === "ACTIVE" ? "Activate campaign?" : status === "ARCHIVED" ? "Archive campaign?" : "Pause campaign?",
      body:
        status === "ACTIVE"
          ? `“${c.name}” may start spending from ${account?.name}. ${c.specification ? `Its ad-set daily budget is ${money(c.specification.dailyBudget / 100, account?.currency ?? "INR")}.` : "Existing ad-set budgets and schedules in Meta apply."} Meta may spend above a daily budget on individual days. Review the account billing and all ad sets before activating.`
          : `${status === "PAUSED" ? "Stop delivery for" : "Archive"} “${c.name}” in Meta. Previously incurred charges remain payable.`,
      label: status === "ACTIVE" ? "Activate campaign" : status === "PAUSED" ? "Pause campaign" : "Archive campaign",
      run: () => metaAdsApi.status(c.id, status),
    });
  }
  return (
    <Box
      data-ads-review
      sx={{ p: { xs: 2, md: 4 }, maxWidth: 1600, mx: "auto", width: "100%", minWidth: 0, flex: 1, minHeight: 0, overflowY: "auto" }}
    >
      <Stack
        direction={{ xs: "column", sm: "row" }}
        spacing={2}
        sx={{ justifyContent: "space-between", alignItems: { sm: "center" }, mb: 3 }}
      >
        <Box>
          <Typography sx={{ fontWeight: 700 }} component="h1" variant="h4">
            Meta Ads
          </Typography>
          <Typography color="text.secondary" sx={{ mt: 1 }}>
            Facebook and Instagram ads. Website visits and WhatsApp conversations.
          </Typography>
        </Box>
        <Stack direction={{ xs: "column", sm: "row" }} spacing={1}>
          <Button
            variant="outlined"
            startIcon={<AutoAwesomeRounded />}
            disabled={!account?.verifiedAt || busy}
            onClick={() => setAiOpen(true)}
          >
            Create with AI
          </Button>
          <Button
            variant="contained"
            disableElevation
            startIcon={<AddRounded />}
            onClick={() => {
              setProposal(null);
              setDraft(null);
            }}
            disabled={!account?.verifiedAt || busy}
          >
            Create campaign
          </Button>
        </Stack>
      </Stack>
      {error && (
        <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError("")}>
          {error}
        </Alert>
      )}
      {notice && (
        <Alert severity="success" sx={{ mb: 2 }} onClose={() => setNotice("")}>
          {notice}
        </Alert>
      )}
      <Paper variant="outlined" sx={{ p: 2, mb: 3 }}>
        <Stack sx={{ gap: 2, alignItems: { sm: "center" } }} direction={{ xs: "column", sm: "row" }}>
          <Box sx={{ flex: 1, minWidth: 0 }}>
            <SelectField
              label="Ad account"
              value={accountId}
              onChange={(v) => {
                setAccountId(v);
                setPage(1);
              }}
            >
              {!accounts.length && <option value="">No account connected</option>}
              {accounts.map((a) => (
                <option key={a.id} value={a.id}>
                  {a.name}
                  {a.currency ? ` · ${a.currency}` : ""}
                </option>
              ))}
            </SelectField>
          </Box>
          <Button
            startIcon={<SyncRounded />}
            disabled={!account?.verifiedAt || busy}
            onClick={() =>
              account &&
              void action(
                () => metaAdsApi.sync(account.id),
                "Sync queued. Refresh shortly to see the latest Meta results.",
              )
            }
          >
            Sync from Meta
          </Button>
          <Button disabled={busy} onClick={() => void load()}>
            Refresh
          </Button>
          {account && (
            <Button
              component="a"
              href={metaLink(account)}
              target="_blank"
              rel="noopener noreferrer"
              endIcon={<OpenInNewRounded />}
            >
              Ads Manager
            </Button>
          )}
        </Stack>
        {account && (
          <Typography variant="caption" color="text.secondary" sx={{ display: "block", mt: 2 }}>
            Last sync: {when(account.syncedAt)} · Reporting: last 7 complete days ·{" "}
            {account.timezone || "Account timezone pending verification"}
          </Typography>
        )}
        {account && (
          <FormControlLabel
            sx={{ mt: 1, ml: 0 }}
            control={
              <Checkbox
                checked={account.autoSync ?? true}
                disabled={busy}
                onChange={(_, checked) =>
                  void action(
                    () => metaAdsApi.syncSettings(account.id, checked),
                    checked ? "Automatic Meta pulls enabled every five minutes." : "Automatic Meta pulls paused.",
                  )
                }
              />
            }
            label="Pull Meta changes every 5 minutes"
          />
        )}
      </Paper>
      {account?.syncError && (
        <Alert severity="warning" sx={{ mb: 2 }}>
          {account.syncError}
        </Alert>
      )}
      <Tabs value={tab} onChange={(_, v) => setTab(v)} aria-label="Meta Ads sections" sx={{ mb: 3 }}>
        <Tab label="Campaigns" />
        <Tab label="Reports" />
        <Tab label="Accounts" />
      </Tabs>
      {loading && !data ? (
        <Box sx={{ py: 6, textAlign: "center" }}>
          <CircularProgress aria-label="Loading ads" />
        </Box>
      ) : tab === 2 ? (
        <Stack spacing={2}>
          <Stack sx={{ justifyContent: "space-between", alignItems: "center" }} direction="row">
            <Typography component="h2" variant="h6">
              Connected ad accounts
            </Typography>
            <Button variant="outlined" onClick={() => setAccountEditor(null)}>
              Connect account
            </Button>
          </Stack>
          <Alert severity="info">
            Use a Meta token with ads_read and ads_management, advertiser access, and Page permissions. Meta manages
            billing and account approvals.
          </Alert>
          {accounts.map((a) => (
            <Paper key={a.id} variant="outlined" sx={{ p: 3 }}>
              <Stack sx={{ gap: 2, justifyContent: "space-between" }} direction={{ xs: "column", md: "row" }}>
                <Box>
                  <Typography variant="h6">{a.name}</Typography>
                  <Typography color="text.secondary">
                    act_{a.metaAccountId} · Page {a.pageId}
                  </Typography>
                  <Typography variant="body2" sx={{ mt: 1 }}>
                    {a.instagramId ? "Instagram linked" : "Instagram not configured"} ·{" "}
                    {a.whatsappNumber ? `WhatsApp +${a.whatsappNumber}` : "WhatsApp not configured"}
                  </Typography>
                  <Chip
                    size="small"
                    label={a.verifiedAt ? "Verified" : "Verification required"}
                    color={a.verifiedAt ? "success" : "default"}
                    sx={{ mt: 2 }}
                  />
                </Box>
                <Stack sx={{ flexWrap: "wrap", gap: 1, alignItems: "center" }} direction="row">
                  <Button
                    disabled={busy}
                    onClick={() =>
                      void action(
                        () => metaAdsApi.verify(a.id),
                        "Account and Page verified. Refresh to see the account currency.",
                      )
                    }
                  >
                    Verify connection
                  </Button>
                  <Button onClick={() => setAccountEditor(a)}>Edit</Button>
                  <Button
                    color="error"
                    onClick={() =>
                      setConfirm({
                        title: "Disconnect account?",
                        body: "This removes the saved connection. Accounts with saved campaigns must be retained for management.",
                        label: "Disconnect account",
                        run: () => metaAdsApi.deleteAccount(a.id),
                      })
                    }
                  >
                    Disconnect
                  </Button>
                </Stack>
              </Stack>
            </Paper>
          ))}
        </Stack>
      ) : !account ? (
        <Paper variant="outlined" sx={{ p: { xs: 3, md: 6 }, textAlign: "center" }}>
          <AdsClickRounded sx={{ fontSize: "3rem", color: "primary.dark", mb: 2 }} />
          <Typography component="h2" variant="h5">
            Connect your first ad account
          </Typography>
          <Typography color="text.secondary" sx={{ my: 2 }}>
            Bring your Facebook and Instagram campaigns into this workspace.
          </Typography>
          <Button variant="contained" disableElevation onClick={() => setAccountEditor(null)}>
            Connect ad account
          </Button>
        </Paper>
      ) : (
        <>
          {!account.verifiedAt && (
            <Alert severity="warning" action={<Button onClick={() => setTab(2)}>Open accounts</Button>} sx={{ mb: 3 }}>
              Verify this connection before creating campaigns or syncing reports.
            </Alert>
          )}
          {tab === 1 && (
            <Stack direction={{ xs: "column", sm: "row" }} spacing={2} sx={{ mb: 3 }}>
              {[
                ["Spend", money(data?.summary.spend ?? 0, account.currency ?? "INR")],
                ["Impressions", (data?.summary.impressions ?? 0).toLocaleString()],
                ["Clicks", (data?.summary.clicks ?? 0).toLocaleString()],
              ].map(([label, value]) => (
                <Paper key={label} variant="outlined" sx={{ p: 3, flex: 1 }}>
                  <Typography color="text.secondary">{label}</Typography>
                  <Typography variant="h4" sx={{ mt: 1 }}>
                    {account.syncedAt ? value : "—"}
                  </Typography>
                </Paper>
              ))}
            </Stack>
          )}
          <Stack
            component="form"
            onSubmit={(e) => {
              e.preventDefault();
              setQuery(search);
              setPage(1);
            }}
            direction="row"
            sx={{ gap: 1, mb: 2 }}
          >
            <TextField
              label="Search campaigns"
              size="small"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              sx={{ flex: 1, minWidth: 0 }}
            />
            <Button type="submit" variant="outlined">
              Search
            </Button>
          </Stack>
          <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
            {data?.meta.total ?? 0} campaigns ·{" "}
            {tab === 1
              ? "Results match your search. Spend is reported in the ad account currency."
              : "Publish drafts paused, review in Meta, then activate when ready."}
          </Typography>
          <Stack spacing={2}>
            {data?.data.map((c) => (
              <Paper variant="outlined" key={c.id} sx={{ p: 3 }}>
                <Stack sx={{ gap: 3, justifyContent: "space-between" }} direction={{ xs: "column", md: "row" }}>
                  <Box sx={{ flex: 1, minWidth: 0 }}>
                    <Stack sx={{ flexWrap: "wrap", alignItems: "center", gap: 1 }} direction="row">
                      <Typography component="h2" variant="h6" sx={{ overflowWrap: "anywhere" }}>
                        {c.name}
                      </Typography>
                      <Chip
                        size="small"
                        label={c.state === "DRAFT" ? "Draft" : c.status || c.state}
                        color={c.status === "ACTIVE" ? "success" : c.state === "NEEDS_REVIEW" ? "warning" : "default"}
                      />
                    </Stack>
                    <Typography color="text.secondary" variant="body2" sx={{ mt: 1 }}>
                      {c.objective.replace("OUTCOME_", "")} ·{" "}
                      {c.specification?.destination === "whatsapp"
                        ? "WhatsApp conversations"
                        : "Website or configured destination"}
                      {c.specification
                        ? ` · ${money(c.specification.dailyBudget / 100, account.currency ?? "INR")}/day`
                        : ""}
                    </Typography>
                    {c.pendingChanges && Object.keys(c.pendingChanges).length > 0 && (
                      <Chip
                        size="small"
                        sx={{ mt: 1 }}
                        color={c.syncConflicts?.length ? "warning" : "default"}
                        label={c.syncConflicts?.length ? "Sync conflict" : "Pending app edits"}
                      />
                    )}
                    {c.lastError && (
                      <Alert severity="warning" sx={{ mt: 2 }}>
                        {c.lastError}
                      </Alert>
                    )}
                    {tab === 1 && (
                      <Stack direction="row" sx={{ flexWrap: "wrap", gap: 3, mt: 2 }}>
                        <Typography>
                          Spend{" "}
                          <strong>
                            {c.insights ? money(Number(c.insights.spend ?? 0), account.currency ?? "INR") : "—"}
                          </strong>
                        </Typography>
                        <Typography>
                          Impressions <strong>{c.insights?.impressions ?? "—"}</strong>
                        </Typography>
                        <Typography>
                          Clicks <strong>{c.insights?.clicks ?? "—"}</strong>
                        </Typography>
                        <Typography>
                          CTR <strong>{c.insights?.ctr ? `${Number(c.insights.ctr).toFixed(2)}%` : "—"}</strong>
                        </Typography>
                      </Stack>
                    )}
                  </Box>
                  <Stack direction="row" sx={{ flexWrap: "wrap", gap: 1, alignItems: "center", maxWidth: { md: 480 } }}>
                    <Button disabled={busy} onClick={() => void show(c)}>
                      Details
                    </Button>
                    {c.state === "DRAFT" ? (
                      <>
                        <Button
                          onClick={() => {
                            setProposal(null);
                            setDraft(c);
                          }}
                        >
                          Edit
                        </Button>
                        <Button
                          variant="outlined"
                          disabled={busy}
                          onClick={() =>
                            setConfirm({
                              title: "Publish paused campaign?",
                              body: `Create “${c.name}” in Meta as a paused campaign, ad set and ad. Review the submitted creative and audience before activating delivery.`,
                              label: "Publish paused",
                              run: () => metaAdsApi.publish(c.id),
                            })
                          }
                        >
                          Publish paused
                        </Button>
                        <Button
                          color="error"
                          onClick={() =>
                            setConfirm({
                              title: "Delete draft?",
                              body: `Permanently delete the local draft “${c.name}”. No Meta campaign will be changed.`,
                              label: "Delete draft",
                              run: () => metaAdsApi.remove(c.id),
                            })
                          }
                        >
                          Delete
                        </Button>
                      </>
                    ) : c.remoteId && !["QUEUED", "PUBLISHING", "NEEDS_REVIEW"].includes(c.state) ? (
                      <>
                        <Button
                          disabled={busy || c.status === "ARCHIVED"}
                          onClick={() => campaignAction(c, c.status === "ACTIVE" ? "PAUSED" : "ACTIVE")}
                        >
                          {c.status === "ACTIVE" ? "Pause" : "Activate"}
                        </Button>
                        <Button
                          disabled={busy || c.status === "ARCHIVED"}
                          onClick={() => campaignAction(c, "ARCHIVED")}
                        >
                          Archive
                        </Button>
                      </>
                    ) : null}
                    {c.specification && !["QUEUED", "PUBLISHING", "NEEDS_REVIEW"].includes(c.state) && (
                      <Button
                        disabled={busy}
                        onClick={() =>
                          void action(
                            () => metaAdsApi.duplicate(c.id),
                            "Campaign copied to a new local draft. Review its schedule before publishing.",
                          )
                        }
                      >
                        Duplicate
                      </Button>
                    )}
                  </Stack>
                </Stack>
              </Paper>
            ))}
          </Stack>
          {!data?.data.length && (
            <Paper variant="outlined" sx={{ p: 5, textAlign: "center" }}>
              <Typography variant="h6">{query ? "No campaigns match your search" : "No campaigns yet"}</Typography>
              <Typography color="text.secondary" sx={{ mt: 1 }}>
                Sync existing campaigns from Meta or create a new draft.
              </Typography>
            </Paper>
          )}
          {(data?.meta.lastPage ?? 0) > 1 && (
            <Pagination count={data?.meta.lastPage} page={page} onChange={(_, v) => setPage(v)} sx={{ mt: 3 }} />
          )}
        </>
      )}
      {accountEditor !== undefined && (
        <AccountEditor
          initial={accountEditor}
          onClose={() => setAccountEditor(undefined)}
          onSaved={() => {
            setAccountEditor(undefined);
            setTab(2);
            void load();
          }}
        />
      )}
      {aiOpen && account && (
        <AdsAiDialog
          account={account}
          onClose={() => setAiOpen(false)}
          onDraft={(value) => {
            setProposal(value);
            setAiOpen(false);
            setDraft(null);
          }}
        />
      )}
      {draft !== undefined && account && (
        <CampaignEditor
          account={account}
          initial={draft}
          proposal={proposal}
          onClose={() => {
            setDraft(undefined);
            setProposal(null);
          }}
          onSaved={() => {
            setDraft(undefined);
            setProposal(null);
            setTab(0);
            void load();
          }}
        />
      )}
      <Dialog open={!!confirm} onClose={() => !busy && setConfirm(null)} maxWidth="sm" fullWidth>
        <DialogTitle>{confirm?.title}</DialogTitle>
        <DialogContent>
          {error && (
            <Alert severity="error" sx={{ mb: 2 }}>
              {error}
            </Alert>
          )}
          <Typography>{confirm?.body}</Typography>
        </DialogContent>
        <DialogActions>
          <Button disabled={busy} onClick={() => setConfirm(null)}>
            Cancel
          </Button>
          <Button
            disabled={busy}
            variant="contained"
            disableElevation
            onClick={() => {
              if (confirm)
                void action(confirm.run, "Change submitted. Refresh or sync to confirm Meta delivery status.").then(
                  (success) => success && setConfirm(null),
                );
            }}
          >
            {busy ? "Submitting…" : confirm?.label}
          </Button>
        </DialogActions>
      </Dialog>
      <Dialog open={!!selected} onClose={() => setSelected(null)} maxWidth="md" fullWidth>
        <DialogTitle>{selected?.name}</DialogTitle>
        <DialogContent>
          <Stack spacing={3}>
            {error && <Alert severity="error">{error}</Alert>}
            {selected?.remoteId && account && (
              <Button
                component="a"
                href={metaLink(account, selected)}
                target="_blank"
                rel="noopener noreferrer"
                endIcon={<OpenInNewRounded />}
              >
                Review campaign in Meta
              </Button>
            )}
            {selected?.remoteId && account && (
              <MetaSyncPanel
                campaign={selected}
                currency={account.currency ?? "INR"}
                onUpdate={(value) => {
                  setSelected(value);
                  setData((old) =>
                    old ? { ...old, data: old.data.map((c) => (c.id === value.id ? value : c)) } : old,
                  );
                }}
              />
            )}
            {selected?.specification && (
              <Box>
                <Typography variant="h6">Saved campaign settings</Typography>
                <Typography>
                  {selected.specification.countries.join(", ")} · Ages {selected.specification.ageMin}–
                  {selected.specification.ageMax === 65 ? "65+" : selected.specification.ageMax} ·{" "}
                  {selected.specification.placements.join(" + ")}
                </Typography>
                <Typography>Ends {when(selected.specification.endAt)}</Typography>
                <Typography sx={{ mt: 2 }}>{selected.specification.message}</Typography>
                <Typography sx={{ fontWeight: 700 }}>{selected.specification.headline}</Typography>
              </Box>
            )}
            {detailBusy ? (
              <CircularProgress aria-label="Loading ad sets and ads" />
            ) : (
              detail &&
              (
                [
                  ["Ad sets", detail.adsets, "adset"],
                  ["Ads", detail.ads, "ad"],
                ] as const
              ).map(([title, rows, kind]) => (
                <Box key={title}>
                  <Typography variant="h6" sx={{ mb: 2 }}>
                    {title}
                  </Typography>
                  {!rows.length && <Typography color="text.secondary">No published {title.toLowerCase()}.</Typography>}
                  {rows.map((row) => (
                    <Paper variant="outlined" key={row.id} sx={{ p: 2, mb: 2 }}>
                      <Typography sx={{ fontWeight: 700 }}>{row.name}</Typography>
                      <Typography variant="body2" color="text.secondary">
                        {row.effective_status} · {row.id}
                        {row.daily_budget
                          ? ` · ${money(Number(row.daily_budget) / 100, account?.currency ?? "INR")}/day`
                          : ""}
                      </Typography>
                      {row.issues_info?.map((issue, i) => (
                        <Alert key={i} severity="warning">
                          {issue.error_message || "Meta flagged an issue. Review in Ads Manager."}
                        </Alert>
                      ))}
                      <Button
                        disabled={busy || row.status === "ARCHIVED"}
                        onClick={() =>
                          selected &&
                          setConfirm({
                            title: `${row.status === "ACTIVE" ? "Pause" : "Activate"} ${kind === "adset" ? "ad set" : "ad"}?`,
                            body: `Change delivery for “${row.name}”. Active objects can spend when their parent campaign and ad set are active.`,
                            label: row.status === "ACTIVE" ? "Pause delivery" : "Activate delivery",
                            run: () =>
                              metaAdsApi.objectStatus(
                                selected.id,
                                kind,
                                row.id,
                                row.status === "ACTIVE" ? "PAUSED" : "ACTIVE",
                              ),
                          })
                        }
                      >
                        {row.status === "ACTIVE" ? "Pause" : "Activate"}
                      </Button>
                    </Paper>
                  ))}
                </Box>
              ))
            )}
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setSelected(null)}>Close details</Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
}

function AccountEditor({
  initial,
  onClose,
  onSaved,
}: {
  initial: AdAccount | null;
  onClose: () => void;
  onSaved: () => void;
}) {
  const [name, setName] = useState(initial?.name ?? ""),
    [metaAccountId, setMetaAccountId] = useState(initial?.metaAccountId ?? ""),
    [pageId, setPageId] = useState(initial?.pageId ?? ""),
    [instagramId, setInstagramId] = useState(initial?.instagramId ?? ""),
    [whatsappNumber, setWhatsAppNumber] = useState(initial?.whatsappNumber ?? ""),
    [accessToken, setAccessToken] = useState("");
  const [busy, setBusy] = useState(false),
    [error, setError] = useState("");
  return (
    <Dialog open onClose={() => !busy && onClose()} maxWidth="sm" fullWidth>
      <Box
        component="form"
        onSubmit={async (e) => {
          e.preventDefault();
          setBusy(true);
          setError("");
          try {
            await metaAdsApi.saveAccount(
              {
                name,
                metaAccountId: metaAccountId.replace(/^act_/, ""),
                pageId,
                instagramId: instagramId || null,
                whatsappNumber: whatsappNumber.replace(/\D/g, "") || null,
                ...(accessToken ? { accessToken } : {}),
              },
              initial?.id,
            );
            onSaved();
          } catch (e) {
            setError(errorText(e));
          } finally {
            setBusy(false);
          }
        }}
      >
        <DialogTitle>{initial ? "Edit ad account" : "Connect ad account"}</DialogTitle>
        <DialogContent>
          <Stack spacing={3} sx={{ pt: 1 }}>
            {error && <Alert severity="error">{error}</Alert>}
            <TextField required label="Account label" value={name} onChange={(e) => setName(e.target.value)} />
            <TextField
              required
              label="Meta ad account ID"
              value={metaAccountId}
              onChange={(e) => setMetaAccountId(e.target.value)}
              helperText="From Meta Ads Manager; act_ is optional."
            />
            <TextField required label="Facebook Page ID" value={pageId} onChange={(e) => setPageId(e.target.value)} />
            <TextField
              label="Linked Instagram account ID (optional)"
              value={instagramId}
              onChange={(e) => setInstagramId(e.target.value)}
            />
            <TextField
              label="Page-linked WhatsApp number (optional)"
              value={whatsappNumber}
              onChange={(e) => setWhatsAppNumber(e.target.value)}
              helperText="Include country code. Use your Facebook Page’s linked number."
            />
            <TextField
              required={!initial}
              label={initial ? "New access token (optional)" : "Meta access token"}
              type="password"
              autoComplete="new-password"
              value={accessToken}
              onChange={(e) => setAccessToken(e.target.value)}
              helperText={
                initial
                  ? "Leave blank to retain the encrypted token."
                  : "Encrypted; requires ads_read, ads_management and Page access."
              }
            />
            <Typography variant="body2" color="text.secondary">
              Save, then verify. Manage billing and linked accounts in Meta.
            </Typography>
            <Button
              component="a"
              href="https://business.facebook.com/settings"
              target="_blank"
              rel="noopener noreferrer"
            >
              Open Meta business settings
            </Button>
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button disabled={busy} onClick={onClose}>
            Cancel
          </Button>
          <Button disabled={busy} type="submit" variant="contained" disableElevation>
            {busy ? "Saving…" : "Save connection"}
          </Button>
        </DialogActions>
      </Box>
    </Dialog>
  );
}

function CampaignEditor({
  account,
  initial,
  proposal,
  onClose,
  onSaved,
}: {
  account: AdAccount;
  initial: AdCampaign | null;
  proposal?: AdSpec | null;
  onClose: () => void;
  onSaved: () => void;
}) {
  const [step, setStep] = useState(0),
    [busy, setBusy] = useState(false),
    [error, setError] = useState("");
  const [spec, setSpec] = useState<AdSpec>(
    proposal
      ? { ...proposal }
      : initial?.specification
        ? { ...initial.specification, name: initial.name }
        : {
            accountId: account.id,
            name: "",
            destination: "website",
            dailyBudget: 50000,
            countries: ["IN"],
            ageMin: 18,
            ageMax: 65,
            placements: ["facebook"],
            startAt: null,
            endAt: new Date(Date.now() + 7 * 86400000).toISOString(),
            headline: "",
            message: "",
            imageUrl: "",
            websiteUrl: "",
            cta: "LEARN_MORE",
            specialCategories: [],
          },
  );
  const [countries, setCountries] = useState(spec.countries.join(", ")),
    [budget, setBudget] = useState(String(spec.dailyBudget / 100));
  const set = <K extends keyof AdSpec>(key: K, value: AdSpec[K]) => setSpec((s) => ({ ...s, [key]: value }));
  const localTime = (s: string | null) =>
    s ? new Date(new Date(s).getTime() - new Date(s).getTimezoneOffset() * 60000).toISOString().slice(0, 16) : "";
  function move() {
    setError("");
    if (step === 0 && !spec.name.trim()) {
      setError("Enter a campaign name.");
      return;
    }
    if (
      step === 1 &&
      (!spec.placements.length || !Number(budget) || !countries.trim() || new Date(spec.endAt).getTime() <= Date.now())
    ) {
      setError("Set a positive budget, audience, placements and future end date.");
      return;
    }
    if (
      step === 2 &&
      (!spec.headline.trim() ||
        !spec.message.trim() ||
        !spec.imageUrl ||
        (spec.destination === "website" && !spec.websiteUrl))
    ) {
      setError("Add the headline, message, image URL and destination.");
      return;
    }
    setStep((s) => s + 1);
  }
  const details = (
    <Stack spacing={1}>
      <Typography>
        Account: <strong>{account.name}</strong> · {account.currency}
      </Typography>
      <Typography>
        Objective: <strong>{spec.destination === "whatsapp" ? "WhatsApp conversations" : "Website visits"}</strong>
      </Typography>
      <Typography>
        Daily ad-set budget: <strong>{money(Number(budget), account.currency ?? "INR")}</strong>
      </Typography>
      <Typography>
        Audience: {countries} · Ages {spec.ageMin}–{spec.ageMax === 65 ? "65+" : spec.ageMax}
      </Typography>
      <Typography>Placements: {spec.placements.join(" + ")} feeds</Typography>
      <Typography>
        Schedule: {spec.startAt ? when(spec.startAt) : "After activation"} → {when(spec.endAt)} (your local time)
      </Typography>
      <Typography>Special categories: {spec.specialCategories.join(", ") || "None declared"}</Typography>
    </Stack>
  );
  return (
    <Dialog open onClose={() => !busy && onClose()} maxWidth="md" fullWidth>
      <Box
        component="form"
        onSubmit={async (e) => {
          e.preventDefault();
          if (step < 3) {
            move();
            return;
          }
          setBusy(true);
          setError("");
          try {
            await metaAdsApi.save(
              {
                ...spec,
                countries: countries
                  .toUpperCase()
                  .split(",")
                  .map((s) => s.trim())
                  .filter(Boolean),
                dailyBudget: Math.round(Number(budget) * 100),
              },
              initial?.id,
            );
            onSaved();
          } catch (e) {
            setError(errorText(e));
          } finally {
            setBusy(false);
          }
        }}
      >
        <DialogTitle>{initial ? "Edit campaign draft" : "Create campaign"}</DialogTitle>
        <DialogContent>
          <Stepper activeStep={step} alternativeLabel sx={{ mb: 4 }}>
            {["Campaign", "Audience", "Creative", "Review"].map((t) => (
              <Step key={t}>
                <StepLabel>{t}</StepLabel>
              </Step>
            ))}
          </Stepper>
          {error && (
            <Alert severity="error" sx={{ mb: 3 }}>
              {error}
            </Alert>
          )}
          <Stack spacing={3}>
            {step === 0 && (
              <>
                <Typography variant="h6" component="h2">
                  Choose the campaign goal
                </Typography>
                <TextField
                  required
                  label="Campaign name"
                  value={spec.name}
                  onChange={(e) => set("name", e.target.value)}
                />
                <SelectField
                  label="Goal and destination"
                  value={spec.destination}
                  onChange={(v) => set("destination", v as AdSpec["destination"])}
                >
                  <option value="website">Website visits · Facebook and Instagram</option>
                  <option value="whatsapp" disabled={!account.whatsappNumber}>
                    WhatsApp conversations
                  </option>
                </SelectField>
                <Typography variant="body2" color="text.secondary">
                  One campaign, one ad set and one image ad. More formats and conversion goals can be managed in Meta
                  Ads Manager.
                </Typography>
                <Box>
                  <Typography sx={{ fontWeight: 700 }}>Special ad categories</Typography>
                  <Typography variant="body2" color="text.secondary">
                    Declare regulated content. Meta may require additional regional disclosures.
                  </Typography>
                  {["HOUSING", "EMPLOYMENT", "CREDIT"].map((v) => (
                    <FormControlLabel
                      key={v}
                      control={
                        <Checkbox
                          checked={spec.specialCategories.includes(v)}
                          onChange={(_, on) => {
                            set(
                              "specialCategories",
                              on ? [...spec.specialCategories, v] : spec.specialCategories.filter((x) => x !== v),
                            );
                            if (on) {
                              set("ageMin", 18);
                              set("ageMax", 65);
                            }
                          }}
                        />
                      }
                      label={v.charAt(0) + v.slice(1).toLowerCase()}
                    />
                  ))}
                </Box>
              </>
            )}
            {step === 1 && (
              <>
                <Typography variant="h6" component="h2">
                  Budget, audience and schedule
                </Typography>
                <TextField
                  required
                  label={`Daily budget (${account.currency})`}
                  type="number"
                  value={budget}
                  onChange={(e) => setBudget(e.target.value)}
                  slotProps={{ htmlInput: { min: 1, step: 0.01 } }}
                  helperText="Meta may spend above this amount on individual days. Billing remains with Meta."
                />
                <TextField
                  required
                  label="Country codes"
                  value={countries}
                  onChange={(e) => setCountries(e.target.value)}
                  helperText="Comma-separated two-letter codes, for example IN, AE. Meta validates availability."
                />
                <Stack sx={{ gap: 2 }} direction="row">
                  <TextField
                    label="Minimum age"
                    type="number"
                    value={spec.ageMin}
                    disabled={!!spec.specialCategories.length}
                    onChange={(e) => set("ageMin", Number(e.target.value))}
                    slotProps={{ htmlInput: { min: 18, max: 65 } }}
                    sx={{ flex: 1 }}
                  />
                  <TextField
                    label="Maximum age (65 means 65+)"
                    type="number"
                    value={spec.ageMax}
                    disabled={!!spec.specialCategories.length}
                    onChange={(e) => set("ageMax", Number(e.target.value))}
                    slotProps={{ htmlInput: { min: 18, max: 65 } }}
                    sx={{ flex: 1 }}
                  />
                </Stack>
                <Box>
                  <Typography sx={{ fontWeight: 700 }}>Feed placements</Typography>
                  {["facebook", "instagram"].map((p) => (
                    <FormControlLabel
                      key={p}
                      control={
                        <Checkbox
                          checked={spec.placements.includes(p)}
                          disabled={p === "instagram" && !account.instagramId}
                          onChange={(_, on) =>
                            set("placements", on ? [...spec.placements, p] : spec.placements.filter((x) => x !== p))
                          }
                        />
                      }
                      label={p === "facebook" ? "Facebook" : "Instagram"}
                    />
                  ))}
                </Box>
                <TextField
                  label="Start (optional, local time)"
                  type="datetime-local"
                  value={localTime(spec.startAt)}
                  onChange={(e) => set("startAt", e.target.value ? new Date(e.target.value).toISOString() : null)}
                  slotProps={{ inputLabel: { shrink: true } }}
                />
                <TextField
                  required
                  label="End (local time)"
                  type="datetime-local"
                  value={localTime(spec.endAt)}
                  onChange={(e) => {
                    if (e.target.value) set("endAt", new Date(e.target.value).toISOString());
                  }}
                  slotProps={{ inputLabel: { shrink: true } }}
                />
              </>
            )}
            {step === 2 && (
              <>
                <Typography variant="h6" component="h2">
                  Create the image ad
                </Typography>
                <TextField
                  required
                  multiline
                  minRows={3}
                  label="Primary text"
                  value={spec.message}
                  onChange={(e) => set("message", e.target.value)}
                />
                <TextField
                  required
                  label="Headline"
                  value={spec.headline}
                  onChange={(e) => set("headline", e.target.value)}
                />
                <TextField
                  required
                  type="url"
                  label="Public image URL"
                  value={spec.imageUrl}
                  onChange={(e) => set("imageUrl", e.target.value)}
                  helperText="Use a public HTTPS image that you have permission to advertise."
                />
                {spec.destination === "website" ? (
                  <>
                    <TextField
                      required
                      type="url"
                      label="Website URL"
                      value={spec.websiteUrl}
                      onChange={(e) => set("websiteUrl", e.target.value)}
                    />
                    <SelectField label="Call to action" value={spec.cta} onChange={(v) => set("cta", v)}>
                      {["LEARN_MORE", "SHOP_NOW", "SIGN_UP", "BOOK_NOW", "CONTACT_US"].map((v) => (
                        <option key={v} value={v}>
                          {ctaLabel(v)}
                        </option>
                      ))}
                    </SelectField>
                  </>
                ) : (
                  <Alert severity="info">
                    The ad opens WhatsApp at +{account.whatsappNumber}. Your connected inbox and configured automations
                    handle incoming messages.
                  </Alert>
                )}
              </>
            )}
            {step === 3 && (
              <>
                <Typography variant="h6" component="h2">
                  Review your campaign draft
                </Typography>
                {details}
                <Divider />
                <Paper variant="outlined" sx={{ p: 3 }}>
                  <Typography variant="caption" color="text.secondary">
                    Illustrative ad preview · Final placement may differ
                  </Typography>
                  <Typography sx={{ fontWeight: 700, mt: 2 }}>{account.name}</Typography>
                  <Typography sx={{ my: 2, whiteSpace: "pre-wrap", overflowWrap: "anywhere" }}>
                    {spec.message}
                  </Typography>
                  {/^https:\/\//.test(spec.imageUrl) && (
                    <Box
                      component="img"
                      src={spec.imageUrl}
                      alt="Selected advertising creative"
                      referrerPolicy="no-referrer"
                      sx={{
                        display: "block",
                        width: "100%",
                        maxHeight: 280,
                        objectFit: "contain",
                        bgcolor: "background.default",
                        mb: 2,
                      }}
                    />
                  )}
                  <Typography variant="h6">{spec.headline}</Typography>
                  <Chip
                    sx={{ mt: 2 }}
                    label={spec.destination === "whatsapp" ? "Send WhatsApp message" : ctaLabel(spec.cta)}
                  />
                </Paper>
                <Alert severity="info">
                  Saving creates a local draft. Publishing creates paused Meta objects. Activate only after reviewing
                  the budget, creative, audience and billing.
                </Alert>
              </>
            )}
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button disabled={busy} onClick={onClose}>
            Cancel
          </Button>
          {step > 0 && (
            <Button
              disabled={busy}
              onClick={() => {
                setStep((s) => s - 1);
                setError("");
              }}
            >
              Back
            </Button>
          )}
          <Button type="submit" variant="contained" disableElevation disabled={busy}>
            {busy ? "Saving…" : ["Set audience", "Add creative", "Review campaign", "Save draft"][step]}
          </Button>
        </DialogActions>
      </Box>
    </Dialog>
  );
}
