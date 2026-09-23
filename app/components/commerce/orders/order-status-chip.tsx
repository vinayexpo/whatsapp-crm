import Chip from "@mui/material/Chip";
import type { OrderStatus } from "~/data/types";

const STATUS_CONFIG: Record<OrderStatus, { label: string; color: "default" | "info" | "warning" | "success" | "error" }> = {
  pending: { label: "Pending", color: "warning" },
  confirmed: { label: "Confirmed", color: "info" },
  preparing: { label: "Preparing", color: "info" },
  ready: { label: "Ready", color: "info" },
  out_for_delivery: { label: "Out for delivery", color: "info" },
  delivered: { label: "Delivered", color: "success" },
  completed: { label: "Completed", color: "success" },
  cancelled: { label: "Cancelled", color: "error" },
};

export function OrderStatusChip({ status }: { status: OrderStatus }) {
  const config = STATUS_CONFIG[status] ?? { label: status, color: "default" as const };

  return <Chip label={config.label} size="small" color={config.color} variant="filled" />;
}
