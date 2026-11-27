import { useState } from "react";
import Stack from "@mui/material/Stack";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import Checkbox from "@mui/material/Checkbox";
import FormControlLabel from "@mui/material/FormControlLabel";
import Alert from "@mui/material/Alert";
import Divider from "@mui/material/Divider";
import Typography from "@mui/material/Typography";
import Select from "@mui/material/Select";

import InputLabel from "@mui/material/InputLabel";
import FormControl from "@mui/material/FormControl";
import { apiClient, ApiError } from "~/utils/api-client";
import type { Category, Product } from "~/data/types";

interface ProductSettingsPanelProps {
  product: Product;
  categories: Category[];
  onUpdated: (product: Product) => void;
  onDelete: () => Promise<void>;
}

export function ProductSettingsPanel({ product, categories, onUpdated, onDelete }: ProductSettingsPanelProps) {
  const [name, setName] = useState(product.name);
  const [description, setDescription] = useState(product.description ?? "");
  const [basePrice, setBasePrice] = useState(String(product.basePrice / 100));
  const [salePrice, setSalePrice] = useState(product.salePrice != null ? String(product.salePrice / 100) : "");
  const [imageUrl, setImageUrl] = useState(product.images[0] ?? "");
  const [sku, setSku] = useState(product.sku ?? "");
  const [brand, setBrand] = useState(product.brand ?? "");
  const [taxRate, setTaxRate] = useState(String(product.taxRateBp / 100));
  const [categoryId, setCategoryId] = useState(product.categoryId ?? "");
  const [active, setActive] = useState(product.isActive);
  const [deliveryAvailable, setDeliveryAvailable] = useState(product.deliveryAvailable);
  const [pickupAvailable, setPickupAvailable] = useState(product.pickupAvailable);
  const [availability, setAvailability] = useState(product.availability);
  const [condition, setCondition] = useState(product.condition);
  const [productUrl, setProductUrl] = useState(product.productUrl ?? "");
  const [gtin, setGtin] = useState(product.gtin ?? "");
  const [mpn, setMpn] = useState(product.mpn ?? "");
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleSave() {
    setSaving(true);
    setError(null);
    try {
      const updated = await apiClient.updateProduct(product.id, {
        name: name.trim(),
        description: description.trim() || null,
        basePrice: Math.round(Number(basePrice) * 100),
        salePrice: salePrice.trim() ? Math.round(Number(salePrice) * 100) : null,
        images: imageUrl.trim() ? [imageUrl.trim(), ...product.images.slice(1)] : [],
        sku: sku.trim() || null,
        brand: brand.trim() || null,
        taxRateBp: Math.round(Number(taxRate) * 100),
        categoryId: categoryId || null,
        isActive: active,
        deliveryAvailable,
        pickupAvailable,
        availability,
        condition,
        productUrl: productUrl.trim() || null,
        gtin: gtin.trim() || null,
        mpn: mpn.trim() || null,
      });
      onUpdated(updated);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to save changes.");
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete() {
    setDeleting(true);
    setError(null);
    try {
      await onDelete();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to delete product.");
    } finally {
      setDeleting(false);
    }
  }

  return (
    <Stack spacing={2.5}>
      {error && <Alert severity="error">{error}</Alert>}

      <TextField fullWidth label="Product name" value={name} onChange={(e) => setName(e.target.value)} />
      <TextField fullWidth label="SKU" value={sku} onChange={(e) => setSku(e.target.value)} />
      <TextField fullWidth label="Brand" value={brand} onChange={(e) => setBrand(e.target.value)} />
      <TextField fullWidth label="Tax rate (%)" type="number" value={taxRate} onChange={(e) => setTaxRate(e.target.value)} helperText="Tax is added to the item price at checkout." slotProps={{htmlInput:{min:0,max:100,step:0.01}}}/>
      <TextField
        fullWidth
        label="Product image URL"
        type="url"
        value={imageUrl}
        onChange={(e) => setImageUrl(e.target.value)}
        helperText="Meta requires a publicly accessible product image."
      />
      <TextField
        fullWidth
        multiline
        minRows={2}
        label="Description"
        value={description}
        onChange={(e) => setDescription(e.target.value)}
      />
      <TextField
        fullWidth
        type="number"
        label="Base price (₹)"
        value={basePrice}
        onChange={(e) => setBasePrice(e.target.value)}
      />
      <TextField
        fullWidth
        type="number"
        label="Sale price (₹, optional)"
        value={salePrice}
        onChange={(e) => setSalePrice(e.target.value)}
      />
      <FormControl fullWidth size="small">
        <InputLabel shrink>Category</InputLabel>
        <Select native sx={{"& .MuiNativeSelect-select":{paddingRight:"16px"}}} value={categoryId} label="Category" onChange={(e) => setCategoryId(e.target.value)}>
          <option value="">None</option>
          {categories.map((category) => (
            <option key={category.id} value={category.id}>
              {category.name}
            </option>
          ))}
        </Select>
      </FormControl>

      <FormControl fullWidth size="small">
        <InputLabel shrink>Availability</InputLabel>
        <Select native sx={{"& .MuiNativeSelect-select":{paddingRight:"16px"}}}
          value={availability}
          label="Availability"
          onChange={(e) => setAvailability(e.target.value as Product["availability"])}
        >
          <option value="in_stock">In stock</option>
          <option value="out_of_stock">Out of stock</option>
          <option value="preorder">Preorder</option>
          <option value="discontinued">Discontinued</option>
        </Select>
      </FormControl>

      <FormControl fullWidth size="small">
        <InputLabel shrink>Condition</InputLabel>
        <Select native sx={{"& .MuiNativeSelect-select":{paddingRight:"16px"}}}
          value={condition}
          label="Condition"
          onChange={(e) => setCondition(e.target.value as Product["condition"])}
        >
          <option value="new">New</option>
          <option value="refurbished">Refurbished</option>
          <option value="used">Used</option>
        </Select>
      </FormControl>

      <TextField
        fullWidth
        label="Product URL (optional)"
        value={productUrl}
        onChange={(e) => setProductUrl(e.target.value)}
      />
      <TextField fullWidth label="GTIN (optional)" value={gtin} onChange={(e) => setGtin(e.target.value)} />
      <TextField fullWidth label="MPN (optional)" value={mpn} onChange={(e) => setMpn(e.target.value)} />

      <FormControlLabel
        control={<Checkbox checked={active} onChange={(e) => setActive(e.target.checked)} />}
        label={active ? "Active" : "Inactive"}
      />
      <FormControlLabel
        control={<Checkbox checked={deliveryAvailable} onChange={(e) => setDeliveryAvailable(e.target.checked)} />}
        label="Available for delivery"
      />
      <FormControlLabel
        control={<Checkbox checked={pickupAvailable} onChange={(e) => setPickupAvailable(e.target.checked)} />}
        label="Available for pickup"
      />

      <Button variant="contained" disableElevation onClick={handleSave} disabled={saving || !name.trim() || !basePrice.trim()}>
        Save changes
      </Button>

      <Divider />

      <Stack spacing={1}>
        <Typography variant="subtitle2">Danger zone</Typography>
        <Typography variant="caption" sx={{ color: "text.secondary" }}>
          Deleting removes this product from your active catalog. Its Meta listing is removed on the next sync.
        </Typography>
        <Button
          color="error"
          variant="outlined"
          onClick={handleDelete}
          disabled={deleting}
          sx={{ alignSelf: "flex-start" }}
        >
          Delete product
        </Button>
      </Stack>
    </Stack>
  );
}
