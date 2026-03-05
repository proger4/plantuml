import { create } from "zustand";
import { persist, createJSONStorage } from "zustand/middleware";
import { api } from "./api";

type AuthState = {
  token: string | null;
  user: { id: number; name: string; color?: string } | null;
  login: (name: string, password: string) => Promise<void>;
  logout: () => void;
};

export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      token: null,
      user: null,
      login: async (name, password) => {
        const res = await api.login(name, password);
        set({ token: res.token, user: res.user });
      },
      logout: () => set({ token: null, user: null }),
    }),
    {
      name: "plantuml-auth",
      storage: createJSONStorage(() => localStorage),
      partialize: (state) => ({ token: state.token, user: state.user }),
    }
  )
);
