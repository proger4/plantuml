import React from "react";

export function Input(props: React.InputHTMLAttributes<HTMLInputElement>) {
  return (
    <input
      {...props}
      className={`w-full rounded-xl border border-black/10 bg-white/90 px-3 py-2 text-sm text-ink shadow-sm outline-none transition focus:border-accent-300 focus:ring-2 focus:ring-accent-100 ${props.className ?? ""}`}
    />
  );
}
