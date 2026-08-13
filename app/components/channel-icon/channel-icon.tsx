import Box from "@mui/material/Box";
import WhatsAppIcon from "@mui/icons-material/WhatsApp";
import InstagramIcon from "@mui/icons-material/Instagram";
import LanguageRoundedIcon from "@mui/icons-material/LanguageRounded";
import PhoneInTalkRoundedIcon from "@mui/icons-material/PhoneInTalkRounded";
import type { ChannelType } from "~/data/types";

const CHANNEL_CONFIG: Record<ChannelType, { color: string; Icon: typeof WhatsAppIcon }> = {
  whatsapp: { color: "#25D366", Icon: WhatsAppIcon },
  instagram: { color: "#E1306C", Icon: InstagramIcon },
  website: { color: "#5B6EF5", Icon: LanguageRoundedIcon },
  voice: { color: "#8E5BF5", Icon: PhoneInTalkRoundedIcon },
};

export function ChannelIcon({ channel, size = 20 }: { channel: ChannelType; size?: number }) {
  const { color, Icon } = CHANNEL_CONFIG[channel];
  return (
    <Box
      sx={{
        width: size,
        height: size,
        borderRadius: "50%",
        bgcolor: color,
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        flexShrink: 0,
      }}
    >
      <Icon sx={{ color: "#fff", fontSize: size * 0.65 }} />
    </Box>
  );
}
