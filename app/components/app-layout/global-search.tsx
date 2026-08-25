import { useEffect, useRef, useState } from "react";
import { useNavigate } from "react-router";
import Box from "@mui/material/Box";
import Paper from "@mui/material/Paper";
import InputBase from "@mui/material/InputBase";
import Popper from "@mui/material/Popper";
import ClickAwayListener from "@mui/material/ClickAwayListener";
import Typography from "@mui/material/Typography";
import CircularProgress from "@mui/material/CircularProgress";
import ListItemButton from "@mui/material/ListItemButton";
import SearchRoundedIcon from "@mui/icons-material/SearchRounded";
import ContactsRoundedIcon from "@mui/icons-material/ContactsRounded";
import ChatBubbleRoundedIcon from "@mui/icons-material/ChatBubbleRounded";
import CampaignRoundedIcon from "@mui/icons-material/CampaignRounded";
import { apiClient } from "~/utils/api-client";
import type { Campaign, Contact, Conversation } from "~/data/types";

interface SearchResults {
  contacts: Contact[];
  conversations: Conversation[];
  campaigns: Campaign[];
}

const EMPTY_RESULTS: SearchResults = { contacts: [], conversations: [], campaigns: [] };

export function GlobalSearch() {
  const navigate = useNavigate();
  const anchorRef = useRef<HTMLDivElement | null>(null);
  const [query, setQuery] = useState("");
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const [results, setResults] = useState<SearchResults>(EMPTY_RESULTS);

  useEffect(() => {
    const trimmed = query.trim();
    if (!trimmed) {
      setResults(EMPTY_RESULTS);
      setLoading(false);
      return;
    }
    setLoading(true);
    const handle = setTimeout(() => {
      Promise.all([
        apiClient.listContacts({ search: trimmed, perPage: 5 }),
        apiClient.listConversations({ search: trimmed, perPage: 5 }),
        apiClient.listCampaigns({ search: trimmed, perPage: 5 }),
      ])
        .then(([contactsRes, conversationsRes, campaignsRes]) => {
          setResults({
            contacts: contactsRes.data,
            conversations: conversationsRes.data,
            campaigns: campaignsRes.data,
          });
        })
        .finally(() => setLoading(false));
    }, 300);
    return () => clearTimeout(handle);
  }, [query]);

  const hasResults = results.contacts.length > 0 || results.conversations.length > 0 || results.campaigns.length > 0;
  const showPanel = open && query.trim().length > 0;

  function goTo(path: string) {
    setOpen(false);
    setQuery("");
    navigate(path);
  }

  return (
    <ClickAwayListener onClickAway={() => setOpen(false)}>
      <Box ref={anchorRef} sx={{ position: "relative", flex: 1, maxWidth: 420 }}>
        <Paper
          variant="outlined"
          sx={{
            display: "flex",
            alignItems: "center",
            px: 1.5,
            py: 0.5,
            borderRadius: 2,
            bgcolor: "background.default",
          }}
        >
          <SearchRoundedIcon fontSize="small" sx={{ color: "text.secondary", mr: 1 }} />
          <InputBase
            placeholder="Search contacts, chats, campaigns…"
            sx={{ fontSize: "0.9rem", flex: 1 }}
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            onFocus={() => setOpen(true)}
          />
        </Paper>
        <Popper open={showPanel} anchorEl={anchorRef.current} placement="bottom-start" sx={{ zIndex: 1300, width: anchorRef.current?.offsetWidth }}>
          <Paper variant="outlined" sx={{ mt: 0.5, maxHeight: 420, overflowY: "auto", borderRadius: 2 }}>
            {loading && (
              <Box sx={{ display: "flex", justifyContent: "center", p: 2 }}>
                <CircularProgress size={20} />
              </Box>
            )}
            {!loading && !hasResults && (
              <Typography variant="body2" sx={{ color: "text.secondary", p: 2, textAlign: "center" }}>
                No results found.
              </Typography>
            )}
            {!loading && results.contacts.length > 0 && (
              <Box sx={{ py: 0.5 }}>
                <Typography variant="caption" sx={{ px: 2, color: "text.secondary", fontWeight: 700 }}>
                  Contacts
                </Typography>
                {results.contacts.map((contact) => (
                  <ListItemButton key={contact.id} onClick={() => goTo(`/contacts?contactId=${contact.id}`)} sx={{ px: 2, py: 0.75, gap: 1.25 }}>
                    <ContactsRoundedIcon fontSize="small" sx={{ color: "text.secondary" }} />
                    <Box sx={{ minWidth: 0 }}>
                      <Typography variant="body2" noWrap>{contact.name}</Typography>
                      <Typography variant="caption" sx={{ color: "text.secondary" }} noWrap>
                        {contact.phone ?? contact.handle}
                      </Typography>
                    </Box>
                  </ListItemButton>
                ))}
              </Box>
            )}
            {!loading && results.conversations.length > 0 && (
              <Box sx={{ py: 0.5 }}>
                <Typography variant="caption" sx={{ px: 2, color: "text.secondary", fontWeight: 700 }}>
                  Chats
                </Typography>
                {results.conversations.map((conversation) => (
                  <ListItemButton
                    key={conversation.id}
                    onClick={() => goTo(`/inbox?contactId=${conversation.contactId}`)}
                    sx={{ px: 2, py: 0.75, gap: 1.25 }}
                  >
                    <ChatBubbleRoundedIcon fontSize="small" sx={{ color: "text.secondary" }} />
                    <Box sx={{ minWidth: 0 }}>
                      <Typography variant="body2" noWrap>{conversation.contact?.name ?? "Conversation"}</Typography>
                      <Typography variant="caption" sx={{ color: "text.secondary" }} noWrap>
                        {conversation.lastMessagePreview}
                      </Typography>
                    </Box>
                  </ListItemButton>
                ))}
              </Box>
            )}
            {!loading && results.campaigns.length > 0 && (
              <Box sx={{ py: 0.5 }}>
                <Typography variant="caption" sx={{ px: 2, color: "text.secondary", fontWeight: 700 }}>
                  Campaigns
                </Typography>
                {results.campaigns.map((campaign) => (
                  <ListItemButton key={campaign.id} onClick={() => goTo("/campaigns")} sx={{ px: 2, py: 0.75, gap: 1.25 }}>
                    <CampaignRoundedIcon fontSize="small" sx={{ color: "text.secondary" }} />
                    <Typography variant="body2" noWrap>{campaign.name}</Typography>
                  </ListItemButton>
                ))}
              </Box>
            )}
          </Paper>
        </Popper>
      </Box>
    </ClickAwayListener>
  );
}
