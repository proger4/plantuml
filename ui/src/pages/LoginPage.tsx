import React, { useState } from "react";
import { useAuthStore } from "../store/authStore";
import { Button } from "../components/uikit/Button";
import { Input } from "../components/uikit/Input";
import { Panel } from "../components/uikit/Panel";

export function LoginPage() {
  const login = useAuthStore((s) => s.login);
  const [name, setName] = useState("ivan");
  const [password, setPassword] = useState("1111");
  const [err, setErr] = useState<string | null>(null);

  return (
    <main className="grid min-h-screen place-items-center p-6">
      <Panel className="w-full max-w-md animate-fade-up p-6">
        <h2 className="mb-2 text-2xl font-bold tracking-tight">PlantUML Studio</h2>
        <p className="mb-4 text-sm text-black/60">Вход в collaborative-редактор диаграмм</p>
        <div className="grid gap-3">
          <Input value={name} onChange={(e) => setName(e.target.value)} placeholder="name" />
          <Input value={password} onChange={(e) => setPassword(e.target.value)} placeholder="password" type="password" />
          <Button
            onClick={async () => {
              setErr(null);
              try {
                await login(name, password);
              } catch (e: any) {
                setErr(e?.message ?? "error");
              }
            }}
          >
            Sign in
          </Button>
          {err && <div className="text-sm text-red-600">{err}</div>}
          <div className="text-xs text-black/60">Seed users: ivan / vladimir / anna (password: 1111)</div>
        </div>
      </Panel>
    </main>
  );
}
