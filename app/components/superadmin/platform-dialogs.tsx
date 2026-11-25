import { useEffect, useState, type FormEvent } from "react";
import {
  Alert,
  Autocomplete,
  Box,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  MenuItem,
  Stack,
  TextField,
  Typography,
} from "@mui/material";
import { apiClient, ApiError } from "~/utils/api-client";
import type { PlatformCompany, PlatformUser, PlatformUserInput } from "~/data/platform-types";
import type { TeamMemberRole } from "~/data/types";

export const roleLabels: Record<TeamMemberRole, string> = {
  superadmin: "Superadmin",
  admin: "Company admin",
  manager: "Manager",
  agent: "Agent",
  branch_manager: "Branch manager",
  staff: "Staff",
};
export const statusLabels: Record<string, string> = {
  active: "Active",
  suspended: "Suspended",
  invited: "Invited",
  connected: "Connected",
  disconnected: "Disconnected",
  error: "Error",
};
export function errorText(error: unknown): string {
  if (error instanceof ApiError) return Object.values(error.errors ?? {}).flat()[0] ?? error.message;
  return error instanceof Error ? error.message : "Could not save your changes. Please try again.";
}
export type CompanyOption = Pick<PlatformCompany, "id" | "name">;

export function CompanyPicker({
  value,
  onChange,
  disabled,
  label = "Company",
  required = false,
}: {
  value: CompanyOption | null;
  onChange: (value: CompanyOption | null) => void;
  disabled?: boolean;
  label?: string;
  required?: boolean;
}) {
  const [options, setOptions] = useState<CompanyOption[]>([]);
  const [search, setSearch] = useState("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  useEffect(() => {
    if (disabled) return;
    let cancelled = false;
    const timeout = setTimeout(() => {
      setLoading(true);
      apiClient
        .platformCompanies({ search, perPage: 30 })
        .then((result) => {
          if (!cancelled) {
            setOptions(result.data);
            setError(null);
          }
        })
        .catch((e) => {
          if (!cancelled) setError(errorText(e));
        })
        .finally(() => {
          if (!cancelled) setLoading(false);
        });
    }, 250);
    return () => {
      cancelled = true;
      clearTimeout(timeout);
    };
  }, [search, disabled]);
  return (
    <Autocomplete
      value={value}
      options={value && !options.some((o) => o.id === value.id) ? [value, ...options] : options}
      disabled={disabled}
      filterOptions={(options) => options}
      loading={loading}
      isOptionEqualToValue={(a, b) => a.id === b.id}
      getOptionLabel={(o) => o.name}
      onChange={(_, v) => onChange(v)}
      onInputChange={(_, v, reason) => {
        if (reason === "input" || reason === "clear") setSearch(v);
      }}
      noOptionsText={error ?? "No companies found. Try another name."}
      renderInput={(params) => (
        <TextField
          {...params}
          label={label}
          required={required}
          error={!!error}
          helperText={error ? "Could not load companies. Type to retry." : undefined}
        />
      )}
    />
  );
}

export function CompanyForm({
  open,
  company,
  onClose,
  onSaved,
}: {
  open: boolean;
  company: PlatformCompany | null;
  onClose: () => void;
  onSaved: () => void;
}) {
  const [name, setName] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  useEffect(() => {
    if (open) {
      setName(company?.name ?? "");
      setError(null);
    }
  }, [open, company?.id]);
  async function save(e: FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      await apiClient.platformSaveCompany(company?.id ?? null, { name: name.trim() });
      onSaved();
      onClose();
    } catch (e) {
      setError(errorText(e));
    } finally {
      setBusy(false);
    }
  }
  return (
    <Dialog open={open} onClose={() => !busy && onClose()} maxWidth="sm" fullWidth aria-labelledby="company-form-title">
      <Box component="form" onSubmit={save}>
        <DialogTitle id="company-form-title">{company ? "Edit company" : "Create company"}</DialogTitle>
        <DialogContent>
          <Stack spacing={3} sx={{ pt: 1 }}>
            {error && <Alert severity="error">{error}</Alert>}
            <TextField
              label="Company name"
              value={name}
              onChange={(e) => setName(e.target.value)}
              autoFocus
              required
              fullWidth
              slotProps={{ htmlInput: { maxLength: 255 } }}
            />
            <Typography variant="body2" color="text.secondary">
              {company
                ? "The company’s users and data stay in the same workspace."
                : "New companies start active with channels ready to configure. Add a company admin after creating the company."}
            </Typography>
          </Stack>
        </DialogContent>
        <DialogActions sx={{ p: 3, pt: 1 }}>
          <Button color="inherit" onClick={onClose} disabled={busy}>
            Cancel
          </Button>
          <Button variant="contained" type="submit" disabled={busy || !name.trim()}>
            {busy ? "Saving…" : company ? "Save changes" : "Create company"}
          </Button>
        </DialogActions>
      </Box>
    </Dialog>
  );
}

export function UserForm({
  open,
  user,
  defaultCompany,
  onClose,
  onSaved,
}: {
  open: boolean;
  user: PlatformUser | null;
  defaultCompany: CompanyOption | null;
  onClose: () => void;
  onSaved: () => void;
}) {
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [role, setRole] = useState<TeamMemberRole>("admin");
  const [status, setStatus] = useState<PlatformUser["status"]>("active");
  const [company, setCompany] = useState<CompanyOption | null>(null);
  const [branch, setBranch] = useState("");
  const [branches, setBranches] = useState<{ id: string; name: string }[]>([]);
  const [branchLoading, setBranchLoading] = useState(false);
  const [branchError, setBranchError] = useState<string | null>(null);
  const [branchRevision, setBranchRevision] = useState(0);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  useEffect(() => {
    if (open) {
      setName(user?.name ?? "");
      setEmail(user?.email ?? "");
      setPassword("");
      setRole(user?.role ?? "admin");
      setStatus(user?.status ?? "active");
      setCompany(user?.companyId ? { id: user.companyId, name: user.companyName ?? "Company" } : defaultCompany);
      setBranch(user?.staffBranchId ?? "");
      setError(null);
    }
  }, [open, user?.id, defaultCompany?.id]);
  useEffect(() => {
    if (!open) {
      setPassword("");
      return;
    }
    if (!company || role === "superadmin") {
      setBranches([]);
      return;
    }
    let cancelled = false;
    setBranchLoading(true);
    setBranchError(null);
    apiClient
      .platformBranches(company.id)
      .then((data) => {
        if (!cancelled) setBranches(data);
      })
      .catch((e) => {
        if (!cancelled) setBranchError(errorText(e));
      })
      .finally(() => {
        if (!cancelled) setBranchLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [open, company?.id, branchRevision, role]);
  const branchRole = role === "branch_manager" || role === "staff";
  async function save(e: FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      const data: PlatformUserInput = {
        name: name.trim(),
        email: email.trim(),
        role,
        status,
        companyId: role === "superadmin" ? null : company?.id,
        staffBranchId: branchRole ? branch || null : null,
      };
      if (!user) data.password = password;
      await apiClient.platformSaveUser(user?.id ?? null, data);
      onSaved();
      onClose();
    } catch (e) {
      setError(errorText(e));
    } finally {
      setBusy(false);
    }
  }
  return (
    <Dialog open={open} onClose={() => !busy && onClose()} maxWidth="sm" fullWidth aria-labelledby="user-form-title">
      <Box component="form" onSubmit={save}>
        <DialogTitle id="user-form-title">{user ? "Edit user" : "Create user"}</DialogTitle>
        <DialogContent>
          <Stack spacing={2.5} sx={{ pt: 1 }}>
            {error && <Alert severity="error">{error}</Alert>}
            <TextField
              label="Full name"
              value={name}
              onChange={(e) => setName(e.target.value)}
              autoFocus
              required
              fullWidth
              slotProps={{ htmlInput: { maxLength: 255 } }}
            />
            <TextField
              label="Email address"
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              fullWidth
              slotProps={{ htmlInput: { maxLength: 255 } }}
            />
            <Stack direction={{ xs: "column", sm: "row" }} spacing={2}>
              <TextField
                select
                label="Role"
                value={role}
                onChange={(e) => {
                  setRole(e.target.value as TeamMemberRole);
                  setBranch("");
                }}
                fullWidth
                disabled={user?.role === "superadmin"}
              >
                {Object.entries(roleLabels)
                  .filter(([r]) => !user || r !== "superadmin" || user.role === "superadmin")
                  .map(([r, label]) => (
                    <MenuItem value={r} key={r}>
                      {label}
                    </MenuItem>
                  ))}
              </TextField>
              <TextField
                select
                label="Access status"
                value={status}
                onChange={(e) => setStatus(e.target.value as PlatformUser["status"])}
                fullWidth
              >
                {["active", "invited", "suspended"].map((v) => (
                  <MenuItem key={v} value={v}>
                    {statusLabels[v]}
                  </MenuItem>
                ))}
              </TextField>
            </Stack>
            {role !== "superadmin" ? (
              <CompanyPicker
                value={company}
                onChange={(v) => {
                  setCompany(v);
                  setBranch("");
                }}
                disabled={!!user}
                required
              />
            ) : (
              <Alert severity="info">Superadmins manage the platform and are not assigned to a company.</Alert>
            )}
            {branchRole && (
              <>
                <TextField
                  select
                  label="Branch"
                  value={branch}
                  onChange={(e) => setBranch(e.target.value)}
                  required
                  fullWidth
                  disabled={!company || branchLoading}
                  helperText={
                    branchLoading
                      ? "Loading branches…"
                      : !company
                        ? "Choose a company first."
                        : branches.length === 0
                          ? "Create a branch in this company before assigning this role."
                          : undefined
                  }
                >
                  <MenuItem value="" disabled>
                    Select a branch
                  </MenuItem>
                  {branches.map((b) => (
                    <MenuItem value={b.id} key={b.id}>
                      {b.name}
                    </MenuItem>
                  ))}
                </TextField>
                {branchError && (
                  <Alert
                    severity="error"
                    action={<Button onClick={() => setBranchRevision((r) => r + 1)}>Retry</Button>}
                  >
                    {branchError}
                  </Alert>
                )}
              </>
            )}
            {!user && (
              <TextField
                label="Initial password"
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
                helperText="At least 8 characters. Share it securely with the user."
                autoComplete="new-password"
                slotProps={{ htmlInput: { minLength: 8, maxLength: 255 } }}
              />
            )}
            {user && (
              <Typography variant="body2" color="text.secondary">
                Company assignment is fixed. Role, branch, or access changes sign the user out. Use Reset password to
                change their password.
              </Typography>
            )}
            {status !== "active" && (
              <Alert severity="warning">
                {status === "invited"
                  ? "Invited users cannot sign in until you set their access to Active. No invitation email is sent."
                  : "Suspended users cannot sign in. Existing sessions will be revoked."}
              </Alert>
            )}
          </Stack>
        </DialogContent>
        <DialogActions sx={{ p: 3, pt: 1 }}>
          <Button color="inherit" disabled={busy} onClick={onClose}>
            Cancel
          </Button>
          <Button
            variant="contained"
            type="submit"
            disabled={
              busy ||
              !name.trim() ||
              !email.trim() ||
              (!user && password.length < 8) ||
              (role !== "superadmin" && !company) ||
              (branchRole && (!branch || branchLoading || !!branchError))
            }
          >
            {busy ? "Saving…" : user ? "Save changes" : "Create user"}
          </Button>
        </DialogActions>
      </Box>
    </Dialog>
  );
}

export type ConfirmAction =
  | { kind: "company-status"; company: PlatformCompany }
  | { kind: "company-delete"; company: PlatformCompany }
  | { kind: "user-delete"; user: PlatformUser }
  | { kind: "password"; user: PlatformUser };
export function ActionDialog({
  action,
  onClose,
  onSaved,
}: {
  action: ConfirmAction | null;
  onClose: () => void;
  onSaved: () => void;
}) {
  const [confirmation, setConfirmation] = useState("");
  const [currentPassword, setCurrentPassword] = useState("");
  const [password, setPassword] = useState("");
  const [repeat, setRepeat] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  useEffect(() => {
    setConfirmation("");
    setCurrentPassword("");
    setPassword("");
    setRepeat("");
    setError(null);
  }, [action]);
  const deleting = action?.kind === "company-delete" || action?.kind === "user-delete";
  const resetting = action?.kind === "password";
  const expected =
    action?.kind === "company-delete" ? action.company.name : action?.kind === "user-delete" ? action.user.email : "";
  const suspending = action?.kind === "company-status" && action.company.status === "active";
  const title =
    action?.kind === "company-status"
      ? `${suspending ? "Suspend" : "Reactivate"} company`
      : resetting
        ? "Reset user password"
        : action?.kind === "company-delete"
          ? "Delete company"
          : "Delete user";
  async function save(e: FormEvent) {
    e.preventDefault();
    if (!action) return;
    setBusy(true);
    setError(null);
    try {
      if (action.kind === "company-status")
        await apiClient.platformSaveCompany(action.company.id, { status: suspending ? "suspended" : "active" });
      else if (action.kind === "company-delete")
        await apiClient.platformDeleteCompany(action.company.id, { confirmation, currentPassword });
      else if (action.kind === "user-delete")
        await apiClient.platformDeleteUser(action.user.id, { confirmation, currentPassword });
      else
        await apiClient.platformResetPassword(action.user.id, {
          currentPassword,
          password,
          password_confirmation: repeat,
        });
      onSaved();
      onClose();
    } catch (e) {
      setError(errorText(e));
    } finally {
      setBusy(false);
    }
  }
  return (
    <Dialog
      open={!!action}
      onClose={() => !busy && onClose()}
      maxWidth="sm"
      fullWidth
      aria-labelledby="platform-action-title"
    >
      <Box component="form" onSubmit={save}>
        <DialogTitle id="platform-action-title">{title}</DialogTitle>
        <DialogContent>
          <Stack spacing={2.5} sx={{ pt: 1 }}>
            {error && <Alert severity="error">{error}</Alert>}
            {action?.kind === "company-status" && (
              <Typography variant="body2">
                {suspending
                  ? `Suspend ${action.company.name}? All users in this company will be signed out and blocked from signing in. The company's data is retained.`
                  : `Reactivate ${action.company.name}? Its active users can sign in again. Individually suspended users remain suspended.`}
              </Typography>
            )}
            {deleting && (
              <>
                <Alert severity="error">
                  {action?.kind === "company-delete"
                    ? "This permanently deletes the company, its users and its database records. This cannot be undone."
                    : "This permanently deletes the user and revokes their sessions. The company and its customer records are retained."}
                </Alert>
                <TextField
                  label={action?.kind === "company-delete" ? "Type the company name" : "Type the user's email"}
                  helperText={expected}
                  value={confirmation}
                  onChange={(e) => setConfirmation(e.target.value)}
                  required
                  autoComplete="off"
                />
              </>
            )}
            {resetting && (
              <>
                <Typography variant="body2">
                  Set a new password for <strong>{action?.kind === "password" ? action.user.email : ""}</strong>.
                  Existing sessions will be signed out. No email is sent.
                </Typography>
                <TextField
                  label="New password"
                  type="password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  required
                  autoComplete="new-password"
                  slotProps={{ htmlInput: { minLength: 8, maxLength: 255 } }}
                />
                <TextField
                  label="Confirm new password"
                  type="password"
                  value={repeat}
                  onChange={(e) => setRepeat(e.target.value)}
                  required
                  autoComplete="new-password"
                  error={!!repeat && repeat !== password}
                  helperText={repeat && repeat !== password ? "Passwords must match." : "At least 8 characters."}
                />
              </>
            )}
            {(deleting || resetting) && (
              <TextField
                label="Your current password"
                type="password"
                value={currentPassword}
                onChange={(e) => setCurrentPassword(e.target.value)}
                required
                autoComplete="current-password"
                helperText="Confirm your superadmin password to continue."
              />
            )}
          </Stack>
        </DialogContent>
        <DialogActions sx={{ p: 3, pt: 1 }}>
          <Button color="inherit" onClick={onClose} disabled={busy}>
            Cancel
          </Button>
          <Button
            type="submit"
            variant="contained"
            color={deleting || suspending ? "error" : "primary"}
            disabled={
              busy ||
              (deleting && (confirmation !== expected || !currentPassword)) ||
              (resetting && (password.length < 8 || repeat !== password || !currentPassword))
            }
          >
            {busy ? "Saving…" : title}
          </Button>
        </DialogActions>
      </Box>
    </Dialog>
  );
}
