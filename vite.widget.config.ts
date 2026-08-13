import { resolve } from "node:path";
import { defineConfig } from "vite";

export default defineConfig({
  build: {
    outDir: "public",
    emptyOutDir: false,
    lib: {
      entry: resolve(__dirname, "app/widget/index.ts"),
      name: "OmniChatWidget",
      formats: ["iife"],
      fileName: () => "widget.js",
    },
  },
});
