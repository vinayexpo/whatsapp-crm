import { useEffect, useState } from "react";
import Box from "@mui/material/Box";
import Drawer from "@mui/material/Drawer";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import IconButton from "@mui/material/IconButton";
import Divider from "@mui/material/Divider";
import Select from "@mui/material/Select";
import MenuItem from "@mui/material/MenuItem";
import FormControl from "@mui/material/FormControl";
import InputLabel from "@mui/material/InputLabel";
import Chip from "@mui/material/Chip";
import CloseRoundedIcon from "@mui/icons-material/CloseRounded";
import ReceiptLongRoundedIcon from "@mui/icons-material/ReceiptLongRounded";
import { apiClient } from "~/utils/api-client";
import type { Order, OrderStatus, TeamMember } from "~/data/types";
import { OrderStatusChip } from "./order-status-chip";
import { PaymentStatusPanel } from "./payment-status-panel";

const ORDER_STATUSES: OrderStatus[] = [
  "pending",
  "confirmed",
  "preparing",
  "ready",
  "out_for_delivery",
  "delivered",
  "completed",
  "cancelled",
];

function formatMoney(amountMinorUnits: number, currency: string): string {
  return new Intl.NumberFormat(undefined, { style: "currency", currency, currencyDisplay: "narrowSymbol" }).format(
    amountMinorUnits / 100,
  );
}

interface OrderDetailDrawerProps {
  order: Order | null;
  onClose: () => void;
  onUpdated: (order: Order) => void;
}

export function OrderDetailDrawer({ order, onClose, onUpdated }: OrderDetailDrawerProps) {
  const [teamMembers, setTeamMembers] = useState<TeamMember[]>([]);
  const [savingStatus, setSavingStatus] = useState(false);
  const [savingAssignee, setSavingAssignee] = useState(false);

  useEffect(() => {
    if (!order) return;
    apiClient
      .listTeamMembers()
      .then(setTeamMembers)
      .catch(() => setTeamMembers([]));
  }, [order?.id]);

  if (!order) {
    return <Drawer anchor="right" open={false} onClose={onClose} />;
  }

  async function handleStatusChange(status: OrderStatus) {
    if (!order) return;
    setSavingStatus(true);
    try {
      const updated = await apiClient.updateOrderStatus(order.id, status);
      onUpdated(updated);
    } finally {
      setSavingStatus(false);
    }
  }

  async function handleAssigneeChange(staffUserId: string) {
    if (!order) return;
    setSavingAssignee(true);
    try {
      const updated = await apiClient.assignOrder(order.id, staffUserId || null);
      onUpdated(updated);
    } finally {
      setSavingAssignee(false);
    }
  }

  return (
    <Drawer anchor="right" open={Boolean(order)} onClose={onClose}>
      <Box sx={{ width: { xs: 340, sm: 460 }, height: "100%", display: "flex", flexDirection: "column" }}>
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
              <ReceiptLongRoundedIcon sx={{ color: "#5B6EF5" }} fontSize="small" />
            </Box>
            <Stack>
              <Typography variant="h6" sx={{ fontSize: "1.05rem" }}>
                #{order.orderNumber}
              </Typography>
              <Typography variant="caption" sx={{ color: "text.secondary" }}>
                {order.branchName ?? "No branch"}
              </Typography>
            </Stack>
          </Stack>
          <IconButton onClick={onClose} size="small">
            <CloseRoundedIcon fontSize="small" />
          </IconButton>
        </Stack>

        <Box sx={{ flex: 1, overflowY: "auto", p: 3, pt: 0 }}>
          <Stack spacing={2.5}>
            <Stack direction="row" spacing={1} sx={{ flexWrap: "wrap" }}>
              <OrderStatusChip status={order.status} />
              <Chip label={order.fulfillmentType === "delivery" ? "Delivery" : "Pickup"} size="small" variant="outlined" />
              <Chip label={order.paymentMethod.toUpperCase()} size="small" variant="outlined" />
              <Chip
                label={order.paymentStatus}
                size="small"
                variant="outlined"
                color={order.paymentStatus === "paid" ? "success" : "default"}
              />
            </Stack>

            <Stack spacing={1}>
              <Typography variant="subtitle2" sx={{ color: "text.secondary" }}>
                Customer
              </Typography>
              <Typography variant="body2">{order.contactName ?? "Unknown"}</Typography>
              {order.deliveryAddress && (
                <Typography variant="body2" sx={{ color: "text.secondary" }}>
                  {order.deliveryAddress}
                </Typography>
              )}
            </Stack>

            <Divider />

            <Stack spacing={1}>
              <Typography variant="subtitle2" sx={{ color: "text.secondary" }}>
                Items
              </Typography>
              {order.items.map((item) => (
                <Stack key={item.id} direction="row" sx={{ justifyContent: "space-between" }}>
                  <Typography variant="body2">
                    {item.quantity}× {item.nameSnapshot}
                  </Typography>
                  <Typography variant="body2" sx={{ fontWeight: 600 }}>
                    {formatMoney(item.lineTotal, order.currency)}
                  </Typography>
                </Stack>
              ))}
            </Stack>

            <Divider />

            <Stack spacing={0.75}>
              <Stack direction="row" sx={{ justifyContent: "space-between" }}>
                <Typography variant="body2" sx={{ color: "text.secondary" }}>
                  Subtotal
                </Typography>
                <Typography variant="body2">{formatMoney(order.subtotal, order.currency)}</Typography>
              </Stack>
              <Stack direction="row" sx={{ justifyContent: "space-between" }}>
                <Typography variant="body2" sx={{ color: "text.secondary" }}>
                  Delivery
                </Typography>
                <Typography variant="body2">{formatMoney(order.deliveryCharge, order.currency)}</Typography>
              </Stack>
              {order.discountTotal > 0 && (
                <Stack direction="row" sx={{ justifyContent: "space-between" }}>
                  <Typography variant="body2" sx={{ color: "text.secondary" }}>
                    Discount
                  </Typography>
                  <Typography variant="body2">-{formatMoney(order.discountTotal, order.currency)}</Typography>
                </Stack>
              )}
              <Stack direction="row" sx={{ justifyContent: "space-between" }}>
                <Typography variant="subtitle1" sx={{ fontWeight: 700 }}>
                  Total
                </Typography>
                <Typography variant="subtitle1" sx={{ fontWeight: 700 }}>
                  {formatMoney(order.grandTotal, order.currency)}
                </Typography>
              </Stack>
            </Stack>

            <Divider />

            <PaymentStatusPanel order={order} />

            <Divider />

            <FormControl size="small" fullWidth disabled={savingStatus}>
              <InputLabel id="order-status-label">Status</InputLabel>
              <Select
                labelId="order-status-label"
                label="Status"
                value={order.status}
                onChange={(e) => handleStatusChange(e.target.value as OrderStatus)}
              >
                {ORDER_STATUSES.map((status) => (
                  <MenuItem key={status} value={status}>
                    {status.replace(/_/g, " ")}
                  </MenuItem>
                ))}
              </Select>
            </FormControl>

            <FormControl size="small" fullWidth disabled={savingAssignee}>
              <InputLabel id="order-assignee-label">Assigned staff</InputLabel>
              <Select
                labelId="order-assignee-label"
                label="Assigned staff"
                value={order.assignedStaffUserId ?? ""}
                onChange={(e) => handleAssigneeChange(e.target.value)}
              >
                <MenuItem value="">Unassigned</MenuItem>
                {teamMembers.map((member) => (
                  <MenuItem key={member.id} value={member.id}>
                    {member.name}
                  </MenuItem>
                ))}
              </Select>
            </FormControl>

            {order.notes && (
              <Stack spacing={0.5}>
                <Typography variant="subtitle2" sx={{ color: "text.secondary" }}>
                  Notes
                </Typography>
                <Typography variant="body2">{order.notes}</Typography>
              </Stack>
            )}
          </Stack>
        </Box>
      </Box>
    </Drawer>
  );
}
