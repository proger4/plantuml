import React from "react";

export function Panel({ className = "", children }: React.PropsWithChildren<{ className?: string }>) {
  return <section className={`rounded-2xl border border-black/10 bg-white/85 shadow-panel ${className}`}>{children}</section>;
}
