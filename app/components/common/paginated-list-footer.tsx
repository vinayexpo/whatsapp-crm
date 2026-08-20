import Stack from "@mui/material/Stack";
import Pagination from "@mui/material/Pagination";

export function PaginatedListFooter({
  page,
  lastPage,
  onPageChange,
}: {
  page: number;
  lastPage: number;
  onPageChange: (page: number) => void;
}) {
  if (lastPage <= 1) return null;

  return (
    <Stack sx={{ alignItems: "center", mt: 3 }}>
      <Pagination page={page} count={lastPage} onChange={(_, p) => onPageChange(p)} color="primary" />
    </Stack>
  );
}
