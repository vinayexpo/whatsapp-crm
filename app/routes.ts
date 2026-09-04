import { type RouteConfig, index, prefix, route } from "@react-router/dev/routes";

const devRoutes = import.meta.env.DEV ? prefix("dev", [route("components", "dev/components.tsx")]) : [];

export default [
  route("login", "routes/login.tsx"),
  route("setup", "routes/setup.tsx"),
  route("privacy-policy", "routes/privacy-policy.tsx"),
  route("terms", "routes/terms.tsx"),
  route("superadmin", "routes/superadmin.tsx"),
  index("routes/home.tsx"),
  route("inbox", "routes/inbox.tsx"),
  route("contacts", "routes/contacts.tsx"),
  route("phonebook", "routes/phonebook.tsx"),
  route("pipeline", "routes/pipeline.tsx"),
  route("campaigns", "routes/campaigns.tsx"),
  route("analytics", "routes/analytics.tsx"),
  route("automations", "routes/automations.tsx"),
  route("ai-assistant", "routes/ai-assistant.tsx"),
  route("chatbots", "routes/chatbots.tsx"),
  route("chat-menus", "routes/chat-menus.tsx"),
  route("templates", "routes/templates.tsx"),
  route("voice-agents", "routes/voice-agents.tsx"),
  route("whatsapp-calling", "routes/whatsapp-calling.tsx"),
  route("settings", "routes/settings.tsx"),
  ...devRoutes,
] satisfies RouteConfig;
