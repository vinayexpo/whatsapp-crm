import { apiRequest } from "./api-client";
export type AdAccount = {
  id: string;
  name: string;
  metaAccountId: string;
  pageId: string;
  instagramId: string | null;
  whatsappNumber: string | null;
  currency: string | null;
  timezone: string | null;
  verifiedAt: string | null;
  syncedAt: string | null;
  syncError: string | null;
  autoSync?: boolean;
};
export type AdSpec = {
  accountId: string;
  name: string;
  destination: "website" | "whatsapp";
  dailyBudget: number;
  countries: string[];
  ageMin: number;
  ageMax: number;
  placements: string[];
  startAt: string | null;
  endAt: string;
  headline: string;
  message: string;
  imageUrl: string;
  websiteUrl: string;
  cta: string;
  specialCategories: string[];
};
export type AdInsights = {
  spend?: string;
  impressions?: string;
  clicks?: string;
  reach?: string;
  ctr?: string;
  cpc?: string;
  date_start?: string;
  date_stop?: string;
  actions?: { action_type: string; value: string }[];
};
export type AdCampaign = {
  id: string;
  accountId: string;
  name: string;
  objective: string;
  state: string;
  remoteId: string | null;
  adsetId: string | null;
  adId: string | null;
  creativeId: string | null;
  status: string | null;
  specification: AdSpec | null;
  insights: AdInsights | null;
  lastError: string | null;
  syncedAt: string | null;
  metaSnapshot?: { campaign: MetaObject; adsets: MetaObject[]; ads: MetaObject[] } | null;
  pendingChanges?: Record<string, Record<string, { base: unknown; value: unknown }>>;
  syncConflicts?: { key: string; field: string; base: unknown; meta: unknown; local: unknown; missing: boolean }[];
  syncState?: string;
  syncError?: string | null;
  syncRevision?: string;
};
export type MetaObject = {
  id: string;
  name: string;
  status?: string;
  objective?: string;
  daily_budget?: string;
  lifetime_budget?: string;
  end_time?: string;
  start_time?: string;
  special_ad_categories?: string[];
  targeting?: {
    geo_locations?: { countries?: string[]; [key: string]: unknown };
    age_min?: number;
    age_max?: number;
    [key: string]: unknown;
  };
  [key: string]: unknown;
};
export type AdObject = {
  id: string;
  name: string;
  status: string;
  effective_status: string;
  daily_budget?: string;
  start_time?: string;
  end_time?: string;
  creative?: { id: string; title?: string; body?: string; image_url?: string; thumbnail_url?: string };
  issues_info?: { error_message?: string }[];
};
export type AdHierarchy = { adsets: AdObject[]; ads: AdObject[] };
export type AdsPage = {
  data: AdCampaign[];
  meta: { currentPage: number; lastPage: number; total: number };
  summary: { spend: number; impressions: number; clicks: number; campaigns: number };
};
const request = async <T>(path: string, method = "GET", body?: object) =>
  (await apiRequest<{ data: T }>(`/api/v1/ads/${path}`, { method, ...(body ? { body: JSON.stringify(body) } : {}) }))
    .data;
export const metaAdsApi = {
  aiDraft: (body: object) =>
    request<{ proposal: AdSpec; rationale: string; warnings: string[] }>("ai/draft", "POST", body),
  syncSettings: (id: string, autoSync: boolean) => request(`accounts/${id}/sync-settings`, "PATCH", { autoSync }),
  pull: (id: string) => request<AdCampaign>(`campaigns/${id}/pull`, "POST"),
  stage: (id: string, body: object) => request<AdCampaign>(`campaigns/${id}/changes`, "PATCH", body),
  resolve: (id: string, body: object) => request<AdCampaign>(`campaigns/${id}/resolve`, "POST", body),
  push: (id: string, revision: string) => request(`campaigns/${id}/push`, "POST", { revision, confirm: true }),
  accounts: () => request<AdAccount[]>("accounts"),
  saveAccount: (body: object, id?: string) =>
    request<AdAccount>(id ? `accounts/${id}` : "accounts", id ? "PATCH" : "POST", body),
  deleteAccount: (id: string) => request(`accounts/${id}`, "DELETE"),
  verify: (id: string) => request<{ account: string; page: string }>(`accounts/${id}/verify`, "POST"),
  sync: (id: string) => request(`accounts/${id}/sync`, "POST"),
  campaigns: (accountId: string, page = 1, search = "") =>
    apiRequest<AdsPage>(`/api/v1/ads/campaigns?${new URLSearchParams({ accountId, page: String(page), search })}`),
  save: (body: AdSpec, id?: string) =>
    request<AdCampaign>(id ? `campaigns/${id}` : "campaigns", id ? "PATCH" : "POST", body),
  duplicate: (id: string) => request<AdCampaign>(`campaigns/${id}/duplicate`, "POST"),
  remove: (id: string) => request(`campaigns/${id}`, "DELETE"),
  publish: (id: string) => request(`campaigns/${id}/publish`, "POST"),
  status: (id: string, status: string) =>
    request<AdCampaign>(`campaigns/${id}/status`, "PATCH", { status, confirm: true }),
  budget: (id: string, dailyBudget: number) =>
    request<AdCampaign>(`campaigns/${id}/budget`, "PATCH", { dailyBudget, confirm: true }),
  details: (id: string) => request<AdHierarchy>(`campaigns/${id}/details`),
  objectStatus: (id: string, kind: string, objectId: string, status: string) =>
    request(`campaigns/${id}/objects/status`, "PATCH", { kind, objectId, status, confirm: true }),
};
