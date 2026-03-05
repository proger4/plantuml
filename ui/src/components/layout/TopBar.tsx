import React from "react";
import { Badge } from "../uikit/Badge";
import { Button } from "../uikit/Button";

type Props = {
  docId: number;
  revision: number;
  lockUserId: number | null;
  meId: number;
  userName: string;
  onSave: () => void;
  onRender: () => void;
};

export function TopBar({ docId, revision, lockUserId, meId, userName, onSave, onRender }: Props) {
  const tone = lockUserId === null || lockUserId === meId ? "ok" : "warn";
  const lockLabel = lockUserId === null ? "lock: none" : `lock: ${lockUserId}`;

  return (
    <header className="flex items-center gap-3 border-b border-black/10 px-4 py-3">
      <h1 className="text-base font-bold tracking-tight">PlantUML Studio</h1>
      <Badge>doc #{docId}</Badge>
      <Badge>rev {revision}</Badge>
      <Badge tone={tone}>{lockLabel}</Badge>
      <div className="ml-auto flex items-center gap-2">
        <Button variant="ghost" onClick={onRender}>Render</Button>
        <Button onClick={onSave}>Save</Button>
        <span className="ml-2 text-sm text-black/70">{userName}</span>
      </div>
    </header>
  );
}
