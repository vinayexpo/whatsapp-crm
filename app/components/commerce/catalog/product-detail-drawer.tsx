import { useEffect, useState } from "react";
import Box from "@mui/material/Box";
import Drawer from "@mui/material/Drawer";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import IconButton from "@mui/material/IconButton";
import Button from "@mui/material/Button";
import Tabs from "@mui/material/Tabs";
import Tab from "@mui/material/Tab";
import CloseRoundedIcon from "@mui/icons-material/CloseRounded";
import Inventory2RoundedIcon from "@mui/icons-material/Inventory2Rounded";
import CloudUploadRoundedIcon from "@mui/icons-material/CloudUploadRounded";
import type { Category, Product } from "~/data/types";
import { apiClient } from "~/utils/api-client";
import { ProductSettingsPanel } from "./product-settings-panel";
import { ProductVariantsPanel } from "./product-variants-panel";
import { ProductAddonsPanel } from "./product-addons-panel";

interface ProductDetailDrawerProps {
  product: Product | null;
  categories: Category[];
  onClose: () => void;
  onUpdated: (product: Product) => void;
  onDelete: () => Promise<void>;
  onPushed?: (message: string) => void;
}

export function ProductDetailDrawer({ product, categories, onClose, onUpdated, onDelete, onPushed }: ProductDetailDrawerProps) {
  const [tab, setTab] = useState<"settings" | "variants" | "addons">("settings");
  const [pushing, setPushing] = useState(false);

  async function handlePushToMeta() {
    if (!product) return;
    setPushing(true);
    try {
      await apiClient.pushProductToMeta(product.id);
      onPushed?.(`Pushed "${product.name}" to Meta.`);
    } catch {
      onPushed?.(`Could not push "${product.name}" to Meta.`);
    } finally {
      setPushing(false);
    }
  }

  useEffect(() => {
    setTab("settings");
  }, [product?.id]);

  return (
    <Drawer anchor="right" open={Boolean(product)} onClose={onClose}>
      {product && (
        <Box sx={{ width: { xs: 340, sm: 440 }, height: "100%", display: "flex", flexDirection: "column" }}>
          <Stack direction="row" sx={{ alignItems: "center", justifyContent: "space-between", p: 3, pb: 2 }}>
            <Stack direction="row" sx={{ alignItems: "center", gap: 1.25 }}>
              <Box
                sx={{
                  width: 36,
                  height: 36,
                  borderRadius: 2,
                  bgcolor: "rgba(91, 110, 245, 0.12)",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                }}
              >
                <Inventory2RoundedIcon sx={{ color: "#5B6EF5" }} fontSize="small" />
              </Box>
              <Typography variant="h6" sx={{ fontSize: "1.1rem" }}>
                {product.name}
              </Typography>
            </Stack>
            <Stack direction="row" sx={{ alignItems: "center", gap: 1 }}>
              {product.metaRetailerId && (
                <Button
                  size="small"
                  variant="outlined"
                  startIcon={<CloudUploadRoundedIcon fontSize="small" />}
                  onClick={handlePushToMeta}
                  disabled={pushing}
                >
                  {pushing ? "Pushing…" : "Push to Meta"}
                </Button>
              )}
              <IconButton onClick={onClose} size="small">
                <CloseRoundedIcon fontSize="small" />
              </IconButton>
            </Stack>
          </Stack>

          <Tabs
            value={tab}
            onChange={(_, v) => setTab(v)}
            variant="scrollable"
            scrollButtons="auto"
            allowScrollButtonsMobile
            sx={{ px: 3, minHeight: 36 }}
          >
            <Tab value="settings" label="Settings" sx={{ minHeight: 36, py: 0.5 }} />
            <Tab value="variants" label="Variants" sx={{ minHeight: 36, py: 0.5 }} />
            <Tab value="addons" label="Add-ons" sx={{ minHeight: 36, py: 0.5 }} />
          </Tabs>

          <Box sx={{ flex: 1, overflowY: "auto", p: 3 }}>
            {tab === "settings" && (
              <ProductSettingsPanel product={product} categories={categories} onUpdated={onUpdated} onDelete={onDelete} />
            )}
            {tab === "variants" && <ProductVariantsPanel product={product} onUpdated={onUpdated} />}
            {tab === "addons" && <ProductAddonsPanel product={product} onUpdated={onUpdated} />}
          </Box>
        </Box>
      )}
    </Drawer>
  );
}
