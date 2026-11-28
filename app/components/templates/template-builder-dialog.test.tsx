// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from "vitest";
import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { TemplateBuilderDialog } from "./template-builder-dialog";
import { apiClient, ApiError } from "~/utils/api-client";
import type { WhatsappTemplate } from "~/data/types";

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});
const proposal = {
  name: "appointment_reminder",
  language: "en_US",
  category: "utility",
  body: "Hi {{1}}, your appointment is on {{2}}. Reply for help.",
  headerText: "Your appointment",
  footerText: "Thank you",
  bodyExamples: ["Alex", "Monday at 10 am"],
};

function openBuilder() {
  return render(
    <TemplateBuilderDialog
      open
      connectionId="connection-1"
      template={null}
      aiFirst
      onClose={vi.fn()}
      onSaved={vi.fn()}
    />,
  );
}

describe("AI template drafting", () => {
  it("keeps editor content until explicitly applied, then saves edited copy and sample values only on request", async () => {
    const generate = vi
      .spyOn(apiClient, "generateTemplateDraft")
      .mockResolvedValue({
        proposal,
        rationale: "An existing appointment reminder.",
        warnings: ["Review before submission."],
      });
    const create = vi
      .spyOn(apiClient, "createTemplate")
      .mockResolvedValue({ id: "draft-1", status: "draft" } as WhatsappTemplate);
    const submit = vi.spyOn(apiClient, "submitTemplate");
    openBuilder();
    fireEvent.change(screen.getByLabelText("Template name"), { target: { value: "manual_name" } });
    fireEvent.change(screen.getByLabelText("Body"), { target: { value: "My original draft." } });
    fireEvent.change(screen.getByLabelText("What should this message say?"), {
      target: { value: "Remind customers about their requested appointment." },
    });
    fireEvent.click(screen.getByRole("button", { name: "Generate suggestion" }));
    await screen.findByRole("button", { name: "Use this draft" });
    expect((screen.getByLabelText("Template name") as HTMLInputElement).value).toBe("manual_name");
    expect((screen.getByLabelText("Body") as HTMLInputElement).value).toBe("My original draft.");
    expect(create).not.toHaveBeenCalled();
    expect(submit).not.toHaveBeenCalled();
    expect(generate).toHaveBeenCalledWith(
      "connection-1",
      expect.objectContaining({ language: "en_US", category: "utility" }),
    );
    fireEvent.click(screen.getByRole("button", { name: "Use this draft" }));
    expect((screen.getByLabelText("Template name") as HTMLInputElement).value).toBe("appointment_reminder");
    expect((screen.getByLabelText(/Example for \{\{1\}\}/) as HTMLInputElement).value).toBe("Alex");
    fireEvent.change(screen.getByLabelText("Body"), {
      target: { value: "Hello {{1}}, your appointment is on {{2}}. Reply to reschedule." },
    });
    fireEvent.change(screen.getByLabelText(/Example for \{\{2\}\}/), { target: { value: "Tuesday at 11 am" } });
    fireEvent.click(screen.getByRole("button", { name: "Save draft" }));
    await waitFor(() =>
      expect(create).toHaveBeenCalledWith(
        "connection-1",
        expect.objectContaining({
          body: "Hello {{1}}, your appointment is on {{2}}. Reply to reschedule.",
          components: expect.arrayContaining([
            expect.objectContaining({ type: "BODY", example: { body_text: [["Alex", "Tuesday at 11 am"]] } }),
          ]),
        }),
      ),
    );
    expect(submit).not.toHaveBeenCalled();
  });

  it("shows provider errors and preserves the manual editor", async () => {
    vi.spyOn(apiClient, "generateTemplateDraft").mockRejectedValue(
      new ApiError("Configure your company AI provider.", 422),
    );
    openBuilder();
    fireEvent.change(screen.getByLabelText("Body"), { target: { value: "Keep this message." } });
    fireEvent.change(screen.getByLabelText("What should this message say?"), {
      target: { value: "Prepare a requested appointment reminder." },
    });
    fireEvent.click(screen.getByRole("button", { name: "Generate suggestion" }));
    await screen.findByText("Configure your company AI provider.");
    expect((screen.getByLabelText("Body") as HTMLInputElement).value).toBe("Keep this message.");
    expect(screen.queryByRole("button", { name: "Use this draft" })).toBeNull();
  });
});
