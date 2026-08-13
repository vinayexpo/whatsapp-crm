import { useEffect, useState } from "react";
import { useNavigate } from "react-router";
import Box from "@mui/material/Box";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Button from "@mui/material/Button";
import Paper from "@mui/material/Paper";
import Chip from "@mui/material/Chip";
import CircularProgress from "@mui/material/CircularProgress";
import WhatsAppIcon from "@mui/icons-material/WhatsApp";
import AddRoundedIcon from "@mui/icons-material/AddRounded";
import PersonAddRoundedIcon from "@mui/icons-material/PersonAddRounded";
import { apiClient } from "~/utils/api-client";
import { useAuth } from "~/hooks/use-auth";
import { CreateCompanyDialog } from "~/components/superadmin/create-company-dialog";
import { CreateAdminDialog } from "~/components/superadmin/create-admin-dialog";
import type { Company } from "~/data/types";
import type { Route } from "./+types/superadmin";

export function meta({}: Route.MetaArgs) {
  return [{ title: "Superadmin — Creative Connects" }];
}

export default function Superadmin() {
  const navigate = useNavigate();
  const { user, status, setUser, setStatus } = useAuth();
  const [companies, setCompanies] = useState<Company[]>([]);
  const [loading, setLoading] = useState(true);
  const [companyDialogOpen, setCompanyDialogOpen] = useState(false);
  const [adminDialogCompany, setAdminDialogCompany] = useState<Company | null>(null);
  const [loggingOut, setLoggingOut] = useState(false);

  useEffect(() => {
    if (status === "loading") {
      apiClient
        .me()
        .then((me) => {
          setUser(me);
          setStatus("authenticated");
        })
        .catch(() => {
          setStatus("unauthenticated");
        });
      return;
    }
    if (status === "unauthenticated") {
      navigate("/login", { replace: true });
      return;
    }
    if (status === "authenticated" && user && user.role !== "superadmin") {
      navigate("/", { replace: true });
    }
  }, [status, user, navigate, setUser, setStatus]);

  useEffect(() => {
    if (status === "authenticated" && user?.role === "superadmin") {
      apiClient
        .listCompanies()
        .then(setCompanies)
        .finally(() => setLoading(false));
    }
  }, [status, user]);

  async function handleLogout() {
    setLoggingOut(true);
    try {
      await apiClient.logout();
    } catch {
      // best-effort: proceed to clear local state and redirect regardless
    }
    setUser(null);
    setStatus("unauthenticated");
    setLoggingOut(false);
    navigate("/login", { replace: true });
  }

  if (status !== "authenticated" || !user || user.role !== "superadmin") {
    return (
      <Box sx={{ display: "flex", alignItems: "center", justifyContent: "center", minHeight: "100vh" }}>
        <CircularProgress />
      </Box>
    );
  }

  return (
    <Box sx={{ minHeight: "100vh", bgcolor: "background.default", px: { xs: 2, md: 6 }, py: 4 }}>
      <Stack sx={{ maxWidth: 960, mx: "auto" }} spacing={3}>
        <Stack direction="row" sx={{ alignItems: "center", justifyContent: "space-between" }}>
          <Stack direction="row" spacing={1.25} sx={{ alignItems: "center" }}>
            <Box
              sx={{
                width: 36,
                height: 36,
                borderRadius: "10px",
                bgcolor: "primary.main",
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
              }}
            >
              <WhatsAppIcon sx={{ color: "primary.contrastText", fontSize: 20 }} />
            </Box>
            <Typography variant="h6" sx={{ fontWeight: 700 }}>
              Creative Connects — Superadmin
            </Typography>
          </Stack>
          <Button onClick={handleLogout} disabled={loggingOut} color="inherit">
            {loggingOut ? "Logging out…" : "Log out"}
          </Button>
        </Stack>

        <Stack direction="row" sx={{ alignItems: "center", justifyContent: "space-between" }}>
          <Typography variant="h5" sx={{ fontWeight: 700 }}>
            Companies
          </Typography>
          <Button
            variant="contained"
            startIcon={<AddRoundedIcon />}
            onClick={() => setCompanyDialogOpen(true)}
          >
            New Company
          </Button>
        </Stack>

        {loading ? (
          <Box sx={{ display: "flex", justifyContent: "center", py: 6 }}>
            <CircularProgress />
          </Box>
        ) : companies.length === 0 ? (
          <Paper variant="outlined" sx={{ p: 4, textAlign: "center" }}>
            <Typography color="text.secondary">No companies yet. Create the first one to get started.</Typography>
          </Paper>
        ) : (
          <Stack spacing={1.5}>
            {companies.map((company) => (
              <Paper key={company.id} variant="outlined" sx={{ p: 2.5 }}>
                <Stack direction="row" sx={{ alignItems: "center", justifyContent: "space-between", flexWrap: "wrap", gap: 1.5 }}>
                  <Stack direction="row" sx={{ alignItems: "center", gap: 1.5 }}>
                    <Typography variant="body1" sx={{ fontWeight: 700 }}>
                      {company.name}
                    </Typography>
                    <Chip
                      label={company.status}
                      size="small"
                      color={company.status === "active" ? "success" : "default"}
                      sx={{ textTransform: "capitalize" }}
                    />
                  </Stack>
                  <Button
                    size="small"
                    startIcon={<PersonAddRoundedIcon />}
                    onClick={() => setAdminDialogCompany(company)}
                  >
                    Add Admin
                  </Button>
                </Stack>
              </Paper>
            ))}
          </Stack>
        )}
      </Stack>

      <CreateCompanyDialog
        open={companyDialogOpen}
        onClose={() => setCompanyDialogOpen(false)}
        onCreated={(company) => setCompanies((prev) => [...prev, company])}
      />
      <CreateAdminDialog
        open={adminDialogCompany !== null}
        company={adminDialogCompany}
        onClose={() => setAdminDialogCompany(null)}
        onCreated={() => setAdminDialogCompany(null)}
      />
    </Box>
  );
}
