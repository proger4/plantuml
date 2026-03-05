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
  onNewDocument: () => void;
  onRandomQuiz: () => void;
  onShare: () => void;
  wsEnabled: boolean;
  onLogout: () => void;
};

export function TopBar({
  docId,
  revision,
  lockUserId,
  meId,
  userName,
  onSave,
  onRender,
  onNewDocument,
  onRandomQuiz,
  onShare,
  wsEnabled,
  onLogout,
}: Props) {
  const tone = lockUserId === null || lockUserId === meId ? "ok" : "warn";
  const lockLabel = lockUserId === null ? "lock: none" : `lock: ${lockUserId}`;

  return (
    <header className="flex items-center gap-3 border-b border-black/10 px-4 py-3">
      <h1 className="text-base font-bold tracking-tight">PlantUML Studio</h1>
      <Badge>doc #{docId}</Badge>
      <Badge>rev {revision}</Badge>
      <Badge tone={tone}>{lockLabel}</Badge>
      <Badge tone={wsEnabled ? "ok" : "warn"}>{wsEnabled ? "ws: online" : "ws: offline"}</Badge>
      <div className="ml-auto flex items-center gap-2">
        <Button variant="ghost" onClick={onShare}>Share</Button>
        <Button variant="ghost" onClick={onNewDocument}>+ New</Button>
        <Button variant="ghost" onClick={onRandomQuiz}>+ Quiz</Button>
        <Button variant="ghost" onClick={onRender}>Render</Button>
        <Button onClick={onSave}>Save</Button>
        <div className="ml-2 flex items-center gap-2 rounded-xl border border-black/10 bg-white/80 px-2 py-1">
          <span className="text-sm text-black/70">{userName}</span>
          <Button variant="ghost" className="px-2 py-1 text-xs" onClick={onLogout}>Logout</Button>
        </div>
      </div>
    </header>
  );
}
