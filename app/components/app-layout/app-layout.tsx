import { type ReactNode, useState } from "react";
import { NavLink, useLocation, useNavigate } from "react-router";
import Box from "@mui/material/Box";
import Drawer from "@mui/material/Drawer";
import AppBar from "@mui/material/AppBar";
import Toolbar from "@mui/material/Toolbar";
import Typography from "@mui/material/Typography";
import List from "@mui/material/List";
import ListItemButton from "@mui/material/ListItemButton";
import ListItemIcon from "@mui/material/ListItemIcon";
import ListItemText from "@mui/material/ListItemText";
import IconButton from "@mui/material/IconButton";
import Avatar from "@mui/material/Avatar";
import Stack from "@mui/material/Stack";
import InputBase from "@mui/material/InputBase";
import Paper from "@mui/material/Paper";
import Menu from "@mui/material/Menu";
import MenuItem from "@mui/material/MenuItem";
import Divider from "@mui/material/Divider";
import PersonRoundedIcon from "@mui/icons-material/PersonRounded";
import DashboardRoundedIcon from "@mui/icons-material/DashboardRounded";
import ChatBubbleRoundedIcon from "@mui/icons-material/ChatBubbleRounded";
import ContactsRoundedIcon from "@mui/icons-material/ContactsRounded";
import ContactPhoneRoundedIcon from "@mui/icons-material/ContactPhoneRounded";
import ViewKanbanRoundedIcon from "@mui/icons-material/ViewKanbanRounded";
import CampaignRoundedIcon from "@mui/icons-material/CampaignRounded";
import BarChartRoundedIcon from "@mui/icons-material/BarChartRounded";
import BoltRoundedIcon from "@mui/icons-material/BoltRounded";
import SmartToyRoundedIcon from "@mui/icons-material/SmartToyRounded";
import ForumRoundedIcon from "@mui/icons-material/ForumRounded";
import PhoneInTalkRoundedIcon from "@mui/icons-material/PhoneInTalkRounded";
import CallRoundedIcon from "@mui/icons-material/CallRounded";
import SettingsRoundedIcon from "@mui/icons-material/SettingsRounded";
import SearchRoundedIcon from "@mui/icons-material/SearchRounded";
import MenuRoundedIcon from "@mui/icons-material/MenuRounded";
import WhatsAppIcon from "@mui/icons-material/WhatsApp";
import ArticleRoundedIcon from "@mui/icons-material/ArticleRounded";
import LogoutRoundedIcon from "@mui/icons-material/LogoutRounded";
import { useCrmStore } from "~/hooks/use-crm-store";
import { useAuth } from "~/hooks/use-auth";
import { apiClient } from "~/utils/api-client";
import { AuthGuard } from "~/components/auth-guard/auth-guard";
import { NotificationMenu } from "~/components/app-layout/notification-menu";

const DRAWER_WIDTH = 248;

const NAV_ITEMS = [
  { label: "Home", to: "/", icon: DashboardRoundedIcon },
  { label: "Inbox", to: "/inbox", icon: ChatBubbleRoundedIcon },
  { label: "Contacts", to: "/contacts", icon: ContactsRoundedIcon },
  { label: "Phonebook", to: "/phonebook", icon: ContactPhoneRoundedIcon, hideForAgent: true },
  { label: "Pipeline", to: "/pipeline", icon: ViewKanbanRoundedIcon },
  { label: "Campaigns", to: "/campaigns", icon: CampaignRoundedIcon, hideForAgent: true },
  { label: "Analytics", to: "/analytics", icon: BarChartRoundedIcon, hideForAgent: true },
  { label: "Automations", to: "/automations", icon: BoltRoundedIcon, hideForAgent: true },
  { label: "AI Assistant", to: "/ai-assistant", icon: SmartToyRoundedIcon },
  { label: "Chatbots", to: "/chatbots", icon: ForumRoundedIcon, hideForAgent: true },
  { label: "Templates", to: "/templates", icon: ArticleRoundedIcon, hideForAgent: true },
  { label: "Voice Agents", to: "/voice-agents", icon: PhoneInTalkRoundedIcon, hideForAgent: true },
  { label: "WhatsApp Calling", to: "/whatsapp-calling", icon: CallRoundedIcon, hideForAgent: true },
  { label: "Settings", to: "/settings", icon: SettingsRoundedIcon },
];

export function AppLayout({ children }: { children: ReactNode }) {
  const location = useLocation();
  const navigate = useNavigate();
  const { currentUser, automationFlows } = useCrmStore();
  const { setUser, setStatus } = useAuth();
  const [mobileOpen, setMobileOpen] = useState(false);
  const [profileMenuAnchor, setProfileMenuAnchor] = useState<HTMLElement | null>(null);
  const [loggingOut, setLoggingOut] = useState(false);
  const profileMenuOpen = Boolean(profileMenuAnchor);
  const activeAutomationCount = automationFlows.filter((flow) => flow.status === "active").length;

  function handleGoToSettings() {
    setProfileMenuAnchor(null);
    navigate("/settings");
  }

  async function handleLogout() {
    setProfileMenuAnchor(null);
    setLoggingOut(true);
    try {
      await apiClient.logout();
    } catch {
      // best-effort: proceed to clear local state and redirect regardless
    }
    setUser(null);
    setStatus("unauthenticated");
    setLoggingOut(false);
    navigate("/login", { replace: true });
  }

  const drawerContent = (
    <Box sx={{ height: "100%", display: "flex", flexDirection: "column" }}>
      <Stack direction="row" sx={{ alignItems: "center", gap: 1.25, px: 2.5, py: 2.75 }}>
        <Box
          sx={{
            width: 36,
            height: 36,
            borderRadius: "10px",
            bgcolor: "primary.main",
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
          }}
        >
          <WhatsAppIcon sx={{ color: "primary.contrastText", fontSize: 20 }} />
        </Box>
        <Typography variant="h6" sx={{ fontSize: "1.1rem", letterSpacing: "-0.02em" }}>
          Creative Connects
        </Typography>
      </Stack>
      <List sx={{ px: 1.5, flex: 1 }}>
        {NAV_ITEMS.filter((item) => !(item.hideForAgent && currentUser?.role === "agent")).map((item) => {
          const isActive = item.to === "/" ? location.pathname === "/" : location.pathname.startsWith(item.to);
          const Icon = item.icon;
          return (
            <ListItemButton
              key={item.to}
              component={NavLink}
              to={item.to}
              onClick={() => setMobileOpen(false)}
              selected={isActive}
              sx={{
                borderRadius: 2,
                mb: 0.5,
                color: isActive ? "primary.dark" : "text.secondary",
                "&.Mui-selected": {
                  bgcolor: "rgba(0, 168, 132, 0.12)",
                  "&:hover": { bgcolor: "rgba(0, 168, 132, 0.18)" },
                },
              }}
            >
              <ListItemIcon sx={{ minWidth: 38, color: isActive ? "primary.main" : "text.secondary" }}>
                <Icon fontSize="small" />
              </ListItemIcon>
              <ListItemText slotProps={{ primary: { sx: { fontWeight: isActive ? 700 : 500, fontSize: "0.92rem" } } }}>
                {item.label}
              </ListItemText>
            </ListItemButton>
          );
        })}
      </List>
      <Box sx={{ p: 2, mx: 1.5, mb: 2, borderRadius: 2, bgcolor: "rgba(124, 77, 255, 0.08)" }}>
        <Typography variant="caption" sx={{ fontWeight: 700, color: "secondary.dark" }}>
          Automations active
        </Typography>
        <Typography variant="caption" sx={{ display: "block", color: "text.secondary", mt: 0.25 }}>
          {activeAutomationCount} chat flow{activeAutomationCount === 1 ? "" : "s"} running across WhatsApp & Instagram
        </Typography>
      </Box>
    </Box>
  );

  const layout = (
    <Box sx={{ display: "flex", minHeight: "100vh", bgcolor: "background.default" }}>
      <AppBar
        position="fixed"
        color="inherit"
        sx={{
          width: { md: `calc(100% - ${DRAWER_WIDTH}px)` },
          ml: { md: `${DRAWER_WIDTH}px` },
          bgcolor: "background.paper",
          borderBottom: "1px solid",
          borderColor: "divider",
        }}
      >
        <Toolbar sx={{ gap: 1.5 }}>
          <IconButton edge="start" onClick={() => setMobileOpen(true)} sx={{ display: { md: "none" } }}>
            <MenuRoundedIcon />
          </IconButton>
          <Paper
            variant="outlined"
            sx={{
              display: "flex",
              alignItems: "center",
              px: 1.5,
              py: 0.5,
              borderRadius: 2,
              flex: 1,
              maxWidth: 420,
              bgcolor: "background.default",
            }}
          >
            <SearchRoundedIcon fontSize="small" sx={{ color: "text.secondary", mr: 1 }} />
            <InputBase placeholder="Search contacts, chats, campaigns…" sx={{ fontSize: "0.9rem", flex: 1 }} />
          </Paper>
          <Box sx={{ flex: 1 }} />
          <NotificationMenu />
          <IconButton onClick={(e) => setProfileMenuAnchor(e.currentTarget)} sx={{ p: 0.25 }}>
            <Avatar
              src={currentUser?.avatarUrl}
              alt={currentUser?.name ?? "Account owner"}
              sx={{ width: 36, height: 36 }}
            />
          </IconButton>
          <Menu
            anchorEl={profileMenuAnchor}
            open={profileMenuOpen}
            onClose={() => setProfileMenuAnchor(null)}
            anchorOrigin={{ vertical: "bottom", horizontal: "right" }}
            transformOrigin={{ vertical: "top", horizontal: "right" }}
          >
            <Box sx={{ px: 2, py: 1, minWidth: 220 }}>
              <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>
                {currentUser?.name ?? "Account owner"}
              </Typography>
              <Typography variant="caption" sx={{ color: "text.secondary", textTransform: "capitalize" }}>
                {currentUser?.role ?? "member"}
              </Typography>
            </Box>
            <Divider />
            <MenuItem onClick={handleGoToSettings}>
              <ListItemIcon>
                <PersonRoundedIcon fontSize="small" />
              </ListItemIcon>
              Account Settings
            </MenuItem>
            <Divider />
            <MenuItem onClick={handleLogout} disabled={loggingOut}>
              <ListItemIcon>
                <LogoutRoundedIcon fontSize="small" />
              </ListItemIcon>
              Log out
            </MenuItem>
          </Menu>
        </Toolbar>
      </AppBar>

      <Box component="nav" sx={{ width: { md: DRAWER_WIDTH }, flexShrink: { md: 0 } }}>
        <Drawer
          variant="temporary"
          open={mobileOpen}
          onClose={() => setMobileOpen(false)}
          ModalProps={{ keepMounted: true }}
          sx={{ display: { xs: "block", md: "none" }, "& .MuiDrawer-paper": { width: DRAWER_WIDTH } }}
        >
          {drawerContent}
        </Drawer>
        <Drawer
          variant="permanent"
          sx={{
            display: { xs: "none", md: "block" },
            "& .MuiDrawer-paper": { width: DRAWER_WIDTH, boxSizing: "border-box", border: "none", borderRight: "1px solid", borderColor: "divider" },
          }}
          open
        >
          {drawerContent}
        </Drawer>
      </Box>

      <Box
        component="main"
        sx={{
          flexGrow: 1,
          width: { md: `calc(100% - ${DRAWER_WIDTH}px)` },
          display: "flex",
          flexDirection: "column",
        }}
      >
        <Toolbar />
        <Box sx={{ flex: 1, display: "flex", flexDirection: "column", minHeight: 0 }}>{children}</Box>
      </Box>
    </Box>
  );

  return <AuthGuard>{layout}</AuthGuard>;
}
