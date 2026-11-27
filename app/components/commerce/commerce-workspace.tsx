import { Children, cloneElement, isValidElement, useCallback, useEffect, useState, type ReactNode } from "react";
import { Link } from "react-router";
import {
  Alert,
  Box,
  Button,
  Chip,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  MenuItem,
  Paper,
  Stack,
  Tab,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Tabs,
  TextField,
  Typography,
} from "@mui/material";
import AddRounded from "@mui/icons-material/AddRounded";
import SyncRounded from "@mui/icons-material/SyncRounded";
import RefreshRounded from "@mui/icons-material/RefreshRounded";
import { AppLayout } from "~/components/app-layout/app-layout";
import { RoleGuard } from "~/components/role-guard/role-guard";
import { useAuth } from "~/hooks/use-auth";
import { apiClient } from "~/utils/api-client";
import { commerceApi } from "~/utils/commerce-api";
import { CommerceSetupPanel } from "./commerce-setup";
import { CreateProductDialog } from "./catalog/create-product-dialog";
import { ProductDetailDrawer } from "./catalog/product-detail-drawer";
import { CategoryListPanel } from "./catalog/category-list-panel";
import { AddonLibraryPanel } from "./catalog/addon-library-panel";
import { InventoryPanel } from "./catalog/inventory-panel";
import { CreateBranchDialog } from "./branches/create-branch-dialog";
import { BranchDetailDrawer } from "./branches/branch-detail-drawer";
import { OrderDetailDrawer } from "./orders/order-detail-drawer";
import { OrderSessionsPanel } from "./orders/order-sessions-panel";
import { PaginatedListFooter } from "~/components/common/paginated-list-footer";
import type { Branch, Category, CommerceSalesReportRow, CommerceSetting, Order, Payment, Product } from "~/data/types";

type Section = "setup" | "products" | "locations" | "orders" | "payments" | "reports";
const pages: { id: Section; label: string; path: string }[] = [
  { id: "setup", label: "Setup", path: "/commerce" },
  { id: "products", label: "Products", path: "/commerce-catalog" },
  { id: "locations", label: "Locations", path: "/commerce-branches" },
  { id: "orders", label: "Orders", path: "/commerce-orders" },
  { id: "payments", label: "Payments", path: "/commerce-payments" },
  { id: "reports", label: "Reports", path: "/commerce-reports" },
];
const money = (n: number, currency = "INR") =>
  new Intl.NumberFormat("en-IN", { style: "currency", currency }).format(n / 100);
const errorText = (e: unknown) => (e instanceof Error ? e.message : "The action could not be completed. Try again.");

function Records({ heads, children }: { heads: string[]; children: ReactNode }) {
  const rows = Children.map(children, (row) => {
    if (!isValidElement<{ children?: ReactNode }>(row) || !row.props.children) return row;
    return cloneElement(
      row,
      {},
      Children.map(row.props.children, (cell, index) => {
        if (!isValidElement<{ children?: ReactNode }>(cell)) return cell;
        return cloneElement(
          cell,
          {},
          <>
            <Typography
              component="span"
              variant="caption"
              color="text.secondary"
              sx={{ display: { xs: "block", sm: "none" }, fontWeight: 700, mb: 0.5 }}
            >
              {heads[index]}
            </Typography>
            {cell.props.children}
          </>,
        );
      }),
    );
  });
  return (
    <TableContainer component={Paper} variant="outlined" sx={{ borderRadius: 2 }}>
      <Table
        size="small"
        sx={{
          "@media(max-width:599px)": {
            display: "block",
            "& thead": { display: "none" },
            "& tbody": { display: "block" },
            "& tr": { display: "block", p: 2, borderBottom: "1px solid", borderColor: "divider" },
            "& td": { display: "block", border: 0, p: 1, width: "100%", maxWidth: "none", whiteSpace: "normal" },
            "& td > .MuiStack-root": { flexWrap: "wrap", gap: 1 },
          },
        }}
      >
        <TableHead>
          <TableRow>
            {heads.map((h) => (
              <TableCell key={h} sx={{ py: 2, fontWeight: 700, whiteSpace: "nowrap" }}>
                {h}
              </TableCell>
            ))}
          </TableRow>
        </TableHead>
        <TableBody>{rows}</TableBody>
      </Table>
    </TableContainer>
  );
}
function Empty({ message, columns }: { message: string; columns: number }) {
  return (
    <TableRow>
      <TableCell colSpan={columns} sx={{ py: 6, textAlign: "center", color: "text.secondary" }}>
        {message}
      </TableCell>
    </TableRow>
  );
}
function State({ value }: { value: string }) {
  return (
    <Chip
      label={value.replaceAll("_", " ")}
      size="small"
      variant="outlined"
      color={value === "error" || value === "failed" ? "error" : value === "conflict" ? "warning" : "default"}
    />
  );
}

export function CommerceWorkspace({ section }: { section: Section }) {
  const { user } = useAuth();
  const manage = user?.role === "admin" || user?.role === "manager";
  const [settings, setSettings] = useState<CommerceSetting | null>(null);
  const [products, setProducts] = useState<Product[]>([]);
  const [branches, setBranches] = useState<Branch[]>([]);
  const [orders, setOrders] = useState<Order[]>([]);
  const [payments, setPayments] = useState<Payment[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [sales, setSales] = useState<CommerceSalesReportRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [query, setQuery] = useState("");
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("");
  const [subtab, setSubtab] = useState("products");
  const [newProduct, setNewProduct] = useState(false);
  const [newBranch, setNewBranch] = useState(false);
  const [product, setProduct] = useState<Product | null>(null);
  const [branch, setBranch] = useState<Branch | null>(null);
  const [order, setOrder] = useState<Order | null>(null);
  const [deletion, setDeletion] = useState<{ type: "product" | "branch"; id: string; name: string } | null>(null);
  const [from, setFrom] = useState(() => new Date(Date.now() - 29 * 86400000).toISOString().slice(0, 10));
  const [to, setTo] = useState(() => new Date().toISOString().slice(0, 10));
  useEffect(() => {
    const timer = setTimeout(() => {
      setSearch(query.trim());
      setPage(1);
    }, 300);
    return () => clearTimeout(timer);
  }, [query]);
  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      if (section === "setup") setSettings(await apiClient.getCommerceSettings());
      if (section === "products") {
        const [p, c, s] = await Promise.all([
          apiClient.listProducts({ page, search: search || undefined }),
          apiClient.listCategories({ perPage: 100 }),
          apiClient.getCommerceSettings(),
        ]);
        setProducts(p.data);
        setLastPage(p.meta.lastPage);
        setCategories(c.data);
        setSettings(s);
      }
      if (section === "locations") {
        const r = await apiClient.listBranches({ page, search: search || undefined });
        setBranches(r.data);
        setLastPage(r.meta.lastPage);
      }
      if (section === "orders") {
        const r = await apiClient.listOrders({ page, ...(status ? { status: status as Order["status"] } : {}) });
        setOrders(r.data);
        setLastPage(r.meta.lastPage);
      }
      if (section === "payments") {
        const r = await apiClient.listPayments({ page });
        setPayments(r.data);
        setLastPage(r.meta.lastPage);
      }
      if (section === "reports") {
        if (!from || !to || from > to) throw Error("Choose a valid date range.");
        setSales(await apiClient.getCommerceSalesReport({ from, to, groupBy: "day" }));
      }
    } catch (e) {
      setError(errorText(e));
    } finally {
      setLoading(false);
    }
  }, [section, page, search, status, from, to]);
  useEffect(() => {
    void load();
  }, [load]);
  async function act(action: () => Promise<unknown>, message?: string) {
    setBusy(true);
    setError(null);
    try {
      await action();
      if (message) setNotice(message);
      await load();
    } catch (e) {
      setError(errorText(e));
    } finally {
      setBusy(false);
    }
  }
  async function openProduct(p: Product) {
    await act(async () => setProduct(await apiClient.getProduct(p.id)));
  }
  async function remove() {
    if (!deletion) return;
    await act(async () => {
      if (deletion.type === "product") {
        await apiClient.deleteProduct(deletion.id);
        setProduct(null);
      } else {
        await apiClient.deleteBranch(deletion.id);
        setBranch(null);
      }
      setDeletion(null);
    }, "Deleted successfully.");
  }
  const title = section === "setup" ? "Commerce setup" : pages.find((p) => p.id === section)!.label;
  const descriptions: Record<Section, string> = {
    setup: "Connect your catalog, configure checkout and start selling on WhatsApp.",
    products: "Manage your products and review changes between your store and Meta.",
    locations: "Manage fulfillment locations, delivery areas and store availability.",
    orders: "Follow customer orders from checkout to fulfillment.",
    payments: "Request payment in WhatsApp and verify money received.",
    reports: "Review placed order value over your selected date range.",
  };
  const visiblePages = pages.filter((p) => manage || !["setup", "reports"].includes(p.id));
  return (
    <AppLayout>
      <RoleGuard allow={["admin", "manager", "branch_manager", "staff"]}>
        <Box sx={{ flex: 1, minWidth: 0, overflowY: "auto", p: { xs: 2, md: 4 }, bgcolor: "background.default" }}>
          <Stack
            direction={{ xs: "column", sm: "row" }}
            spacing={2}
            sx={{ justifyContent: "space-between", alignItems: { sm: "center" }, mb: 3 }}
          >
            <Box>
              <Typography variant="overline" color="text.secondary">
                COMMERCE
              </Typography>
              <Typography component="h1" variant="h4" sx={{ fontSize: { xs: 26, md: 30 } }}>
                {title}
              </Typography>
              <Typography color="text.secondary" variant="body2" sx={{ mt: 1 }}>
                {descriptions[section]}
              </Typography>
            </Box>
            <Stack direction="row" spacing={1} sx={{ flexWrap: "wrap", gap: 1 }}>
              <Button
                aria-label="Refresh commerce data"
                startIcon={<RefreshRounded />}
                variant="outlined"
                onClick={load}
                disabled={loading || busy}
              >
                Refresh
              </Button>
              {section === "products" && manage && (
                <>
                  <Button
                    startIcon={<SyncRounded />}
                    variant="outlined"
                    disabled={busy || !settings?.metaCatalogId}
                    onClick={() =>
                      act(
                        () => commerceApi.sync(),
                        "Sync queued. Refresh to see verified results. Automatic sync runs every five minutes when enabled.",
                      )
                    }
                  >
                    Sync both ways
                  </Button>
                  <Button
                    variant="contained"
                    disableElevation
                    startIcon={<AddRounded />}
                    onClick={() => setNewProduct(true)}
                  >
                    Add product
                  </Button>
                </>
              )}
              {section === "locations" && manage && (
                <Button
                  variant="contained"
                  disableElevation
                  startIcon={<AddRounded />}
                  onClick={() => setNewBranch(true)}
                >
                  Add location
                </Button>
              )}
            </Stack>
          </Stack>
          <Tabs
            value={section}
            variant="scrollable"
            scrollButtons="auto"
            aria-label="Commerce sections"
            sx={{ mb: 3, borderBottom: 1, borderColor: "divider" }}
          >
            {visiblePages.map((p) => (
              <Tab key={p.id} value={p.id} label={p.label} component={Link} to={p.path} />
            ))}
          </Tabs>
          {error && (
            <Alert
              severity="error"
              sx={{ mb: 3 }}
              action={
                <Button onClick={load} color="inherit">
                  Retry
                </Button>
              }
            >
              {error}
            </Alert>
          )}
          {notice && (
            <Alert severity="success" onClose={() => setNotice(null)} sx={{ mb: 3 }}>
              {notice}
            </Alert>
          )}
          {loading ? (
            <Box sx={{ display: "flex", justifyContent: "center", py: 8 }}>
              <CircularProgress aria-label="Loading commerce" />
            </Box>
          ) : (
            <>
              {section === "setup" &&
                settings &&
                (manage ? (
                  <CommerceSetupPanel settings={settings} onSaved={setSettings} />
                ) : (
                  <Alert severity="info">Ask an administrator to configure commerce.</Alert>
                ))}
              {section === "products" && (
                <>
                  {settings?.syncError && (
                    <Alert severity="error" sx={{ mb: 3 }}>
                      {settings.syncError}
                    </Alert>
                  )}
                  {!settings?.metaCatalogId && (
                    <Alert severity="info" sx={{ mb: 3 }}>
                      Your store is not connected to Meta yet. {manage && <Link to="/commerce">Connect a catalog</Link>}
                    </Alert>
                  )}
                  <Stack
                    direction={{ xs: "column", md: "row" }}
                    spacing={2}
                    sx={{ mb: 3, justifyContent: "space-between", alignItems: { md: "center" } }}
                  >
                    <Tabs
                      value={subtab}
                      onChange={(_, v) => setSubtab(v)}
                      variant="scrollable"
                      aria-label="Product tools"
                    >
                      <Tab value="products" label="Products" />
                      <Tab value="categories" label="Categories" />
                      {manage && <Tab value="addons" label="Add-ons" />}
                      <Tab value="inventory" label="Inventory" />
                    </Tabs>
                    <Typography variant="caption" color="text.secondary">
                      {settings?.lastSyncAt
                        ? `Last sync: ${new Date(settings.lastSyncAt).toLocaleString()}`
                        : "No completed sync yet"}
                    </Typography>
                  </Stack>
                  {subtab === "products" && (
                    <>
                      <TextField
                        label="Search products"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        size="small"
                        sx={{ mb: 3, width: { xs: "100%", sm: 320 } }}
                      />
                      <Records heads={["Product", "Price", "Availability", "Meta sync", "Actions"]}>
                        {products.map((p) => (
                          <TableRow key={p.id}>
                            <TableCell>
                              <Button
                                onClick={() => openProduct(p)}
                                sx={{ textAlign: "left", justifyContent: "flex-start", minHeight: 44 }}
                              >
                                {p.name}
                              </Button>
                              <Typography variant="caption" component="div" color="text.secondary">
                                {p.sku || "No SKU"}
                              </Typography>
                            </TableCell>
                            <TableCell sx={{ whiteSpace: "nowrap" }}>
                              {money(p.salePrice ?? p.basePrice, settings?.currency)}
                            </TableCell>
                            <TableCell>
                              <State value={p.availability} />
                            </TableCell>
                            <TableCell>
                              <State value={p.metaSyncState ?? "local"} />
                              {p.metaSyncError && (
                                <Typography variant="caption" component="div" sx={{ maxWidth: 240, mt: 1 }}>
                                  {p.metaSyncError}
                                </Typography>
                              )}
                            </TableCell>
                            <TableCell>
                              <Stack direction="row" spacing={1}>
                                <Button onClick={() => openProduct(p)}>Details</Button>
                                {manage && p.metaSyncState === "conflict" && (
                                  <>
                                    <Button
                                      disabled={busy}
                                      onClick={() =>
                                        act(
                                          () => commerceApi.resolve(p.id, "local"),
                                          "Local copy submitted to Meta; awaiting confirmation.",
                                        )
                                      }
                                    >
                                      Keep local
                                    </Button>
                                    <Button
                                      disabled={busy}
                                      onClick={() =>
                                        act(() => commerceApi.resolve(p.id, "meta"), "Meta copy imported.")
                                      }
                                    >
                                      Use Meta
                                    </Button>
                                  </>
                                )}
                              </Stack>
                            </TableCell>
                          </TableRow>
                        ))}
                        {!products.length && (
                          <Empty
                            columns={5}
                            message="No products yet. Add a product or connect your Meta catalog to import your range."
                          />
                        )}
                      </Records>
                      <PaginatedListFooter page={page} lastPage={lastPage} onPageChange={setPage} />
                    </>
                  )}
                  {subtab === "categories" && <CategoryListPanel categories={categories} onChanged={load} />}
                  {subtab === "addons" && <AddonLibraryPanel />}
                  {subtab === "inventory" && <InventoryPanel />}
                </>
              )}
              {section === "locations" && (
                <>
                  <TextField
                    label="Search locations"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    size="small"
                    sx={{ mb: 3, width: { xs: "100%", sm: 320 } }}
                  />
                  <Records heads={["Location", "Address", "Status", "Fulfillment", "Actions"]}>
                    {branches.map((b) => (
                      <TableRow key={b.id}>
                        <TableCell sx={{ fontWeight: 700 }}>
                          {b.name}
                          {b.isDefault && (
                            <Typography variant="caption" component="div">
                              Default location
                            </Typography>
                          )}
                        </TableCell>
                        <TableCell>{b.address || "No address added"}</TableCell>
                        <TableCell>
                          <State value={b.status} />
                        </TableCell>
                        <TableCell>Delivery from {money(b.defaultDeliveryCharge ?? 0, b.currency)}</TableCell>
                        <TableCell>
                          <Button onClick={() => setBranch(b)}>Manage location</Button>
                        </TableCell>
                      </TableRow>
                    ))}
                    {!branches.length && (
                      <Empty columns={5} message="Add your first location to configure pickup, delivery and stock." />
                    )}
                  </Records>
                  <PaginatedListFooter page={page} lastPage={lastPage} onPageChange={setPage} />
                </>
              )}
              {section === "orders" && (
                <>
                  <Stack direction={{ xs: "column", sm: "row" }} spacing={2} sx={{ mb: 3 }}>
                    <TextField
                      label="Order status"
                      select
                      size="small"
                      value={status}
                      onChange={(e) => {
                        setStatus(e.target.value);
                        setPage(1);
                      }}
                      slotProps={{ select: { native: true }, inputLabel: { shrink: true } }}
                      sx={{ minWidth: 220, "& .MuiNativeSelect-select": { paddingRight: "16px" } }}
                    >
                      <option value="">All statuses</option>
                      {[
                        "pending",
                        "confirmed",
                        "preparing",
                        "ready",
                        "out_for_delivery",
                        "delivered",
                        "completed",
                        "cancelled",
                      ].map((s) => (
                        <option value={s} key={s}>
                          {s.replaceAll("_", " ")}
                        </option>
                      ))}
                    </TextField>
                    <Button onClick={() => setSubtab((s) => (s === "sessions" ? "products" : "sessions"))}>
                      {subtab === "sessions" ? "Show orders" : "View active carts"}
                    </Button>
                  </Stack>
                  {subtab === "sessions" ? (
                    <OrderSessionsPanel />
                  ) : (
                    <>
                      <Records heads={["Order", "Customer", "Location", "Status", "Total", "Payment", "Actions"]}>
                        {orders.map((o) => (
                          <TableRow key={o.id}>
                            <TableCell sx={{ fontWeight: 700 }}>#{o.orderNumber}</TableCell>
                            <TableCell>{o.contactName ?? "Customer"}</TableCell>
                            <TableCell>{o.branchName ?? "—"}</TableCell>
                            <TableCell>
                              <State value={o.status} />
                            </TableCell>
                            <TableCell sx={{ whiteSpace: "nowrap" }}>{money(o.grandTotal, o.currency)}</TableCell>
                            <TableCell>
                              <State value={o.paymentStatus} />
                            </TableCell>
                            <TableCell>
                              <Stack direction="row" spacing={1}>
                                <Button onClick={() => act(async () => setOrder(await apiClient.getOrder(o.id)))}>
                                  Details
                                </Button>
                                {o.paymentStatus !== "paid" && o.status !== "cancelled" && (
                                  <Button
                                    disabled={busy}
                                    onClick={() =>
                                      act(
                                        () => commerceApi.requestPayment(o.id),
                                        "Payment request submitted to WhatsApp. Check Payments for verification.",
                                      )
                                    }
                                  >
                                    Request payment
                                  </Button>
                                )}
                              </Stack>
                            </TableCell>
                          </TableRow>
                        ))}
                        {!orders.length && (
                          <Empty
                            columns={7}
                            message="Orders will appear here when customers finish checkout on WhatsApp."
                          />
                        )}
                      </Records>
                      <PaginatedListFooter page={page} lastPage={lastPage} onPageChange={setPage} />
                    </>
                  )}
                </>
              )}
              {section === "payments" && (
                <>
                  <Alert severity="info" sx={{ mb: 3 }}>
                    WhatsApp checkout uses the Razorpay account linked in WhatsApp Manager. Verify pending payments
                    before retrying a request.
                  </Alert>
                  <Records heads={["Order", "Method", "Amount", "Status", "Reference", "Actions"]}>
                    {payments.map((p) => (
                      <TableRow key={p.id}>
                        <TableCell>#{p.orderNumber}</TableCell>
                        <TableCell>{p.method === "gateway:whatsapp" ? "WhatsApp · Razorpay" : p.method}</TableCell>
                        <TableCell sx={{ whiteSpace: "nowrap" }}>{money(p.amount)}</TableCell>
                        <TableCell>
                          <State value={p.status} />
                          {p.failureReason && (
                            <Typography variant="caption" component="div" sx={{ maxWidth: 240, mt: 1 }}>
                              {p.failureReason}
                            </Typography>
                          )}
                        </TableCell>
                        <TableCell
                          sx={{ fontFamily: "monospace", fontSize: 12, overflowWrap: "anywhere", maxWidth: 200 }}
                        >
                          {p.providerReference ?? "Not sent"}
                        </TableCell>
                        <TableCell>
                          {p.method === "gateway:whatsapp" ? (
                            <Button
                              disabled={busy || p.status === "paid"}
                              onClick={() =>
                                act(async () => {
                                  const result = await commerceApi.verifyPayment(p.id);
                                  setNotice(`Verified payment status: ${result.status}.`);
                                })
                              }
                            >
                              Verify payment
                            </Button>
                          ) : ["cod", "upi"].includes(p.method) && p.status !== "paid" ? (
                            <Button
                              disabled={busy}
                              onClick={() => act(() => apiClient.markPaymentPaid(p.id), "Payment marked received.")}
                            >
                              Confirm received
                            </Button>
                          ) : (
                            "—"
                          )}
                        </TableCell>
                      </TableRow>
                    ))}
                    {!payments.length && (
                      <Empty
                        columns={6}
                        message="No payments yet. Request payment from an unpaid order to get started."
                      />
                    )}
                  </Records>
                  <PaginatedListFooter page={page} lastPage={lastPage} onPageChange={setPage} />
                </>
              )}
              {section === "reports" && (
                <>
                  <Stack direction={{ xs: "column", sm: "row" }} spacing={2} sx={{ mb: 3 }}>
                    <TextField
                      label="From"
                      type="date"
                      value={from}
                      onChange={(e) => setFrom(e.target.value)}
                      slotProps={{ inputLabel: { shrink: true } }}
                    />
                    <TextField
                      label="To"
                      type="date"
                      value={to}
                      onChange={(e) => setTo(e.target.value)}
                      slotProps={{ inputLabel: { shrink: true } }}
                    />
                  </Stack>
                  <Box sx={{ display: "grid", gridTemplateColumns: { xs: "1fr", sm: "1fr 1fr" }, gap: 3, mb: 3 }}>
                    {[
                      ["Placed order value", money(sales.reduce((n, r) => n + r.revenue, 0))],
                      ["Orders placed", sales.reduce((n, r) => n + r.orderCount, 0).toLocaleString()],
                    ].map(([label, value]) => (
                      <Paper variant="outlined" key={label} sx={{ p: 3, borderRadius: 2 }}>
                        <Typography variant="h4">{value}</Typography>
                        <Typography variant="body2" color="text.secondary" sx={{ mt: 1 }}>
                          {label}
                        </Typography>
                      </Paper>
                    ))}
                  </Box>
                  <Records heads={["Date", "Orders placed", "Order value"]}>
                    {sales.map((r) => (
                      <TableRow key={r.period}>
                        <TableCell>{r.period}</TableCell>
                        <TableCell>{r.orderCount}</TableCell>
                        <TableCell>{money(r.revenue)}</TableCell>
                      </TableRow>
                    ))}
                    {!sales.length && <Empty columns={3} message="No orders in this date range." />}
                  </Records>
                  <Typography variant="caption" component="div" color="text.secondary" sx={{ mt: 2 }}>
                    Order value is not settled revenue. Check Payments to confirm money received.
                  </Typography>
                </>
              )}
            </>
          )}
          <CreateProductDialog
            open={newProduct}
            categories={categories}
            onClose={() => setNewProduct(false)}
            onCreate={async (input) => {
              const p = await apiClient.createProduct(input);
              setProduct(p);
              await load();
            }}
          />
          <CreateBranchDialog
            open={newBranch}
            onClose={() => setNewBranch(false)}
            onCreate={async (input) => {
              await apiClient.createBranch({ ...input, apiConnectionId: settings?.apiConnectionId ?? null });
              await load();
            }}
          />
          <ProductDetailDrawer
            product={product}
            categories={categories}
            onClose={() => setProduct(null)}
            onUpdated={(p) => {
              setProduct(p);
              void load();
            }}
            onDelete={async () => {
              if (product) setDeletion({ type: "product", id: product.id, name: product.name });
            }}
            onPushed={setNotice}
          />
          <BranchDetailDrawer
            branch={branch}
            onClose={() => setBranch(null)}
            onUpdated={(b) => {
              setBranch(b);
              void load();
            }}
            onDeleted={() => void load()}
            onDelete={async () => {
              if (branch) setDeletion({ type: "branch", id: branch.id, name: branch.name });
            }}
          />
          <OrderDetailDrawer
            order={order}
            onClose={() => setOrder(null)}
            onUpdated={(o) => {
              setOrder(o);
              void load();
            }}
          />
          <Dialog
            open={Boolean(deletion)}
            onClose={() => !busy && setDeletion(null)}
            fullWidth
            maxWidth="xs"
            aria-labelledby="commerce-delete-title"
          >
            <DialogTitle id="commerce-delete-title">Delete {deletion?.name}?</DialogTitle>
            <DialogContent>
              <Typography>
                {deletion?.type === "product"
                  ? "This removes the product from your store. If it was synced, the next sync also removes it from Meta."
                  : "This removes the location and its delivery settings. Existing orders must remain intact."}
              </Typography>
            </DialogContent>
            <DialogActions>
              <Button disabled={busy} onClick={() => setDeletion(null)}>
                Cancel
              </Button>
              <Button color="error" variant="contained" disableElevation disabled={busy} onClick={remove}>
                Delete
              </Button>
            </DialogActions>
          </Dialog>
        </Box>
      </RoleGuard>
    </AppLayout>
  );
}
