import { useEffect, useState } from "react";
import Box from "@mui/material/Box";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Button from "@mui/material/Button";
import Paper from "@mui/material/Paper";
import Grid from "@mui/material/Grid";
import Chip from "@mui/material/Chip";
import TextField from "@mui/material/TextField";
import InputAdornment from "@mui/material/InputAdornment";
import Select from "@mui/material/Select";
import MenuItem from "@mui/material/MenuItem";
import CircularProgress from "@mui/material/CircularProgress";
import Alert from "@mui/material/Alert";
import StorefrontRoundedIcon from "@mui/icons-material/StorefrontRounded";
import AddRoundedIcon from "@mui/icons-material/AddRounded";
import SearchRoundedIcon from "@mui/icons-material/SearchRounded";
import { AppLayout } from "~/components/app-layout/app-layout";
import { RoleGuard } from "~/components/role-guard/role-guard";
import { CreateBranchDialog } from "~/components/commerce/branches/create-branch-dialog";
import { BranchDetailDrawer } from "~/components/commerce/branches/branch-detail-drawer";
import { PaginatedListFooter } from "~/components/common/paginated-list-footer";
import { apiClient } from "~/utils/api-client";
import type { Branch, BranchStatus } from "~/data/types";
import type { Route } from "./+types/commerce-branches";

export function meta({}: Route.MetaArgs) {
  return [
    { title: "Branches — Creative Connects" },
    { name: "description", content: "Manage store branches used for WhatsApp commerce and ordering." },
  ];
}

export default function CommerceBranches() {
  const [branches, setBranches] = useState<Branch[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [selectedBranchId, setSelectedBranchId] = useState<string | null>(null);
  const [createOpen, setCreateOpen] = useState(false);
  const [search, setSearch] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState<BranchStatus | "all">("all");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const timeout = setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => clearTimeout(timeout);
  }, [search]);

  useEffect(() => {
    setPage(1);
  }, [debouncedSearch, statusFilter]);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError(null);
    apiClient
      .listBranches({
        page,
        search: debouncedSearch || undefined,
        status: statusFilter === "all" ? undefined : statusFilter,
      })
      .then(({ data, meta }) => {
        if (cancelled) return;
        setBranches(data);
        setLastPage(meta.lastPage);
      })
      .catch(() => {
        if (!cancelled) setError("Could not load branches.");
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [page, debouncedSearch, statusFilter]);

  const selectedBranch = branches.find((b) => b.id === selectedBranchId) ?? null;

  async function handleCreate(input: { name: string; slug: string; address?: string; phone?: string }) {
    const created = await apiClient.createBranch(input);
    if (page === 1) {
      setBranches((prev) => [created, ...prev]);
    } else {
      setPage(1);
    }
    setSelectedBranchId(created.id);
  }

  function handleUpdated(updated: Branch) {
    setBranches((prev) => prev.map((b) => (b.id === updated.id ? updated : b)));
  }

  function handleDeleted(branchId: string) {
    setBranches((prev) => prev.filter((b) => b.id !== branchId));
    if (selectedBranchId === branchId) setSelectedBranchId(null);
  }

  async function handleDelete() {
    if (!selectedBranch) return;
    await apiClient.deleteBranch(selectedBranch.id);
    handleDeleted(selectedBranch.id);
  }

  return (
    <AppLayout>
      <RoleGuard allow={["superadmin", "admin", "manager", "branch_manager"]}>
        <Box sx={{ p: { xs: 2, md: 4 }, flex: 1, minWidth: 0, overflowY: "auto" }}>
          <Stack
            direction="row"
            sx={{ alignItems: "flex-start", justifyContent: "space-between", mb: 3, flexWrap: "wrap", gap: 1.5 }}
          >
            <Stack>
              <Typography variant="h4" sx={{ fontSize: { xs: "1.5rem", md: "1.8rem" } }}>
                Branches
              </Typography>
              <Typography variant="body2" sx={{ color: "text.secondary", mt: 0.5 }}>
                Manage the branches customers can order from over WhatsApp.
              </Typography>
            </Stack>
            <Button variant="contained" startIcon={<AddRoundedIcon />} onClick={() => setCreateOpen(true)}>
              New Branch
            </Button>
          </Stack>

          <Stack direction={{ xs: "column", sm: "row" }} spacing={1.5} sx={{ mb: 2.5 }}>
            <TextField
              placeholder="Search branches…"
              size="small"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              sx={{ flex: 1, maxWidth: { sm: 280 } }}
              slotProps={{
                input: {
                  startAdornment: (
                    <InputAdornment position="start">
                      <SearchRoundedIcon fontSize="small" />
                    </InputAdornment>
                  ),
                },
              }}
            />
            <Select
              size="small"
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value as BranchStatus | "all")}
              sx={{ minWidth: 150 }}
            >
              <MenuItem value="all">All statuses</MenuItem>
              <MenuItem value="active">Active</MenuItem>
              <MenuItem value="inactive">Inactive</MenuItem>
            </Select>
          </Stack>

          {error && (
            <Alert severity="error" sx={{ mb: 2.5 }}>
              {error}
            </Alert>
          )}

          {loading ? (
            <Box sx={{ display: "flex", justifyContent: "center", py: 6 }}>
              <CircularProgress size={28} />
            </Box>
          ) : (
            <>
              <Grid container spacing={2}>
                {branches.map((branch) => (
                  <Grid key={branch.id} size={{ xs: 12, sm: 6, md: 4, lg: 3 }}>
                    <Paper
                      variant="outlined"
                      sx={{
                        borderRadius: 3,
                        p: 2.5,
                        cursor: "pointer",
                        transition: "border-color 0.15s",
                        "&:hover": { borderColor: "primary.main" },
                      }}
                      onClick={() => setSelectedBranchId(branch.id)}
                    >
                      <Stack direction="row" sx={{ alignItems: "flex-start", justifyContent: "space-between" }}>
                        <Box
                          sx={{
                            width: 40,
                            height: 40,
                            borderRadius: 2,
                            bgcolor: "rgba(91, 110, 245, 0.12)",
                            display: "flex",
                            alignItems: "center",
                            justifyContent: "center",
                          }}
                        >
                          <StorefrontRoundedIcon sx={{ color: "#5B6EF5" }} fontSize="small" />
                        </Box>
                        <Stack direction="row" spacing={0.5}>
                          {branch.isDefault && <Chip label="Default" size="small" variant="outlined" />}
                          <Chip
                            label={branch.status === "active" ? "Active" : "Inactive"}
                            size="small"
                            color={branch.status === "active" ? "success" : "default"}
                            variant={branch.status === "active" ? "filled" : "outlined"}
                          />
                        </Stack>
                      </Stack>
                      <Typography variant="subtitle1" sx={{ fontWeight: 700, mt: 1.5 }}>
                        {branch.name}
                      </Typography>
                      <Typography variant="caption" sx={{ color: "text.secondary" }}>
                        {branch.address || "No address set"}
                      </Typography>
                    </Paper>
                  </Grid>
                ))}
                {branches.length === 0 && (
                  <Grid size={12}>
                    <Paper variant="outlined" sx={{ borderRadius: 3, p: 5, textAlign: "center" }}>
                      <Typography variant="body2" sx={{ color: "text.secondary" }}>
                        No branches yet. Create one to start taking orders over WhatsApp.
                      </Typography>
                    </Paper>
                  </Grid>
                )}
              </Grid>

              <PaginatedListFooter page={page} lastPage={lastPage} onPageChange={setPage} />
            </>
          )}
        </Box>

        <CreateBranchDialog open={createOpen} onClose={() => setCreateOpen(false)} onCreate={handleCreate} />

        <BranchDetailDrawer
          branch={selectedBranch}
          onClose={() => setSelectedBranchId(null)}
          onUpdated={handleUpdated}
          onDeleted={handleDeleted}
          onDelete={handleDelete}
        />
      </RoleGuard>
    </AppLayout>
  );
}
