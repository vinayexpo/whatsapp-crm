import { useState } from "react";
import Dialog from "@mui/material/Dialog";
import DialogTitle from "@mui/material/DialogTitle";
import DialogContent from "@mui/material/DialogContent";
import DialogActions from "@mui/material/DialogActions";
import Button from "@mui/material/Button";
import Stack from "@mui/material/Stack";
import TextField from "@mui/material/TextField";
import MenuItem from "@mui/material/MenuItem";
import type { TeamMemberRole } from "~/data/types";

type InvitableRole = Extract<TeamMemberRole, "manager" | "agent">;

interface InviteMemberDialogProps {
  open: boolean;
  onClose: () => void;
  onInvite: (member: { name: string; email: string; password: string; role: InvitableRole }) => void;
}

const ROLE_OPTIONS: { value: InvitableRole; label: string }[] = [
  { value: "manager", label: "Manager" },
  { value: "agent", label: "Agent" },
];

export function InviteMemberDialog({ open, onClose, onInvite }: InviteMemberDialogProps) {
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [role, setRole] = useState<InvitableRole>("agent");

  function resetAndClose() {
    setName("");
    setEmail("");
    setPassword("");
    setRole("agent");
    onClose();
  }

  function handleInvite() {
    if (!name.trim() || !email.trim() || password.length < 8) return;
    onInvite({ name: name.trim(), email: email.trim(), password, role });
    resetAndClose();
  }

  const canInvite = name.trim().length > 0 && /\S+@\S+\.\S+/.test(email.trim()) && password.length >= 8;

  return (
    <Dialog open={open} onClose={resetAndClose} maxWidth="xs" fullWidth>
      <DialogTitle sx={{ fontWeight: 700 }}>Invite Team Member</DialogTitle>
      <DialogContent>
        <Stack spacing={2.5} sx={{ mt: 0.5 }}>
          <TextField
            label="Full name"
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder="e.g. Jordan Lee"
            fullWidth
            autoFocus
          />
          <TextField
            label="Email address"
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder="jordan.lee@company.com"
            fullWidth
          />
          <TextField
            label="Password"
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            placeholder="Minimum 8 characters"
            helperText="Minimum 8 characters"
            fullWidth
          />
          <TextField select label="Role" value={role} onChange={(e) => setRole(e.target.value as InvitableRole)} fullWidth>
            {ROLE_OPTIONS.map((opt) => (
              <MenuItem key={opt.value} value={opt.value}>
                {opt.label}
              </MenuItem>
            ))}
          </TextField>
        </Stack>
      </DialogContent>
      <DialogActions sx={{ px: 3, pb: 2.5 }}>
        <Button onClick={resetAndClose} color="inherit">
          Cancel
        </Button>
        <Button variant="contained" onClick={handleInvite} disabled={!canInvite}>
          Send Invite
        </Button>
      </DialogActions>
    </Dialog>
  );
}
