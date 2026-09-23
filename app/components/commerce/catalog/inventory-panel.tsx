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
import TextField from "@mui/material/TextField";
import Select from "@mui/material/Select";
import MenuItem from "@mui/material/MenuItem";
import Switch from "@mui/material/Switch";
import Chip from "@mui/material/Chip";
import Alert from "@mui/material/Alert";
import CircularProgress from "@mui/material/CircularProgress";
import { PaginatedListFooter } from "~/components/common/paginated-list-footer";
import { apiClient, ApiError } from "~/utils/api-client";
import type { Branch, InventoryRow } from "~/data/types";

export function InventoryPanel() {
  const [rows, setRows] = useState<InventoryRow[]>([]);
  const [branches, setBranches] = useState<Branch[]>([]);
  const [branchFilter, setBranchFilter] = useState<string | "all">("all");
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [savingId, setSavingId] = useState<number | null>(null);

  useEffect(() => {
    apiClient
      .listBranches({ perPage: 100 })
      .then(({ data }) => setBranches(data))
      .catch(() => {});
  }, []);

  useEffect(() => {
    setPage(1);
  }, [branchFilter]);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError(null);
    apiClient
      .listInventory({ page, branchId: branchFilter === "all" ? undefined : branchFilter })
      .then(({ data, meta }) => {
        if (cancelled) return;
        setRows(data);
        setLastPage(meta.lastPage);
      })
      .catch(() => {
        if (!cancelled) setError("Could not load inventory.");
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [page, branchFilter]);

  async function handleStockChange(row: InventoryRow, stockQuantity: number) {
    setSavingId(row.id);
    setError(null);
    try {
      const updated = await apiClient.updateInventory(row.id, { stockQuantity });
      setRows((prev) => prev.map((r) => (r.id === row.id ? updated : r)));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to update stock.");
    } finally {
      setSavingId(null);
    }
  }

  async function handleTrackStockToggle(row: InventoryRow) {
    setSavingId(row.id);
    setError(null);
    try {
      const updated = await apiClient.updateInventory(row.id, { trackStock: !row.trackStock });
      setRows((prev) => prev.map((r) => (r.id === row.id ? updated : r)));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to update stock tracking.");
    } finally {
      setSavingId(null);
    }
  }

  return (
    <Stack spacing={2.5}>
      <Typography variant="body2" sx={{ color: "text.secondary" }}>
        Stock levels per branch. Products without a row here are treated as always available.
      </Typography>

      {branches.length > 1 && (
        <Select
          size="small"
          value={branchFilter}
          onChange={(e) => setBranchFilter(e.target.value)}
          sx={{ minWidth: 200, alignSelf: "flex-start" }}
        >
          <MenuItem value="all">All branches</MenuItem>
          {branches.map((branch) => (
            <MenuItem key={branch.id} value={branch.id}>
              {branch.name}
            </MenuItem>
          ))}
        </Select>
      )}

      {error && <Alert severity="error">{error}</Alert>}

      {loading ? (
        <Box sx={{ display: "flex", justifyContent: "center", py: 6 }}>
          <CircularProgress size={28} />
        </Box>
      ) : rows.length === 0 ? (
        <Paper variant="outlined" sx={{ borderRadius: 3, p: 5, textAlign: "center" }}>
          <Typography variant="body2" sx={{ color: "text.secondary" }}>
            No inventory rows yet. Set stock for a product from its detail panel to start tracking.
          </Typography>
        </Paper>
      ) : (
        <>
          <TableContainer component={Paper} variant="outlined" sx={{ borderRadius: 3 }}>
            <Table size="small">
              <TableHead>
                <TableRow>
                  <TableCell>Product</TableCell>
                  <TableCell>Branch</TableCell>
                  <TableCell>Stock</TableCell>
                  <TableCell>Low-stock alert</TableCell>
                  <TableCell>Track stock</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {rows.map((row) => {
                  const isLow =
                    row.trackStock && row.lowStockThreshold != null && row.stockQuantity <= row.lowStockThreshold;
                  return (
                    <TableRow key={row.id}>
                      <TableCell>
                        {row.productName ?? "—"}
                        {row.productVariantName ? ` (${row.productVariantName})` : ""}
                      </TableCell>
                      <TableCell>{row.branchName ?? "—"}</TableCell>
                      <TableCell>
                        <Stack direction="row" spacing={1} sx={{ alignItems: "center" }}>
                          <TextField
                            size="small"
                            type="number"
                            value={row.stockQuantity}
                            onChange={(e) => {
                              const value = Number(e.target.value);
                              setRows((prev) => prev.map((r) => (r.id === row.id ? { ...r, stockQuantity: value } : r)));
                            }}
                            onBlur={(e) => handleStockChange(row, Number(e.target.value))}
                            disabled={savingId === row.id}
                            sx={{ width: 90 }}
                          />
                          {isLow && <Chip label="Low" size="small" color="warning" />}
                        </Stack>
                      </TableCell>
                      <TableCell>{row.lowStockThreshold ?? "—"}</TableCell>
                      <TableCell>
                        <Switch
                          size="small"
                          checked={row.trackStock}
                          onChange={() => handleTrackStockToggle(row)}
                          disabled={savingId === row.id}
                        />
                      </TableCell>
                    </TableRow>
                  );
                })}
              </TableBody>
            </Table>
          </TableContainer>

          <PaginatedListFooter page={page} lastPage={lastPage} onPageChange={setPage} />
        </>
      )}
    </Stack>
  );
}
