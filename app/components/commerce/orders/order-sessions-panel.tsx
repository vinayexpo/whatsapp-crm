import { useEffect, useState } from "react";
import Box from "@mui/material/Box";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Paper from "@mui/material/Paper";
import Table from "@mui/material/Table";
import TableBody from "@mui/material/TableBody";
import TableCell from "@mui/material/TableCell";
import TableContainer from "@mui/material/TableContainer";
import TableHead from "@mui/material/TableHead";
import TableRow from "@mui/material/TableRow";
import Select from "@mui/material/Select";
import MenuItem from "@mui/material/MenuItem";
import Chip from "@mui/material/Chip";
import Alert from "@mui/material/Alert";
import CircularProgress from "@mui/material/CircularProgress";
import { PaginatedListFooter } from "~/components/common/paginated-list-footer";
import { apiClient } from "~/utils/api-client";
import type { OrderSession, OrderSessionStatus } from "~/data/types";

const STATUS_COLOR: Record<OrderSessionStatus, "primary" | "success" | "default" | "error"> = {
  active: "primary",
  completed: "success",
  abandoned: "default",
  expired: "error",
};

function formatMoney(amountMinorUnits: number): string {
  return new Intl.NumberFormat().format(amountMinorUnits / 100);
}

export function OrderSessionsPanel() {
  const [sessions, setSessions] = useState<OrderSession[]>([]);
  const [statusFilter, setStatusFilter] = useState<OrderSessionStatus | "all">("active");
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setPage(1);
  }, [statusFilter]);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError(null);
    apiClient
      .listOrderSessions({ page, status: statusFilter === "all" ? undefined : statusFilter })
      .then(({ data, meta }) => {
        if (cancelled) return;
        setSessions(data);
        setLastPage(meta.lastPage);
      })
      .catch(() => {
        if (!cancelled) setError("Could not load order sessions.");
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [page, statusFilter]);

  return (
    <Stack spacing={2.5}>
      <Typography variant="body2" sx={{ color: "text.secondary" }}>
        WhatsApp conversations currently browsing or checking out — useful for spotting stuck or abandoned carts.
      </Typography>

      <Select
        size="small"
        value={statusFilter}
        onChange={(e) => setStatusFilter(e.target.value as OrderSessionStatus | "all")}
        sx={{ minWidth: 180, alignSelf: "flex-start" }}
      >
        <MenuItem value="all">All statuses</MenuItem>
        <MenuItem value="active">Active</MenuItem>
        <MenuItem value="completed">Completed</MenuItem>
        <MenuItem value="abandoned">Abandoned</MenuItem>
        <MenuItem value="expired">Expired</MenuItem>
      </Select>

      {error && <Alert severity="error">{error}</Alert>}

      {loading ? (
        <Box sx={{ display: "flex", justifyContent: "center", py: 6 }}>
          <CircularProgress size={28} />
        </Box>
      ) : sessions.length === 0 ? (
        <Paper variant="outlined" sx={{ borderRadius: 3, p: 5, textAlign: "center" }}>
          <Typography variant="body2" sx={{ color: "text.secondary" }}>
            No order sessions match this filter.
          </Typography>
        </Paper>
      ) : (
        <>
          <TableContainer component={Paper} variant="outlined" sx={{ borderRadius: 3, overflowX: "auto" }}>
            <Table size="small" sx={{ minWidth: 640 }}>
              <TableHead>
                <TableRow>
                  <TableCell>Customer</TableCell>
                  <TableCell>Branch</TableCell>
                  <TableCell>Status</TableCell>
                  <TableCell>Step</TableCell>
                  <TableCell align="right">Cart total</TableCell>
                  <TableCell>Last activity</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {sessions.map((session) => (
                  <TableRow key={session.id} sx={{ "&:last-child td": { border: 0 } }}>
                    <TableCell>{session.contactName ?? "—"}</TableCell>
                    <TableCell>{session.branchName ?? "—"}</TableCell>
                    <TableCell>
                      <Chip label={session.status} size="small" color={STATUS_COLOR[session.status]} />
                    </TableCell>
                    <TableCell>{session.step}</TableCell>
                    <TableCell align="right">{formatMoney(session.cartTotal)}</TableCell>
                    <TableCell>
                      <Typography variant="caption" sx={{ color: "text.secondary" }}>
                        {session.lastInteractionAt ? new Date(session.lastInteractionAt).toLocaleString() : "—"}
                      </Typography>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>

          <PaginatedListFooter page={page} lastPage={lastPage} onPageChange={setPage} />
        </>
      )}
    </Stack>
  );
}
