import { useEffect, useState } from "react";
import Box from "@mui/material/Box";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import Button from "@mui/material/Button";
import Paper from "@mui/material/Paper";
import Grid from "@mui/material/Grid";
import IconButton from "@mui/material/IconButton";
import Menu from "@mui/material/Menu";
import MenuItem from "@mui/material/MenuItem";
import FolderRoundedIcon from "@mui/icons-material/FolderRounded";
import AddRoundedIcon from "@mui/icons-material/AddRounded";
import UploadFileRoundedIcon from "@mui/icons-material/UploadFileRounded";
import DownloadRoundedIcon from "@mui/icons-material/DownloadRounded";
import MoreVertRoundedIcon from "@mui/icons-material/MoreVertRounded";
import { AppLayout } from "~/components/app-layout/app-layout";
import { RoleGuard } from "~/components/role-guard/role-guard";
import { CreateFolderDialog } from "~/components/phonebook/create-folder-dialog";
import { ImportContactsDialog } from "~/components/phonebook/import-contacts-dialog";
import { FolderDetailDrawer } from "~/components/phonebook/folder-detail-drawer";
import { apiClient } from "~/utils/api-client";
import type { PhonebookFolder } from "~/data/types";
import type { Route } from "./+types/phonebook";

export function meta({}: Route.MetaArgs) {
  return [
    { title: "Phonebook — Creative Connects" },
    { name: "description", content: "Organize contacts into folders for bulk campaign targeting." },
  ];
}

export default function Phonebook() {
  const [folders, setFolders] = useState<PhonebookFolder[]>([]);
  const [selectedFolderId, setSelectedFolderId] = useState<string | null>(null);
  const [createOpen, setCreateOpen] = useState(false);
  const [importOpen, setImportOpen] = useState(false);
  const [importDefaultFolderId, setImportDefaultFolderId] = useState<string | undefined>(undefined);
  const [menuAnchor, setMenuAnchor] = useState<{ el: HTMLElement; folderId: string } | null>(null);
  const [selectedFolder, setSelectedFolder] = useState<PhonebookFolder | null>(null);

  useEffect(() => {
    apiClient.listPhonebookFolders().then(setFolders).catch(() => {
      // folders list stays empty on failure
    });
  }, []);

  useEffect(() => {
    if (!selectedFolderId) {
      setSelectedFolder(null);
      return;
    }
    apiClient.getPhonebookFolder(selectedFolderId).then(setSelectedFolder).catch(() => {
      setSelectedFolder(null);
    });
  }, [selectedFolderId]);

  function refreshFolders() {
    apiClient.listPhonebookFolders().then(setFolders).catch(() => {});
    if (selectedFolderId) {
      apiClient.getPhonebookFolder(selectedFolderId).then(setSelectedFolder).catch(() => {});
    }
  }

  async function handleCreateFolder(name: string) {
    const created = await apiClient.createPhonebookFolder(name);
    setFolders((prev) => [created, ...prev]);
  }

  function handleOpenImport(folderId?: string) {
    setImportDefaultFolderId(folderId);
    setImportOpen(true);
  }

  function handleImported(result: { folder: PhonebookFolder }) {
    setFolders((prev) => {
      const exists = prev.some((f) => f.id === result.folder.id);
      return exists ? prev.map((f) => (f.id === result.folder.id ? result.folder : f)) : [result.folder, ...prev];
    });
    if (selectedFolderId === result.folder.id) setSelectedFolder(result.folder);
  }

  async function handleRemoveContact(folderId: string, contactId: string) {
    await apiClient.removeContactFromFolder(folderId, contactId);
    refreshFolders();
  }

  async function handleDeleteFolder(folderId: string) {
    await apiClient.deletePhonebookFolder(folderId);
    setFolders((prev) => prev.filter((f) => f.id !== folderId));
    if (selectedFolderId === folderId) setSelectedFolderId(null);
  }

  return (
    <AppLayout>
      <RoleGuard allow={["superadmin", "admin", "manager"]}>
      <Box sx={{ p: { xs: 2, md: 4 }, flex: 1, minWidth: 0 }}>
        <Stack direction="row" sx={{ alignItems: "flex-start", justifyContent: "space-between", mb: 3, flexWrap: "wrap", gap: 1.5 }}>
          <Stack>
            <Typography variant="h4" sx={{ fontSize: { xs: "1.5rem", md: "1.8rem" } }}>
              Phonebook
            </Typography>
            <Typography variant="body2" sx={{ color: "text.secondary", mt: 0.5 }}>
              {folders.length} folder{folders.length === 1 ? "" : "s"} for organizing bulk campaign audiences
            </Typography>
          </Stack>
          <Stack direction="row" spacing={1.5}>
            <Button
              variant="outlined"
              startIcon={<DownloadRoundedIcon />}
              onClick={() => apiClient.exportAllContacts("xlsx")}
            >
              Export All Contacts
            </Button>
            <Button variant="outlined" startIcon={<UploadFileRoundedIcon />} onClick={() => handleOpenImport()}>
              Import Contacts
            </Button>
            <Button variant="contained" startIcon={<AddRoundedIcon />} onClick={() => setCreateOpen(true)}>
              New Folder
            </Button>
          </Stack>
        </Stack>

        <Grid container spacing={2}>
          {folders.map((folder) => (
            <Grid key={folder.id} size={{ xs: 12, sm: 6, md: 4, lg: 3 }}>
              <Paper
                variant="outlined"
                sx={{
                  borderRadius: 3,
                  p: 2.5,
                  cursor: "pointer",
                  transition: "border-color 0.15s",
                  "&:hover": { borderColor: "primary.main" },
                }}
                onClick={() => setSelectedFolderId(folder.id)}
              >
                <Stack direction="row" sx={{ alignItems: "flex-start", justifyContent: "space-between" }}>
                  <Box
                    sx={{
                      width: 40,
                      height: 40,
                      borderRadius: 2,
                      bgcolor: "rgba(0, 168, 132, 0.12)",
                      display: "flex",
                      alignItems: "center",
                      justifyContent: "center",
                    }}
                  >
                    <FolderRoundedIcon sx={{ color: "primary.main" }} fontSize="small" />
                  </Box>
                  <IconButton
                    size="small"
                    onClick={(e) => {
                      e.stopPropagation();
                      setMenuAnchor({ el: e.currentTarget, folderId: folder.id });
                    }}
                  >
                    <MoreVertRoundedIcon fontSize="small" />
                  </IconButton>
                </Stack>
                <Typography variant="subtitle1" sx={{ fontWeight: 700, mt: 1.5 }}>
                  {folder.name}
                </Typography>
                <Typography variant="caption" sx={{ color: "text.secondary" }}>
                  {folder.contactCount} contact{folder.contactCount === 1 ? "" : "s"}
                </Typography>
              </Paper>
            </Grid>
          ))}
          {folders.length === 0 && (
            <Grid size={12}>
              <Paper variant="outlined" sx={{ borderRadius: 3, p: 5, textAlign: "center" }}>
                <Typography variant="body2" sx={{ color: "text.secondary" }}>
                  No folders yet. Create one to start organizing contacts for bulk campaigns.
                </Typography>
              </Paper>
            </Grid>
          )}
        </Grid>
      </Box>

      <Menu anchorEl={menuAnchor?.el} open={Boolean(menuAnchor)} onClose={() => setMenuAnchor(null)}>
        <MenuItem
          onClick={() => {
            if (menuAnchor) handleOpenImport(menuAnchor.folderId);
            setMenuAnchor(null);
          }}
        >
          Import contacts here
        </MenuItem>
        <MenuItem
          onClick={() => {
            if (menuAnchor) handleDeleteFolder(menuAnchor.folderId);
            setMenuAnchor(null);
          }}
        >
          Delete folder
        </MenuItem>
      </Menu>

      <CreateFolderDialog open={createOpen} onClose={() => setCreateOpen(false)} onCreate={handleCreateFolder} />

      <ImportContactsDialog
        open={importOpen}
        onClose={() => setImportOpen(false)}
        folders={folders}
        defaultFolderId={importDefaultFolderId}
        onImported={handleImported}
      />

      <FolderDetailDrawer
        folder={selectedFolder}
        onClose={() => setSelectedFolderId(null)}
        onImport={() => handleOpenImport(selectedFolder?.id)}
        onRemoveContact={handleRemoveContact}
        onDeleteFolder={handleDeleteFolder}
      />
      </RoleGuard>
    </AppLayout>
  );
}
