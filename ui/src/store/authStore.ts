import { create } from "zustand";
import { api } from "./api";

type AuthState = {
  token: string | null;
  user: { id: number; name: string } | null;
  login: (name: string, password: string) => Promise<void>;
  logout: () => void;
};

export const useAuthStore = create<AuthState>((set) => ({
  token: null,
  user: null,
  login: async (name, password) => {
    const res = await api.login(name, password);
    set({ token: res.token, user: res.user });
  },
  logout: () => set({ token: null, user: null }),
}));
