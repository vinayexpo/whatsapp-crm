import { useEffect, type ReactNode } from "react";
import { useNavigate } from "react-router";
import Box from "@mui/material/Box";
import CircularProgress from "@mui/material/CircularProgress";
import { useAuth } from "~/hooks/use-auth";
import type { TeamMemberRole } from "~/data/types";

export function RoleGuard({ allow, children }: { allow: TeamMemberRole[]; children: ReactNode }) {
  const navigate = useNavigate();
  const { user } = useAuth();

  const blocked = Boolean(user) && !allow.includes(user!.role);

  useEffect(() => {
    if (blocked) {
      navigate("/", { replace: true });
    }
  }, [blocked, navigate]);

  if (!user || blocked) {
    return (
      <Box sx={{ display: "flex", alignItems: "center", justifyContent: "center", minHeight: "60vh" }}>
        <CircularProgress />
      </Box>
    );
  }

  return <>{children}</>;
}
