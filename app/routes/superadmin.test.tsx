import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { cleanup, fireEvent, render, screen, waitFor, within } from "@testing-library/react";
import { MemoryRouter, Route, Routes } from "react-router";
import { AuthProvider } from "~/hooks/use-auth";
import Superadmin from "./superadmin";

const { api } = vi.hoisted(() => ({
  api: new Proxy({} as Record<string, ReturnType<typeof vi.fn>>, {
    get(target, key: string) {
      return (target[key] ??= vi.fn());
    },
  }),
}));
vi.mock("~/utils/api-client", () => ({
  apiClient: api,
  ApiError: class extends Error {
    status = 500;
  },
}));

const company = {
  id: "company-1",
  name: "Northstar Retail",
  status: "active",
  usersCount: 3,
  activeAdminsCount: 1,
  connectedChannelsCount: 2,
  createdAt: "2026-11-01T10:00:00Z",
};
const owner = { id: "owner", name: "Platform Owner", email: "owner@example.test", role: "superadmin", companyId: null };
const member = {
  id: "member",
  name: "Jordan Lee",
  email: "jordan@example.test",
  role: "agent",
  status: "active",
  companyId: company.id,
  companyName: company.name,
  companyStatus: "active",
  staffBranchId: null,
  staffBranchName: null,
  createdAt: "2026-11-01T10:00:00Z",
};
const page = (data: unknown[]) => ({ data, meta: { currentPage: 1, lastPage: 1, perPage: 20, total: data.length } });
beforeEach(() => {
  for (const mock of Object.values(api)) mock.mockReset();
  api.me.mockResolvedValue(owner);
  api.platformSummary.mockResolvedValue({
    companies: 1,
    activeCompanies: 1,
    suspendedCompanies: 0,
    companiesWithoutAdmin: 1,
    users: 2,
    activeUsers: 2,
    suspendedUsers: 0,
    contacts: 12,
    conversations: 8,
    campaigns: 2,
    orders: 3,
    branches: 1,
    connectedChannels: 2,
    disconnectedChannels: 1,
    generatedAt: "2026-11-25T10:00:00Z",
  });
  api.platformCompanies.mockResolvedValue(page([company]));
  api.platformCompany.mockResolvedValue({ company, connections: [] });
  api.platformUsers.mockResolvedValue(page([member, { ...owner, status: "active", createdAt: member.createdAt }]));
  api.platformActivity.mockResolvedValue(page([]));
  api.platformBranches.mockResolvedValue([]);
  api.platformSaveCompany.mockResolvedValue(company);
  api.platformResetPassword.mockResolvedValue(undefined);
  api.platformProfile.mockResolvedValue({ ...owner, name: "Updated Owner" });
});
afterEach(cleanup);
async function open(path = "/superadmin") {
  render(
    <MemoryRouter initialEntries={[path]}>
      <AuthProvider>
        <Routes>
          <Route path="/superadmin" element={<Superadmin />} />
          <Route path="/dashboard" element={<h1>Tenant dashboard</h1>} />
          <Route path="/login" element={<h1>Login</h1>} />
        </Routes>
      </AuthProvider>
    </MemoryRouter>,
  );
  await screen.findByRole("heading", { level: 1 });
}

describe("superadmin platform management", () => {
  it("shows real summary data and navigates to companies needing an admin", async () => {
    await open();
    expect(screen.getByRole("heading", { name: "Platform overview" })).toBeTruthy();
    fireEvent.click(await screen.findByRole("button", { name: "Review companies" }));
    await screen.findByRole("heading", { name: "Companies" });
    await waitFor(() =>
      expect(api.platformCompanies).toHaveBeenCalledWith(expect.objectContaining({ needsAdmin: true })),
    );
  });
  it("creates a company through the form and refreshes platform data", async () => {
    await open();
    fireEvent.click(screen.getByRole("button", { name: "Create company" }));
    const dialog = await screen.findByRole("dialog", { name: "Create company" });
    fireEvent.change(within(dialog).getByLabelText(/^Company name/), { target: { value: "New Workspace" } });
    fireEvent.submit(within(dialog).getByRole("button", { name: "Create company" }).closest("form")!);
    await waitFor(() => expect(api.platformSaveCompany).toHaveBeenCalledWith(null, { name: "New Workspace" }));
    await screen.findByText("Changes saved successfully.");
    expect(api.platformSummary.mock.calls.length).toBeGreaterThan(1);
  });
  it("keeps active company deletion disabled and confirms suspension explicitly", async () => {
    await open("/superadmin?view=companies");
    const table = await screen.findByRole("table", { name: "Companies" });
    expect((within(table).getByRole("button", { name: "Delete Northstar Retail" }) as HTMLButtonElement).disabled).toBe(
      true,
    );
    fireEvent.click(within(table).getByRole("button", { name: "Suspend Northstar Retail" }));
    const dialog = await screen.findByRole("dialog", { name: "Suspend company" });
    expect(api.platformSaveCompany).not.toHaveBeenCalled();
    fireEvent.click(within(dialog).getByRole("button", { name: "Suspend company" }));
    await waitFor(() => expect(api.platformSaveCompany).toHaveBeenCalledWith(company.id, { status: "suspended" }));
  });
  it("requires matching password confirmation and the superadmin password", async () => {
    await open("/superadmin?view=users");
    const table = await screen.findByRole("table", { name: "Platform users" });
    fireEvent.click(within(table).getByRole("button", { name: "Reset password for Jordan Lee" }));
    const dialog = await screen.findByRole("dialog", { name: "Reset user password" });
    const submit = within(dialog).getByRole("button", { name: "Reset user password" }) as HTMLButtonElement;
    expect(submit.disabled).toBe(true);
    fireEvent.change(within(dialog).getByLabelText(/^New password/), { target: { value: "new-password" } });
    fireEvent.change(within(dialog).getByLabelText(/^Confirm new password/), { target: { value: "different" } });
    fireEvent.change(within(dialog).getByLabelText(/^Your current password/), { target: { value: "owner-password" } });
    expect(submit.disabled).toBe(true);
    fireEvent.change(within(dialog).getByLabelText(/^Confirm new password/), { target: { value: "new-password" } });
    fireEvent.submit(submit.closest("form")!);
    await waitFor(() =>
      expect(api.platformResetPassword).toHaveBeenCalledWith(member.id, {
        currentPassword: "owner-password",
        password: "new-password",
        password_confirmation: "new-password",
      }),
    );
  });
  it("routes own-account edits to settings rather than exposing delete or reset actions", async () => {
    await open("/superadmin?view=users");
    const table = await screen.findByRole("table", { name: "Platform users" });
    expect(within(table).queryByRole("button", { name: "Delete Platform Owner" })).toBeNull();
    fireEvent.click(within(table).getByRole("button", { name: "Your account" }));
    await screen.findByRole("heading", { name: "Account settings" });
  });
  it("provides a retry when a list cannot be loaded", async () => {
    api.platformCompanies.mockRejectedValueOnce(new Error("Could not load companies"));
    await open("/superadmin?view=companies");
    await screen.findByText("Could not load companies");
    fireEvent.click(screen.getByRole("button", { name: "Retry" }));
    await screen.findByRole("table", { name: "Companies" });
    expect(api.platformCompanies).toHaveBeenCalledTimes(2);
  });
  it("rejects tenant users before requesting platform data", async () => {
    api.me.mockResolvedValue({ ...owner, role: "admin", companyId: company.id });
    await open();
    expect(screen.getByRole("heading", { name: "Tenant dashboard" })).toBeTruthy();
    expect(api.platformSummary).not.toHaveBeenCalled();
    expect(api.platformCompanies).not.toHaveBeenCalled();
  });
});
