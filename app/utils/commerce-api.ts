import { apiRequest } from "./api-client";
import type { CommerceSetting, Payment } from "~/data/types";

export type CommerceOverview = {
  products: number;
  locations: number;
  orders: number;
  conflicts: number;
  pendingSync: number;
  syncErrors: number;
  collected: number;
  awaitingPayment: number;
};
export type CommerceSetup = Partial<CommerceSetting> & {
  razorpayKeyId?: string;
  razorpayKeySecret?: string;
  razorpayWebhookSecret?: string;
};
const post = async <T>(path: string, body?: object) =>
  (
    await apiRequest<{ data: T }>(`/api/v1/commerce/${path}`, {
      method: "POST",
      ...(body ? { body: JSON.stringify(body) } : {}),
    })
  ).data;
export const commerceApi = {
  overview: async () => (await apiRequest<{ data: CommerceOverview }>("/api/v1/commerce/overview")).data,
  saveSetup: async (body: CommerceSetup) =>
    (
      await apiRequest<{ data: CommerceSetting }>("/api/v1/commerce/settings", {
        method: "PATCH",
        body: JSON.stringify(body),
      })
    ).data,
  check: () =>
    post<{ catalog: string; paymentConfigurationActive: boolean; razorpayVerified: boolean }>("integrations/check"),
  sync: () => post<{ queued: boolean }>("products/sync-meta"),
  resolve: (id: string, source: "local" | "meta") => post(`products/${id}/resolve-meta`, { source }),
  requestPayment: (id: string) => post<Payment>(`orders/${id}/request-payment`),
  verifyPayment: (id: string) => post<Payment>(`payments/${id}/verify`),
};
