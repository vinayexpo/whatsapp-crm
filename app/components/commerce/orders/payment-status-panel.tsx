import { useEffect, useState } from "react";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Chip from "@mui/material/Chip";
import Button from "@mui/material/Button";
import Alert from "@mui/material/Alert";
import { apiClient, ApiError } from "~/utils/api-client";
import type { Order, Payment } from "~/data/types";

interface PaymentStatusPanelProps {
  order: Order;
}

function statusColor(status: Payment["status"]): "success" | "error" | "default" | "warning" {
  switch (status) {
    case "paid":
      return "success";
    case "failed":
      return "error";
    case "refunded":
      return "warning";
    default:
      return "default";
  }
}

export function PaymentStatusPanel({ order }: PaymentStatusPanelProps) {
  const [payments, setPayments] = useState<Payment[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [confirming, setConfirming] = useState<string | null>(null);

  useEffect(() => {
    apiClient
      .listPayments({ orderId: order.id })
      .then(({ data }) => setPayments(data))
      .catch(() => setPayments([]));
  }, [order.id]);

  async function handleMarkPaid(payment: Payment) {
    setError(null);
    setConfirming(payment.id);
    try {
      const updated = await apiClient.markPaymentPaid(payment.id);
      setPayments((prev) => prev.map((p) => (p.id === updated.id ? updated : p)));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to confirm payment.");
    } finally {
      setConfirming(null);
    }
  }

  if (payments.length === 0) {
    return null;
  }

  return (
    <Stack spacing={1}>
      <Typography variant="subtitle2" sx={{ color: "text.secondary" }}>
        Payments
      </Typography>
      {error && <Alert severity="error">{error}</Alert>}
      {payments.map((payment) => (
        <Stack
          key={payment.id}
          direction={{ xs: "column", sm: "row" }}
          spacing={1}
          sx={{ alignItems: { xs: "flex-start", sm: "center" }, justifyContent: "space-between" }}
        >
          <Stack direction="row" spacing={1} sx={{ alignItems: "center", flexWrap: "wrap" }}>
            <Chip label={payment.method} size="small" variant="outlined" />
            <Chip label={payment.status} size="small" color={statusColor(payment.status)} />
            {payment.failureReason && (
              <Typography variant="caption" sx={{ color: "error.main" }}>
                {payment.failureReason}
              </Typography>
            )}
          </Stack>
          {payment.status === "pending" && (payment.method === "cod" || payment.method === "upi") && (
            <Button
              size="small"
              variant="outlined"
              onClick={() => handleMarkPaid(payment)}
              disabled={confirming === payment.id}
            >
              Mark paid
            </Button>
          )}
        </Stack>
      ))}
    </Stack>
  );
}
