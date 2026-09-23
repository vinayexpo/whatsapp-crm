import { useEffect, useState } from "react";
import Box from "@mui/material/Box";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Button from "@mui/material/Button";
import Paper from "@mui/material/Paper";
import Grid from "@mui/material/Grid";
import Chip from "@mui/material/Chip";
import Tabs from "@mui/material/Tabs";
import Tab from "@mui/material/Tab";
import TextField from "@mui/material/TextField";
import InputAdornment from "@mui/material/InputAdornment";
import CircularProgress from "@mui/material/CircularProgress";
import Alert from "@mui/material/Alert";
import Inventory2RoundedIcon from "@mui/icons-material/Inventory2Rounded";
import AddRoundedIcon from "@mui/icons-material/AddRounded";
import SearchRoundedIcon from "@mui/icons-material/SearchRounded";
import { AppLayout } from "~/components/app-layout/app-layout";
import { RoleGuard } from "~/components/role-guard/role-guard";
import { CreateProductDialog } from "~/components/commerce/catalog/create-product-dialog";
import { ProductDetailDrawer } from "~/components/commerce/catalog/product-detail-drawer";
import { CategoryListPanel } from "~/components/commerce/catalog/category-list-panel";
import { AddonLibraryPanel } from "~/components/commerce/catalog/addon-library-panel";
import { PaginatedListFooter } from "~/components/common/paginated-list-footer";
import { apiClient } from "~/utils/api-client";
import type { Category, Product } from "~/data/types";
import type { Route } from "./+types/commerce-catalog";

export function meta({}: Route.MetaArgs) {
  return [
    { title: "Catalog — Creative Connects" },
    { name: "description", content: "Manage products, categories, and add-ons for WhatsApp commerce." },
  ];
}

export default function CommerceCatalog() {
  const [tab, setTab] = useState<"products" | "categories" | "addons">("products");
  const [products, setProducts] = useState<Product[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [selectedProductId, setSelectedProductId] = useState<string | null>(null);
  const [createOpen, setCreateOpen] = useState(false);
  const [search, setSearch] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const timeout = setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => clearTimeout(timeout);
  }, [search]);

  useEffect(() => {
    setPage(1);
  }, [debouncedSearch]);

  function refreshCategories() {
    apiClient
      .listCategories({ perPage: 200 })
      .then(({ data }) => setCategories(data))
      .catch(() => {
        // categories stay empty on failure
      });
  }

  useEffect(() => {
    refreshCategories();
  }, []);

  useEffect(() => {
    if (tab !== "products") return;
    let cancelled = false;
    setLoading(true);
    setError(null);
    apiClient
      .listProducts({ page, search: debouncedSearch || undefined })
      .then(({ data, meta }) => {
        if (cancelled) return;
        setProducts(data);
        setLastPage(meta.lastPage);
      })
      .catch(() => {
        if (!cancelled) setError("Could not load products.");
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [tab, page, debouncedSearch]);

  const selectedProduct = products.find((p) => p.id === selectedProductId) ?? null;

  async function handleCreate(input: { name: string; basePrice: number; categoryId?: string | null }) {
    const created = await apiClient.createProduct(input);
    if (page === 1) {
      setProducts((prev) => [created, ...prev]);
    } else {
      setPage(1);
    }
    setSelectedProductId(created.id);
  }

  function handleUpdated(updated: Product) {
    setProducts((prev) => prev.map((p) => (p.id === updated.id ? updated : p)));
  }

  function handleDeleted(productId: string) {
    setProducts((prev) => prev.filter((p) => p.id !== productId));
    if (selectedProductId === productId) setSelectedProductId(null);
  }

  async function handleDelete() {
    if (!selectedProduct) return;
    await apiClient.deleteProduct(selectedProduct.id);
    handleDeleted(selectedProduct.id);
  }

  return (
    <AppLayout>
      <RoleGuard allow={["superadmin", "admin", "manager", "branch_manager", "staff"]}>
        <Box sx={{ p: { xs: 2, md: 4 }, flex: 1, minWidth: 0, overflowY: "auto" }}>
          <Stack
            direction="row"
            sx={{ alignItems: "flex-start", justifyContent: "space-between", mb: 3, flexWrap: "wrap", gap: 1.5 }}
          >
            <Stack>
              <Typography variant="h4" sx={{ fontSize: { xs: "1.5rem", md: "1.8rem" } }}>
                Catalog
              </Typography>
              <Typography variant="body2" sx={{ color: "text.secondary", mt: 0.5 }}>
                Manage the products, categories, and add-ons customers see when ordering over WhatsApp.
              </Typography>
            </Stack>
            {tab === "products" && (
              <Button variant="contained" startIcon={<AddRoundedIcon />} onClick={() => setCreateOpen(true)}>
                New Product
              </Button>
            )}
          </Stack>

          <Tabs
            value={tab}
            onChange={(_, v) => setTab(v)}
            variant="scrollable"
            scrollButtons="auto"
            allowScrollButtonsMobile
            sx={{ mb: 3, minHeight: 36 }}
          >
            <Tab value="products" label="Products" sx={{ minHeight: 36, py: 0.5 }} />
            <Tab value="categories" label="Categories" sx={{ minHeight: 36, py: 0.5 }} />
            <Tab value="addons" label="Add-on Library" sx={{ minHeight: 36, py: 0.5 }} />
          </Tabs>

          {tab === "products" && (
            <>
              <Stack direction={{ xs: "column", sm: "row" }} spacing={1.5} sx={{ mb: 2.5 }}>
                <TextField
                  placeholder="Search products…"
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
                    {products.map((product) => (
                      <Grid key={product.id} size={{ xs: 12, sm: 6, md: 4, lg: 3 }}>
                        <Paper
                          variant="outlined"
                          sx={{
                            borderRadius: 3,
                            p: 2.5,
                            cursor: "pointer",
                            transition: "border-color 0.15s",
                            "&:hover": { borderColor: "primary.main" },
                          }}
                          onClick={() => setSelectedProductId(product.id)}
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
                              <Inventory2RoundedIcon sx={{ color: "#5B6EF5" }} fontSize="small" />
                            </Box>
                            <Chip
                              label={product.isActive ? "Active" : "Inactive"}
                              size="small"
                              color={product.isActive ? "success" : "default"}
                              variant={product.isActive ? "filled" : "outlined"}
                            />
                          </Stack>
                          <Typography variant="subtitle1" sx={{ fontWeight: 700, mt: 1.5 }}>
                            {product.name}
                          </Typography>
                          <Typography variant="caption" sx={{ color: "text.secondary" }}>
                            {product.basePrice} · {product.variants.length} variant
                            {product.variants.length === 1 ? "" : "s"} · {product.addons.length} add-on
                            {product.addons.length === 1 ? "" : "s"}
                          </Typography>
                        </Paper>
                      </Grid>
                    ))}
                    {products.length === 0 && (
                      <Grid size={12}>
                        <Paper variant="outlined" sx={{ borderRadius: 3, p: 5, textAlign: "center" }}>
                          <Typography variant="body2" sx={{ color: "text.secondary" }}>
                            No products yet. Create one to start building your catalog.
                          </Typography>
                        </Paper>
                      </Grid>
                    )}
                  </Grid>

                  <PaginatedListFooter page={page} lastPage={lastPage} onPageChange={setPage} />
                </>
              )}
            </>
          )}

          {tab === "categories" && <CategoryListPanel categories={categories} onChanged={refreshCategories} />}

          {tab === "addons" && <AddonLibraryPanel />}
        </Box>

        <CreateProductDialog
          open={createOpen}
          categories={categories}
          onClose={() => setCreateOpen(false)}
          onCreate={handleCreate}
        />

        <ProductDetailDrawer
          product={selectedProduct}
          categories={categories}
          onClose={() => setSelectedProductId(null)}
          onUpdated={handleUpdated}
          onDelete={handleDelete}
        />
      </RoleGuard>
    </AppLayout>
  );
}
