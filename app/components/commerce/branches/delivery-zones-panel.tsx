import { useEffect, useState } from "react";
import Box from "@mui/material/Box";
import Stack from "@mui/material/Stack";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import IconButton from "@mui/material/IconButton";
import Alert from "@mui/material/Alert";
import Divider from "@mui/material/Divider";
import Typography from "@mui/material/Typography";
import Paper from "@mui/material/Paper";
import Grid from "@mui/material/Grid";
import Switch from "@mui/material/Switch";
import CircularProgress from "@mui/material/CircularProgress";
import DeleteRoundedIcon from "@mui/icons-material/DeleteRounded";
import AddRoundedIcon from "@mui/icons-material/AddRounded";
import { apiClient, ApiError } from "~/utils/api-client";
import type { Branch, DeliveryZone } from "~/data/types";

interface DeliveryZonesPanelProps {
  branch: Branch;
}

export function DeliveryZonesPanel({ branch }: DeliveryZonesPanelProps) {
  const [zones, setZones] = useState<DeliveryZone[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [creating, setCreating] = useState(false);
  const [name, setName] = useState("");
  const [radiusKm, setRadiusKm] = useState("");
  const [deliveryCharge, setDeliveryCharge] = useState("");
  const [freeDeliveryThreshold, setFreeDeliveryThreshold] = useState("");
  const [minOrderAmount, setMinOrderAmount] = useState("");

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    apiClient
      .listDeliveryZones({ branchId: branch.id })
      .then(({ data }) => {
        if (!cancelled) setZones(data);
      })
      .catch(() => {
        if (!cancelled) setError("Could not load delivery zones.");
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [branch.id]);

  async function handleCreate() {
    setError(null);
    setCreating(true);
    try {
      const zone = await apiClient.createDeliveryZone({
        branchId: branch.id,
        name: name.trim(),
        radiusKm: radiusKm.trim() ? Number(radiusKm) : null,
        deliveryCharge: deliveryCharge.trim() ? Number(deliveryCharge) : 0,
        freeDeliveryThreshold: freeDeliveryThreshold.trim() ? Number(freeDeliveryThreshold) : null,
        minOrderAmount: minOrderAmount.trim() ? Number(minOrderAmount) : null,
      });
      setZones((prev) => [...prev, zone].sort((a, b) => a.sortOrder - b.sortOrder));
      setName("");
      setRadiusKm("");
      setDeliveryCharge("");
      setFreeDeliveryThreshold("");
      setMinOrderAmount("");
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to create delivery zone.");
    } finally {
      setCreating(false);
    }
  }

  async function handleToggleActive(zone: DeliveryZone) {
    try {
      const updated = await apiClient.updateDeliveryZone(zone.id, { isActive: !zone.isActive });
      setZones((prev) => prev.map((z) => (z.id === zone.id ? updated : z)));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to update delivery zone.");
    }
  }

  async function handleDelete(zone: DeliveryZone) {
    if (!window.confirm(`Delete zone "${zone.name}"?`)) return;
    try {
      await apiClient.deleteDeliveryZone(zone.id);
      setZones((prev) => prev.filter((z) => z.id !== zone.id));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to delete delivery zone.");
    }
  }

  return (
    <Stack spacing={2.5}>
      <Typography variant="subtitle2">Delivery zones</Typography>
      <Typography variant="caption" sx={{ color: "text.secondary" }}>
        Zones are evaluated in order; the first matching radius sets the delivery charge for an order.
      </Typography>

      {error && <Alert severity="error">{error}</Alert>}

      {loading ? (
        <Box sx={{ display: "flex", justifyContent: "center", py: 3 }}>
          <CircularProgress size={24} />
        </Box>
      ) : (
        <Stack spacing={1.5}>
          {zones.map((zone) => (
            <Paper key={zone.id} variant="outlined" sx={{ p: 1.5, borderRadius: 2 }}>
              <Stack direction="row" sx={{ alignItems: "center", justifyContent: "space-between" }}>
                <Stack>
                  <Typography variant="body2" sx={{ fontWeight: 600 }}>
                    {zone.name}
                  </Typography>
                  <Typography variant="caption" sx={{ color: "text.secondary" }}>
                    {zone.radiusKm != null ? `${zone.radiusKm} km radius` : "Flat zone"} · charge {zone.deliveryCharge}
                    {zone.freeDeliveryThreshold != null ? ` · free above ${zone.freeDeliveryThreshold}` : ""}
                  </Typography>
                </Stack>
                <Stack direction="row" sx={{ alignItems: "center" }}>
                  <Switch size="small" checked={zone.isActive} onChange={() => handleToggleActive(zone)} />
                  <IconButton size="small" onClick={() => handleDelete(zone)}>
                    <DeleteRoundedIcon fontSize="small" />
                  </IconButton>
                </Stack>
              </Stack>
            </Paper>
          ))}
          {zones.length === 0 && (
            <Typography variant="caption" sx={{ color: "text.secondary" }}>
              No delivery zones yet — orders fall back to the branch's default delivery charge.
            </Typography>
          )}
        </Stack>
      )}

      <Divider />

      <Typography variant="subtitle2">Add a zone</Typography>
      <Grid container spacing={1.5}>
        <Grid size={12}>
          <TextField fullWidth size="small" label="Name" value={name} onChange={(e) => setName(e.target.value)} />
        </Grid>
        <Grid size={6}>
          <TextField
            fullWidth
            size="small"
            type="number"
            label="Radius (km)"
            value={radiusKm}
            onChange={(e) => setRadiusKm(e.target.value)}
          />
        </Grid>
        <Grid size={6}>
          <TextField
            fullWidth
            size="small"
            type="number"
            label="Delivery charge"
            value={deliveryCharge}
            onChange={(e) => setDeliveryCharge(e.target.value)}
          />
        </Grid>
        <Grid size={6}>
          <TextField
            fullWidth
            size="small"
            type="number"
            label="Free delivery above"
            value={freeDeliveryThreshold}
            onChange={(e) => setFreeDeliveryThreshold(e.target.value)}
          />
        </Grid>
        <Grid size={6}>
          <TextField
            fullWidth
            size="small"
            type="number"
            label="Minimum order amount"
            value={minOrderAmount}
            onChange={(e) => setMinOrderAmount(e.target.value)}
          />
        </Grid>
      </Grid>
      <Button
        variant="outlined"
        startIcon={<AddRoundedIcon />}
        onClick={handleCreate}
        disabled={creating || !name.trim()}
        sx={{ alignSelf: "flex-start" }}
      >
        Add zone
      </Button>
    </Stack>
  );
}
