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
      observe() {
        intersect([{ isIntersecting: true }] as IntersectionObserverEntry[], {} as IntersectionObserver);
      }
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
  it("keeps cycling through workflow stages without playback controls", () => {
    const view = render(<WorkflowMotion industry={industries[0]} />);
    const figure = screen.getByRole("figure");
    expect(figure.getAttribute("data-running")).toBe("true");
    act(() => vi.advanceTimersByTime(1400));
    expect(screen.getByText(industries[0].scenarios[0].steps[1])).toBeTruthy();
    act(() => vi.advanceTimersByTime(1400));
    expect(screen.getByText(industries[0].scenarios[0].steps[2])).toBeTruthy();
    act(() => vi.advanceTimersByTime(1400));
    expect(screen.getByText(industries[0].scenarios[0].steps[0])).toBeTruthy();
    act(() => vi.advanceTimersByTime(42000));
    expect(figure.getAttribute("data-running")).toBe("true");
    expect(screen.getByText(industries[0].scenarios[0].steps[0])).toBeTruthy();
    expect(screen.queryByRole("button")).toBeNull();
    view.rerender(<WorkflowMotion industry={industries[0]} scenario={industries[0].scenarios[1]} />);
    expect(screen.getByRole("figure").getAttribute("data-running")).toBe("true");
    expect(screen.getByText(industries[0].scenarios[1].steps[0])).toBeTruthy();
  });
  it("shows the final state without motion or playback controls for reduced motion", () => {
    reduced = true;
    render(<WorkflowMotion industry={industries[0]} />);
    act(() => vi.advanceTimersByTime(10000));
    expect(screen.getByRole("figure").getAttribute("data-running")).toBe("false");
    expect(screen.getByText(industries[0].scenarios[0].steps[2])).toBeTruthy();
    expect(screen.queryByRole("button")).toBeNull();
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
