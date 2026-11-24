// @vitest-environment jsdom
import { afterEach, describe, expect, it } from "vitest";
import { cleanup, fireEvent, render, screen } from "@testing-library/react";
import { MemoryRouter } from "react-router";
import Landing from "./landing";

afterEach(cleanup);

function openLanding() {
  return render(
    <MemoryRouter>
      <Landing />
    </MemoryRouter>,
  );
}

describe("public landing page", () => {
  it("switches AI demonstrations with keyboard navigation and changes the sample reply", () => {
    openLanding();
    fireEvent.click(screen.getByRole("button", { name: "Browse products" }));
    expect(screen.getByText(/Of course! Browse our collection/)).toBeTruthy();
    fireEvent.keyDown(screen.getByRole("tab", { name: "AI replies" }), { key: "ArrowRight" });
    expect(screen.getByRole("tab", { name: "Voice agents" }).getAttribute("aria-selected")).toBe("true");
    expect(document.activeElement).toBe(screen.getByRole("tab", { name: "Voice agents" }));
    fireEvent.click(screen.getByRole("button", { name: "Play visual walkthrough" }));
    expect(screen.getByRole("button", { name: "Pause walkthrough" }).getAttribute("aria-pressed")).toBe("true");
    fireEvent.click(screen.getByRole("tab", { name: "Human handoff" }));
    expect(screen.getByText("Conversation history available")).toBeTruthy();
  });

  it("updates example workflows and the feature preview", () => {
    openLanding();
    fireEvent.click(screen.getByRole("button", { name: "Help a shopper" }));
    expect(screen.getByText("Let them browse the catalog")).toBeTruthy();
    fireEvent.click(screen.getByRole("tab", { name: "Commerce" }));
    expect(screen.getByText("ORDER #CC-2048")).toBeTruthy();
    fireEvent.keyDown(screen.getByRole("tab", { name: "Commerce" }), { key: "End" });
    expect(screen.getByRole("tab", { name: "Analytics" }).getAttribute("aria-selected")).toBe("true");
    expect(screen.getByRole("img", { name: /Illustrative weekly conversation/ })).toBeTruthy();
  });

  it("closes navigation with Escape from its toggle and sends workspace links to sign-in", () => {
    openLanding();
    const toggle = screen.getByRole("button", { name: "Open navigation" });
    fireEvent.click(toggle);
    expect(toggle.getAttribute("aria-expanded")).toBe("true");
    fireEvent.keyDown(toggle, { key: "Escape" });
    expect(toggle.getAttribute("aria-expanded")).toBe("false");
    expect(document.activeElement).toBe(toggle);
    expect(screen.getByRole("link", { name: "Open your workspace" }).getAttribute("href")).toBe("/login");
  });
});
