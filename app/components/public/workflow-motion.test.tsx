// @vitest-environment jsdom
import { act, cleanup, fireEvent, render, screen } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { WorkflowMotion } from "./workflow-motion";
import { industries } from "~/data/industries";

let reduced = false;
let intersect: IntersectionObserverCallback;
beforeEach(() => {
  vi.useFakeTimers();
  reduced = false;
  vi.stubGlobal("matchMedia", () => ({ matches: reduced, addEventListener: vi.fn(), removeEventListener: vi.fn() }));
  vi.stubGlobal(
    "IntersectionObserver",
    class {
      constructor(callback: IntersectionObserverCallback) {
        intersect = callback;
      }
      observe() {}
      disconnect() {}
    },
  );
  Object.defineProperty(document, "hidden", { configurable: true, value: false });
});
afterEach(() => {
  cleanup();
  vi.useRealTimers();
  vi.unstubAllGlobals();
  delete (document as unknown as { hidden?: boolean }).hidden;
});

describe("illustrative workflow motion", () => {
  it("advances the preview and pauses both timing and animation state", () => {
    render(<WorkflowMotion industry={industries[0]} />);
    const figure = screen.getByRole("figure");
    expect(figure.getAttribute("data-running")).toBe("true");
    act(() => vi.advanceTimersByTime(2400));
    expect(screen.getByText(industries[0].scenarios[0].steps[1])).toBeTruthy();
    fireEvent.click(screen.getByRole("button", { name: "Pause workflow animation" }));
    act(() => vi.advanceTimersByTime(7200));
    expect(figure.getAttribute("data-running")).toBe("false");
    expect(screen.getByText(industries[0].scenarios[0].steps[1])).toBeTruthy();
    fireEvent.click(screen.getByRole("button", { name: "Replay workflow preview" }));
    expect(screen.getByText(industries[0].scenarios[0].steps[0])).toBeTruthy();
    expect(figure.getAttribute("data-running")).toBe("true");
  });
  it("keeps reduced-motion previews still and lets the user step through", () => {
    reduced = true;
    render(<WorkflowMotion industry={industries[0]} />);
    act(() => vi.advanceTimersByTime(10000));
    expect(screen.getByRole("figure").getAttribute("data-running")).toBe("false");
    expect(screen.getByText(industries[0].scenarios[0].steps[0])).toBeTruthy();
    fireEvent.click(screen.getByRole("button", { name: "Show next workflow step" }));
    expect(screen.getByText(industries[0].scenarios[0].steps[1])).toBeTruthy();
    fireEvent.click(screen.getByRole("button", { name: "Replay workflow preview" }));
    expect(screen.getByRole("figure").getAttribute("data-running")).toBe("false");
  });
  it("stops when offscreen or the document is hidden", () => {
    render(<WorkflowMotion industry={industries[0]} />);
    act(() => intersect([{ isIntersecting: false }] as IntersectionObserverEntry[], {} as IntersectionObserver));
    expect(screen.getByRole("figure").getAttribute("data-running")).toBe("false");
    act(() => vi.advanceTimersByTime(7200));
    expect(screen.getByText(industries[0].scenarios[0].steps[0])).toBeTruthy();
    act(() => intersect([{ isIntersecting: true }] as IntersectionObserverEntry[], {} as IntersectionObserver));
    expect(screen.getByRole("figure").getAttribute("data-running")).toBe("true");
    Object.defineProperty(document, "hidden", { configurable: true, value: true });
    fireEvent(document, new Event("visibilitychange"));
    expect(screen.getByRole("figure").getAttribute("data-running")).toBe("false");
  });
});
