import { useRef, useState } from "react";
import Dialog from "@mui/material/Dialog";
import DialogTitle from "@mui/material/DialogTitle";
import DialogContent from "@mui/material/DialogContent";
import DialogActions from "@mui/material/DialogActions";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import Alert from "@mui/material/Alert";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import ToggleButtonGroup from "@mui/material/ToggleButtonGroup";
import ToggleButton from "@mui/material/ToggleButton";
import Select from "@mui/material/Select";
import MenuItem from "@mui/material/MenuItem";
import UploadFileRoundedIcon from "@mui/icons-material/UploadFileRounded";
import DownloadRoundedIcon from "@mui/icons-material/DownloadRounded";
import { apiClient, ApiError } from "~/utils/api-client";
import type { ImportSummary, PhonebookFolder } from "~/data/types";

interface ImportContactsDialogProps {
  open: boolean;
  onClose: () => void;
  folders: PhonebookFolder[];
  defaultFolderId?: string;
  onImported: (result: { folder: PhonebookFolder; summary: ImportSummary }) => void;
}

export function ImportContactsDialog({ open, onClose, folders, defaultFolderId, onImported }: ImportContactsDialogProps) {
  const [target, setTarget] = useState<"new" | "existing">(defaultFolderId ? "existing" : "new");
  const [folderName, setFolderName] = useState("");
  const [folderId, setFolderId] = useState(defaultFolderId ?? "");
  const [file, setFile] = useState<File | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [summary, setSummary] = useState<ImportSummary | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  function handleClose() {
    setTarget(defaultFolderId ? "existing" : "new");
    setFolderName("");
    setFolderId(defaultFolderId ?? "");
    setFile(null);
    setError(null);
    setSummary(null);
    onClose();
  }

  async function handleDownloadTemplate() {
    try {
      await apiClient.downloadContactTemplate();
    } catch {
      setError("Failed to download template.");
    }
  }

  async function handleSubmit() {
    if (!file) return;
    if (target === "new" && !folderName.trim()) return;
    if (target === "existing" && !folderId) return;

    setSubmitting(true);
    setError(null);
    try {
      const result = await apiClient.importContactsToFolder({
        file,
        folderName: target === "new" ? folderName.trim() : undefined,
        folderId: target === "existing" ? folderId : undefined,
      });
      setSummary(result.summary);
      onImported(result);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Import failed.");
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <Dialog open={open} onClose={handleClose} maxWidth="sm" fullWidth>
      <DialogTitle>Import Contacts</DialogTitle>
      <DialogContent>
        {error && (
          <Alert severity="error" sx={{ mb: 2 }}>
            {error}
          </Alert>
        )}

        {summary ? (
          <Stack spacing={1.5} sx={{ mt: 1 }}>
            <Alert severity="success">
              {summary.created} created, {summary.attached} attached, {summary.skipped} skipped.
            </Alert>
            {summary.errors.length > 0 && (
              <Alert severity="warning">
                <Stack spacing={0.5}>
                  {summary.errors.map((e, idx) => (
                    <Typography key={idx} variant="caption">
                      Row {e.row}: {e.message}
                    </Typography>
                  ))}
                </Stack>
              </Alert>
            )}
          </Stack>
        ) : (
          <Stack spacing={2.5} sx={{ mt: 1 }}>
            <Button
              variant="text"
              size="small"
              startIcon={<DownloadRoundedIcon />}
              onClick={handleDownloadTemplate}
              sx={{ alignSelf: "flex-start" }}
            >
              Download blank template (.xlsx)
            </Button>

            <ToggleButtonGroup
              exclusive
              value={target}
              onChange={(_, value) => value && setTarget(value)}
              size="small"
            >
              <ToggleButton value="new">New folder</ToggleButton>
              <ToggleButton value="existing">Existing folder</ToggleButton>
            </ToggleButtonGroup>

            {target === "new" ? (
              <TextField
                fullWidth
                label="Folder name"
                value={folderName}
                onChange={(e) => setFolderName(e.target.value)}
              />
            ) : (
              <Select
                fullWidth
                displayEmpty
                value={folderId}
                onChange={(e) => setFolderId(e.target.value)}
              >
                <MenuItem value="" disabled>
                  Select a folder
                </MenuItem>
                {folders.map((f) => (
                  <MenuItem key={f.id} value={f.id}>
                    {f.name}
                  </MenuItem>
                ))}
              </Select>
            )}

            <Button
              variant="outlined"
              component="label"
              startIcon={<UploadFileRoundedIcon />}
              sx={{ alignSelf: "flex-start" }}
            >
              {file ? file.name : "Choose CSV or XLSX file"}
              <input
                ref={fileInputRef}
                type="file"
                hidden
                accept=".csv,.txt,.xlsx"
                onChange={(e) => setFile(e.target.files?.[0] ?? null)}
              />
            </Button>
          </Stack>
        )}
      </DialogContent>
      <DialogActions sx={{ px: 3, pb: 2.5 }}>
        {summary ? (
          <Button variant="contained" onClick={handleClose}>
            Done
          </Button>
        ) : (
          <>
            <Button onClick={handleClose}>Cancel</Button>
            <Button
              variant="contained"
              onClick={handleSubmit}
              disabled={
                submitting || !file || (target === "new" ? !folderName.trim() : !folderId)
              }
            >
              Import
            </Button>
          </>
        )}
      </DialogActions>
    </Dialog>
  );
}
