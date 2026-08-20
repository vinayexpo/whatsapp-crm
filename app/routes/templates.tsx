import { useEffect, useMemo, useState } from "react";
import Box from "@mui/material/Box";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Button from "@mui/material/Button";
import Paper from "@mui/material/Paper";
import Chip from "@mui/material/Chip";
import IconButton from "@mui/material/IconButton";
import Tooltip from "@mui/material/Tooltip";
import Alert from "@mui/material/Alert";
import MenuItem from "@mui/material/MenuItem";
import TextField from "@mui/material/TextField";
import AddRoundedIcon from "@mui/icons-material/AddRounded";
import EditRoundedIcon from "@mui/icons-material/EditRounded";
import DeleteRoundedIcon from "@mui/icons-material/DeleteRounded";
import SendRoundedIcon from "@mui/icons-material/SendRounded";
import SearchRoundedIcon from "@mui/icons-material/SearchRounded";
import InputAdornment from "@mui/material/InputAdornment";
import Select from "@mui/material/Select";
import { AppLayout } from "~/components/app-layout/app-layout";
import { RoleGuard } from "~/components/role-guard/role-guard";
import { TemplateBuilderDialog } from "~/components/templates/template-builder-dialog";
import { PaginatedListFooter } from "~/components/common/paginated-list-footer";
import { apiClient, ApiError } from "~/utils/api-client";
import type { ApiConnection, WhatsappTemplate, WhatsappTemplateStatus } from "~/data/types";
import type { Route } from "./+types/templates";

export function meta({}: Route.MetaArgs) {
  return [
    { title: "Templates — Creative Connects" },
    { name: "description", content: "Create and submit WhatsApp message templates for Meta approval." },
  ];
}

const STATUS_COLOR: Record<WhatsappTemplateStatus, "default" | "warning" | "success" | "error"> = {
  draft: "default",
  pending: "warning",
  approved: "success",
  rejected: "error",
};

export default function Templates() {
  const [connections, setConnections] = useState<ApiConnection[]>([]);
  const [connectionId, setConnectionId] = useState<string>("");
  const [templates, setTemplates] = useState<WhatsappTemplate[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [builderOpen, setBuilderOpen] = useState(false);
  const [editingTemplate, setEditingTemplate] = useState<WhatsappTemplate | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [submittingId, setSubmittingId] = useState<string | null>(null);
  const [search, setSearch] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState<WhatsappTemplateStatus | "all">("all");

  const whatsappConnections = useMemo(() => connections.filter((c) => c.channel === "whatsapp"), [connections]);

  useEffect(() => {
    const timeout = setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => clearTimeout(timeout);
  }, [search]);

  useEffect(() => {
    setPage(1);
  }, [debouncedSearch, statusFilter]);

  useEffect(() => {
    apiClient.listApiConnections().then((list) => {
      setConnections(list);
      const first = list.find((c) => c.channel === "whatsapp");
      if (first) setConnectionId(first.id);
    }).catch(() => {
      // connections list stays empty on failure
    });
  }, []);

  useEffect(() => {
    setPage(1);
  }, [connectionId]);

  useEffect(() => {
    if (!connectionId) {
      setTemplates([]);
      setLastPage(1);
      return;
    }
    apiClient
      .listTemplates(connectionId, {
        page,
        search: debouncedSearch || undefined,
        status: statusFilter === "all" ? undefined : statusFilter,
      })
      .then(({ data, meta }) => {
        setTemplates(data);
        setLastPage(meta.lastPage);
      })
      .catch(() => setTemplates([]));
  }, [connectionId, page, debouncedSearch, statusFilter]);

  function openCreate() {
    setEditingTemplate(null);
    setBuilderOpen(true);
  }

  function openEdit(template: WhatsappTemplate) {
    setEditingTemplate(template);
    setBuilderOpen(true);
  }

  function handleSaved(saved: WhatsappTemplate) {
    setTemplates((prev) => {
      const exists = prev.some((t) => t.id === saved.id);
      if (exists) return prev.map((t) => (t.id === saved.id ? saved : t));
      if (page !== 1) {
        setPage(1);
        return prev;
      }
      return [saved, ...prev];
    });
    setBuilderOpen(false);
  }

  async function handleDelete(template: WhatsappTemplate) {
    if (!window.confirm(`Delete draft "${template.name}"?`)) return;
    setError(null);
    try {
      await apiClient.deleteTemplate(template.id);
      setTemplates((prev) => prev.filter((t) => t.id !== template.id));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to delete template.");
    }
  }

  async function handleSubmit(template: WhatsappTemplate) {
    setSubmittingId(template.id);
    setError(null);
    try {
      const updated = await apiClient.submitTemplate(template.id);
      setTemplates((prev) => prev.map((t) => (t.id === updated.id ? updated : t)));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to submit template.");
    } finally {
      setSubmittingId(null);
    }
  }

  return (
    <AppLayout>
      <RoleGuard allow={["superadmin", "admin", "manager"]}>
        <Box sx={{ p: { xs: 2, md: 4 }, flex: 1, minWidth: 0, overflowY: "auto" }}>
          <Stack
            direction="row"
            sx={{ alignItems: "flex-start", justifyContent: "space-between", mb: 3, flexWrap: "wrap", gap: 1.5 }}
          >
            <Stack>
              <Typography variant="h4" sx={{ fontSize: { xs: "1.5rem", md: "1.8rem" } }}>
                Message Templates
              </Typography>
              <Typography variant="body2" sx={{ color: "text.secondary", mt: 0.5 }}>
                Create WhatsApp message templates and submit them to Meta for review.
              </Typography>
            </Stack>
            <Button
              variant="contained"
              startIcon={<AddRoundedIcon />}
              onClick={openCreate}
              disabled={!connectionId}
            >
              New Template
            </Button>
          </Stack>

          {error && (
            <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError(null)}>
              {error}
            </Alert>
          )}

          {whatsappConnections.length > 1 && (
            <TextField
              select
              label="Connection"
              value={connectionId}
              onChange={(e) => setConnectionId(e.target.value)}
              sx={{ mb: 2, minWidth: 260 }}
              size="small"
            >
              {whatsappConnections.map((c) => (
                <MenuItem key={c.id} value={c.id}>
                  {c.label}
                </MenuItem>
              ))}
            </TextField>
          )}

          {connectionId && (
            <Stack direction={{ xs: "column", sm: "row" }} spacing={1.5} sx={{ mb: 2.5 }}>
              <TextField
                placeholder="Search templates…"
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
                onChange={(e) => setStatusFilter(e.target.value as WhatsappTemplateStatus | "all")}
                sx={{ minWidth: 160 }}
              >
                <MenuItem value="all">All statuses</MenuItem>
                <MenuItem value="draft">Draft</MenuItem>
                <MenuItem value="pending">Pending</MenuItem>
                <MenuItem value="approved">Approved</MenuItem>
                <MenuItem value="rejected">Rejected</MenuItem>
              </Select>
            </Stack>
          )}

          {!connectionId ? (
            <Paper variant="outlined" sx={{ borderRadius: 3, p: 5, textAlign: "center" }}>
              <Typography variant="body2" sx={{ color: "text.secondary" }}>
                Connect a WhatsApp Business account in Settings before creating templates.
              </Typography>
            </Paper>
          ) : (
            <Stack spacing={1.5}>
              {templates.map((template) => (
                <Paper key={template.id} variant="outlined" sx={{ borderRadius: 3, p: 2.5 }}>
                  <Stack direction="row" sx={{ alignItems: "flex-start", justifyContent: "space-between", gap: 2 }}>
                    <Stack sx={{ minWidth: 0, flex: 1 }}>
                      <Stack direction="row" sx={{ alignItems: "center", gap: 1, flexWrap: "wrap" }}>
                        <Typography variant="subtitle1" sx={{ fontWeight: 700 }}>
                          {template.name}
                        </Typography>
                        <Chip
                          label={template.status}
                          size="small"
                          color={STATUS_COLOR[template.status]}
                          variant={template.status === "draft" ? "outlined" : "filled"}
                        />
                        <Chip label={template.category} size="small" variant="outlined" />
                        <Chip label={template.language} size="small" variant="outlined" />
                      </Stack>
                      <Typography variant="body2" sx={{ color: "text.secondary", mt: 0.75, whiteSpace: "pre-wrap" }}>
                        {template.body}
                      </Typography>
                      {template.status === "rejected" && template.rejectionReason && (
                        <Typography variant="caption" sx={{ color: "error.main", mt: 0.5 }}>
                          Rejected: {template.rejectionReason}
                        </Typography>
                      )}
                    </Stack>
                    <Stack direction="row" spacing={0.5}>
                      {template.status === "draft" && (
                        <>
                          <Tooltip title="Edit draft">
                            <IconButton size="small" onClick={() => openEdit(template)}>
                              <EditRoundedIcon fontSize="small" />
                            </IconButton>
                          </Tooltip>
                          <Tooltip title="Submit for Meta review">
                            <IconButton
                              size="small"
                              color="primary"
                              onClick={() => handleSubmit(template)}
                              disabled={submittingId === template.id}
                            >
                              <SendRoundedIcon fontSize="small" />
                            </IconButton>
                          </Tooltip>
                          <Tooltip title="Delete draft">
                            <IconButton size="small" color="error" onClick={() => handleDelete(template)}>
                              <DeleteRoundedIcon fontSize="small" />
                            </IconButton>
                          </Tooltip>
                        </>
                      )}
                    </Stack>
                  </Stack>
                </Paper>
              ))}
              {templates.length === 0 && (
                <Paper variant="outlined" sx={{ borderRadius: 3, p: 5, textAlign: "center" }}>
                  <Typography variant="body2" sx={{ color: "text.secondary" }}>
                    No templates yet. Create a draft or sync existing approved templates from Settings.
                  </Typography>
                </Paper>
              )}
            </Stack>
          )}

          {connectionId && <PaginatedListFooter page={page} lastPage={lastPage} onPageChange={setPage} />}
        </Box>

        <TemplateBuilderDialog
          open={builderOpen}
          connectionId={connectionId || null}
          template={editingTemplate}
          onClose={() => setBuilderOpen(false)}
          onSaved={handleSaved}
        />
      </RoleGuard>
    </AppLayout>
  );
}
