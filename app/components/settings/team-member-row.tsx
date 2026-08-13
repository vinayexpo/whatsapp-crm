import Avatar from "@mui/material/Avatar";
import Box from "@mui/material/Box";
import Chip from "@mui/material/Chip";
import IconButton from "@mui/material/IconButton";
import MenuItem from "@mui/material/MenuItem";
import Select from "@mui/material/Select";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import DeleteOutlineRoundedIcon from "@mui/icons-material/DeleteOutlineRounded";
import type { TeamMember, TeamMemberRole } from "~/data/types";

interface TeamMemberRowProps {
  member: TeamMember;
  onRoleChange: (memberId: string, role: TeamMemberRole) => void;
  onRemove: (memberId: string) => void;
  readOnly?: boolean;
}

const ROLE_OPTIONS: { value: TeamMemberRole; label: string }[] = [
  { value: "superadmin", label: "Superadmin" },
  { value: "admin", label: "Admin" },
  { value: "manager", label: "Manager" },
  { value: "agent", label: "Agent" },
];

export function TeamMemberRow({ member, onRoleChange, onRemove, readOnly = false }: TeamMemberRowProps) {
  const isLocked = member.role === "admin" || member.role === "superadmin";
  const disabled = readOnly || isLocked;

  return (
    <Stack
      direction="row"
      sx={{ alignItems: "center", justifyContent: "space-between", gap: 2, py: 1.5, flexWrap: "wrap" }}
    >
      <Stack direction="row" sx={{ alignItems: "center", gap: 1.5, minWidth: 220 }}>
        <Avatar src={member.avatarUrl} alt={member.name} sx={{ width: 40, height: 40 }} />
        <Box>
          <Stack direction="row" sx={{ alignItems: "center", gap: 0.75 }}>
            <Typography variant="body2" sx={{ fontWeight: 700 }}>
              {member.name}
            </Typography>
            {member.status === "invited" && <Chip label="Invited" size="small" color="warning" sx={{ height: 20, fontSize: "0.68rem" }} />}
          </Stack>
          <Typography variant="caption" sx={{ color: "text.secondary" }}>
            {member.email}
          </Typography>
        </Box>
      </Stack>
      <Stack direction="row" sx={{ alignItems: "center", gap: 1 }}>
        <Select
          size="small"
          value={member.role}
          onChange={(e) => onRoleChange(member.id, e.target.value as TeamMemberRole)}
          sx={{ minWidth: 130 }}
          disabled={disabled}
        >
          {ROLE_OPTIONS.map((opt) => (
            <MenuItem key={opt.value} value={opt.value} disabled={opt.value === "admin" || opt.value === "superadmin"}>
              {opt.label}
            </MenuItem>
          ))}
        </Select>
        <IconButton
          aria-label={`Remove ${member.name}`}
          onClick={() => onRemove(member.id)}
          size="small"
          disabled={disabled}
        >
          <DeleteOutlineRoundedIcon fontSize="small" />
        </IconButton>
      </Stack>
    </Stack>
  );
}
