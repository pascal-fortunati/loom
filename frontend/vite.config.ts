import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import tailwindcss from "@tailwindcss/vite";

// Configuration Vite du frontend Loom (Vue + Tailwind v4.2 + DaisyUI).
export default defineConfig({
  plugins: [vue(), tailwindcss()],
  server: {
    host: "0.0.0.0",
    port: 5173,
  },
});
