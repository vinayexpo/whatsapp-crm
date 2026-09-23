import { useState } from "react";
import Stack from "@mui/material/Stack";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import Switch from "@mui/material/Switch";
import FormControlLabel from "@mui/material/FormControlLabel";
import Alert from "@mui/material/Alert";
import Divider from "@mui/material/Divider";
import Typography from "@mui/material/Typography";
import Grid from "@mui/material/Grid";
import { apiClient, ApiError } from "~/utils/api-client";
import type { Branch } from "~/data/types";

interface BranchSettingsPanelProps {
  branch: Branch;
  onUpdated: (branch: Branch) => void;
  onDelete: () => Promise<void>;
}

export function BranchSettingsPanel({ branch, onUpdated, onDelete }: BranchSettingsPanelProps) {
  const [name, setName] = useState(branch.name);
  const [address, setAddress] = useState(branch.address ?? "");
  const [phone, setPhone] = useState(branch.phone ?? "");
  const [active, setActive] = useState(branch.status === "active");
  const [isDefault, setIsDefault] = useState(branch.isDefault);
  const [minOrderAmount, setMinOrderAmount] = useState(
    branch.minOrderAmount != null ? String(branch.minOrderAmount) : "",
  );
  const [defaultDeliveryCharge, setDefaultDeliveryCharge] = useState(
    branch.defaultDeliveryCharge != null ? String(branch.defaultDeliveryCharge) : "",
  );
  const [deliveryRadiusKm, setDeliveryRadiusKm] = useState(
    branch.deliveryRadiusKm != null ? String(branch.deliveryRadiusKm) : "",
  );
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleSave() {
    setSaving(true);
    setError(null);
    try {
      const updated = await apiClient.updateBranch(branch.id, {
        name: name.trim(),
        address: address.trim() || null,
        phone: phone.trim() || null,
        status: active ? "active" : "inactive",
        isDefault,
        minOrderAmount: minOrderAmount.trim() ? Number(minOrderAmount) : null,
        defaultDeliveryCharge: defaultDeliveryCharge.trim() ? Number(defaultDeliveryCharge) : null,
        deliveryRadiusKm: deliveryRadiusKm.trim() ? Number(deliveryRadiusKm) : null,
      });
      onUpdated(updated);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to save changes.");
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete() {
    if (!window.confirm(`Delete "${branch.name}"? This cannot be undone.`)) return;
    setDeleting(true);
    setError(null);
    try {
      await onDelete();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to delete branch.");
      setDeleting(false);
    }
  }

  return (
    <Stack spacing={2.5}>
      {error && <Alert severity="error">{error}</Alert>}

      <TextField fullWidth label="Branch name" value={name} onChange={(e) => setName(e.target.value)} />
      <TextField fullWidth label="Address" value={address} onChange={(e) => setAddress(e.target.value)} />
      <TextField fullWidth label="Phone" value={phone} onChange={(e) => setPhone(e.target.value)} />

      <FormControlLabel
        control={<Switch checked={active} onChange={(e) => setActive(e.target.checked)} />}
        label={active ? "Active" : "Inactive"}
      />
      <FormControlLabel
        control={<Switch checked={isDefault} onChange={(e) => setIsDefault(e.target.checked)} />}
        label="Default branch"
      />

      <Typography variant="subtitle2">Ordering defaults</Typography>
      <Grid container spacing={2}>
        <Grid size={12}>
          <TextField
            fullWidth
            type="number"
            label="Minimum order amount"
            value={minOrderAmount}
            onChange={(e) => setMinOrderAmount(e.target.value)}
          />
        </Grid>
        <Grid size={12}>
          <TextField
            fullWidth
            type="number"
            label="Default delivery charge"
            value={defaultDeliveryCharge}
            onChange={(e) => setDefaultDeliveryCharge(e.target.value)}
          />
        </Grid>
        <Grid size={12}>
          <TextField
            fullWidth
            type="number"
            label="Delivery radius (km)"
            value={deliveryRadiusKm}
            onChange={(e) => setDeliveryRadiusKm(e.target.value)}
          />
        </Grid>
      </Grid>

      <Button variant="contained" onClick={handleSave} disabled={saving || !name.trim()}>
        Save changes
      </Button>

      <Divider />

      <Stack spacing={1}>
        <Typography variant="subtitle2">Danger zone</Typography>
        <Typography variant="caption" sx={{ color: "text.secondary" }}>
          Deleting this branch permanently removes it and its pricing/availability/inventory overrides.
        </Typography>
        <Button color="error" variant="outlined" onClick={handleDelete} disabled={deleting} sx={{ alignSelf: "flex-start" }}>
          Delete branch
        </Button>
      </Stack>
    </Stack>
  );
}
