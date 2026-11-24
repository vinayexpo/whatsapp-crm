import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { act, cleanup, fireEvent, render, screen } from "@testing-library/react";
import { MemoryRouter, Route, Routes } from "react-router";
import { AuthProvider } from "~/hooks/use-auth";
import { apiClient } from "~/utils/api-client";
import Login from "./login";

vi.mock("~/utils/api-client", () => ({
  apiClient: { me: vi.fn(), login: vi.fn() },
  ApiError: class extends Error {},
}));
vi.mock("~/hooks/use-bootstrap-status", () => ({ useBootstrapStatus: () => "ready" }));

beforeEach(() => {
  vi.resetAllMocks();
  vi.mocked(apiClient.me).mockRejectedValue(new Error("Unauthenticated"));
});
afterEach(cleanup);

function openLogin() {
  render(
    <MemoryRouter initialEntries={["/login"]}>
      <AuthProvider>
        <Routes>
          <Route path="/login" element={<Login />} />
          <Route path="/dashboard" element={<h1>Workspace dashboard</h1>} />
          <Route path="/superadmin" element={<h1>Superadmin workspace</h1>} />
          <Route path="/" element={<h1>Public landing page</h1>} />
        </Routes>
      </AuthProvider>
    </MemoryRouter>,
  );
}

describe("workspace entry after introducing the public landing page", () => {
  it.each([
    ["admin", "Workspace dashboard"],
    ["superadmin", "Superadmin workspace"],
  ] as const)("routes a signed-in %s to the correct workspace", async (role, heading) => {
    vi.mocked(apiClient.me).mockResolvedValue({ id: "test-user", role } as Awaited<ReturnType<typeof apiClient.me>>);
    openLogin();
    expect(await screen.findByRole("heading", { name: heading })).toBeTruthy();
    expect(screen.queryByRole("heading", { name: "Public landing page" })).toBeNull();
  });

  it.each([
    ["admin", "Workspace dashboard"],
    ["superadmin", "Superadmin workspace"],
  ] as const)("routes a new %s login to the correct workspace", async (role, heading) => {
    vi.mocked(apiClient.login).mockResolvedValue({ id: "test-user", role } as Awaited<
      ReturnType<typeof apiClient.login>
    >);
    await act(async () => { openLogin(); });
    fireEvent.change(screen.getByLabelText(/^Email/), { target: { value: "demo@example.test" } });
    fireEvent.change(screen.getByLabelText(/^Password/), { target: { value: "demo-password" } });
    fireEvent.submit(screen.getByLabelText(/^Password/).closest("form")!);
    expect(await screen.findByRole("heading", { name: heading })).toBeTruthy();
    expect(apiClient.login).toHaveBeenCalledWith("demo@example.test", "demo-password");
  });
});
