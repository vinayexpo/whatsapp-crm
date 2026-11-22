import { useCallback, useEffect, useRef, useState } from "react";

// A bounded batch, rather than a trailing debounce: continuous message traffic
// must still refresh counts, without one API request per delivery receipt.
export function useBatchedRefresh(scope: string | undefined) {
  const [revision, setRevision] = useState(0);
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const refresh = useCallback(() => {
    if (timer.current !== null) return;
    timer.current = setTimeout(() => {
      timer.current = null;
      setRevision((value) => value + 1);
    }, 500);
  }, []);

  useEffect(() => () => {
    if (timer.current !== null) clearTimeout(timer.current);
    timer.current = null;
  }, [scope]);

  return [revision, refresh] as const;
}
