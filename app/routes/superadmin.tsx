import { useCallback, useEffect, useState, type FormEvent } from "react";
import { Link, useNavigate, useSearchParams } from "react-router";
import {
  Alert,
  AppBar,
  Avatar,
  Box,
  Button,
  Chip,
  CircularProgress,
  Divider,
  Drawer,
  IconButton,
  InputAdornment,
  List,
  ListItemButton,
  ListItemIcon,
  ListItemText,
  MenuItem,
  Paper,
  Skeleton,
  Snackbar,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TablePagination,
  TableRow,
  TextField,
  Toolbar,
  Tooltip,
  Typography,
  ThemeProvider,
  createTheme,
} from "@mui/material";
import { theme as muiTheme } from "~/mui-theme";
import { BrandLogo } from "~/components/brand-logo/brand-logo";
import DashboardOutlinedIcon from "@mui/icons-material/DashboardOutlined";
import BusinessOutlinedIcon from "@mui/icons-material/BusinessOutlined";
import PeopleOutlineIcon from "@mui/icons-material/PeopleOutlineRounded";
import HistoryRoundedIcon from "@mui/icons-material/HistoryRounded";
import ManageAccountsOutlinedIcon from "@mui/icons-material/ManageAccountsOutlined";
import LogoutRoundedIcon from "@mui/icons-material/LogoutRounded";
import AddRoundedIcon from "@mui/icons-material/AddRounded";
import SearchRoundedIcon from "@mui/icons-material/SearchRounded";
import RefreshRoundedIcon from "@mui/icons-material/RefreshRounded";
import MenuRoundedIcon from "@mui/icons-material/MenuRounded";
import CloseRoundedIcon from "@mui/icons-material/CloseRounded";
import EditOutlinedIcon from "@mui/icons-material/EditOutlined";
import DeleteOutlineRoundedIcon from "@mui/icons-material/DeleteOutlineRounded";
import LockResetRoundedIcon from "@mui/icons-material/LockResetRounded";
import PauseCircleOutlineRoundedIcon from "@mui/icons-material/PauseCircleOutlineRounded";
import PlayCircleOutlineRoundedIcon from "@mui/icons-material/PlayCircleOutlineRounded";
import ArrowForwardRoundedIcon from "@mui/icons-material/ArrowForwardRounded";
import ArrowOutwardRoundedIcon from "@mui/icons-material/ArrowOutwardRounded";
import ShieldOutlinedIcon from "@mui/icons-material/ShieldOutlined";
import { apiClient, ApiError } from "~/utils/api-client";
import { useAuth } from "~/hooks/use-auth";
import {
  ActionDialog,
  CompanyForm,
  CompanyPicker,
  UserForm,
  errorText,
  roleLabels,
  statusLabels,
  type CompanyOption,
  type ConfirmAction,
} from "~/components/superadmin/platform-dialogs";
import type {
  PlatformAuditEntry,
  PlatformCompany,
  PlatformCompanyDetail,
  PlatformSummary,
  PlatformUser,
} from "~/data/platform-types";
import type { TeamMember } from "~/data/types";
import s from "./superadmin.module.css";
const platformTheme = createTheme(muiTheme, {
  palette: {
    primary: { main: "#00785F", dark: "#00614D" },
    success: { main: "#167347" },
    error: { main: "#B4232C" },
  },
  components: {
    MuiButton: { defaultProps: { disableElevation: true }, styleOverrides: { root: { minHeight: 44 } } },
    MuiAutocomplete: {
      styleOverrides: { popupIndicator: { width: 44, height: 44 }, clearIndicator: { width: 44, height: 44 } },
    },
  },
});
export function meta() {
  return [{ title: "Platform administration — Creative Connects" }];
}
const navItems = [
  { id: "overview", label: "Overview", icon: DashboardOutlinedIcon },
  { id: "companies", label: "Companies", icon: BusinessOutlinedIcon },
  { id: "users", label: "Users & roles", icon: PeopleOutlineIcon },
  { id: "activity", label: "Activity", icon: HistoryRoundedIcon },
  { id: "account", label: "Account", icon: ManageAccountsOutlinedIcon },
] as const;
type View = (typeof navItems)[number]["id"];
const date = (value?: string) =>
  value
    ? new Intl.DateTimeFormat("en", { day: "numeric", month: "short", year: "numeric" }).format(new Date(value))
    : "—";
const timestamp = (value?: string) =>
  value
    ? new Intl.DateTimeFormat("en", {
        day: "numeric",
        month: "short",
        year: "numeric",
        hour: "numeric",
        minute: "2-digit",
      }).format(new Date(value))
    : "—";
const number = (value?: number) => new Intl.NumberFormat("en").format(value ?? 0);
function useLoad<T>(loader: () => Promise<T>, enabled = true) {
  const [data, setData] = useState<T | null>(null);
  const [loading, setLoading] = useState(enabled);
  const [error, setError] = useState<string | null>(null);
  const [attempt, setAttempt] = useState(0);
  useEffect(() => {
    if (!enabled) return;
    let cancelled = false;
    setLoading(true);
    setError(null);
    loader()
      .then((data) => {
        if (!cancelled) setData(data);
      })
      .catch((e) => {
        if (!cancelled) setError(errorText(e));
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [loader, enabled, attempt]);
  return { data, loading, error, retry: () => setAttempt((a) => a + 1) };
}
function useDebounced(value: string) {
  const [result, setResult] = useState(value);
  useEffect(() => {
    const id = setTimeout(() => setResult(value), 300);
    return () => clearTimeout(id);
  }, [value]);
  return result;
}
function Status({ value }: { value: string }) {
  return (
    <Chip
      label={statusLabels[value] ?? value}
      size="small"
      variant="outlined"
      color={
        value === "active" || value === "connected"
          ? "success"
          : value === "suspended" || value === "error"
            ? "error"
            : "default"
      }
      sx={{ fontWeight: 600, textTransform: "capitalize" }}
    />
  );
}
function Failure({ message, retry }: { message: string; retry: () => void }) {
  return (
    <Alert
      severity="error"
      action={
        <Button onClick={retry} color="inherit">
          Retry
        </Button>
      }
    >
      {message}
    </Alert>
  );
}
function LoadingRows() {
  return (
    <Stack spacing={2} sx={{ p: 3 }} aria-label="Loading records" role="status">
      {[0, 1, 2, 3].map((i) => (
        <Skeleton key={i} variant="rounded" height={48} />
      ))}
    </Stack>
  );
}
function Empty({ title, body, action }: { title: string; body: string; action?: React.ReactNode }) {
  return (
    <Stack
      spacing={2}
      sx={{
        alignItems: "center",
        px: 3,
        py: 7,
        textAlign: "center",
      }}
    >
      <Typography variant="h6">{title}</Typography>
      <Typography variant="body2" color="text.secondary" sx={{ maxWidth: 440 }}>
        {body}
      </Typography>
      {action}
    </Stack>
  );
}
function Pager({
  page,
  setPage,
  total,
  perPage,
  setPerPage,
}: {
  page: number;
  setPage: (n: number) => void;
  total: number;
  perPage: number;
  setPerPage: (n: number) => void;
}) {
  return (
    <TablePagination
      component="div"
      count={total}
      page={page - 1}
      rowsPerPage={perPage}
      onPageChange={(_, p) => setPage(p + 1)}
      rowsPerPageOptions={[10, 20, 50]}
      onRowsPerPageChange={(e) => {
        setPerPage(Number(e.target.value));
        setPage(1);
      }}
      sx={{
        borderTop: 1,
        borderColor: "divider",
        ".MuiTablePagination-toolbar": { flexWrap: "wrap", justifyContent: "flex-end", px: { xs: 1, sm: 2 } },
        ".MuiIconButton-root": { width: 44, height: 44 },
        ".MuiTablePagination-selectLabel": { display: { xs: "none", sm: "block" } },
      }}
    />
  );
}
function Search({ value, onChange, label }: { value: string; onChange: (v: string) => void; label: string }) {
  return (
    <TextField
      label={label}
      value={value}
      onChange={(e) => onChange(e.target.value)}
      fullWidth
      slotProps={{
        input: {
          startAdornment: (
            <InputAdornment position="start">
              <SearchRoundedIcon fontSize="small" />
            </InputAdornment>
          ),
        },
        htmlInput: { maxLength: 255 },
      }}
    />
  );
}
function CompanyDetails({
  id,
  onClose,
  onUsers,
  onEdit,
  revision,
}: {
  id: string | null;
  onClose: () => void;
  onUsers: (c: PlatformCompany) => void;
  onEdit: (c: PlatformCompany) => void;
  revision: number;
}) {
  const load = useCallback(() => apiClient.platformCompany(id!), [id, revision]);
  const result = useLoad<PlatformCompanyDetail>(load, !!id);
  const c = result.data?.company;
  return (
    <Drawer
      anchor="right"
      open={!!id}
      onClose={onClose}
      slotProps={{ paper: { sx: { width: { xs: "100%", sm: 560 }, maxWidth: "100%" } } }}
    >
      <Box sx={{ p: 3 }}>
        <Stack
          direction="row"
          spacing={2}
          sx={{
            alignItems: "center",
            justifyContent: "space-between",
          }}
        >
          <Typography variant="h6" component="h2">
            Company details
          </Typography>
          <IconButton onClick={onClose} aria-label="Close company details" sx={{ width: 44, height: 44 }}>
            <CloseRoundedIcon />
          </IconButton>
        </Stack>
        <Divider sx={{ my: 2 }} />
        {result.loading ? (
          <LoadingRows />
        ) : result.error ? (
          <Failure message={result.error} retry={result.retry} />
        ) : (
          c && (
            <Stack spacing={3}>
              <Box>
                <Typography variant="h5" sx={{ overflowWrap: "anywhere", mb: 1 }}>
                  {c.name}
                </Typography>
                <Status value={c.status} />
                <Typography variant="body2" color="text.secondary" sx={{ mt: 1 }}>
                  Created {date(c.createdAt)}
                </Typography>
              </Box>
              {c.activeAdminsCount === 0 && (
                <Alert severity="warning">
                  This company has no active company admin. Add an admin to complete access setup.
                </Alert>
              )}
              <Stack
                direction="row"
                sx={{
                  flexWrap: "wrap",
                  gap: 1,
                }}
              >
                <Button variant="contained" startIcon={<PeopleOutlineIcon />} onClick={() => onUsers(c)}>
                  Manage users
                </Button>
                <Button variant="outlined" startIcon={<EditOutlinedIcon />} onClick={() => onEdit(c)}>
                  Edit company
                </Button>
              </Stack>
              <Box>
                <Typography
                  variant="subtitle1"
                  sx={{
                    fontWeight: 700,
                    mb: 2,
                  }}
                >
                  Workspace usage
                </Typography>
                <Box className={s.detailMetrics}>
                  {[
                    ["Users", c.usersCount],
                    ["Contacts", c.contactsCount],
                    ["Conversations", c.conversationsCount],
                    ["Campaigns", c.campaignsCount],
                    ["Branches", c.branchesCount],
                    ["Orders", c.ordersCount],
                  ].map(([label, value]) => (
                    <Box key={label} className={s.detailMetric}>
                      <Typography variant="h5">{number(value as number)}</Typography>
                      <Typography variant="body2" color="text.secondary">
                        {label}
                      </Typography>
                    </Box>
                  ))}
                </Box>
              </Box>
              <Box>
                <Typography
                  variant="subtitle1"
                  sx={{
                    fontWeight: 700,
                    mb: 2,
                  }}
                >
                  Channel connections
                </Typography>
                <Stack spacing={0} divider={<Divider />}>
                  {result.data?.connections.length ? (
                    result.data.connections.map((connection) => (
                      <Stack
                        key={connection.id}
                        direction="row"
                        sx={{
                          justifyContent: "space-between",
                          alignItems: "center",
                          gap: 2,
                          py: 2,
                        }}
                      >
                        <Box sx={{ minWidth: 0 }}>
                          <Typography
                            variant="body2"
                            sx={{
                              fontWeight: 600,
                            }}
                          >
                            {connection.label}
                          </Typography>
                          <Typography variant="caption" color="text.secondary" sx={{ overflowWrap: "anywhere" }}>
                            {connection.accountName}
                          </Typography>
                        </Box>
                        <Status value={connection.status} />
                      </Stack>
                    ))
                  ) : (
                    <Typography variant="body2" color="text.secondary">
                      No connections configured.
                    </Typography>
                  )}
                </Stack>
              </Box>
              <Typography variant="caption" color="text.secondary">
                Only connection status is shown here. Credentials and customer conversations stay in the company
                workspace.
              </Typography>
            </Stack>
          )
        )}
      </Box>
    </Drawer>
  );
}
function CompaniesView({
  revision,
  onCreate,
  onEdit,
  onAction,
  onDetails,
  onUsers,
}: {
  revision: number;
  onCreate: () => void;
  onEdit: (c: PlatformCompany) => void;
  onAction: (a: ConfirmAction) => void;
  onDetails: (id: string) => void;
  onUsers: (c: PlatformCompany) => void;
}) {
  const [params, setParams] = useSearchParams();
  const [search, setSearch] = useState("");
  const query = useDebounced(search);
  const [status, setStatus] = useState("");
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(20);
  const needsAdmin = params.get("needsAdmin") === "1";
  useEffect(() => setPage(1), [query, status, needsAdmin]);
  const load = useCallback(
    () =>
      apiClient.platformCompanies({
        search: query,
        status: status || undefined,
        page,
        perPage,
        needsAdmin: needsAdmin || undefined,
      }),
    [query, status, page, perPage, revision, needsAdmin],
  );
  const result = useLoad(load);
  useEffect(() => {
    if (result.data && page > result.data.meta.lastPage) setPage(Math.max(1, result.data.meta.lastPage));
  }, [result.data, page]);
  const rowActions = (c: PlatformCompany) => (
    <Stack direction="row" spacing={0.25} className={s.rowActions}>
      <Tooltip title="Edit company">
        <IconButton aria-label={`Edit ${c.name}`} onClick={() => onEdit(c)}>
          <EditOutlinedIcon fontSize="small" />
        </IconButton>
      </Tooltip>
      <Tooltip title="Manage users">
        <IconButton aria-label={`Manage users for ${c.name}`} onClick={() => onUsers(c)}>
          <PeopleOutlineIcon fontSize="small" />
        </IconButton>
      </Tooltip>
      <Tooltip title={c.status === "active" ? "Suspend company" : "Reactivate company"}>
        <IconButton
          aria-label={`${c.status === "active" ? "Suspend" : "Reactivate"} ${c.name}`}
          onClick={() => onAction({ kind: "company-status", company: c })}
        >
          {c.status === "active" ? (
            <PauseCircleOutlineRoundedIcon fontSize="small" />
          ) : (
            <PlayCircleOutlineRoundedIcon fontSize="small" />
          )}
        </IconButton>
      </Tooltip>
      <Tooltip title={c.status === "active" ? "Suspend the company before deleting" : "Delete company"}>
        <span>
          <IconButton
            aria-label={`Delete ${c.name}`}
            color="error"
            disabled={c.status !== "suspended"}
            onClick={() => onAction({ kind: "company-delete", company: c })}
          >
            <DeleteOutlineRoundedIcon fontSize="small" />
          </IconButton>
        </span>
      </Tooltip>
    </Stack>
  );
  return (
    <>
      <div className={s.pageHeading}>
        <div>
          <Typography component="h1" variant="h4">
            Companies
          </Typography>
          <Typography color="text.secondary" variant="body2">
            Create workspaces, manage access and review company usage.
          </Typography>
        </div>
        <Button variant="contained" startIcon={<AddRoundedIcon />} onClick={onCreate}>
          Create company
        </Button>
      </div>
      <Paper variant="outlined" className={s.records}>
        <div className={s.filters}>
          <Search label="Search companies" value={search} onChange={setSearch} />
          <TextField
            label="Status"
            select
            value={status}
            onChange={(e) => setStatus(e.target.value)}
            sx={{ minWidth: { xs: "100%", sm: 190 } }}
          >
            <MenuItem value="">All statuses</MenuItem>
            <MenuItem value="active">Active</MenuItem>
            <MenuItem value="suspended">Suspended</MenuItem>
          </TextField>
        </div>
        {needsAdmin && (
          <Alert
            severity="warning"
            sx={{ mx: 3, mb: 2 }}
            action={
              <Button color="inherit" onClick={() => setParams({ view: "companies" })}>
                Clear filter
              </Button>
            }
          >
            Showing active companies without an active company admin.
          </Alert>
        )}
        {result.loading ? (
          <LoadingRows />
        ) : result.error ? (
          <Box sx={{ p: 3 }}>
            <Failure message={result.error} retry={result.retry} />
          </Box>
        ) : !result.data?.data.length ? (
          <Empty
            title={search || status || needsAdmin ? "No matching companies" : "Create your first company"}
            body={
              search || status || needsAdmin
                ? "Try a different search or remove a filter."
                : "Each company has its own users, channels and customer data."
            }
            action={
              !search && !status && !needsAdmin ? (
                <Button onClick={onCreate} variant="outlined">
                  Create company
                </Button>
              ) : undefined
            }
          />
        ) : (
          <>
            <TableContainer className={s.desktopRecords}>
              <Table size="small" aria-label="Companies">
                <TableHead>
                  <TableRow>
                    {["Company", "Users", "Active admins", "Channels", "Status", "Created", "Actions"].map((t) => (
                      <TableCell key={t} align={["Users", "Active admins", "Channels"].includes(t) ? "right" : "left"}>
                        {t}
                      </TableCell>
                    ))}
                  </TableRow>
                </TableHead>
                <TableBody>
                  {result.data.data.map((c) => (
                    <TableRow key={c.id} hover>
                      <TableCell>
                        <Button className={s.recordName} color="inherit" onClick={() => onDetails(c.id)}>
                          {c.name}
                        </Button>
                        {c.activeAdminsCount === 0 && (
                          <Typography component="div" variant="caption" color="warning.dark">
                            No active admin
                          </Typography>
                        )}
                      </TableCell>
                      <TableCell align="right">{number(c.usersCount)}</TableCell>
                      <TableCell align="right">{number(c.activeAdminsCount)}</TableCell>
                      <TableCell align="right">{number(c.connectedChannelsCount)}</TableCell>
                      <TableCell>
                        <Status value={c.status} />
                      </TableCell>
                      <TableCell sx={{ whiteSpace: "nowrap" }}>{date(c.createdAt)}</TableCell>
                      <TableCell>{rowActions(c)}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
            <div className={s.mobileRecords}>
              {result.data.data.map((c) => (
                <article className={s.mobileRecord} key={c.id}>
                  <Stack
                    direction="row"
                    sx={{
                      gap: 2,
                      justifyContent: "space-between",
                      alignItems: "center",
                    }}
                  >
                    <Button className={s.recordName} color="inherit" onClick={() => onDetails(c.id)}>
                      {c.name}
                    </Button>
                    <Status value={c.status} />
                  </Stack>
                  <Typography variant="body2" color="text.secondary" sx={{ mt: 1 }}>
                    {number(c.usersCount)} users · {number(c.activeAdminsCount)} active admins ·{" "}
                    {number(c.connectedChannelsCount)} connected channels
                  </Typography>
                  {c.activeAdminsCount === 0 && (
                    <Typography variant="caption" color="warning.dark">
                      No active admin
                    </Typography>
                  )}
                  <Stack
                    direction="row"
                    sx={{
                      alignItems: "center",
                      justifyContent: "space-between",
                      mt: 1,
                    }}
                  >
                    <Typography variant="caption" color="text.secondary">
                      {date(c.createdAt)}
                    </Typography>
                    {rowActions(c)}
                  </Stack>
                </article>
              ))}
            </div>
            <Pager
              page={page}
              perPage={perPage}
              total={result.data.meta.total}
              setPage={setPage}
              setPerPage={setPerPage}
            />
          </>
        )}
      </Paper>
    </>
  );
}
function UsersView({
  revision,
  currentId,
  onCreate,
  onEdit,
  onAction,
  onAccount,
}: {
  revision: number;
  currentId: string;
  onCreate: (c: CompanyOption | null) => void;
  onEdit: (u: PlatformUser) => void;
  onAction: (a: ConfirmAction) => void;
  onAccount: () => void;
}) {
  const [params, setParams] = useSearchParams();
  const companyId = params.get("company") ?? "";
  const [search, setSearch] = useState("");
  const query = useDebounced(search);
  const [role, setRole] = useState("");
  const [status, setStatus] = useState("");
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(20);
  const companyLoad = useCallback(() => apiClient.platformCompany(companyId), [companyId, revision]);
  const companyResult = useLoad(companyLoad, !!companyId);
  const company = companyId && companyResult.data ? companyResult.data.company : null;
  useEffect(() => setPage(1), [query, role, status, companyId]);
  const load = useCallback(
    () =>
      apiClient.platformUsers({
        search: query,
        role: role || undefined,
        status: status || undefined,
        companyId: companyId || undefined,
        page,
        perPage,
      }),
    [query, role, status, companyId, page, perPage, revision],
  );
  const result = useLoad(load);
  useEffect(() => {
    if (result.data && page > result.data.meta.lastPage) setPage(Math.max(1, result.data.meta.lastPage));
  }, [result.data, page]);
  const rowActions = (u: PlatformUser) =>
    u.id === currentId ? (
      <Button color="inherit" onClick={onAccount} sx={{ minHeight: 44 }}>
        Your account
      </Button>
    ) : (
      <Stack direction="row" spacing={0.25} className={s.rowActions}>
        <Tooltip title="Edit user">
          <IconButton aria-label={`Edit ${u.name}`} onClick={() => onEdit(u)}>
            <EditOutlinedIcon fontSize="small" />
          </IconButton>
        </Tooltip>
        <Tooltip title="Reset password">
          <IconButton
            aria-label={`Reset password for ${u.name}`}
            onClick={() => onAction({ kind: "password", user: u })}
          >
            <LockResetRoundedIcon fontSize="small" />
          </IconButton>
        </Tooltip>
        <Tooltip title="Delete user">
          <IconButton
            aria-label={`Delete ${u.name}`}
            color="error"
            onClick={() => onAction({ kind: "user-delete", user: u })}
          >
            <DeleteOutlineRoundedIcon fontSize="small" />
          </IconButton>
        </Tooltip>
      </Stack>
    );
  return (
    <>
      <div className={s.pageHeading}>
        <div>
          <Typography component="h1" variant="h4">
            Users & roles
          </Typography>
          <Typography color="text.secondary" variant="body2">
            Manage company teams and platform administrators.
          </Typography>
        </div>
        <Button variant="contained" startIcon={<AddRoundedIcon />} onClick={() => onCreate(company)}>
          Create user
        </Button>
      </div>
      <Paper variant="outlined" className={s.records}>
        <div className={s.userFilters}>
          <Search label="Search name or email" value={search} onChange={setSearch} />
          <CompanyPicker
            label="Filter by company"
            value={company}
            onChange={(c) => setParams(c ? { view: "users", company: c.id } : { view: "users" })}
          />
          <TextField label="Role" select value={role} onChange={(e) => setRole(e.target.value)}>
            <MenuItem value="">All roles</MenuItem>
            {Object.entries(roleLabels).map(([value, label]) => (
              <MenuItem value={value} key={value}>
                {label}
              </MenuItem>
            ))}
          </TextField>
          <TextField label="Access status" select value={status} onChange={(e) => setStatus(e.target.value)}>
            <MenuItem value="">All statuses</MenuItem>
            {["active", "invited", "suspended"].map((v) => (
              <MenuItem key={v} value={v}>
                {statusLabels[v]}
              </MenuItem>
            ))}
          </TextField>
        </div>
        {companyId && companyResult.error && (
          <Box sx={{ px: 3, pb: 2 }}>
            <Failure message={companyResult.error} retry={companyResult.retry} />
          </Box>
        )}
        {result.loading ? (
          <LoadingRows />
        ) : result.error ? (
          <Box sx={{ p: 3 }}>
            <Failure message={result.error} retry={result.retry} />
          </Box>
        ) : !result.data?.data.length ? (
          <Empty
            title="No matching users"
            body={
              companyId
                ? "Create an admin or team member for this company, or clear your filters."
                : "Try a different search or remove a filter."
            }
            action={
              companyId ? (
                <Button variant="outlined" onClick={() => onCreate(company)}>
                  Create user
                </Button>
              ) : undefined
            }
          />
        ) : (
          <>
            <TableContainer className={s.desktopRecords}>
              <Table size="small" aria-label="Platform users">
                <TableHead>
                  <TableRow>
                    {["User", "Company", "Role", "Access", "Created", "Actions"].map((t) => (
                      <TableCell key={t}>{t}</TableCell>
                    ))}
                  </TableRow>
                </TableHead>
                <TableBody>
                  {result.data.data.map((u) => (
                    <TableRow key={u.id} hover>
                      <TableCell>
                        <Typography
                          variant="body2"
                          sx={{
                            fontWeight: 600,
                          }}
                        >
                          {u.name}
                          {u.id === currentId ? " (you)" : ""}
                        </Typography>
                        <Typography variant="caption" color="text.secondary" sx={{ overflowWrap: "anywhere" }}>
                          {u.email}
                        </Typography>
                      </TableCell>
                      <TableCell>
                        <Typography variant="body2">{u.companyName ?? "Platform account"}</Typography>
                        {u.companyStatus === "suspended" && (
                          <Typography component="div" variant="caption" color="error.main">
                            Company suspended
                          </Typography>
                        )}
                        {u.staffBranchName && (
                          <Typography component="div" variant="caption" color="text.secondary">
                            {u.staffBranchName}
                          </Typography>
                        )}
                      </TableCell>
                      <TableCell sx={{ whiteSpace: "nowrap" }}>{roleLabels[u.role] ?? "Unassigned"}</TableCell>
                      <TableCell>
                        <Status value={u.status} />
                      </TableCell>
                      <TableCell sx={{ whiteSpace: "nowrap" }}>{date(u.createdAt)}</TableCell>
                      <TableCell>{rowActions(u)}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
            <div className={s.mobileRecords}>
              {result.data.data.map((u) => (
                <article className={s.mobileRecord} key={u.id}>
                  <Stack
                    direction="row"
                    sx={{
                      gap: 2,
                      alignItems: "center",
                      justifyContent: "space-between",
                    }}
                  >
                    <Typography
                      variant="body1"
                      sx={{
                        fontWeight: 700,
                        overflowWrap: "anywhere",
                      }}
                    >
                      {u.name}
                      {u.id === currentId ? " (you)" : ""}
                    </Typography>
                    <Status value={u.status} />
                  </Stack>
                  <Typography variant="body2" color="text.secondary" sx={{ overflowWrap: "anywhere", mt: 0.5 }}>
                    {u.email}
                  </Typography>
                  <Typography variant="body2" sx={{ mt: 1 }}>
                    {roleLabels[u.role] ?? "Unassigned"} · {u.companyName ?? "Platform account"}
                  </Typography>
                  {u.staffBranchName && (
                    <Typography variant="caption" color="text.secondary">
                      {u.staffBranchName}
                    </Typography>
                  )}
                  {u.companyStatus === "suspended" && (
                    <Typography component="div" variant="caption" color="error.main">
                      Company suspended
                    </Typography>
                  )}
                  <Stack
                    direction="row"
                    sx={{
                      alignItems: "center",
                      justifyContent: "space-between",
                      mt: 1,
                    }}
                  >
                    <Typography variant="caption" color="text.secondary">
                      {date(u.createdAt)}
                    </Typography>
                    {rowActions(u)}
                  </Stack>
                </article>
              ))}
            </div>
            <Pager
              page={page}
              perPage={perPage}
              total={result.data.meta.total}
              setPage={setPage}
              setPerPage={setPerPage}
            />
          </>
        )}
      </Paper>
    </>
  );
}
const actions = [
  "company.created",
  "company.updated",
  "company.suspended",
  "company.reactivated",
  "company.deleted",
  "user.created",
  "user.updated",
  "user.deleted",
  "user.password_reset",
  "profile.updated",
];
const actionLabel = (value: string) =>
  value
    .split(".")
    .map((v) => v.replaceAll("_", " "))
    .join(" · ");
function ActivityRows({ entries }: { entries: PlatformAuditEntry[] }) {
  return (
    <Stack divider={<Divider />}>
      {entries.map((entry) => (
        <Box key={entry.id} sx={{ py: 2.5, px: 3 }}>
          <Stack
            direction="row"
            sx={{
              flexWrap: "wrap",
              justifyContent: "space-between",
              gap: 1,
            }}
          >
            <Typography
              variant="body2"
              sx={{
                fontWeight: 600,
              }}
            >
              {entry.description}
            </Typography>
            <Typography variant="caption" color="text.secondary" sx={{ whiteSpace: "nowrap" }}>
              {timestamp(entry.createdAt)}
            </Typography>
          </Stack>
          <Typography
            variant="caption"
            color="text.secondary"
            sx={{ display: "block", mt: 0.75, overflowWrap: "anywhere" }}
          >
            {entry.actorName} · {entry.companyName ?? "Platform"} · {actionLabel(entry.action)}
          </Typography>
        </Box>
      ))}
    </Stack>
  );
}
function ActivityView({ revision }: { revision: number }) {
  const [search, setSearch] = useState("");
  const query = useDebounced(search);
  const [action, setAction] = useState("");
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(20);
  useEffect(() => setPage(1), [query, action, from, to]);
  const invalidDates = !!from && !!to && to < from;
  const load = useCallback(
    () =>
      apiClient.platformActivity({
        search: query,
        action: action || undefined,
        from: from || undefined,
        to: to || undefined,
        page,
        perPage,
      }),
    [query, action, from, to, page, perPage, revision],
  );
  const result = useLoad(load, !invalidDates);
  return (
    <>
      <div className={s.pageHeading}>
        <div>
          <Typography component="h1" variant="h4">
            Platform activity
          </Typography>
          <Typography variant="body2" color="text.secondary">
            A read-only history of company, user and account changes.
          </Typography>
        </div>
        <Chip icon={<ShieldOutlinedIcon />} label="Read-only audit trail" variant="outlined" />
      </div>
      <Paper variant="outlined" className={s.records}>
        <div className={s.activityFilters}>
          <Search label="Search activity" value={search} onChange={setSearch} />
          <TextField select label="Action" value={action} onChange={(e) => setAction(e.target.value)}>
            <MenuItem value="">All actions</MenuItem>
            {actions.map((a) => (
              <MenuItem value={a} key={a}>
                {actionLabel(a)}
              </MenuItem>
            ))}
          </TextField>
          <TextField
            label="From"
            type="date"
            value={from}
            onChange={(e) => setFrom(e.target.value)}
            slotProps={{ inputLabel: { shrink: true } }}
          />
          <TextField
            label="To"
            type="date"
            value={to}
            onChange={(e) => setTo(e.target.value)}
            error={invalidDates}
            helperText={invalidDates ? "Choose the same or a later date." : undefined}
            slotProps={{ inputLabel: { shrink: true } }}
          />
        </div>
        {invalidDates ? (
          <Alert severity="warning" sx={{ m: 3 }}>
            The end date must be on or after the start date.
          </Alert>
        ) : result.loading ? (
          <LoadingRows />
        ) : result.error ? (
          <Box sx={{ p: 3 }}>
            <Failure message={result.error} retry={result.retry} />
          </Box>
        ) : !result.data?.data.length ? (
          <Empty
            title="No activity found"
            body={
              search || action || from || to
                ? "Try another search or date range."
                : "Company, user and account changes made through this console will appear here. Earlier changes are not backfilled."
            }
          />
        ) : (
          <>
            <ActivityRows entries={result.data.data} />
            <Pager
              page={page}
              perPage={perPage}
              total={result.data.meta.total}
              setPage={setPage}
              setPerPage={setPerPage}
            />
          </>
        )}
      </Paper>
      <Typography variant="caption" color="text.secondary" sx={{ display: "block", mt: 2 }}>
        Times are shown in your local timezone. Deleted company names remain in the audit trail.
      </Typography>
    </>
  );
}
function Overview({
  summary,
  loading,
  error,
  retry,
  revision,
  onNavigate,
  onCreate,
  onDetails,
}: {
  summary: PlatformSummary | null;
  loading: boolean;
  error: string | null;
  retry: () => void;
  revision: number;
  onNavigate: (view: View, extra?: Record<string, string>) => void;
  onCreate: () => void;
  onDetails: (id: string) => void;
}) {
  const companiesLoad = useCallback(() => apiClient.platformCompanies({ perPage: 5 }), [revision]);
  const companies = useLoad(companiesLoad);
  const activityLoad = useCallback(() => apiClient.platformActivity({ perPage: 5 }), [revision]);
  const activity = useLoad(activityLoad);
  return (
    <>
      <div className={s.pageHeading}>
        <div>
          <Typography component="h1" variant="h4">
            Platform overview
          </Typography>
          <Typography variant="body2" color="text.secondary">
            A clear view of your companies, users and connected workspaces.
          </Typography>
        </div>
        <Button variant="contained" startIcon={<AddRoundedIcon />} onClick={onCreate}>
          Create company
        </Button>
      </div>
      {error ? (
        <Failure message={error} retry={retry} />
      ) : loading ? (
        <div className={s.summaryGrid}>
          {[0, 1, 2, 3].map((i) => (
            <Skeleton key={i} variant="rounded" height={134} />
          ))}
        </div>
      ) : (
        summary && (
          <>
            <Paper variant="outlined" className={s.summaryGrid}>
              <Box className={s.primaryMetric}>
                <Typography component="div" className={s.metricValue}>
                  {number(summary.companies)}
                </Typography>
                <Typography
                  variant="body2"
                  sx={{
                    fontWeight: 600,
                  }}
                >
                  Total companies
                </Typography>
                <Typography variant="caption" color="text.secondary">
                  {number(summary.activeCompanies)} active · {number(summary.suspendedCompanies)} suspended
                </Typography>
              </Box>
              {[
                [summary.users, "Platform users", `${number(summary.activeUsers)} with active access`],
                [
                  summary.connectedChannels,
                  "Connected channels",
                  `${number(summary.disconnectedChannels)} not connected`,
                ],
                [
                  summary.companiesWithoutAdmin,
                  "Companies needing an admin",
                  "Active workspaces without an active admin",
                ],
              ].map(([value, label, detail]) => (
                <Box key={label} className={s.metric}>
                  <Typography component="div" className={s.secondaryValue}>
                    {number(value as number)}
                  </Typography>
                  <Typography
                    variant="body2"
                    sx={{
                      fontWeight: 600,
                    }}
                  >
                    {label}
                  </Typography>
                  <Typography variant="caption" color="text.secondary">
                    {detail}
                  </Typography>
                </Box>
              ))}
            </Paper>
            {summary.companiesWithoutAdmin > 0 && (
              <Alert
                severity="warning"
                sx={{ mt: 3 }}
                action={
                  <Button color="inherit" onClick={() => onNavigate("companies", { needsAdmin: "1" })}>
                    Review companies
                  </Button>
                }
              >
                {number(summary.companiesWithoutAdmin)} active{" "}
                {summary.companiesWithoutAdmin === 1 ? "company needs" : "companies need"} a company admin before the
                team can get started.
              </Alert>
            )}
          </>
        )
      )}
      <div className={s.overviewGrid}>
        <Paper variant="outlined">
          <div className={s.panelHeading}>
            <Typography variant="h6" component="h2">
              Recent companies
            </Typography>
            <Button endIcon={<ArrowForwardRoundedIcon />} onClick={() => onNavigate("companies")}>
              View all
            </Button>
          </div>
          <Divider />
          {companies.loading ? (
            <LoadingRows />
          ) : companies.error ? (
            <Box sx={{ p: 3 }}>
              <Failure message={companies.error} retry={companies.retry} />
            </Box>
          ) : !companies.data?.data.length ? (
            <Empty
              title="Your first company starts here"
              body="Create a workspace, then add its first admin."
              action={
                <Button variant="outlined" onClick={onCreate}>
                  Create company
                </Button>
              }
            />
          ) : (
            <List disablePadding>
              {companies.data.data.map((c) => (
                <ListItemButton
                  key={c.id}
                  onClick={() => onDetails(c.id)}
                  sx={{ gap: 2, px: 3, py: 2.5, borderBottom: 1, borderColor: "divider" }}
                >
                  <Avatar variant="rounded" sx={{ bgcolor: "#E7F4EE", color: "primary.dark", width: 40, height: 40 }}>
                    {c.name.charAt(0).toUpperCase()}
                  </Avatar>
                  <ListItemText
                    primary={c.name}
                    secondary={`${number(c.usersCount)} users · ${number(c.connectedChannelsCount)} connected channels`}
                    slotProps={{
                      primary: { variant: "body2", sx: { fontWeight: 600, overflowWrap: "anywhere" } },
                      secondary: { variant: "caption" },
                    }}
                  />
                  <Status value={c.status} />
                </ListItemButton>
              ))}
            </List>
          )}
        </Paper>
        <Paper variant="outlined">
          <div className={s.panelHeading}>
            <Typography variant="h6" component="h2">
              Workspace usage
            </Typography>
            <Chip label="All companies" variant="outlined" size="small" />
          </div>
          <Divider />
          {loading ? (
            <LoadingRows />
          ) : error ? (
            <Box sx={{ p: 3 }}>
              <Failure message={error} retry={retry} />
            </Box>
          ) : (
            summary && (
              <Box sx={{ px: 3, py: 1 }}>
                {[
                  ["Contacts", summary.contacts],
                  ["Conversations", summary.conversations],
                  ["Campaigns", summary.campaigns],
                  ["Branches", summary.branches],
                  ["Orders", summary.orders],
                ].map(([label, value]) => (
                  <Stack
                    key={label}
                    direction="row"
                    sx={{
                      justifyContent: "space-between",
                      alignItems: "center",
                      py: 2,
                      borderBottom: 1,
                      borderColor: "divider",
                    }}
                  >
                    <Typography variant="body2" color="text.secondary">
                      {label}
                    </Typography>
                    <Typography
                      variant="body1"
                      sx={{
                        fontWeight: 700,
                      }}
                    >
                      {number(value as number)}
                    </Typography>
                  </Stack>
                ))}
                <Typography variant="caption" color="text.secondary" sx={{ display: "block", py: 2 }}>
                  Totals across all companies. Refresh to retrieve the latest counts.
                </Typography>
              </Box>
            )
          )}
        </Paper>
      </div>
      <Paper variant="outlined" sx={{ mt: 3 }}>
        <div className={s.panelHeading}>
          <Typography component="h2" variant="h6">
            Recent activity
          </Typography>
          <Button endIcon={<ArrowForwardRoundedIcon />} onClick={() => onNavigate("activity")}>
            View all
          </Button>
        </div>
        <Divider />
        {activity.loading ? (
          <LoadingRows />
        ) : activity.error ? (
          <Box sx={{ p: 3 }}>
            <Failure message={activity.error} retry={activity.retry} />
          </Box>
        ) : !activity.data?.data.length ? (
          <Empty
            title="No platform changes recorded yet"
            body="Actions performed in this console will appear here. Earlier activity is not backfilled."
          />
        ) : (
          <ActivityRows entries={activity.data.data} />
        )}
      </Paper>
    </>
  );
}
function AccountView({ user, onSaved }: { user: TeamMember; onSaved: (u: TeamMember) => void }) {
  const [name, setName] = useState(user.name);
  const [email, setEmail] = useState(user.email);
  const [currentPassword, setCurrentPassword] = useState("");
  const [password, setPassword] = useState("");
  const [repeat, setRepeat] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  async function save(e: FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      const updated = await apiClient.platformProfile({
        name: name.trim(),
        email: email.trim(),
        currentPassword,
        ...(password ? { password, password_confirmation: repeat } : {}),
      });
      onSaved(updated);
      setCurrentPassword("");
      setPassword("");
      setRepeat("");
    } catch (e) {
      setError(errorText(e));
    } finally {
      setBusy(false);
    }
  }
  return (
    <>
      <div className={s.pageHeading}>
        <div>
          <Typography component="h1" variant="h4">
            Account settings
          </Typography>
          <Typography variant="body2" color="text.secondary">
          Manage your profile and password.
          </Typography>
        </div>
      </div>
      <Paper component="form" variant="outlined" onSubmit={save} sx={{ p: { xs: 2.5, sm: 4 }, maxWidth: 760 }}>
        <Stack spacing={3}>
          {error && <Alert severity="error">{error}</Alert>}
          <Stack
            direction="row"
            spacing={2}
            sx={{
              alignItems: "center",
            }}
          >
            <Avatar sx={{ width: 52, height: 52, bgcolor: "primary.dark" }}>{user.name.charAt(0).toUpperCase()}</Avatar>
            <Box>
              <Typography
                variant="subtitle1"
                sx={{
                  fontWeight: 700,
                }}
              >
                {user.name}
              </Typography>
              <Chip label="Superadmin" size="small" variant="outlined" />
            </Box>
          </Stack>
          <Stack direction={{ xs: "column", sm: "row" }} spacing={2}>
            <TextField
              label="Full name"
              value={name}
              onChange={(e) => setName(e.target.value)}
              required
              fullWidth
              slotProps={{ htmlInput: { maxLength: 255 } }}
            />
            <TextField
              label="Email address"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              type="email"
              fullWidth
              slotProps={{ htmlInput: { maxLength: 255 } }}
            />
          </Stack>
          <Divider />
          <Typography component="h2" variant="h6">
            Security
          </Typography>
          <TextField
            label="Your current password"
            type="password"
            value={currentPassword}
            onChange={(e) => setCurrentPassword(e.target.value)}
            required
            autoComplete="current-password"
            helperText="Required to confirm any account changes."
          />
          <Stack direction={{ xs: "column", sm: "row" }} spacing={2}>
            <TextField
              label="New password (optional)"
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              fullWidth
              helperText="Leave blank to keep your current password."
              autoComplete="new-password"
              slotProps={{ htmlInput: { minLength: 8, maxLength: 255 } }}
            />
            <TextField
              label="Confirm new password"
              type="password"
              value={repeat}
              onChange={(e) => setRepeat(e.target.value)}
              fullWidth
              autoComplete="new-password"
              error={!!repeat && repeat !== password}
              helperText={repeat && repeat !== password ? "Passwords must match." : undefined}
            />
          </Stack>
          <Typography variant="body2" color="text.secondary">
            Changing your password signs out other devices. You stay signed in here. You cannot change your own role or
            disable your account.
          </Typography>
          <Box>
            <Button
              variant="contained"
              type="submit"
              disabled={
                busy ||
                !name.trim() ||
                !email.trim() ||
                !currentPassword ||
                (!!password && (password.length < 8 || password !== repeat))
              }
            >
              {busy ? "Saving…" : "Save account changes"}
            </Button>
          </Box>
        </Stack>
      </Paper>
    </>
  );
}
export default function Superadmin() {
  const navigate = useNavigate();
  const [params, setParams] = useSearchParams();
  const requested = params.get("view") ?? "overview";
  const view: View = navItems.some((v) => v.id === requested) ? (requested as View) : "overview";
  const { user, status, setUser, setStatus } = useAuth();
  const [authError, setAuthError] = useState<string | null>(null);
  const [authAttempt, setAuthAttempt] = useState(0);
  const [loggingOut, setLoggingOut] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);
  const [revision, setRevision] = useState(0);
  const [companyOpen, setCompanyOpen] = useState(false);
  const [editingCompany, setEditingCompany] = useState<PlatformCompany | null>(null);
  const [userOpen, setUserOpen] = useState(false);
  const [editingUser, setEditingUser] = useState<PlatformUser | null>(null);
  const [defaultCompany, setDefaultCompany] = useState<CompanyOption | null>(null);
  const [action, setAction] = useState<ConfirmAction | null>(null);
  const [detailId, setDetailId] = useState<string | null>(null);
  const [notice, setNotice] = useState("");
  useEffect(() => {
    if (status !== "loading") return;
    let cancelled = false;
    setAuthError(null);
    apiClient
      .me()
      .then((me) => {
        if (!cancelled) {
          setUser(me);
          setStatus("authenticated");
        }
      })
      .catch((e) => {
        if (cancelled) return;
        if (e instanceof ApiError && (e.status === 401 || e.status === 403)) setStatus("unauthenticated");
        else setAuthError(errorText(e));
      });
    return () => {
      cancelled = true;
    };
  }, [status, setUser, setStatus, authAttempt]);
  useEffect(() => {
    if (status === "unauthenticated") navigate("/login", { replace: true });
    else if (status === "authenticated" && user?.role !== "superadmin") navigate("/dashboard", { replace: true });
  }, [status, user, navigate]);
  const summaryLoad = useCallback(() => apiClient.platformSummary(), [revision]);
  const summary = useLoad(summaryLoad, status === "authenticated" && user?.role === "superadmin");
  function changed() {
    setRevision((r) => r + 1);
    setNotice("Changes saved successfully.");
  }
  function switchView(next: View, extra: Record<string, string> = {}) {
    setParams({ view: next, ...extra });
    setMobileOpen(false);
  }
  function createCompany() {
    setEditingCompany(null);
    setCompanyOpen(true);
  }
  function editCompany(c: PlatformCompany) {
    setEditingCompany(c);
    setDetailId(null);
    setCompanyOpen(true);
  }
  function createUser(c: CompanyOption | null) {
    setDefaultCompany(c);
    setEditingUser(null);
    setUserOpen(true);
  }
  function companyUsers(c: PlatformCompany) {
    setDetailId(null);
    switchView("users", { company: c.id });
  }
  async function logout() {
    setLoggingOut(true);
    try {
      await apiClient.logout();
    } catch {
      /* Local access must still be cleared if the server is unreachable. */
    } finally {
      setUser(null);
      setStatus("unauthenticated");
      setLoggingOut(false);
      navigate("/login", { replace: true });
    }
  }
  if (status !== "authenticated" || !user || user.role !== "superadmin")
    return (
      <Box sx={{ minHeight: "100vh", display: "grid", placeItems: "center", p: 3 }}>
        {authError ? (
          <Failure message={authError} retry={() => setAuthAttempt((n) => n + 1)} />
        ) : (
          <CircularProgress aria-label="Checking superadmin access" />
        )}
      </Box>
    );
  const sidebar = (
    <div className={s.sidebar}>
      <div className={s.brand}>
        <BrandLogo compact />
        <IconButton aria-label="Close navigation" onClick={() => setMobileOpen(false)} className={s.sidebarClose}>
          <CloseRoundedIcon />
        </IconButton>
      </div>
      <div className={s.consoleLabel}>
        <ShieldOutlinedIcon fontSize="small" /> Platform administration
      </div>
      <List component="nav" aria-label="Superadmin navigation" sx={{ px: 2, py: 2 }}>
        {navItems.map((item) => (
          <ListItemButton
            key={item.id}
            selected={view === item.id}
            onClick={() => switchView(item.id)}
            aria-current={view === item.id ? "page" : undefined}
            sx={{
              borderRadius: 2,
              mb: 0.75,
              minHeight: 48,
              "&.Mui-selected": { bgcolor: "#E5F4ED", color: "primary.dark" },
              "&.Mui-selected .MuiListItemIcon-root": { color: "primary.dark" },
            }}
          >
            <ListItemIcon sx={{ minWidth: 36 }}>
              <item.icon fontSize="small" />
            </ListItemIcon>
            <ListItemText
              primary={item.label}
              slotProps={{ primary: { variant: "body2", sx: { fontWeight: view === item.id ? 700 : 500 } } }}
            />
          </ListItemButton>
        ))}
      </List>
      <div className={s.sidebarBottom}>
        <Typography variant="caption" color="text.secondary">
          PLATFORM ACCESS
        </Typography>
        <Typography variant="body2" sx={{ mt: 0.75 }}>
          Manage companies and teams from one place.
        </Typography>
        <Button
          component={Link}
          to="/"
          target="_blank"
          rel="noopener"
          endIcon={<ArrowOutwardRoundedIcon />}
          sx={{ mt: 1.5, minHeight: 44 }}
        >
          View public website
        </Button>
        <Divider sx={{ my: 2 }} />
        <Stack
          direction="row"
          spacing={1.5}
          sx={{
            alignItems: "center",
          }}
        >
          <Avatar sx={{ width: 36, height: 36, bgcolor: "primary.dark", fontSize: ".875rem" }}>
            {user.name.charAt(0).toUpperCase()}
          </Avatar>
          <Box sx={{ minWidth: 0 }}>
            <Typography
              variant="body2"
              noWrap
              sx={{
                fontWeight: 700,
              }}
            >
              {user.name}
            </Typography>
            <Typography variant="caption" color="text.secondary">
              Superadmin
            </Typography>
          </Box>
        </Stack>
        <Button
          fullWidth
          onClick={logout}
          disabled={loggingOut}
          color="inherit"
          startIcon={<LogoutRoundedIcon />}
          sx={{ mt: 2, justifyContent: "flex-start", minHeight: 44 }}
        >
          {loggingOut ? "Signing out…" : "Sign out"}
        </Button>
      </div>
    </div>
  );
  return (
    <ThemeProvider theme={platformTheme}>
      <Box className={s.shell}>
        <a className={s.skipLink} href="#platform-main">
          Skip to content
        </a>
        <Drawer variant="permanent" className={s.permanentDrawer} slotProps={{ paper: { className: s.drawerPaper } }}>
          {sidebar}
        </Drawer>
        <Drawer
          variant="temporary"
          open={mobileOpen}
          onClose={() => setMobileOpen(false)}
          slotProps={{ paper: { className: s.drawerPaper } }}
        >
          {sidebar}
        </Drawer>
        <Box className={s.workspace}>
          <AppBar position="sticky" color="inherit" elevation={0} className={s.appBar}>
            <Toolbar sx={{ minHeight: "72px!important", gap: 2, px: { xs: 2, md: 4 } }}>
              <IconButton
                aria-label="Open navigation"
                className={s.mobileMenu}
                onClick={() => setMobileOpen(true)}
                sx={{ width: 44, height: 44 }}
              >
                <MenuRoundedIcon />
              </IconButton>
              <Typography variant="body2" color="text.secondary" sx={{ flex: 1 }}>
                Platform{" "}
                <Box component="span" sx={{ mx: 1.5, color: "divider" }}>
                  /
                </Box>
                <Box component="span" sx={{ color: "text.primary", fontWeight: 600 }}>
                  {navItems.find((i) => i.id === view)?.label}
                </Box>
              </Typography>
              <Tooltip title="Refresh platform data">
                <span>
                  <IconButton
                    aria-label="Refresh platform data"
                    onClick={() => setRevision((r) => r + 1)}
                    disabled={summary.loading}
                    sx={{ width: 44, height: 44 }}
                  >
                    <RefreshRoundedIcon />
                  </IconButton>
                </span>
              </Tooltip>
              <Chip
                label="Superadmin"
                variant="outlined"
                size="small"
                sx={{ display: { xs: "none", sm: "inline-flex" } }}
              />
            </Toolbar>
          </AppBar>
          <Box component="main" id="platform-main" className={s.main}>
            {view === "overview" && (
              <Overview
                summary={summary.data}
                loading={summary.loading}
                error={summary.error}
                retry={summary.retry}
                revision={revision}
                onNavigate={switchView}
                onCreate={createCompany}
                onDetails={setDetailId}
              />
            )}
            {view === "companies" && (
              <CompaniesView
                revision={revision}
                onCreate={createCompany}
                onEdit={editCompany}
                onAction={setAction}
                onDetails={setDetailId}
                onUsers={companyUsers}
              />
            )}
            {view === "users" && (
              <UsersView
                revision={revision}
                currentId={user.id}
                onCreate={createUser}
                onEdit={(u) => {
                  setEditingUser(u);
                  setUserOpen(true);
                }}
                onAction={setAction}
                onAccount={() => switchView("account")}
              />
            )}
            {view === "activity" && <ActivityView revision={revision} />}
            {view === "account" && (
              <AccountView
                user={user}
                onSaved={(u) => {
                  setUser(u);
                  changed();
                }}
              />
            )}
            <Box className={s.footer}>
              <Typography variant="caption" color="text.secondary">
                Creative Connects · Platform administration
              </Typography>
              {summary.data && (
                <Typography variant="caption" color="text.secondary">
                  Data retrieved {timestamp(summary.data.generatedAt)}
                </Typography>
              )}
            </Box>
          </Box>
        </Box>
        <CompanyForm
          open={companyOpen}
          company={editingCompany}
          onClose={() => setCompanyOpen(false)}
          onSaved={changed}
        />
        <UserForm
          open={userOpen}
          user={editingUser}
          defaultCompany={defaultCompany}
          onClose={() => setUserOpen(false)}
          onSaved={changed}
        />
        <ActionDialog
          action={action}
          onClose={() => setAction(null)}
          onSaved={() => {
            setDetailId(null);
            changed();
          }}
        />
        <CompanyDetails
          id={detailId}
          onClose={() => setDetailId(null)}
          onUsers={companyUsers}
          onEdit={editCompany}
          revision={revision}
        />
        <Snackbar
          open={!!notice}
          autoHideDuration={5000}
          onClose={() => setNotice("")}
          anchorOrigin={{ vertical: "bottom", horizontal: "center" }}
        >
          <Alert severity="success" variant="filled" onClose={() => setNotice("")}>
            {notice}
          </Alert>
        </Snackbar>
      </Box>
    </ThemeProvider>
  );
}
