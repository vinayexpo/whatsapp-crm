import Table from "@mui/material/Table";
import TableBody from "@mui/material/TableBody";
import TableCell from "@mui/material/TableCell";
import TableContainer from "@mui/material/TableContainer";
import TableHead from "@mui/material/TableHead";
import TableRow from "@mui/material/TableRow";
import Paper from "@mui/material/Paper";
import Typography from "@mui/material/Typography";
import Box from "@mui/material/Box";
import type { Order } from "~/data/types";
import { OrderStatusChip } from "./order-status-chip";

function formatMoney(amountMinorUnits: number, currency: string): string {
  return new Intl.NumberFormat(undefined, { style: "currency", currency, currencyDisplay: "narrowSymbol" }).format(
    amountMinorUnits / 100,
  );
}

interface OrderListTableProps {
  orders: Order[];
  onSelect: (order: Order) => void;
}

export function OrderListTable({ orders, onSelect }: OrderListTableProps) {
  if (orders.length === 0) {
    return (
      <Paper variant="outlined" sx={{ borderRadius: 3, p: 5, textAlign: "center" }}>
        <Typography variant="body2" sx={{ color: "text.secondary" }}>
          No orders yet. Orders placed over WhatsApp will show up here.
        </Typography>
      </Paper>
    );
  }

  return (
    <TableContainer component={Paper} variant="outlined" sx={{ borderRadius: 3, overflowX: "auto" }}>
      <Table size="small" sx={{ minWidth: 640 }}>
        <TableHead>
          <TableRow>
            <TableCell>Order</TableCell>
            <TableCell>Customer</TableCell>
            <TableCell>Branch</TableCell>
            <TableCell>Status</TableCell>
            <TableCell align="right">Total</TableCell>
            <TableCell>Placed</TableCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {orders.map((order) => (
            <TableRow
              key={order.id}
              hover
              onClick={() => onSelect(order)}
              sx={{ cursor: "pointer", "&:last-child td": { border: 0 } }}
            >
              <TableCell>
                <Typography variant="body2" sx={{ fontWeight: 600 }}>
                  #{order.orderNumber}
                </Typography>
              </TableCell>
              <TableCell>{order.contactName ?? "—"}</TableCell>
              <TableCell>{order.branchName ?? "—"}</TableCell>
              <TableCell>
                <OrderStatusChip status={order.status} />
              </TableCell>
              <TableCell align="right">
                <Box sx={{ fontWeight: 600 }}>{formatMoney(order.grandTotal, order.currency)}</Box>
              </TableCell>
              <TableCell>
                <Typography variant="caption" sx={{ color: "text.secondary" }}>
                  {order.placedAt ? new Date(order.placedAt).toLocaleString() : "—"}
                </Typography>
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </TableContainer>
  );
}
