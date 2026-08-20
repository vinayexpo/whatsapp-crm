import { useEffect, useState } from "react";
import Box from "@mui/material/Box";
import Drawer from "@mui/material/Drawer";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import IconButton from "@mui/material/IconButton";
import Button from "@mui/material/Button";
import Menu from "@mui/material/Menu";
import MenuItem from "@mui/material/MenuItem";
import Divider from "@mui/material/Divider";
import List from "@mui/material/List";
import ListItem from "@mui/material/ListItem";
import ListItemAvatar from "@mui/material/ListItemAvatar";
import ListItemText from "@mui/material/ListItemText";
import Avatar from "@mui/material/Avatar";
import CloseRoundedIcon from "@mui/icons-material/CloseRounded";
import DeleteRoundedIcon from "@mui/icons-material/DeleteRounded";
import DownloadRoundedIcon from "@mui/icons-material/DownloadRounded";
import UploadFileRoundedIcon from "@mui/icons-material/UploadFileRounded";
import { ChannelIcon } from "~/components/channel-icon/channel-icon";
import { PaginatedListFooter } from "~/components/common/paginated-list-footer";
import { apiClient } from "~/utils/api-client";
import type { Contact, PhonebookFolder } from "~/data/types";

interface FolderDetailDrawerProps {
  folder: PhonebookFolder | null;
  onClose: () => void;
  onImport: () => void;
  onRemoveContact: (folderId: string, contactId: string) => void;
  onDeleteFolder: (folderId: string) => void;
}

export function FolderDetailDrawer({ folder, onClose, onImport, onRemoveContact, onDeleteFolder }: FolderDetailDrawerProps) {
  const [exportMenuAnchor, setExportMenuAnchor] = useState<HTMLElement | null>(null);
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);

  useEffect(() => {
    setPage(1);
  }, [folder?.id]);

  useEffect(() => {
    if (!folder) {
      setContacts([]);
      setLastPage(1);
      return;
    }
    apiClient
      .getPhonebookFolderContacts(folder.id, { page })
      .then(({ data, meta }) => {
        setContacts(data);
        setLastPage(meta.lastPage);
      })
      .catch(() => {
        setContacts([]);
      });
  }, [folder, page]);

  async function handleExport(format: "csv" | "xlsx") {
    if (!folder) return;
    setExportMenuAnchor(null);
    await apiClient.exportPhonebookFolder(folder.id, format, folder.name.replace(/\s+/g, "-").toLowerCase());
  }

  return (
    <Drawer anchor="right" open={Boolean(folder)} onClose={onClose}>
      {folder && (
        <Box sx={{ width: { xs: 340, sm: 420 }, p: 3, height: "100%", overflowY: "auto" }}>
          <Stack direction="row" sx={{ justifyContent: "flex-end" }}>
            <IconButton onClick={onClose} size="small">
              <CloseRoundedIcon fontSize="small" />
            </IconButton>
          </Stack>

          <Stack sx={{ mb: 2 }}>
            <Typography variant="h6">{folder.name}</Typography>
            <Typography variant="body2" sx={{ color: "text.secondary" }}>
              {folder.contactCount} contact{folder.contactCount === 1 ? "" : "s"}
            </Typography>
          </Stack>

          <Stack direction="row" spacing={1} sx={{ mb: 3 }}>
            <Button size="small" variant="outlined" startIcon={<UploadFileRoundedIcon />} onClick={onImport}>
              Import
            </Button>
            <Button
              size="small"
              variant="outlined"
              startIcon={<DownloadRoundedIcon />}
              onClick={(e) => setExportMenuAnchor(e.currentTarget)}
            >
              Export
            </Button>
            <Menu anchorEl={exportMenuAnchor} open={Boolean(exportMenuAnchor)} onClose={() => setExportMenuAnchor(null)}>
              <MenuItem onClick={() => handleExport("xlsx")}>Export as XLSX</MenuItem>
              <MenuItem onClick={() => handleExport("csv")}>Export as CSV</MenuItem>
            </Menu>
            <Button
              size="small"
              variant="outlined"
              color="error"
              startIcon={<DeleteRoundedIcon />}
              onClick={() => onDeleteFolder(folder.id)}
            >
              Delete
            </Button>
          </Stack>

          <Divider sx={{ mb: 1 }} />

          <List dense>
            {contacts.map((contact) => (
              <ListItem
                key={contact.id}
                secondaryAction={
                  <IconButton edge="end" size="small" onClick={() => onRemoveContact(folder.id, contact.id)}>
                    <CloseRoundedIcon fontSize="small" />
                  </IconButton>
                }
              >
                <ListItemAvatar>
                  <Avatar src={contact.avatarUrl} alt={contact.name} sx={{ width: 32, height: 32 }} />
                </ListItemAvatar>
                <ListItemText
                  primary={
                    <Stack direction="row" sx={{ alignItems: "center", gap: 0.75 }}>
                      <Typography variant="body2" sx={{ fontWeight: 600 }}>
                        {contact.name}
                      </Typography>
                      <ChannelIcon channel={contact.channel} size={14} />
                    </Stack>
                  }
                  secondary={contact.handle}
                />
              </ListItem>
            ))}
            {contacts.length === 0 && (
              <Typography variant="body2" sx={{ color: "text.secondary", textAlign: "center", py: 3 }}>
                No contacts in this folder yet.
              </Typography>
            )}
          </List>

          <PaginatedListFooter page={page} lastPage={lastPage} onPageChange={setPage} />
        </Box>
      )}
    </Drawer>
  );
}
