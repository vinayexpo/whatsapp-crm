import { act, cleanup, renderHook } from "@testing-library/react";
import { afterEach, expect, it, vi } from "vitest";
import { useBatchedRefresh } from "./use-batched-refresh";

afterEach(() => { cleanup(); vi.useRealTimers(); });

it("batches bursts while still refreshing during continuous traffic", () => {
  vi.useFakeTimers();
  const { result } = renderHook(() => useBatchedRefresh("user"));
  act(() => {
    for (let i = 0; i < 100; i++) result.current[1]();
    vi.advanceTimersByTime(500);
  });
  expect(result.current[0]).toBe(1);
  act(() => { result.current[1](); vi.advanceTimersByTime(500); });
  expect(result.current[0]).toBe(2);
});

it("cancels pending refreshes when the user changes or the hook unmounts", () => {
  vi.useFakeTimers();
  const { result, rerender, unmount } = renderHook(({ scope }) => useBatchedRefresh(scope), { initialProps: { scope: "a" } });
  act(() => result.current[1]());
  rerender({ scope: "b" });
  act(() => vi.advanceTimersByTime(500));
  expect(result.current[0]).toBe(0);
  act(() => result.current[1]());
  unmount();
  expect(vi.getTimerCount()).toBe(0);
});
