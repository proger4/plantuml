import React from "react";

type Props = React.ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: "primary" | "ghost";
};

export function Button({ variant = "primary", className = "", ...props }: Props) {
  const base = "inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-60";
  const tone =
    variant === "primary"
      ? "bg-accent-500 text-white hover:bg-accent-700"
      : "bg-transparent text-ink hover:bg-black/5";

  return <button className={`${base} ${tone} ${className}`} {...props} />;
}
