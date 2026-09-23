import { useEffect, useState } from "react";
import Box from "@mui/material/Box";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Select from "@mui/material/Select";
import MenuItem from "@mui/material/MenuItem";
import CircularProgress from "@mui/material/CircularProgress";
import Alert from "@mui/material/Alert";
import Tabs from "@mui/material/Tabs";
import Tab from "@mui/material/Tab";
import { AppLayout } from "~/components/app-layout/app-layout";
import { RoleGuard } from "~/components/role-guard/role-guard";
import { OrderListTable } from "~/components/commerce/orders/order-list-table";
import { OrderDetailDrawer } from "~/components/commerce/orders/order-detail-drawer";
import { OrderSessionsPanel } from "~/components/commerce/orders/order-sessions-panel";
import { PaginatedListFooter } from "~/components/common/paginated-list-footer";
import { apiClient } from "~/utils/api-client";
import type { Branch, Order, OrderStatus } from "~/data/types";
import type { Route } from "./+types/commerce-orders";

export function meta({}: Route.MetaArgs) {
  return [
    { title: "Orders — Creative Connects" },
    { name: "description", content: "Track and manage orders placed over WhatsApp commerce." },
  ];
}

export default function CommerceOrders() {
  const [tab, setTab] = useState<"orders" | "sessions">("orders");
  const [orders, setOrders] = useState<Order[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [selectedOrderId, setSelectedOrderId] = useState<string | null>(null);
  const [statusFilter, setStatusFilter] = useState<OrderStatus | "all">("all");
  const [branchFilter, setBranchFilter] = useState<string | "all">("all");
  const [branches, setBranches] = useState<Branch[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    apiClient
      .listBranches({ perPage: 100 })
      .then(({ data }) => setBranches(data))
      .catch(() => {});
  }, []);

  useEffect(() => {
    setPage(1);
  }, [statusFilter, branchFilter]);

  useEffect(() => {
    if (tab !== "orders") return;
    let cancelled = false;
    setLoading(true);
    setError(null);
    apiClient
      .listOrders({
        page,
        status: statusFilter === "all" ? undefined : statusFilter,
        branchId: branchFilter === "all" ? undefined : branchFilter,
      })
      .then(({ data, meta }) => {
        if (cancelled) return;
        setOrders(data);
        setLastPage(meta.lastPage);
      })
      .catch(() => {
        if (!cancelled) setError("Could not load orders.");
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [tab, page, statusFilter, branchFilter]);

  const selectedOrder = orders.find((o) => o.id === selectedOrderId) ?? null;

  function handleUpdated(updated: Order) {
    setOrders((prev) => prev.map((o) => (o.id === updated.id ? updated : o)));
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
                Orders
              </Typography>
              <Typography variant="body2" sx={{ color: "text.secondary", mt: 0.5 }}>
                Orders placed by customers over WhatsApp.
              </Typography>
            </Stack>
          </Stack>

          <Tabs
            value={tab}
            onChange={(_, v) => setTab(v)}
            variant="scrollable"
            scrollButtons="auto"
            allowScrollButtonsMobile
            sx={{ mb: 3, minHeight: 36 }}
          >
            <Tab value="orders" label="Orders" sx={{ minHeight: 36, py: 0.5 }} />
            <Tab value="sessions" label="Order Sessions" sx={{ minHeight: 36, py: 0.5 }} />
          </Tabs>

          {tab === "orders" && (
            <>
              <Stack direction={{ xs: "column", sm: "row" }} spacing={1.5} sx={{ mb: 2.5 }}>
                <Select
                  size="small"
                  value={statusFilter}
                  onChange={(e) => setStatusFilter(e.target.value as OrderStatus | "all")}
                  sx={{ minWidth: 180 }}
                >
                  <MenuItem value="all">All statuses</MenuItem>
                  <MenuItem value="pending">Pending</MenuItem>
                  <MenuItem value="confirmed">Confirmed</MenuItem>
                  <MenuItem value="preparing">Preparing</MenuItem>
                  <MenuItem value="ready">Ready</MenuItem>
                  <MenuItem value="out_for_delivery">Out for delivery</MenuItem>
                  <MenuItem value="delivered">Delivered</MenuItem>
                  <MenuItem value="completed">Completed</MenuItem>
                  <MenuItem value="cancelled">Cancelled</MenuItem>
                </Select>

                {branches.length > 1 && (
                  <Select
                    size="small"
                    value={branchFilter}
                    onChange={(e) => setBranchFilter(e.target.value)}
                    sx={{ minWidth: 180 }}
                  >
                    <MenuItem value="all">All branches</MenuItem>
                    {branches.map((branch) => (
                      <MenuItem key={branch.id} value={branch.id}>
                        {branch.name}
                      </MenuItem>
                    ))}
                  </Select>
                )}
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
                  <OrderListTable orders={orders} onSelect={(order) => setSelectedOrderId(order.id)} />

                  <PaginatedListFooter page={page} lastPage={lastPage} onPageChange={setPage} />
                </>
              )}
            </>
          )}

          {tab === "sessions" && <OrderSessionsPanel />}
        </Box>

        <OrderDetailDrawer order={selectedOrder} onClose={() => setSelectedOrderId(null)} onUpdated={handleUpdated} />
      </RoleGuard>
    </AppLayout>
  );
}
