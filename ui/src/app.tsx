import React from "react";
import { useAuthStore } from "./store/authStore";
import { LoginPage } from "./pages/LoginPage";
import { StudioPage } from "./pages/StudioPage";

/**
 * Minimal App:
 * - if no token => Login
 * - else => Studio (fixed doc=1)
 *
 * TODO:
 * - routing (login/studio)
 * - plus menu: new doc / random quiz
 * - sidebar: personal docs list
 */
export default function App() {
  const token = useAuthStore((s) => s.token);

  return token ? <StudioPage /> : <LoginPage />;
}
