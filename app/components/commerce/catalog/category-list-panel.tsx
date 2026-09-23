import { useState } from "react";
import Stack from "@mui/material/Stack";
import Paper from "@mui/material/Paper";
import Typography from "@mui/material/Typography";
import IconButton from "@mui/material/IconButton";
import Chip from "@mui/material/Chip";
import Button from "@mui/material/Button";
import DeleteRoundedIcon from "@mui/icons-material/DeleteRounded";
import AddRoundedIcon from "@mui/icons-material/AddRounded";
import { apiClient } from "~/utils/api-client";
import type { Category } from "~/data/types";
import { CreateCategoryDialog } from "./create-category-dialog";

interface CategoryListPanelProps {
  categories: Category[];
  onChanged: () => void;
}

export function CategoryListPanel({ categories, onChanged }: CategoryListPanelProps) {
  const [createOpen, setCreateOpen] = useState(false);

  async function handleCreate(input: { name: string; slug: string; parentId?: string | null }) {
    await apiClient.createCategory(input);
    onChanged();
  }

  async function handleDelete(category: Category) {
    if (!window.confirm(`Delete "${category.name}"?`)) return;
    await apiClient.deleteCategory(category.id);
    onChanged();
  }

  function parentName(category: Category): string | null {
    if (!category.parentId) return null;
    return categories.find((c) => c.id === category.parentId)?.name ?? null;
  }

  return (
    <Stack spacing={2}>
      <Stack direction="row" sx={{ justifyContent: "flex-end" }}>
        <Button variant="contained" startIcon={<AddRoundedIcon />} onClick={() => setCreateOpen(true)}>
          New Category
        </Button>
      </Stack>

      <Stack spacing={1}>
        {categories.map((category) => (
          <Paper key={category.id} variant="outlined" sx={{ p: 1.5, borderRadius: 2 }}>
            <Stack direction="row" sx={{ alignItems: "center", justifyContent: "space-between" }}>
              <Stack direction="row" spacing={1} sx={{ alignItems: "center" }}>
                <Typography variant="body2" sx={{ fontWeight: 600 }}>
                  {category.name}
                </Typography>
                {parentName(category) && (
                  <Chip label={`in ${parentName(category)}`} size="small" variant="outlined" />
                )}
                {!category.isActive && <Chip label="Inactive" size="small" />}
              </Stack>
              <IconButton size="small" onClick={() => handleDelete(category)}>
                <DeleteRoundedIcon fontSize="small" />
              </IconButton>
            </Stack>
          </Paper>
        ))}
        {categories.length === 0 && (
          <Typography variant="body2" sx={{ color: "text.secondary" }}>
            No categories yet. Create one to organize your products.
          </Typography>
        )}
      </Stack>

      <CreateCategoryDialog
        open={createOpen}
        categories={categories}
        onClose={() => setCreateOpen(false)}
        onCreate={handleCreate}
      />
    </Stack>
  );
}
