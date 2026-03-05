import React, { useLayoutEffect, useMemo, useRef } from "react";

type Props = {
  code: string;
  onChange: (next: string, caretLeft: number, caretRight: number) => void;
  fontSize?: number;
  readOnly?: boolean;
};

export function Editor({ code, onChange, fontSize = 13, readOnly = false }: Props) {
  const ref = useRef<HTMLTextAreaElement | null>(null);
  const selectionRef = useRef<{ left: number; right: number } | null>(null);

  const handle = useMemo(
    () => (e: React.ChangeEvent<HTMLTextAreaElement>) => {
      const el = e.currentTarget;
      const left = el.selectionStart ?? 0;
      const right = el.selectionEnd ?? 0;
      selectionRef.current = { left, right };
      onChange(el.value, left, right);
    },
    [onChange]
  );

  useLayoutEffect(() => {
    const el = ref.current;
    const sel = selectionRef.current;
    if (!el || !sel) return;
    if (document.activeElement !== el) return;
    const max = el.value.length;
    const left = Math.max(0, Math.min(max, sel.left));
    const right = Math.max(left, Math.min(max, sel.right));
    el.setSelectionRange(left, right);
  }, [code]);

  return (
    <div className="flex h-full flex-col">
      <div className="border-b border-black/10 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-black/60">Editor</div>
      <textarea
        ref={ref}
        value={code}
        onChange={handle}
        readOnly={readOnly}
        style={{ fontSize }}
        className={`h-full w-full resize-none border-none p-3 font-mono leading-6 text-ink outline-none ${readOnly ? "bg-black/5" : "bg-white/70"}`}
      />
    </div>
  );
}
