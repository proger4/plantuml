import React from "react";

export function Badge({ children, tone = "neutral" }: React.PropsWithChildren<{ tone?: "neutral" | "ok" | "warn" }>) {
  const cls =
    tone === "ok"
      ? "bg-accent-100 text-accent-700"
      : tone === "warn"
      ? "bg-amber-100 text-amber-700"
      : "bg-black/10 text-black/70";
  return <span className={`rounded-full px-2.5 py-1 text-xs font-medium ${cls}`}>{children}</span>;
}
