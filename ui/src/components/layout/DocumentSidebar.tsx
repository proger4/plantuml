import React from "react";
import type { DocumentRow } from "../../types/api";

export function DocumentSidebar({ docs, activeId, onPick }: { docs: DocumentRow[]; activeId: number; onPick: (id: number) => void }) {
  return (
    <aside className="h-full border-r border-black/10 bg-white/60 p-3">
      <div className="mb-2 text-xs font-semibold uppercase tracking-wide text-black/50">Documents</div>
      <div className="space-y-2">
        {docs.map((doc) => (
          <button
            key={doc.id}
            onClick={() => onPick(doc.id)}
            className={`w-full rounded-xl px-3 py-2 text-left text-sm transition ${doc.id === activeId ? "bg-accent-100 text-accent-700" : "bg-white hover:bg-black/5"}`}
          >
            <div className="font-medium">#{doc.id} {doc.unique_slug ?? "untitled"}</div>
            <div className="text-xs text-black/50">rev {doc.current_revision}</div>
          </button>
        ))}
      </div>
    </aside>
  );
}
