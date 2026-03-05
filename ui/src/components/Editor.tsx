import React, { useMemo, useRef } from "react";

type Props = {
  code: string;
  onChange: (next: string, caretLeft: number, caretRight: number) => void;
  fontSize?: number;
};

export function Editor({ code, onChange, fontSize = 13 }: Props) {
  const ref = useRef<HTMLTextAreaElement | null>(null);

  const handle = useMemo(
    () => (e: React.ChangeEvent<HTMLTextAreaElement>) => {
      const el = e.currentTarget;
      onChange(el.value, el.selectionStart ?? 0, el.selectionEnd ?? 0);
    },
    [onChange]
  );

  return (
    <div className="flex h-full flex-col">
      <div className="border-b border-black/10 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-black/60">Editor</div>
      <textarea
        ref={ref}
        value={code}
        onChange={handle}
        style={{ fontSize }}
        className="h-full w-full resize-none border-none bg-white/70 p-3 font-mono leading-6 text-ink outline-none"
      />
    </div>
  );
}
