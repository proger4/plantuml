import React from "react";

export function Preview({ svg }: { svg: string }) {
  return (
    <div className="flex h-full flex-col">
      <div className="border-b border-black/10 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-black/60">Preview (SVG)</div>
      <div className="h-full overflow-auto bg-white/70 p-3" dangerouslySetInnerHTML={{ __html: svg || "<div style='color:#888'>no render</div>" }} />
    </div>
  );
}
