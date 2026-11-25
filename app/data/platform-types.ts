import type { CompanyStatus, PaginationMeta, TeamMemberRole } from "./types";

export interface PlatformCompany {
  id: string;
  name: string;
  status: CompanyStatus;
  createdAt: string;
  usersCount: number;
  activeAdminsCount: number;
  connectedChannelsCount: number;
  contactsCount?: number;
  conversationsCount?: number;
  campaignsCount?: number;
  branchesCount?: number;
  ordersCount?: number;
}
export interface PlatformCompanyDetail {
  company: PlatformCompany;
  connections: { id: string; channel: string; label: string; status: string; accountName: string }[];
}
export interface PlatformUser {
  id: string;
  name: string;
  email: string;
  role: TeamMemberRole;
  status: "active" | "invited" | "suspended";
  companyId: string | null;
  companyName: string | null;
  companyStatus: CompanyStatus | null;
  staffBranchId: string | null;
  staffBranchName: string | null;
  createdAt: string;
}
export interface PlatformUserInput {
  name: string;
  email: string;
  role: TeamMemberRole;
  status: PlatformUser["status"];
  companyId?: string | null;
  staffBranchId?: string | null;
  password?: string;
}
export interface PlatformAuditEntry {
  id: string;
  actorName: string;
  companyName: string | null;
  action: string;
  targetType: string;
  targetId: string;
  description: string;
  createdAt: string;
}
export interface PlatformSummary {
  companies: number;
  activeCompanies: number;
  suspendedCompanies: number;
  companiesWithoutAdmin: number;
  users: number;
  activeUsers: number;
  suspendedUsers: number;
  contacts: number;
  conversations: number;
  campaigns: number;
  orders: number;
  branches: number;
  connectedChannels: number;
  disconnectedChannels: number;
  generatedAt: string;
}
export interface PlatformFilters {
  page?: number;
  perPage?: number;
  search?: string;
  status?: string;
  role?: string;
  companyId?: string;
  action?: string;
  from?: string;
  to?: string;
  needsAdmin?: boolean;
}
export interface PlatformPage<T> {
  data: T[];
  meta: PaginationMeta;
}
