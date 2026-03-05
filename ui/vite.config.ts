import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";

// NOTE: we intentionally keep config minimal.
export default defineConfig({
  plugins: [react()],
});
