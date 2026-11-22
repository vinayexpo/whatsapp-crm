import { act, cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import type { PaginatedResponse, PhonebookFolder } from "~/data/types";
import { CampaignBuilderDialog } from "./campaign-builder-dialog";

const { listPhonebookFolders, getPhonebookFolder } = vi.hoisted(() => ({
  listPhonebookFolders: vi.fn(),
  getPhonebookFolder: vi.fn(),
}));

vi.mock("~/utils/api-client", () => ({
  apiClient: {
    listPhonebookFolders,
    getPhonebookFolder,
    listVoiceAgents: vi.fn().mockResolvedValue({ data: [] }),
    listWhatsappCallFlows: vi.fn().mockResolvedValue({ data: [] }),
  },
}));

vi.mock("~/hooks/use-crm-store", () => ({ useCrmStore: () => ({ templatesRevision: 0 }) }));

function folder(id: string, name: string): PhonebookFolder {
  return { id, name, contactCount: 12, createdAt: "", updatedAt: "" };
}

function page(data: PhonebookFolder[], currentPage = 1, lastPage = 1): PaginatedResponse<PhonebookFolder> {
  return { data, meta: { currentPage, lastPage, perPage: 100, total: 131 } };
}

function builder(open = true) {
  return <CampaignBuilderDialog open={open} onClose={vi.fn()} onCreate={vi.fn()} contacts={[]} apiConnections={[]} />;
}

beforeEach(() => {
  listPhonebookFolders.mockReset();
  getPhonebookFolder.mockReset();
});

afterEach(cleanup);

describe("campaign phonebook audience", () => {
  it("includes and selects a birthday phonebook from the second page", async () => {
    const birthdays = folder("birthdays", "22-11-2026 Birthdays");
    listPhonebookFolders
      .mockResolvedValueOnce(page([folder("older", "21-11-2026 Birthdays")], 1, 2))
      .mockResolvedValueOnce(page([birthdays], 2, 2));
    getPhonebookFolder.mockResolvedValue({ ...birthdays, contacts: [] });
    render(builder());
    fireEvent.click(screen.getByRole("button", { name: "By phonebook folder" }));
    fireEvent.mouseDown(await screen.findByRole("combobox", { name: "Phonebook folder" }));
    fireEvent.click(await screen.findByRole("option", { name: "22-11-2026 Birthdays (12)" }));

    expect(listPhonebookFolders).toHaveBeenNthCalledWith(1, { page: 1, perPage: 100 });
    expect(listPhonebookFolders).toHaveBeenNthCalledWith(2, { page: 2, perPage: 100 });
    await waitFor(() => expect(getPhonebookFolder).toHaveBeenCalledWith("birthdays"));
    expect(screen.getByRole("combobox", { name: "Phonebook folder" }).textContent).toContain(birthdays.name);
  });

  it("shows loading rather than claiming there are no phonebooks", async () => {
    let resolve!: (value: PaginatedResponse<PhonebookFolder>) => void;
    listPhonebookFolders.mockReturnValue(new Promise<PaginatedResponse<PhonebookFolder>>((done) => { resolve = done; }));
    render(builder());
    fireEvent.click(screen.getByRole("button", { name: "By phonebook folder" }));
    expect(screen.getByRole("status").textContent).toContain("Loading phonebook folders");
    expect(screen.queryByText(/No phonebook folders yet/)).toBeNull();
    await act(async () => resolve(page([])));
    expect(await screen.findByText(/No phonebook folders yet/)).toBeTruthy();
  });

  it("reports a later page failure and retries the complete list", async () => {
    listPhonebookFolders
      .mockResolvedValueOnce(page([folder("older", "Older folder")], 1, 2))
      .mockRejectedValueOnce(new Error("Network error"))
      .mockResolvedValueOnce(page([folder("birthdays", "22-11-2026 Birthdays")]));
    render(builder());
    fireEvent.click(screen.getByRole("button", { name: "By phonebook folder" }));
    expect((await screen.findByRole("alert")).textContent).toContain("Could not load phonebook folders");
    expect(screen.queryByText(/No phonebook folders yet/)).toBeNull();
    fireEvent.click(screen.getByRole("button", { name: "Retry" }));
    await screen.findByRole("combobox", { name: "Phonebook folder" });
    expect(listPhonebookFolders).toHaveBeenNthCalledWith(3, { page: 1, perPage: 100 });
  });

  it("ignores an old request after closing and reopening the builder", async () => {
    let resolveOld!: (value: PaginatedResponse<PhonebookFolder>) => void;
    listPhonebookFolders
      .mockReturnValueOnce(new Promise<PaginatedResponse<PhonebookFolder>>((done) => { resolveOld = done; }))
      .mockResolvedValueOnce(page([folder("birthdays", "22-11-2026 Birthdays")]));
    const view = render(builder());
    view.rerender(builder(false));
    view.rerender(builder());
    fireEvent.click(screen.getByRole("button", { name: "By phonebook folder" }));
    await screen.findByRole("combobox", { name: "Phonebook folder" });
    await act(async () => resolveOld(page([folder("stale", "Stale folder")], 1, 2)));
    fireEvent.mouseDown(screen.getByRole("combobox", { name: "Phonebook folder" }));
    expect(await screen.findByRole("option", { name: "22-11-2026 Birthdays (12)" })).toBeTruthy();
    expect(screen.queryByRole("option", { name: "Stale folder (12)" })).toBeNull();
    expect(listPhonebookFolders).toHaveBeenCalledTimes(2);
  });
});
